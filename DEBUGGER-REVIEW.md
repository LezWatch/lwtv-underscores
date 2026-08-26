# Debugger Tools — Review & Improvement Plan

Reviewed: `php/debugger/`, `php/validator/`, `php/admin-menu/class-validation.php`,
`php/admin-menu/class-debugging.php`, `php/_components/class-debugger.php`,
`php/wp-cli/cli-debug.php`, `php/wp-cli/cli-generate.php` (`run_debug_checker`),
`php/queeries/class-post-type.php`.

There are really **two** things called "debugger" in the codebase:

1. **The data validation stack** — `php/debugger/*` (scanners) → `php/validator/*` (admin
   views) → `Admin_Menu\Validation` (tabs) → `wp lwtv debug` (CLI) → cron day-of-week rotation.
2. **The debug logging stack** — `_Components\Debugger::debug_log()` +
   `Admin_Menu\Debugging` (ACF options page).

Most of the below is #1, since that's where the bugs and the scale risk are. #2 is in §6.

---

## Status — 2026-08-20

Shipped across four commits: `b6994ef0`, `de30e6f2`, `b47e982f`, `5b1d348e`, `44ee0947`.
`composer lint` clean, `vendor/bin/phpunit` green, `wp lwtv debug actors` verified working.

**Done**

| | |
|---|---|
| 1.1 | Airdates legacy key → shared `CPTs\Shows\Airdates`, with 14 unit tests |
| 1.2 | Transient key mismatches → constants on the scanner classes |
| 1.3a | `ltrim()` prefix bug → gone, along with the whole suffix-list approach (§9.1) |
| 1.4 | `$term->ID` no-op/fatal, both sites — plus a third found later in the theme |
| 1.5 | PHP 8.1 `false`-to-array → `Debugger\Status`; also fixed a dashboard-widget fatal |
| 1.8 | CLI exit code |
| 1.9 | `--force`, plus cache-age reporting and `--order` |
| 1.9a | `?message=` scheme, `Actor_Wiki`, dead `admin_post` hook — all removed |
| 1.9b | Three `Dupes::compare_duplicates()` bugs, incl. the override that never worked |
| §3 | Twitter/Instagram message mixup, always-true `isset()` dupe check, escaping |
| §6 | "Debuging" typo |
| §7 | CLI collapsed to a table-driven registry (validators not yet) |

**Outstanding, roughly in order**

| | |
|---|---|
| 2.1 | `fields => 'ids'` at the `Post_Type::make()` call sites — still serialising `WP_Post` objects |
| §6 | Debug log rotation, memoised option reads, log viewer |
| 1.7 | Decide the fate of `find_actors_incomplete()` |
| §8 | Typed findings → `Audit::finalize()` → `build/` extraction → repair layer → collapse the validators |

**Not verified yet:** the Watch Providers create-term and lookup buttons, and the TMDB
backfill write path. See Open questions.

---

## 1. Actual bugs (fix these first)

### 1.1 The Shows airdate check is reading a legacy meta key (FIXED)

`debugger/class-shows.php` reads `lezshows_airdates` as a single serialized array:

```php
'airdates' => array( 'message' => 'No airdates.', 'meta' => 'lezshows_airdates' ),
// ...
if ( is_array( $check['airdates'] ) ) {
    $start  = $check['airdates']['start'];
    $finish = $check['airdates']['finish'];
```

But ACF now stores `lezshows_airdates_start` and `lezshows_airdates_finish` as separate
keys (see `acf-json/group_lwtv_shows_details.json`), and the stats layer explicitly treats
`lezshows_airdates` as the *legacy* fallback (`statistics/class-stats-counter.php:51`,
`build/class-taxonomy-optimized.php:571-596`).

Consequences for any show that's been migrated:

- **"No airdates." fires falsely** (this key has no `empty_ok`/`skip`).
- `is_array()` is never true, so **the missing-end-date and start-after-end checks never
  run at all** — the two checks that actually matter.

Fix: read the ACF keys first, fall back to the legacy serialized array. **You already have
a correct implementation in this same directory** — `OnAir::fix_on_air_status()`
(`class-onair.php:137-145`) does exactly the right thing:

```php
$start  = get_post_meta( $show_id, 'lezshows_airdates_start', true );
$finish = get_post_meta( $show_id, 'lezshows_airdates_finish', true );
if ( empty( $start ) || empty( $finish ) ) {
    $legacy = get_post_meta( $show_id, 'lezshows_airdates', true );
    if ( is_array( $legacy ) ) {
        $start  = $start ?: ( $legacy['start'] ?? '' );
        $finish = $finish ?: ( $legacy['finish'] ?? '' );
    }
}
```

Extract that into a shared `get_show_airdates( int $show_id ): array` helper and have both
OnAir and Shows call it. This is the single highest-value fix in the file.

### 1.2 Two transient key mismatches — CLI never hits cache (FIXED)

| Writer | Admin reader | CLI reader |
|---|---|---|
| `lwtv_debug_show_url` (`class-shows.php:452`) | `lwtv_debug_show_url` ✓ | `lwtv_debug_show_urls` ✗ (`cli-debug.php:333`) |
| `lwtv_debug_on_air_problems` (`class-onair.php:83`) | `lwtv_debug_on_air_problems` ✓ | `lwtv_debug_on_air` ✗ (`cli-debug.php:353`) |

`wp lwtv debug show_urls` therefore *always* falls through to `find_shows_bad_url()`, which
is the most expensive check on the site (see 1.3). Same for `on_air`.

Fix: centralise the keys as constants on the scanner classes and reference them from both
readers. There is no reason for three files to spell the same string independently.

### 1.3 `find_shows_bad_url()` is expensive *and* low-signal — replace, don't optimise

`class-shows.php:386-449` loops every published show and does a synchronous
`wp_remote_get( $url, array( 'timeout' => 10 ) )` for **every** Ways-to-Watch URL, inline,
in whatever request triggered it.

At a few thousand shows with multiple URLs each, worst case is measured in hours. It also:

- runs on a **plain page load** if the transient is cold (`Show_URLs::make()` does
  `false === $items → full scan`), so simply visiting the tab kicks it off;
- has **no per-URL result cache** — the same Netflix/Hulu URL is fetched once per show;
- has **no dedupe**, no concurrency, no batching, no `set_time_limit` handling;
- is **not in the cron rotation at all** (`cli-generate.php` covers mon–sun;
  `find_shows_bad_url` and `find_actors_incomplete` appear nowhere), so it's never warm.

#### Why making it cheaper isn't enough

HTTP is the wrong instrument for this particular question:

- **Streaming services block datacentre IPs.** Netflix, Amazon, and Disney+ routinely 403
  server-side requests. The code already half-knows this — one branch reads *"We might be
  blocked from automated testing."* Every one of those is a false positive.
- **200 does not mean the show is there.** Netflix returns 200 with "this title is no
  longer available"; Amazon returns 200 with a search page. The signal you most want — *the
  show left this service* — is invisible to status codes.
- **Geo-dependence.** The corpus is international. A URL that resolves from a US IP may
  404 or redirect from an EU one, so the result depends on where the cron box sits.
- **Redirects are expected.** The legacy meta key was `lezshows_affiliate`; some of these
  are affiliate links, where a 301 is the entire point. Flagging 301/308 as "update the
  page so it doesn't have to redirect" is permanent noise.

So even at zero cost the output would be dominated by wrong answers. The fix is not a
faster link checker.

#### The signal is already computed, and thrown away

`theme/class-ways-to-watch.php::generate_links()` already classifies every one of these
URLs on each show render:

1. Parse to `scheme://host`.
2. Look it up in the `lez_watch_urls` provider registry via `get_term_by_url()` (exact
   match against the `lezwatchurls_all_N_url` term-meta rows).
3. On a miss, retry the www/non-www variant.
4. On a second miss → **`$old_style_urls[]`**, which falls through to
   `generate_links_old()` and *guesses* a provider name by string-munging the hostname
   against the hardcoded `SUBDOMAINS` / `TLDS` / `URL_OWNER` / `PRETTY_NAME` constants.

**Step 4 is where the signal lives.** Any URL reaching `$old_style_urls` has a host that
is not registered in the provider taxonomy, so the public site is rendering a guessed label
for it (and per 1.3a, often a wrong one). Detecting that costs one DB query per distinct
host and no network, and it's already being computed on every show page — just discarded.

How to *use* that signal needs care, though, because of the ratio below.

#### Measured 2026-08-20 — and it walks back two things I claimed above

Real numbers, from `DEBUGGER-QUERIES.sql`:

| | distinct hosts | show-host pairs |
|---|---|---|
| registered | 30 | 409 |
| **unregistered** | **124** | **932** |

So **154 hosts in use, 124 unregistered** — not "hundreds." And the distribution is
steeply top-heavy:

| top N unregistered hosts | pairs covered |
|---|---|
| 5 | 586 / 932 (63%) |
| 10 | 678 / 932 (73%) |
| **12** | **704 / 932 (76%)** |
| 20 | 761 / 932 (82%) |

Registering roughly a dozen hosts covers three quarters of unregistered usage. This is an
afternoon, not a backlog. Frequency-ranking (check #1 below) is still the right shape, but
the threshold conversation is much easier than expected.

**Correction 1 — the guess path is not producing garbage.** The top 12 unregistered hosts
all render correctly today:

```
netflix.com → 'Netflix'    cbs.com  → 'CBS'      hbo.com  → 'HBO'
amazon.com  → 'Amazon'     cwtv.com → 'The CW'   fox.com  → 'FOX'
hulu.com    → 'Hulu'       abc.com  → 'ABC'      bbc.co.uk → 'BBC'
crunchyroll.com → 'Crunchyroll'   hbomax.com → 'HBO Max'   nbc.com → 'NBC'
```

The three-letter-uppercase rule and the `PRETTY_NAME` entries carry the majors. Only **20
of 932** unregistered pairs get a genuinely ugly label, and they're all long tail:
`abc.go.com` → "Abc.go", `gem.cbc.ca` → "Gem.cbc", `therokuchannel.roku.com` →
"Therokuchannel.roku", `iview.abc.net.au` → "Iview.abc.net.au", `rtve.es` → "Rtve.es",
`animenetwork.net`, `6play.fr`, `alibi.uktv.co.uk`.

**Correction 2 — 1.3a is a 5-pair bug, not a widespread one.** I claimed the `ltrim()`
defect was mislabelling "a large share" of pages. Measured against the real host list it
affects **two hosts, 5 show-host pairs**: `watch.amazon.com` → "Mazon" (4) and
`gshow.globo.com` → "Lobo" (1). The reason is luck: `'www.'` — which prefixes almost every
host in the data — has character set `{w, .}`, and no host in use starts with `w` or `.`
after it, so the dominant case survives. Still worth the one-line fix; not the emergency I
made it out to be.

#### What this means for priority

The taxonomy port is **architectural tidying, not a bug fix**, and it should be sold as
such. The wins are real but they aren't "the front end is broken":

- **Control.** `hide_display` only works for registered hosts. Unregistered ones always render.
- **Retiring dead code.** `SUBDOMAINS`, `TLDS`, `URL_OWNER`, `PRETTY_NAME` and
  `generate_links_old()` all exist to compensate for an unpopulated registry.
- **Consistency.** "Amazon" vs the existing "Prime Video" term is the same service under
  two names, decided by which field an editor filled in.
- **A single source of truth** for provider naming, instead of a constant in a theme class.

Cheapest concrete win in the whole area: the **"Prime Video" term already exists** and just
doesn't list `amazon.com`. One URL row, 174 shows.

Also worth noting the `PRETTY_NAME` typo `'roosterteeth' => 'Roster Teeth'` — should be
"Rooster Teeth" (4 shows). And `sky.com` hits the three-letter rule and renders "SKY"
rather than "Sky".

#### Replacement: three checks, ~zero network

1. **High-traffic unregistered hosts** — hosts appearing on **≥ N shows** (start at 5 and
   tune) that have no `lez_watch_urls` term. Turns a several-hundred-item backlog into a
   short, ranked, genuinely actionable list: each entry earns a term, which buys a correct
   display name and `hide_display` control. The long tail is left alone by design.
2. **Syntax and internal consistency** — `wp_http_validate_url()`, `http://` that should
   be `https://`, duplicate URLs on one show, and hosts that contradict the show's
   `lez_stations` terms. Pure cross-referencing, which is what the rest of the debugger is
   already good at.
3. **Registry drift** — `lez_watch_urls` terms whose URLs appear on no show. With only 23
   terms this is a tiny, cheap check, and a term going to zero uses is a real signal (a
   provider that's been renamed, or URLs that changed shape).

Sizing queries for all of this are in `DEBUGGER-QUERIES.sql` (read-only).

**Host liveness is now optional and probably not worth it.** The original appeal was that
the taxonomy bounded the work — but 23 terms means it only ever covered a fraction of the
hosts in use, so it answers "is Netflix up" and nothing about the long tail. Given nobody
is acting on the current output (confirmed), the honest recommendation is to skip it and
revisit only if defunct providers turn out to be a real editorial problem. If it *is* built:
dedupe, `HEAD` before `GET`, 3–5s timeout, per-host concurrency caps, `Retry-After`
handling, and a **two-strike rule** (only report after failing on two separate runs), since
single-run failures are dominated by transient noise.

#### Not TMDB for URLs — but the TMDB numbers are the surprise here

Rejected as a *URL* check: TMDB's `/3/tv/{id}/watch/providers` returns provider *names* per
region plus one TMDB link, and the docs are explicit it is *"not going to return full deep
links."* It cannot validate a Ways to Watch URL, and it carries a mandatory JustWatch
attribution requirement with access revocation attached.

But the coverage query came back very differently from what I predicted:

| | count |
|---|---|
| published shows | 2262 |
| has a non-empty `lezshows_tmdb_id` | **248** (11%) |
| row exists but empty — *lookup ran, found nothing* | **0** |
| no row at all — *never attempted* | **2014** (89%) |
| has a non-empty IMDb ID | **2225** (98%) |

**I was wrong to predict patchy coverage.** There is no evidence TMDB lacks your shows —
there are zero recorded failed lookups. 89% simply never had a lookup attempted, because
`lezshows_tmdb_id` is only auto-populated on save and most shows haven't been saved since
that landed. This is a **backfill** problem, not a coverage problem.

And it's a tractable one: 2225 shows have an IMDb ID, and TMDB supports lookup by external
ID (`/3/find/{imdb_id}?external_source=imdb_id`). So the 2014 can be filled without human
effort, bounded by API rate limits.

Two cautions before committing to the full run:

- **The hit rate is still unknown.** Zero failures recorded doesn't mean zero failures
  exist — it means failures were never *recorded*. Run a sample of ~100 first and measure,
  then decide. Web series are still the likely soft spot.
- **Record the misses this time.** Whatever does the backfill should write a sentinel on a
  failed lookup (or a separate `lezshows_tmdb_checked` timestamp), so "no match" and "never
  tried" stop being indistinguishable. That ambiguity is the only reason this needed a
  query to answer.

This is plausibly higher value than anything else in §1.3 — it feeds `grading/class-tmdb.php`
and the show score, not just Ways to Watch. Tracked separately rather than folded in here.

### 1.3a `clean_subdomain()` uses `ltrim()` as if it stripped a prefix (FIXED — see §9.1)

`theme/class-ways-to-watch.php:244-254`:

```php
$hostname = ltrim( $hostname, $remove );   // $remove is e.g. 'gshow.' or 'watch.'
```

`ltrim()`'s second argument is a **character list**, not a prefix. So after the intended
prefix is removed it keeps eating any following character that appears anywhere in that
prefix. The `substr()` guard above it means this only fires on hosts that genuinely start
with one of `SUBDOMAINS`, but for those it is frequently wrong:

| Host | Renders as | Should be |
|---|---|---|
| `gshow.globo.com` | **Lobo** | Globo |
| `watch.tvnz.co.nz` | **Vnz** | TVNZ |
| `watch.cbc.ca` | **Bc** | CBC |
| `watch.aetv.com` | **Etv** | AETV |
| `www.wwe.com` | **E** | WWE |
| `play.apple.com` | **E** | Apple |
| `premium.example.com` | **Xample** | Example |

7 of 10 realistic hosts tested come out wrong. `gshow.globo.com` is clearly a supported
case — `.globo` is in the `TLDS` list — and it renders a button labelled "Lobo".
`watch.tvnz.co.nz` is worse than it looks: the mangled slug `vnz` also misses the
`PRETTY_NAME` lookup (which has `tvnz` => `TVNZ`), so the pretty name is lost too.

Fix: `substr( $hostname, strlen( $remove ) )`, or `str_starts_with()` plus a substring.

**Measured blast radius: 2 hosts, 5 show-host pairs** — `watch.amazon.com` → "Mazon" (4
shows) and `gshow.globo.com` → "Lobo" (1 show). Everything else survives because `'www.'`
has character set `{w, .}` and no host in use starts with `w` or `.` after it. The table
above is what the algorithm *can* do, not what it currently does to your data.

Still worth fixing — it's one line, both labels are live and wrong, and the same
`clean_subdomain()` call sits inside `check_alt_url()`, so the www/non-www fallback is
corrupted for those hosts too. But it's a small correctness fix, not a priority.

### 1.4 The "add NONE term" auto-fixes silently do nothing, and can fatal (FIXED — three sites, not two)

`class-shows.php:151-154` (tropes):

```php
$term = get_term_by( 'name', 'none', 'lez_tropes' );
wp_set_object_terms( $show_id, $term->ID, 'lez_tropes', true );
```

`class-characters.php:169-172` (clichés) — identical bug:

```php
$term = get_term_by( 'name', 'none', 'lez_cliches' );
wp_set_object_terms( $char_id, $term->ID, 'lez_cliches', true );
```

Two problems in both:

- `WP_Term` has **`term_id`, not `ID`**. `$term->ID` is undefined → `null` → the term is
  never set. Both of these have been quietly no-op'ing.
- `get_term_by()` returns `false` when the term is missing. `false->ID` is a **fatal** on
  PHP 8.1+. One renamed term takes down the whole Shows or Characters scan.

The `lez_cliches` one is the more consequential of the pair, since BYQ and the death
statistics key off cliché terms — characters that should carry `none` have been sitting
with no cliché term at all.

Fix: `if ( $term instanceof \WP_Term ) { wp_set_object_terms( $id, array( $term->term_id ), $tax, true ); }`
— and per §8 this becomes a repair action, not something a scan does.

### 1.5 `$option['x'] = ...` on a `false` option — deprecated on PHP 8.1+ (FIXED)

Repeated in all six scanners (`class-shows.php:186`, `class-actors.php:62,202,279`,
`class-characters.php:105,217`, `class-onair.php:86`, `class-queers.php:119`,
`class-dupes.php:51`):

```php
$option = get_option( 'lwtv_debugger_status' );   // false on fresh install
$option['show_problems'] = array( ... );          // "Automatic conversion of false to array is deprecated"
```

Same class of issue in `Admin_Menu\Validation::last_run()`, which does
`$options['timestamp']` with no `isset()` guard — a warning on any fresh site or after the
option is cleared.

And `Validation::current_status()` has `if ( empty( $options ) || ! array( $options ) )` —
`array( $options )` is always truthy, so that guard does nothing. It wants `! is_array()`.

### 1.6 Two admin tabs were unreachable — one resolved, one deliberate

`Validation::TOOL_TABS` had 9 entries while the `switch` in `settings_page()` handled 11
cases. Neither extra had a nav tab; you could only reach them by hand-typing the query
string.

- **`tab_actor_wiki`** — resolved by deletion. It was the superseded pre-Gutenberg WikiData
  UI, and its replacement already ships as an editor panel. See 1.9a.
- **`tab_show_urls`** — **deleted**, along with `Show_URLs::make()` and
  `find_shows_bad_url()`. The scan-on-cold-transient problem it was hiding from is fixed
  structurally rather than by omission: `tab_watch_term_check` never scans on render at all.
  It reads what `wp lwtv debug watchurls` (Sunday cron) left in a transient, and its only
  live action is a wall-clock-bounded re-probe of the URLs already flagged.

Post-cleanup the switch has 10 cases against 9 tabs, and that single remaining gap is
`show_urls` by choice.

### 1.7 `lwtv_debug_actor_empty` is a dead end

`find_actors_incomplete()` (`class-actors.php:219`) writes `lwtv_debug_actor_empty` and an
`actor_empty` status entry. Nothing reads either — no validator view, no CLI type, no cron
day. It shows up in `current_status()` counts and nowhere else. Either wire it up or delete it.

### 1.8 CLI exits 0 on failure (FIXED)

`cli-debug.php:104`: `\WP_CLI::error( $exception->getMessage(), false )` — the `false`
suppresses the exit. Cron (`cron/debug.sh`) can't distinguish a crashed check from a clean
one. Drop the `false`, or `WP_CLI::halt( 1 )`.

### 1.9a The `?message=` notice scheme and the Actor Wiki tab (REMOVED)

All of this was the **pre-Gutenberg WikiData UI**, superseded and left behind. Four
fragments of one dead limb in `admin-menu/class-validation.php`:

- `add_action( 'load-$page_id', ... )` — single-quoted, so the hook name was the literal
  string `load-$page_id`. Matched nothing, so `admin_notices()` never fired.
- `admin_notices()` was `private`, and passed an **HTML string** to `add_action` where a
  callable belongs. Even if the hook had fired, that's a `TypeError`, not a notice.
- `add_action( 'admin_post_lwtv_data_check_wikidata_actors', array( $this, 'check_actors_wikidata' ) )`
  pointed at a method **that does not exist on `Validation`** (it lives on
  `Debugger\Actors`). A fatal if ever reached.
- `Validator\Actor_Wiki` — the superseded view, itself broken: it called
  `check_actors_wikidata()` with no arguments (scanning *every* actor at up to two
  15-second remote calls each, on page render) and ran `esc_html()` against an array, so
  the dropdown never rendered. Its header already said
  `CURRENTLY NOT USED as it's WAAAAY too resource intensive`.

The decisive fact: **nothing anywhere set `?message=`.** No `add_query_arg`, no redirect,
nothing. So even correctly wired, none of the four notice strings could ever have fired.
There was no behaviour to preserve.

What replaced it, and is live and current: `blocks/src/wikidata-actor/` registers a
`PluginDocumentSettingPanel` titled **"WikiData Checker"** on the actor edit screen. It
fetches `/lwtv/v1/wikidata/{postId}`, hides fields that already match, renders the
mismatches with click-to-copy, and has a Refresh button. `rest-api/class-wikidata.php`
backs it with permission checks, `hide_actor_data()` privacy handling, prepared SQL, and a
documented "editors get a fresh fetch, everyone else gets stored meta" contract.

**Removed:** `add_admin_notices()`, `admin_notices()`, both `add_action` calls in `init()`,
the vestigial `$page_id` property, the `Actor_Wiki` import and switch case, and
`validator/class-actor-wiki.php` itself.

**Kept:** `Debugger\Actors::check_actors_wikidata()` — still used by the REST endpoint and
by `wp-cli/cli-check.php`.

**Follow-up for §8:** per-finding fix links will need admin feedback, but
redirect-and-read-a-query-arg is the wrong shape for it. Use WP's transient-backed admin
notices or `add_settings_error()` when that lands.

Note: `_components/class-admin-menu.php:23` carries the same vestigial
`protected $page_id = null;` from the same copy-paste. Harmless; left alone.

### 1.9b Duplicate-detection bugs in `Dupes::compare_duplicates()` (fixed)

Found while fixing the equivalent false-positive in `Shows`:

- `substr( $slug, 0, -2 )` strips exactly two characters, but `get_dupes()` matches
  `-[0-9]+$`. So `show-10` became `show-` and never resolved, silently skipping every
  show with a suffix of 10 or higher.
- `true !== $duplicate['override']` could never be false. ACF `true_false` fields store
  raw meta as `'1'`/`'0'`, never a real boolean — so the **dupe override checkbox has
  never suppressed anything**. Editors marking a false positive as "not a duplicate" had
  no effect.
- Two posts both missing an IMDb ID compared equal (`'' === ''`) and were reported as
  likely dupes.

### 1.9 No way to force a fresh CLI run (FIXED)

Every `run_*_check()` in `cli-debug.php` reads the transient and only rescans when it's
`false`. So `wp lwtv debug shows` can happily report week-old data with no indication.
Add `--force` (and print the cache age when serving cached results).

---

## 2. Scale & performance

### 2.1 `Post_Type::make()` is serialising thousands of `WP_Post` objects into a transient

`queeries/class-post-type.php:31-74` defaults to `posts_per_page = -1`, `fields = 'all'`,
then **caches the entire `WP_Query` object** for 30 minutes. Every debugger calls it as
`( new Post_Type() )->make( 'post_type_shows' )` and then immediately does
`wp_list_pluck( $the_loop->posts, 'ID' )` — it only ever wanted the IDs.

So each scan writes a multi-megabyte serialised blob of full post objects (post_content and
all) into the object cache / `wp_options`, to extract an array of integers.

Fix, in order of effort:

1. Pass `'ids'` from every debugger call site — one-line change each, immediate win.
2. Better: give the scanners a dedicated `get_ids( $post_type )` helper that queries
   `fields => 'ids'` and caches *only the ID array*.
3. `make()` caching a `WP_Query` object at all is questionable — consider caching the ID
   list and rehydrating, or dropping the cache for the `-1` case.

### 2.2 Scans are all-or-nothing, in-request

Every scanner is "loop every post, then write one transient". There's no batching, no
resume, no progress. This is fine for a 300-show site and increasingly not fine as the
database grows. The codebase already has the right pattern —
`php/schedulers/class-tmdb-batch-task.php`, `class-cache-batch-task.php`,
`class-cache-queue.php`. The debuggers should use it.

### 2.3 Read-modify-write race on `lwtv_debugger_status`

All six scanners do `get_option()` → mutate → `update_option()` with no locking. Cron
running a scan while an admin clicks "Rerun" on a different tab means one of the two count
updates is lost. Low severity, easy fix: store per-check status as its own option, or use
a single atomic write per check.

### 2.4 Scans mutate data while claiming to only report

See §8 — this is now a planned piece of work rather than just an observation. Shows and
Characters are done; Actors still writes during its scan.

---

## 3. Security & correctness hygiene (mostly FIXED)

- **No capability check inside the validators.** `Show_Checker::make()` etc. rely entirely
  on `add_submenu_page( ..., 'upload_files', ... )`. Add a `current_user_can()` guard in
  each `make()`, since these methods are public statics that trigger expensive writes.
- **Raw meta interpolated into HTML.** `'IMDb ID is invalid (ex: tt12345) -- ' . $imdb`,
  `'... -- ' . $url`, `'Instagram ID is invalid -- ' . $check['insta']`. It's laundered
  through `wp_kses_post()` at render time, but escape at construction instead
  (`esc_html()` / `esc_url()`) — the strings also go to CLI and JSON output where kses
  isn't applied.
- **`</br>`** is not valid HTML. Used as the problem separator in every scanner. Should be
  `<br />` — or better, keep `problem` as an **array** and let each renderer join it (see §5).
- **Copy-paste bug**: `class-actors.php:162` reports the *Instagram* value in the Twitter
  error message — `'Twitter ID is invalid -- ' . $check['insta']`.
- **`isset()` on an assigned variable**: `class-shows.php:217`
  `if ( isset( $pos_imdb ) && $pos_imdb === $check['imdb'] )` — always `true`. Also means a
  show with *no* IMDb ID matches another with no IMDb ID → false "Likely Dupe".
- **i18n**: `_n( 'show needs', 'shows need', $count )` in `class-show-checker.php:70` is
  missing the `'lwtv'` text domain, and the `translators:` comment above it references a
  `%s` that isn't there. CLAUDE.md requires the text domain on all user-facing strings.
  Most of `debugger/` and `validator/` is un-internationalised.
- **Alternate syntax**: the validator views use `<?php ... ?>` HTML interleaving with
  `if/else` braces (fine) but `debugger/` uses `if:`/`endif:` in a few spots — per your
  saved preference, new code should use braces.

---

## 4. The structural opportunity: adopt the `build/format/templates` split

Right now each check is one method that queries WordPress, evaluates rules, mutates data,
renders/persists, and writes status — all interleaved. That's why there are **zero tests**
for any of it, despite CLAUDE.md's rule that new display/transform logic goes in `build/`
and is added test-first.

Proposed shape, mirroring what `statistics/` and `this-year/` already do:

```
php/debugger/
├── build/                       # PURE. array in → findings out. Unit-testable.
│   ├── class-show-rules.php     # evaluate( array $show_data ): array $findings
│   ├── class-actor-rules.php
│   └── class-character-rules.php
├── collect/                     # WP glue: bulk-fetch the data the rules need
│   └── class-show-collector.php
├── repair/                      # explicit, opt-in fixes
├── class-runner.php             # batching, transient/option persistence, status
└── class-registry.php           # single source of truth for check definitions
```

The rules layer is the valuable part: `Show_Rules::evaluate()` takes a plain array of a
show's meta/terms and returns findings. That's testable in `tests/unit/` with no WordPress
bootstrap, which means the airdate bug in 1.1 becomes a two-line regression test.

---

## 5. Unify with the audit system — the good design is already in the repo

`debugger/class-audit.php` is markedly better engineered than the rest of `debugger/`:

- typed **issue vocabulary** (`ISSUE_TYPES` with `level` + `label`)
- stable **finding keys**
- **baselines** with `new` / `open` / `resolved` diffing
- **acknowledgements** ("ignore") applied as a *display* filter so they never corrupt the
  baseline — the comment on `finalize()` shows someone thought this through
- a documented "WP-CLI-free so a future wp-admin surface can reuse it" contract

The classic debugger has none of that. Its findings are `array( url, id, problem )` where
`problem` is an HTML-joined string blob. You cannot:

- tell a *new* problem from one that's been sitting there for six months
- acknowledge a known-fine false positive (the intersectionality note in
  `class-show-checker.php:76` is literally a human-readable workaround for this)
- count or filter by issue type
- see when something got fixed

**This is the biggest win available.** Give every finding an `issue_type` from a registry,
route all scanners through `Audit::finalize()`, and you get baselines, diffing, and
acknowledgements across all ten checks for roughly the cost of reshaping the finding
arrays. The tab badges become "3 new / 41 open" instead of a raw count that nobody can act on.

Two things to sort out when generalising `Audit`:

- `IGNORE_META` is hardcoded to `lezchars_audit_ignore` and `is_ignored()` only applies to
  findings with a `char_id`. Needs to become per-post-type (`lezshows_`/`lezactor_`) to
  cover show- and actor-level findings.
- Baselines are stored one option per scope (`lwtv_audit_baseline_{scope}`). Full-site
  scopes at current scale will be large options; check the size before scaling this to
  ten checks, and consider a custom table or per-post meta if it gets heavy.

---

## 6. Debug logging stack (`_Components\Debugger` + `Admin_Menu\Debugging`)

- **Unbounded log file.** `debug_log()` appends to `wp-content/debug-lwtv.log` forever with
  no rotation or size cap. With 20 topics enabled on a busy site this fills a disk. Add a
  size check with rotate-on-threshold, or a `wp lwtv debug-log rotate` command.
- **Two option reads per log call.** `is_debug_mode()` and `is_topic_enabled()` each call
  `get_field( ..., 'option' )` on *every* `debug_log()` invocation. Memoise both in static
  properties for the request — this is called in hot paths (`calculations`, `caching`,
  `statistics` topics).
- **Fail-open topics.** `is_topic_enabled()` returns `true` when `log_topics` is empty, so
  deselecting everything logs *everything*. Whether that's intended isn't documented; if it
  is, say so in the field description.
- **`WP_DEBUG` forces debug mode on.** `is_debug_mode()` short-circuits to `true` if
  `WP_DEBUG` is defined — meaning any dev environment writes `debug-lwtv.log` regardless of
  the plugin toggle. Probably intended, worth a comment.
- **Typo**: "Debuging Tools" (missing an `g`) appears twice in the menu registration —
  `admin-menu/class-debugging.php:69-70`. User-facing.
- **No log viewer.** The options page toggles logging but there's no way to read the log
  from wp-admin. A tail view (last N lines, filtered by topic) would close the loop, and
  the topic list is already a typed constant to filter on.
- **Dead sibling.** `error_log()` in the same class ignores debug mode and topics entirely
  and writes to PHP's error log. Fine as an escape hatch, but it's exposed as a template
  tag (`lwtv_plugin()->error_log()`) next to `debug_log()`, which invites misuse.

Also: `VALID_LOG_TOPICS` is a hardcoded list of 20 strings, and `debug_log( $type, ... )`
accepts any string with no validation against it. A typo'd topic silently logs (because
`is_topic_enabled()` fails open) but can never be turned off from the UI. Validate the
topic against the constant, or at least log a one-time warning for unknown topics.

---

## 7. Duplication worth collapsing

- **The "full scan or recheck" preamble** is copy-pasted ~7 times, verbatim, across
  `class-shows.php` (×3), `class-actors.php` (×4). ~20 lines each.
- **The "save transient + update option + return"** epilogue is copy-pasted ~9 times.
- **`php/validator/*.php`** is ten files of 102–107 lines that differ only in the
  transient key, the nonce name, the tab slug, and three strings. (An eleventh,
  `class-actor-wiki.php`, was the odd one out at 62 lines; deleted per 1.9a.)

All three collapse into a config array + one runner + one renderer. The `TOOL_TABS`
constant in `Validation` is already 80% of the config you'd need — extend it with the
transient key, the scanner callable, and the empty/error copy, then delete the ten files.
That also structurally prevents 1.2 (key mismatches) and 1.6 (orphan tabs) from recurring.

---

## 8. Splitting detect from repair (decided: `--fix-it` + per-finding fix links)

**Decisions taken:** repairs move behind `--fix-it`; wp-admin gets **per-finding fix
links**; findings **advertise what a fix would do** before you run it.

Per-finding fix links and "fixable" tagging both require findings to be individually
addressable, which the current `array( url, id, problem )` shape can't do — `problem` is an
HTML blob of several unrelated issues joined with `</br>`. So the §5 audit reshape is a
**prerequisite**, not a follow-up. That reorders the plan (see below).

### 8.1 You already have the target pattern

`class-onair.php` is the model — and it's the only scanner that got this right:

- `find_on_air_problems()` detects and returns findings, touching nothing.
- `fix_on_air_status( $show_id )` is a separate public method that performs one repair.
- `cli-debug.php:365-376` already gates it behind `--fix-it` with a progress bar.

Every other scanner should end up shaped like OnAir. This isn't a new pattern to invent,
it's one to generalise.

### 8.2 Full inventory of writes to relocate

| Location | Current write | Becomes |
|---|---|---|
| ~~`class-shows.php:147`~~ | `lezshows_worthit_rating` → `'TBD'` when thumb empty | **DONE** — `Shows::set_thumb_tbd()` |
| ~~`class-shows.php:153`~~ | set `none` trope (broken, 1.4) | **DONE** — `Shows::add_none_trope()` |
| `class-shows.php:391` | `Ways_To_Watch::migrate_ways_to_watch()` | see 8.4 — not a finding |
| ~~`class-characters.php:171`~~ | set `none` cliché (broken, 1.4) | **DONE** — `Characters::add_none_cliche()` |
| `class-actors.php:156` | delete `lezactors_instagram` when it's an IMDb ID | repair `actor-instagram-is-imdb` |
| `class-actors.php:165` | delete `lezactors_twitter` when it's an IMDb ID | repair `actor-twitter-is-imdb` |
| `class-actors.php:176-177` | homepage → wikipedia, clear homepage | repair `actor-homepage-is-wikipedia` |
| `class-actors.php:180` | delete homepage when it equals wikipedia | repair `actor-homepage-dupe-wikipedia` |
| `class-actors.php:398` | delete `debug_check` meta on drafts | see 8.4 — stray cleanup |
| `class-actors.php:488-491` | write `lezactors_saved_wikidata` | see 8.4 — cache, not a fix |
| `class-actors.php:578` | write `lezactors_wikidata_qid` | see 8.4 — cache, not a fix |
| `class-onair.php:148-161` | `lezshows_on_air` | already correct — keep as-is |

Note that four of these currently fire *unconditionally on every scan* even when nothing
changed (the `worthit_rating` → TBD write in particular re-writes the same value every
run), so this also removes a pile of pointless `update_post_meta` calls per scan.

**Shows and Characters are done (2026-08-26).** The three writes above moved into
`fix_show_data()` / `fix_character_data()` — dispatchers in the `OnAir::fix_on_air_status()`
mould, registered as the `fixer` for the `shows` and `chars` checks in `cli-debug.php`, so
`--fix-it` reaches them through the existing `apply_fixes()` path. Both scans are now
side-effect free. Consequences worth remembering:

- `'thumb'` and `'tropes'` in `ITEMS_TO_CHECK` lost their `'skip' => true`. They only
  carried it because the scan repaired them before it could report them; with the repair
  gone the findings have to be visible or `--fix-it` has nothing to iterate.
- Both scans therefore report more than they used to. Messages advertise the fix inline
  ("— fixable, adds the "none" trope") since the §8.3 `fix_label` field doesn't exist yet.
- `fix_show_data()` returns `false` for a show whose only problems have no automated
  repair, so `apply_fixes()` counts it as "could not be fixed automatically". That's the
  cost of the flat `problem` blob — the per-issue shape in §8.3 is what makes the count
  honest. Not misleading, just noisy.

Still outstanding here: the four Actors writes (destructive deletes, own review pass) and
everything in §8.3–§8.5.

### 8.3 Finding shape

One row per issue, not per post, with fixability declared up front:

```php
array(
    'post_id'    => 4213,
    'post_type'  => 'post_type_shows',
    'issue_type' => 'show-missing-trope',      // key into the registry
    'message'    => 'No tropes set.',          // human copy, from the registry
    'context'    => array(),                   // optional extra (the bad URL, the bad ID)
    'fixable'    => true,
    'fix_label'  => 'Add the "none" trope',    // what the fix will actually do
)
```

`fixable`/`fix_label` come from the issue registry keyed on `issue_type`, so the detect pass
never has to know *how* a fix works — only that one is registered. That keeps the rules
layer pure and unit-testable (§4) while still letting a scan report
"12 of these 40 can be fixed automatically."

A single registry entry then drives everything:

```php
'show-missing-trope' => array(
    'level'   => 'show',
    'label'   => 'No tropes set',
    'fix'     => array( Show_Repairs::class, 'add_none_trope' ),
    'fix_label' => 'Add the "none" trope',
),
```

CLI `--fix-it` iterates findings where `fixable`, calls the registered callable. The admin
per-finding link is the same callable behind `admin_post_lwtv_debug_fix` with a nonce, the
`issue_type`, and the `post_id`. One implementation, two surfaces.

### 8.4 Writes that are *not* fixes

Four of the writes above shouldn't become `--fix-it` actions at all, and lumping them in
would be a mistake:

- **`migrate_ways_to_watch()`** (`class-shows.php:391`) is a data migration that happens to
  live inside a URL checker. It belongs in a one-shot `wp lwtv migrate` command, not in a
  scan. Right now it only runs against shows whose URL check happens to execute — which,
  given 1.3 and 1.6, is close to never.
- **`lezactors_wikidata_qid`** and **`lezactors_saved_wikidata`** are result caching for an
  expensive remote lookup. Legitimate, but they should be an explicit cache write with a
  TTL, not an incidental side effect of comparison.
- **`delete_post_meta( ..., 'debug_check' )`** on drafts (`class-actors.php:398`) references
  a `debug_check` meta key that **nothing else in the codebase reads or writes**. It's
  vestigial — delete it.

### 8.5 Transient shape migration

Findings live in week-long transients. Changing the shape means new code will read old-shape
payloads until they expire. Either bump the key names (`lwtv_debug_shows_v2`) or add a
`'version'` marker and treat a mismatch as a cache miss. Cheap to do, annoying to debug if
forgotten. `Actors::flag_shadow_sync_failure()` (`class-actors.php:41`) also appends
directly to the transient and will need updating to the new shape at the same time.

---

## 9. Built during this pass, beyond the original plan

Things that weren't in the review when it was written, mostly because they came out of
looking at real data.

### 9.1 Ways to Watch naming, rebuilt (`b47e982f`)

The whole suffix-stripping approach is gone. `CPTs\Shows\Host_Name` is pure, unit-tested
(23 tests) and answers "which label carries the name" using a short list of compound public
suffixes rather than a hand-maintained pile of pseudo-TLDs. It also generates the candidate
host forms used for term matching, by dropping leading labels — so `gshow.globo.com` offers
`globo.com` without anyone having listed `gshow.` anywhere.

Fixed along the way, all of them live defects:

- **Every unregistered host rendered in ALL CAPS.** `$uc_string || 3 === strlen(...)` —
  the `||` short-circuited and `generate_links_old()` always passes `true`. `NETFLIX`,
  `CRUNCHYROLL`, `TELLOFILMS`.
- **Curated 3-character term names were being overridden.** `ViX` → "VIX", `Max` → "MAX".
  The registered path now uses `$term->name` verbatim; nothing reformats it.
- **`hide_display` had never worked.** `$terms[0]->ID` — `WP_Term` has `term_id`. Third
  instance of the 1.4 bug.
- **`'es'` in `TLDS` had no leading dot**, so it ate the last two characters of anything
  ending in "es": `rtve.es` → "Rtve.".
- **`.go.com` was unreachable**, shadowed by `.com` matching first.
- `build_link()` now escapes its URL and name.
- Scheme- and www-insensitive matching in one query, with candidate priority resolved in
  PHP so a term on `abc.go.com` beats one on `go.com`.
- Deleted `check_alt_url()`, superseded and uncalled.

**Measured, and it walked back two of my claims.** 154 hosts in use, 124 unregistered —
not "hundreds". The top 12 are 76% of unregistered usage. And 1.3a turned out to affect
**5 show-host pairs**, not "a large share": `'www.'` has character set `{w, .}` and nothing
in the corpus starts with `w` or `.` after it, so the dominant case survived by luck.

### 9.2 Provider name enrichment (`5b1d348e`)

`CPTs\Shows\Watch_Host_Names` caches a name discovered from each host's own
`og:site_name` / `application-name`, in one non-autoloaded option keyed by normalised host.
Rendering reads it only — no request ever fetches anything. Populated by
`wp lwtv waystowatch enrich`, which skips hosts that already have a term, records genuine
misses so it doesn't re-ask, and deliberately does *not* record errors so an outage can't
permanently mark a host unnamed.

Display is three tiers: term name → discovered name → `Host_Name::guess()`.

Rationale for building it despite the backlog being small: hosts arrive continuously with
new shows, so the value is in the flow, not the backlog. My initial analysis was static and
wrong about this.

### 9.3 Watch Providers admin tab (`5b1d348e`)

New tab listing hosts with no provider term, ranked by show count, with the proposed name
in an editable field and a one-click **Create term** button that writes the ACF repeater in
the same shape ACF itself uses. Plus a bounded **Look up names** button.

Two supporting pieces:

- `CPTs\Shows\Watch_Hosts` — shared by the tab and the CLI so they can't disagree about
  what's registered. `term_for()` delegates to the theme's own matcher.
- **The tab bar is now a dropdown**, with counts moved into the option labels. Ten checks
  was already wrapping.

Notice plumbing is new, since 1.9a deleted the `?message=` scheme: a per-user transient,
read-and-cleared on render, carrying arbitrary text plus an optional link. That is the
pattern §8's per-finding fix links should reuse.

Note the handlers register in `_Components\Admin_Menu::init()`, **not**
`Admin_Menu\Validation::init()` — the latter fires on `admin_menu`, which `admin-post.php`
never triggers. That was the second independent reason the old wikidata `admin_post` hook
was dead.

### 9.4 TMDB backfill (`5b1d348e`)

`wp lwtv tmdb [status|backfill] [shows|actors|all]`. Needed no new API code —
`CPTs::get_tmdb_info()` already falls back to `find/{imdb_id}?external_source=imdb_id`.

Coverage on a recent local copy:

| | shows | actors |
|---|---|---|
| published | 2255 | 6070 |
| has a TMDB ID | 242 | 822 |
| checked, no match | 0 | 0 |
| no IMDb to look up with | 37 | 153 |
| **candidates** | **1976** | **5095** |

Every published post lands in exactly one bucket, which is a decent sign the queries are
right. **Zero recorded failed lookups** — so the earlier prediction of patchy TMDB coverage
was unfounded; it's a backfill problem, not a coverage problem.

Design points worth keeping:

- `lezshows_tmdb_checked` / `lezactors_tmdb_checked` sentinels, registered in
  `CPTs\Post_Meta`, written on a hit or a genuine no-match but **never** on an API error.
  That ambiguity is the only reason this needed a query to diagnose.
- `--order=oldest|newest|random`, because oldest-first is a biased sampler. The 100% hit
  rate measured so far was on the oldest 100 shows and should not be extrapolated from.
- Shows detect a `wrong_kind` outcome: TMDB filing a TV movie under `movie_results`.
  Reported, not stored — a movie ID would break the later `/tv/{id}` call — and not
  sentinelled, since TMDB does have data.

### 9.5 `lez_watch_urls` admin column (`b47e982f`)

A **URLs** count column on the taxonomy list. The root cause of the clutter turned out to
be that `CPTs\Shows\Ways_To_Watch` was **never instantiated**, so its existing filters to
drop `description`, `count` and `slug` had never run. `count` is permanently 0 there because
these terms are never *assigned* to shows — the relationship is resolved by URL matching in
term meta.

### 9.6 Review fixes (`44ee0947`)

Four Gemini findings on the PR, all in code written during this pass:

- `$total` unescaped in a `printf()`.
- The lookup button bounded by **count** rather than wall clock — 10 hosts × 6s was 60s
  worst case, on top of a typical 30s `max_execution_time`. Now a 15s budget that stops
  before *starting* a request that could overrun, a separate `UI_TIMEOUT` of 3s, and
  `UI_BATCH` demoted to a secondary guard. This was a smaller replay of the exact mistake
  §1.3 is about.
- `Airdates::get()` made static, matching `resolve()` and `is_still_airing()`.
- Stateless classes instantiated per iteration: the four pure `_Components\Debugger`
  helpers made static (**nine** call sites, worst being four per actor across ~6070), and
  `Theme\Ways_To_Watch::get_term_by_url()` likewise.

---

## Suggested order of work

Everything in the **Done** table at the top has shipped. What's left, in the order I'd take it:

**Verify what's built but unexercised** — cheap, and the riskiest unknowns:

1. **Create one term from the Watch Providers tab**, then check it renders on a show page
   *and* opens cleanly on the term edit screen. `Watch_Hosts::create_term()` writes the ACF
   repeater rows by hand (`lezwatchurls_all_N_url` plus the `_`-prefixed field keys); if
   that shape is wrong the term will look fine in the list and quietly match nothing.
2. **`wp lwtv waystowatch hosts`** — no writes, and it cross-checks `Watch_Hosts::in_use()`
   against the SQL numbers in §9.1. If the host count isn't near 154, normalisation is off.
3. **`wp lwtv tmdb backfill shows --dry-run --order=random`** — the only hit rate measured
   so far is from a biased sample. Then a real run of 100 before `--all`.

**Small and independent:**

4. **`fields => 'ids'`** at every `Post_Type::make()` debugger call site (2.1). Still the
   cheapest performance win outstanding.
5. **Debug log rotation + memoised option reads** (§6). `debug_log()` still reads two ACF
   options per call and the log file still grows without bound.
6. ~~**Delete `find_shows_bad_url()` and link `tab_show_urls`** (1.3, 1.6).~~ **Done.**
   `find_shows_bad_url()`, `Shows::TRANSIENT_URL`, `validator/class-show-urls.php` and the
   unreachable `tab_show_urls` case are gone. Host liveness was *not* skipped after all —
   it moved to `wp lwtv debug watchurls` / the Watch Term Check tab, which probes the few
   hundred **term** URLs instead of the thousands of show URLs that resolve to them. Its
   stale `show_url` status entry is pruned by `wp lwtv migrate acf debugstatus`.
7. **Decide `find_actors_incomplete()`** (1.7): wire it up or delete it.

**Then the structural chunk (§8), which is unchanged and still worth doing together:**

8. **Issue registry + typed findings** (§5, §8.3), including the transient shape migration
   (§8.5). Everything below depends on it.
9. **Route findings through `Audit::finalize()`** (§5) — baselines, new/open/resolved,
   acknowledgements. Needs `IGNORE_META` generalised per post type.
10. **Extract pure rule evaluation into `debugger/build/`** with tests (§4). `Host_Name` and
    `Airdates` are both working examples of the shape now — pure, no WP, unit-tested.
11. **Move the writes to a repair layer** (§8.2) behind `--fix-it`, plus per-finding admin
    fix links (§8.1). The notice plumbing from §9.3 is the piece that was missing.
12. **Collapse the ten validator files** (§7). The CLI registry and the `Watch_Hosts`
    extraction are both precedents to copy.
13. Add the missing checks to the cron rotation — including `waystowatch enrich`, which is
    safe to run weekly since it skips anything already asked.

---

## Open questions

**Answered during this pass:**

- **TMDB coverage** — not a coverage problem. Zero recorded failed lookups; 1976 shows and
  5095 actors simply never had one attempted, with IMDb IDs available to backfill from.
- **Is anyone acting on Show URLs output?** No. Nothing to preserve.
- **Ways to Watch hosts** — 154 in use, 124 unregistered, top 12 = 76% of unregistered
  usage. Also walked back the claim that 1.3a was widespread; it's 5 pairs.
- **Scheme split** — 1292 https vs 50 http. Handled in the matcher rather than by
  registering both variants per term.
- **`PRETTY_NAME` migration hazard** — none. No term was named like a constant key.
- **37 shows with no IMDb vs "Excellent!" on the tab** — both correct. The Shows check
  exempts web series (`has_term( 'web-series', 'lez_formats' )`); the Actors check doesn't,
  which is why the actor numbers matched exactly at 153. Panel copy now says so.
- **Where should the provider screen live?** A tenth Validation tab, with the tab bar
  converted to a dropdown so it can keep growing.

**Still open:**

- **Does the ACF repeater shape written by `create_term()` match what ACF expects?** The
  one thing in this pass with a plausible silent-failure mode.
- **What's the real TMDB hit rate?** `--order=random` hasn't been run.
- **What threshold makes a host "worth registering"?** The tab is unfiltered right now.
  `--min-shows` exists on the CLI; the distribution suggests somewhere around 3–5.
- **Should `enrich` go on the weekly cron?** It's designed for it — skips already-asked
  hosts, so repeat runs are nearly free — but it isn't wired in yet.
- **Is `amazon.com` really Prime Video?** 174 shows, and the term already exists. Still
  worth eyeballing a few before merging them.
