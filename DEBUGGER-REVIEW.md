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

## Status — 2026-08-27

`composer lint` clean, `vendor/bin/phpunit` green. Each converted check verified with a live
`--force` run.

### What the debugger looks like now

The architecture the plan asked for exists, for every check:

```
php/debugger/
├── build/     PURE. Unit-tested, no WordPress.
│   ├── class-issue-registry.php   the issue vocabulary: copy, level, repairs
│   ├── class-findings.php         build, group, describe, render, prune
│   ├── class-baseline.php         new / open / resolved
│   └── class-*-rules.php          the rules, one class per subject (11)
├── collect/   WordPress glue. Fetches what the rules need, decides nothing.
│   └── class-*-collector.php      one per rules class that needs reads (9)
├── format/
│   └── class-rows.php             findings → the rows the surfaces read
├── class-scan.php                 the shared scan halves: targets in, rows out
├── class-baseline-store.php       per-check baseline storage
├── class-repair.php               admin_post per-finding repairs
└── class-*.php                    the scanners, now orchestration
```

A finding is typed (`post_id` + `issue_type`), knows whether a repair exists, and is diffed
against the previous run. One registry drives the CLI's `--fix-it`, the admin's per-finding
buttons, and the copy in both. Every scanner is now the same three steps between two calls
to `Scan`: get targets, collect, evaluate, finish.

**Done since 2026-08-20**

| | |
|---|---|
| 1.4 | The `none` term lookups were by *name*; the term is named `None!`. Fixed by slug |
| 1.7 | `find_actors_incomplete()` wired up — tab, CLI entry, cron slot |
| 1.9c | BYQ reporting gate counted show findings and death-year findings together (new) |
| 2.1 | `Post_Type::get_ids()` — nine debugger sites plus `cli-shadow.php` |
| 2.4 | Scans no longer mutate data; every write moved behind a repair |
| §4 | `build/` + `collect/` split — **every check**. 11 rules classes, 9 collectors, ~200 tests |
| §5 | Baselines and new/open/resolved — as a pure `Build\Baseline`, not via `Audit` |
| §8.1–8.5 | Detect/repair split, typed findings, both repair surfaces, stray writes gone |
| 14 | Every check converted — all eleven report "N new / M open" |
| §7 | All three duplications collapsed — validators into `Report`, scanners into `Scan` |
| §5 | Acknowledgements — **declined**, not enough false positives to earn the machinery |
| §3 | Capability check now lives once, in `Validator\Report::make()` |

**Outstanding, roughly in order**

| | |
|---|---|
| §6 | Debug log rotation, memoised option reads, log viewer |
| §8.4 | The wikidata cache writes still want an explicit TTL |

**Not verified yet:** the Watch Providers create-term and lookup buttons. The TMDB backfill
write path **has** now been run and works.

**Two conventions worth knowing before editing any of this:**

- **Additive row changes only.** Finding rows are a superset of the old
  `array( url, id, problem )`, which is why no transient migration has been needed. The
  first change that *removes* or repurposes a key needs the version marker in §8.5.
- **A repair that is a judgement call is `manual`** — offered per finding in wp-admin, never
  applied by `--fix-it`. See §8.3 and the acknowledgement note in §5.

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

### 1.9c The BYQ reporting gate counted the wrong things (FIXED — 2026-08-26)

`find_byq_problems()` decides whether to report a character at all with:

```php
if ( ! empty( $problems ) && ( count( $shows ) - count( $problems ) ) !== 1 ) {
```

The intent, per the comment, is "report unless the character is marked dead on exactly one
show". But `$problems` does not only hold show-level findings — the missing-`lezchars_death_year`
problem goes in the same array, and it is not about a show at all. So a character with one
show missing the trope *and* no death year has `count( $shows ) - count( $problems ) = 1 - 2
= -1`, passes the gate, and reports; the same character with a death year has `1 - 1 = 0`,
also reports; a character on two shows with one bad trope and no death year has `2 - 2 = 0`,
reports. It is hard to construct the case the comment describes, and the arithmetic mixes
two different kinds of finding.

**The intent, confirmed:** a character is killed on one show, and may have been on others
that never killed her. Show A carries the BYQ trope; Show B correctly does not. So a
per-show "missing trope" finding cannot be judged on its own merits — being on a show with
no trope is completely normal.

The invariant is therefore on the shows that *do* carry it: **exactly one**. None means
nobody recorded the death on the show it happened on. More than one means two of her shows
claim a queer death, which can be legitimate (a show can kill someone else) but is worth an
eyeball — and reports nothing anyway, since with no trope missing there are no findings to
show. The gate can only ever suppress, never invent.

**Fixed accordingly:** the gate now counts only `char-show-no-byq-trope` findings, and the
missing-death-year finding reports on its own terms. Two behaviour changes fall out, both
surfacing things that were previously hidden:

| shows | missing trope | death year | before | after |
|---|---|---|---|---|
| 2 | 0 | absent | silent | reports the death year |
| 3 | 1 | absent | silent | reports both |
| 2 | 1 | present | silent | silent (the Show A / Show B case, correct) |

So **expect the BYQ count to go up**, and the increase is real rather than noise: a dead
character with no death year whose tropes were otherwise fine used to be dropped from this
report entirely.

One rough edge left, not worth chasing yet: with three shows where two carry the trope, the
character surfaces via the one show *missing* it, so the message points at the show that is
arguably fine while the anomaly is the two that claim deaths. It surfaces the right
character with a slightly confusing reason.

### 1.7 `lwtv_debug_actor_empty` is a dead end

`find_actors_incomplete()` (`class-actors.php:219`) writes `lwtv_debug_actor_empty` and an
`actor_empty` status entry. Nothing reads either — no validator view, no CLI type, no cron
day. It shows up in `current_status()` counts and nowhere else. Either wire it up or delete it.

**WIRED UP (2026-08-26).** Kept rather than deleted, and given all four things it was
missing: `validator/class-actor-empty.php` and a `TOOL_TABS` entry (as "Incomplete Actors"),
a `switch` case in `settings_page()`, an `actor_empty` entry in the CLI check registry, and
a slot on Thursday's cron run alongside the other two actor scans. It emits typed findings
(`actor-no-image`, `actor-no-bio`) and diffs against a baseline like the rest.

Its panel copy says out loud that this is a completeness report rather than a fault report —
a brand new actor legitimately has neither a photo nor a bio yet, and a report that reads as
an accusation is one people learn to ignore.

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

**DONE (2026-08-26), taking option 2.** `Post_Type::get_ids( $post_type )` queries
`fields => 'ids'` and caches just the integers. All nine debugger call sites use it, plus
`cli-shadow.php`, which had the identical pattern. Each site collapsed from six lines to
one, and the `is_object()`/`have_posts()` dance went with them.

Two things fell out of it:

- **A stray `wp_reset_query()`** in `find_shows_no_imdb()` is gone. Constructing a `WP_Query`
  never touched the globals it resets — only `the_post()` would have, and that scan never
  called it. It was defending against nothing.
- **`make()` was left alone.** It still caches whole `WP_Query` objects, which is still
  questionable (option 3), but its remaining callers — the REST endpoints and
  `export-json` — genuinely iterate posts. Worth revisiting, not worth bundling.

Still outstanding nearby, both easy and both the same mistake in different clothes:

- **`class-what-happened-json.php:265`** calls `make()` for every show, then loops
  `the_post()` and uses nothing but `get_the_ID()` and meta. That is a full post-object load
  on a public REST endpoint for data it does not read.
- ~~**`Queery_Taxonomy::get_posts_for_terms()`** (used by the BYQ check) has not been looked
  at and may have the same `fields` default.~~ **Looked at, and it is a different problem.**
  It does default to `fields => 'all'`, but it **does not cache the query**, so the
  serialised-blob issue above does not apply — a caller asking for full posts pays memory
  for one request, not a multi-megabyte cache write. Two smaller things were real and are
  fixed:

  - `posts_per_page` was `wp_count_posts( $post_type )->publish` — an upper bound derived
    from every published post of the type, used to page a query that returns a filtered
    subset. It did the same job as `-1` while adding a lookup and implying a limit that was
    never meaningful. Now `-1`.
  - It gained an optional `$fields` argument, and the BYQ **debugger** scan passes `'ids'`,
    since it only plucked them out again.

  The BYQ **REST** endpoint deliberately still asks for full posts: `class-byq.php` reads
  `post_title` and `post_name` off each result, not just the ID. Its `generate_death_list_array()`
  also accepts a caller-supplied query, so it could be handed either shape — worth
  remembering before "optimising" it later.

  Its `wp_reset_query()` was left alone. Unlike the stray one removed from
  `find_shows_no_imdb()`, this one restores the global query, which a template-context
  caller could in principle be relying on, and CLAUDE.md records direct `wp_reset_query()`
  as intentional in this codebase.

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

### 2.4 Scans mutate data while claiming to only report (FIXED)

See §8. Every scan is now detect-only. What remains is not a scan mutating data: the
wikidata cache writes (§8.4), which want an explicit TTL, and
`migrate_ways_to_watch()`, which is a migration in the wrong place.

---

## 3. Security & correctness hygiene (mostly FIXED)

- ~~**No capability check inside the validators.** `Show_Checker::make()` etc. rely entirely
  on `add_submenu_page( ..., 'upload_files', ... )`. Add a `current_user_can()` guard in
  each `make()`, since these methods are public statics that trigger expensive writes.~~
  **FIXED (2026-08-26)** — by the §7 collapse, in one place instead of ten:
  `Validator\Report::make()` verifies `upload_files`, the cap the submenu is registered
  with. The repair path guards separately and more tightly: `Debugger\Repair` requires
  `edit_post` on the specific post, on the grounds that reading a report and changing a post
  are not the same right.
- **Raw meta interpolated into HTML.** ~~Escape at construction instead.~~ **Resolved the
  other way, deliberately (2026-08-26).** Escaping at construction was tried and reverted:
  the admin renders through `wp_kses_post()`, so a pre-escaped string got escaped twice and
  displayed its entities. Messages now carry raw values and each renderer escapes —
  `wp_kses_post()` in the admin, `Findings::plain()` for the CLI, which also strips markup
  and decodes entities. The concern was right; the fix belonged at the other end.
- **`</br>`** is not valid HTML. Still the separator in the composed `problem` blob, which
  the admin table has always been fed. But `problem` is now *derived*: rows carry `issues`
  and raw `messages`, so a renderer can join them however it likes, and the CLI already
  does. Fixing the blob itself is now a one-line change in `Findings::problem_from()`
  whenever the admin table is next touched.
- ~~**Copy-paste bug**: `class-actors.php:162` reports the *Instagram* value in the Twitter
  error message.~~ **Fixed**, and now structurally prevented: `Actor_Rules::social()` takes
  the platform as a parameter and derives both the meta key and the label from it, so the
  two cases cannot drift apart again.
- ~~**`isset()` on an assigned variable**: `class-shows.php:217` — always `true`, so a show
  with *no* IMDb ID matched another with no IMDb ID → false "Likely Dupe".~~ **Fixed**, and
  now covered by a regression test (`ShowRulesTest::test_two_missing_imdb_ids_are_not_a_match`).
- **i18n**: `_n( 'show needs', 'shows need', $count )` in `class-show-checker.php:70` is
  missing the `'lwtv'` text domain, and the `translators:` comment above it references a
  `%s` that isn't there. CLAUDE.md requires the text domain on all user-facing strings.
  Most of `debugger/` and `validator/` is un-internationalised — and the copy that moved
  into `Issue_Registry` is not translated either. That is now the right place to fix it
  once: one file holds every finding message, instead of the strings being scattered across
  ten scanners.
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

**Done for Shows (2026-08-26).** `debugger/build/`, `debugger/collect/` and
`debugger/format/` all exist now:

- `build/class-show-rules.php` — every rule that decides whether a show has a problem,
  pure, with `CHECKS` (the old `Shows::ITEMS_TO_CHECK`) declared here since it is rule
  config rather than WordPress glue. 30 tests in `tests/unit/Debugger/ShowRulesTest.php`,
  two of which are regressions for bugs that actually shipped: the airdate check reading
  only the legacy meta key (§1.1) and the duplicate matcher treating two *missing* IMDb IDs
  as a match.
- `collect/class-show-collector.php` — everything that touches the database, and nothing
  that decides anything.
- `find_shows_problems()` is now ~35 lines of orchestration: pick the IDs, collect, evaluate,
  diff, render.

Three things worth knowing:

1. **Airdate problems became four issue types** (`show-no-airdates`, `show-no-start-date`,
   `show-no-end-date`, `show-airdate-inverted`) instead of four different strings under one
   `show-airdate`. That was the follow-up §8.3 left open. `show-airdate` stays registered as
   a retired entry so baselines written before the split still resolve to readable copy —
   but the first scan after deploy will report the old key as resolved and the new ones as
   new. Run `wp lwtv debug shows --reset-baseline` first if that churn is not wanted.
2. **The duplicate and intersection copy moved into the registry**, where §8.3 said human
   copy belongs. They were the last two messages still living in scanner code.
3. **Collection is batched, and that is a real performance change.** The old scan read five
   taxonomies per show one show at a time; `wp_get_object_terms()` accepts a list of IDs, so
   it is now one term query per 200 shows. That is the §2.1 complaint addressed from a
   different angle than `fields => 'ids'`, and the two are complementary.

`Characters::check_disabled_characters()` became dead when the cross-check moved into the
collector, and was deleted — same reasoning as the vestigial `debug_check` write in §8.4.

**Characters followed (2026-08-26).** `Build\Character_Rules` +
`Collect\Character_Collector`, 21 tests, and `find_characters_problems()` down to the same
~35 lines of orchestration. Notes:

- **The dead-without-a-date rule is the one worth having tested.** BYQ and the death
  statistics both key off the `dead` cliché, so a dead character with no date is counted in
  one place and missing from another. There is now a test pinning that `dead-queers` — a
  *show* trope — does not match it, which a substring comparison would have got wrong.
- **The collector primes the post cache for every show a batch references.** The rules want
  show titles in their messages, and asking one at a time is a query per show row.
- **One copy fix:** the old message appended the show title unconditionally, so a show row
  naming no show produced `No role set for .` It now reads `No role set.` in that case.
- **`has_years` still only tests "is an array"**, exactly as before. An empty `appears[]`
  is a different situation from the field never having been filled in, and it was never
  reported; preserving that was deliberate rather than an oversight.

**Actors followed too (2026-08-26), which completes §4 for all three post-based checks.**
`Build\Actor_Rules` + `Collect\Actor_Collector`, 26 tests. The collector is the thinnest of
the three, because every actor check is a meta read. Four notes:

- **Messages now carry raw values.** The rules used to call `esc_html()` and
  `sanitize_url()` while composing copy, which is both impure and wrong: the admin renders
  through `wp_kses_post()`, so a pre-escaped string got escaped twice and displayed its
  entities. Escaping belongs to the renderer, and there is a test pinning it.
- **`looks_like_actor_imdb()` moved into the rules**, where it belongs, and the repair
  (`Actors::remove_imdb_from_social()`) now re-checks through it. The 6-digit floor is a
  named constant (`IMDB_MIN_DIGITS`) with the reasoning attached, and the boundary is
  tested at 5 and 6 digits.
- **The `$warnings` array is gone.** It collected "death date set without date of birth"
  and nothing ever read it. The judgement is still unmade — plenty of people have no
  recorded DoB — so the *data* is still collected and the open question is recorded on
  `Actor_Rules::meta_keys()` instead of in a write-only variable.
- **`get_post_field( 'post_name' )` is no longer collected.** The actor scan gathered it as
  `dupes` and never used it; duplicate detection lives in the `dupes` check.

**Three more followed (2026-08-26): on-air, BYQ and duplicates** — chosen because each had
real logic and a history of bugs, rather than for tidiness:

- **`Build\On_Air_Rules`** takes the year as a parameter instead of reading the clock, which
  is the whole reason it can be tested: "is this show on air" is a question about a date, and
  a rule that asks the system what day it is can only be tested on days the answer suits.
  `OnAir::check_if_on_air()` now delegates to it, so the scan and the repair cannot drift
  apart on what "on air" means. It also documented a dead clause: the original tested the
  *computed* verdict for emptiness, and that is always 'yes' or 'no'.
- **`Build\Byq_Rules`** holds the reporting gate from §1.9c. That gate was wrong for as long
  as it was interleaved with the ACF reads, and the fix is only checkable because the rules
  are now separable — `ByqRulesTest` encodes the whole invariant, including that the gate can
  only ever suppress findings and never invent them.
- **`Build\Duplicate_Rules`** holds all three §1.9b bugs' worth of comparisons, each with a
  test: the ACF override that could never be true, two missing IMDb IDs counting as a match,
  and the two-character suffix assumption that mangled `-10` and up. `Dupes` is down to 38
  lines; `compare_duplicates()` and `get_dupes()` stay as thin pass-throughs for callers
  outside the class.

**`watchurls` needs nothing here** — its rules were already extracted into
`CPTs\Shows\Watch_Url_Health::classify()`, which is pure and has its own test file. Worth
knowing before someone goes looking for work that is already done.

**And the last four (2026-08-26), which completes §4.** They were left for last because
their logic is one or two comparisons over already-pure helpers, and that is how they turned
out — with one thing worth doing properly:

- **`Build\Imdb_Rules` serves both IMDb checks**, rather than one class each. They are the
  same three rules — not set, malformed, disagrees with the oracle — differing only in the
  ID prefix, the oracle's name, and (shows only) an exemption for web series and an override
  to waive the comparison. All of that is a config map. Keeping them together is the point:
  these two had already drifted apart once, which is how the actor check ended up reporting
  the Instagram value in a Twitter message (§3). Most of `ImdbRulesTest` asserts on both
  levels in the same test.
- **`Build\Queer_Rules`** is the two-way comparison: a queer actor with no tag is a gap in
  our data, a tag with no queer actor is a claim we cannot support, and they are separate
  issue types.
- **`Build\Actor_Completeness_Rules`** is barely a rule — two booleans in, up to two
  findings out. It exists so `actor_empty` has the same shape as everything else, and so the
  next completeness rule has an obvious home.

Two collectors earned their keep beyond consistency: `Queer_Collector` resolves each actor's
is-queer verdict once per batch rather than once per character-actor pair, and
`Imdb_Collector` resolves the web-series exemption with one term query per batch instead of a
`has_term()` each.

**A new repair came out of the first live run:** an actor whose IMDb field held
`https://www.imdb.com/fr/name/nm10688602/` — the page URL pasted instead of the ID. That is
its own diagnosis rather than a flavour of "invalid", so it got its own issue types
(`show-imdb-url-pasted` / `actor-imdb-url-pasted`) and a repair that writes the ID from
inside the URL.

Unlike the IMDb-in-a-social-field repair, this one is **not** `manual` and is safe in bulk,
and the difference is worth stating: there, the intent was a guess, so the repair deletes
rather than moves. Here the correct value is literally inside the wrong one, in a known
position. `Imdb_Rules::id_from_url()` is strict about both halves of that claim — it must be
an imdb.com URL, and the ID's prefix must match the level, because a title URL in an actor's
field is probably the wrong record entirely and turning it into a valid-looking wrong answer
would be worse than leaving it visible. Values that are merely junk keep reporting as
`*-imdb-invalid` with no repair offered.

**And a bug that repair exposed:** `run_check()` gated the whole `--fix-it` path on the check
declaring a `fixer` of its own, warning "not available for this check" and reporting only.
That was correct when repairs were check-level, and quietly wrong once they became per-issue
— `actor_imdb` has no dispatcher, but its pasted-URL findings each have a repair, so
`--fix-it` refused a check it could have fixed. It now asks `has_repairs()` *after* the
findings are in hand, because whether these findings are repairable is a question about the
findings, not about the check definition. Second time that key has been the wrong
abstraction; it is no longer load-bearing for anything except the fallback path.

### Acknowledgement, arrived at from the other end (2026-08-26)

§5 wanted acknowledgements as debugger-private "ignore" meta. The first one shipped instead
as **real editorial data**: the `lezshows_no_chars` ACF field ("No Known Characters"). A
show carrying it drops out of the Shows report *and* renders a different panel on the front
end, so the acknowledgement is a statement about the show rather than a way of silencing a
report. Where that option exists, it is the better shape — an ignore flag nobody outside
the debugger can see is a worse version of a field that says the same thing.

Three mechanisms came out of it, all reusable:

- **`acknowledged_by` in `Show_Rules::CHECKS`** — names a meta key that, when truthy, means
  an editor has ruled on this check for this post. Distinct from `empty_ok`: the field is
  still empty, but somebody has decided that is the truth here. The collector picks the key
  up automatically via `meta_keys()`.
- **`manual` in `Issue_Registry`** — fixable, but a judgement call. Offered as a per-finding
  button in wp-admin; `Issue_Registry::bulk_fixable_types()` excludes it and `--fix-it`
  skips it. Bulk-flagging every characterless show would have erased the exact distinction
  the check exists to surface. `Findings::describe()` says "fixable in wp-admin" for these,
  so CLI output does not promise something `--fix-it` will not do.
- **`Shows::flag_no_characters()`** writes through `update_field()`, not
  `update_post_meta()`, because ACF true_false fields also carry a `_fieldname` field-key
  row that the editor UI reads.

This is also where §5's acknowledgements *stopped*. Having shipped the good shape once, the
generic fallback turned out not to be needed: see "Acknowledgements: declined on volume" in
§5. The precedent stands for any future case — prefer a real field where the distinction is
meaningful to readers, and only reach for debugger-private ignore meta if the reports ever
accumulate enough false positives to justify it.

---

## 5. Unify with the audit system — the good design is already in the repo

`debugger/class-audit.php` is markedly better engineered than the rest of `debugger/`:

- typed **issue vocabulary** (`ISSUE_TYPES` with `level` + `label`)
- stable **finding keys**
- **baselines** with `new` / `open` / `resolved` diffing
- **acknowledgements** ("ignore") applied as a *display* filter so they never corrupt the
  baseline — the comment on `finalize()` shows someone thought this through
- a documented "WP-CLI-free so a future wp-admin surface can reuse it" contract

The classic debugger had none of that. Its findings were `array( url, id, problem )` where
`problem` was an HTML-joined string blob, so you could not:

- tell a *new* problem from one that had been sitting there for six months
- acknowledge a known-fine false positive (the intersectionality note in
  `class-show-checker.php:76` is literally a human-readable workaround for this)
- count or filter by issue type
- see when something got fixed

**This was the biggest win available**, and it has largely landed — the first three of those
four now work for every check, and the tab badges read "4 new / 41" as hoped. But *not* by routing everything through `Audit::finalize()`; see below for why, and
which half is still outstanding.

### Done differently (2026-08-26): the diff was extracted, `Audit` left alone

Baselines and new/open/resolved now exist for every check, but **not** by routing them
through `Audit::finalize()`. The recommendation above was not taken, and the
reasoning is worth recording because it will come up again:

- `Audit::finding_key()` is `show_id:char_id:issue_type:year`. Making it post-type-agnostic
  changes the string every existing audit baseline was stored under, so every audit scope
  would report everything as new on the first run after deploy. That is a migration, not a
  refactor, and it buys nothing for `cli-audit.php` — still the only consumer.
- `finalize()` bundles ignore-filtering into diffing, and the debugger's ignore story is
  different (per post type, and not built yet).

So the diff itself was extracted as a pure class instead:

- `debugger/build/class-baseline.php` — `snapshot()`, `tag()`, `diff()`, `row_status()`,
  `describe_summary()`. Pure, unit-tested in `tests/unit/Debugger/BaselineTest.php`.
- `debugger/class-baseline-store.php` — one non-autoloaded option per check plus an index,
  the same storage shape as `Audit` but a separate key namespace.

`Audit` can be pointed at the same pure diff later; that is now a small change rather than
the risky first move.

Three decisions inside it:

1. **Identity is `post_id:issue_type`, excluding the message.** A show renamed, or a
   per-post message reworded, must not make a months-old problem look new.
2. **A first run reports everything as `open`, not `new`.** Calling a decade of accumulated
   problems "new" on the day this shipped would be false, and would teach everyone to
   ignore the number permanently. `Baseline_Store::exists()` distinguishes "never run" from
   "ran and found nothing".
3. **A recheck is tagged, not diffed.** The admin "Recheck" button re-scans only the posts
   already flagged. Feeding that to `diff()` would report every finding on every unvisited
   post as resolved *and* store the subset as the whole truth, so the next full scan would
   call everything new. `Baseline_Store::tag_only()` stamps statuses, returns no summary,
   and does not write the baseline. This was the one real bug in wiring it up.

Surfaces: tab badges read "Shows Info (4 new / 41)" when a check has a breakdown and fall
back to a plain count when it does not; the table flags only the new lines, since marking
everything else "open" is noise on a report that is long-standing by nature; the CLI prints
one summary line. `wp lwtv debug <check> --reset-baseline` clears one, for after a
deliberate mass change.

Anything that changes a count *without* scanning — an admin repair, the shadow-sync hook —
records the new count with no summary, so the badge drops to a plain number rather than
showing arithmetic nobody did. Those paths deliberately leave the baseline alone: it records
what the last scan found, so the next scan correctly reports the fixed finding as resolved.

### Acknowledgements: declined on volume (2026-08-27)

The last piece of this section was a general acknowledgement mechanism — one way to say
"reviewed, this is fine" for any issue type. **Not built, deliberately.** The reports do not
carry enough permanent false positives to earn it. A generic store plus registry flag plus
both surfaces is roughly 250 lines and a test file, and paying that to hide a handful of
rows would be the wrong trade.

Two things this pass got wrong, worth correcting since they were the argument *for* building
it:

- **The intersectionality note is not an ignore workaround.** §5 above claimed it was.
  `Show_Rules::intersections()` only fires when a show *is* tagged `disabled` and no
  character carries the flag, so it is a genuine inconsistency, not a permanent row on every
  show. The note is data-entry advice — the fix may be to remove the show's term rather than
  tag a character.
- **The real candidates were elsewhere**, and still too few to matter:
  `watch-url-blocked` (a host that always 403s our user agent is a false positive by
  construction, and is term-level rather than post-level) and `actor_empty` (its own tab copy
  admits it is a completeness report).

So the debugger keeps the narrow mechanism it already has: `acknowledged_by` in
`Show_Rules::CHECKS`, pointing at a named meta key. One check uses it
(`show-no-characters` / `lezshows_no_chars`), and that field earns its keep by also driving
front-end copy. Adding a second is a two-line change to `CHECKS` plus an ACF field.

**If this is ever revisited**, the design was settled even though the code was not:

- **Filter display, never the baseline.** The baseline stores the **raw** finding set for
  exactly this reason. An ignored finding kept out of the baseline comes back as `new` the
  moment it is un-ignored. This is the one non-obvious constraint.
- **Sticky until removed**, not fingerprinted or time-boxed. A stale acknowledgement is a
  human problem; storing who and when, and showing its age, is enough to surface it.
  Fingerprinting "what was acknowledged" has nothing meaningful to hash for most types.
- **Generic per-post meta for the general case, named fields where the flag means something
  beyond silence.** `lezshows_no_chars` stays a named field because the front end reads it.
- **Opt-in per issue type**, via a registry flag. `show-no-score` is a bug, not a judgement
  call, and should not be silenceable.

Two notes on `Audit`'s version, if that is what gets generalised instead:

- `IGNORE_META` is hardcoded to `lezchars_audit_ignore` and `is_ignored()` only applies to
  findings with a `char_id`. It would need to become per-post-type to cover show- and
  actor-level findings.
- Baselines are stored one option per scope (`lwtv_audit_baseline_{scope}`). Full-site
  scopes at current scale will be large options; check the size before scaling this to
  ten checks, and consider a custom table or per-post meta if it gets heavy.

**A correction to a "loose end" this pass first claimed (2026-08-27).** I recorded that
`lezshows_tvmaze_ignore` "silences an IMDb finding" and that its name lies. It does not.
The flag gates exactly one IMDb issue type — `show-imdb-stale` — and staleness is defined as
our ID disagreeing with `lezshows_imdb_canonical`, which is *what TVMaze last told us*. With
no TVMaze entry there is no canonical, so there is nothing to compare and nothing to report.
All three consumers mean the same thing: the TVMaze oracle has no entry for this show.

- `cli-tvmaze.php` — skips it in the unmatched backlog.
- `Imdb_Verify_Task` — gates its `oracle_id => lezshows_tvmaze_id` lookup.
- `Imdb_Rules::stale()` — skips the comparison.

`not_set` and `invalid` never consult it; a legitimately IMDb-less show is handled by
`exempt_tax`/`web-series` instead. **No rename needed** — the field is correctly named, and
renaming it would have been a meta migration paid for a misreading. What was actually wrong
was the comment in `Imdb_Rules`' docblock ("an editor has waived the staleness check", which
frames an absence of oracle data as an editorial opinion about IMDb) and the terse note in
`Imdb_Collector::LEVELS`. Both now say what the flag is for.

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

- ~~**The "full scan or recheck" preamble** is copy-pasted ~7 times, verbatim, across
  `class-shows.php` (×3), `class-actors.php` (×4). ~20 lines each.~~ **DONE (2026-08-27).**
- ~~**The "save transient + update option + return"** epilogue is copy-pasted ~9 times.~~
  **DONE (2026-08-27).**
- ~~**`php/validator/*.php`** is twelve files of 102–107 lines that differ only in the
  transient key, the nonce name, the tab slug, and three strings.~~ **DONE (2026-08-26).**

All three are collapsed. The scanner halves went into `debugger/class-scan.php`:

### The scanner collapse

`Scan` is three static methods, and it is the only place left that touches
`Baseline_Store` outside the store itself:

- **`Scan::post_ids( $items, $post_type )`** — the preamble, for the eight call sites that
  want "the IDs I was handed, or every published post of one type". `Scan::targets( $items,
  $callable )` is the same thing where the full-scan source is not a post-type query: BYQ
  passes a closure around its `lez_cliches`/`dead` taxonomy query.
- **`Scan::finish( $check, $findings, $is_recheck, $to_rows = null )`** — the epilogue.
  `$check` is `array( 'scope', 'transient', 'label' )`, and `scope` keys *both* the baseline
  and the status entry, so the two can no longer drift apart the way §1.2's transient keys
  did.

The `$is_recheck` argument is the reason this was worth centralising rather than tidying.
A recheck visits only what was already flagged, so diffing it against the baseline reports
every post it did not look at as resolved **and** stores the subset as the whole truth,
making the next full scan call everything new. That decision was previously made
independently at eleven call sites, and `class-watch-urls.php` had written its ternary the
other way round (`empty( $items )` rather than `$is_recheck`) — correct, but only by
accident of which branch it put first.

Two checks pass a `$to_rows` callable rather than taking the default `Rows::from_findings()`,
which is why the seam exists at all:

- `class-dupes.php` adds a `name` key per row, because `cli-dupes.php` names it as an output
  column and a post ID alone tells you nothing in that table.
- `class-watch-urls.php` uses `Rows::from_term_findings()` (one row per URL, not grouped per
  post) and then sorts by severity — after tagging, so each row keeps the status it was
  given.

Eleven epilogues and ten preambles became two lines each. `Post_Type` and `Rows` are no
longer imported by five of the scanners, which is the honest signal that the querying and
the rendering really did leave them.

The validator files were collapsed the day before:

### The validator collapse

Ten files, 1,042 lines, replaced by `validator/class-report.php` (202 lines) plus config in
`TOOL_TABS`. `class-watch-providers.php` and `class-watch-term-check.php` stayed — at 416
and 384 lines they have their own renderers, their own admin-post handlers, and forms that
do work rather than re-running a scan.

`TOOL_TABS` now holds everything a report needs: transient, scanner callable, column
heading, clean copy, singular/plural problem copy, and an optional note. The `switch` in
`settings_page()` became a lookup, so a tab cannot exist without a scanner (1.6) and the
transient key is named once (1.2). The two non-report tabs name their own renderer with a
`render` key.

Five things fixed once instead of ten times:

- **The capability check** §3 asked for. `Report::make()` verifies `upload_files` — the cap
  the submenu is registered with — because `make()` is a public static and the menu
  registration guards the route, not the method.
- **The unreachable branch.** Every template had an `elseif ( false === $items )` "Bogus!"
  arm after an `empty()` check that had already caught `false`. Ten copies of dead code.
- **The i18n gap.** `_n()` calls were missing the `'lwtv'` text domain, and their
  `translators:` comments referenced a `%s` that was not in the string.
- **Sentences instead of spliced fragments.** The old copy built `'The following ' . _n(
  'show needs', 'shows need' ) . ' your attention.'` — fine in English, close to
  untranslatable, and the reason the on-air view read "The following miss-matched on-air
  checks been found". Config now holds two complete sentences.
- **Drift the copy-paste had already caused**: the on-air view's table header said
  "Duplicate" and its translator comment said "number of dupes", both inherited from the
  duplicates file. The duplicates tab's nonce was `run_duplicate_clicked` while its slug was
  `dupe_checker`; nonce actions are now derived from the slug, so they cannot disagree.

---

## 8. Splitting detect from repair (decided: `--fix-it` + per-finding fix links)

**Decisions taken:** repairs move behind `--fix-it`; wp-admin gets **per-finding fix
links**; findings **advertise what a fix would do** before you run it.

**Status: done.** Detect/repair split (8.1–8.2), typed findings and the issue registry
(8.3), the stray writes cleaned up (8.4), no transient migration needed (8.5), and both
repair surfaces wired to the same registry. All ten post-based checks are converted; only
`watchurls` was the last one and needed the shape to stretch to terms — see item 14. §5's
baselines landed too, differently: see the note there.

The original reasoning, which held up: per-finding fix links and "fixable" tagging both
require findings to be individually addressable, which `array( url, id, problem )` could not
do — `problem` was an HTML blob of several unrelated issues joined with `</br>`. So the
reshape was a prerequisite rather than a follow-up, and everything else in this section
waited on it.

### 8.1 You already have the target pattern

`class-onair.php` was the model — the only scanner that got this right:

- `find_on_air_problems()` detects and returns findings, touching nothing.
- `fix_on_air_status( $show_id )` is a separate public method that performs one repair.
- `cli-debug.php` already gated it behind `--fix-it` with a progress bar.

**Generalised as of 2026-08-26.** Every scanner is now shaped this way, and OnAir itself was
converted to typed findings like the rest — its two issue types both point at
`fix_on_air_status()`, which also gave it per-finding repair buttons in wp-admin.

### 8.2 Full inventory of writes to relocate

| Location | Current write | Becomes |
|---|---|---|
| ~~`class-shows.php:147`~~ | `lezshows_worthit_rating` → `'TBD'` when thumb empty | **DONE** — `Shows::set_thumb_tbd()` |
| ~~`class-shows.php:153`~~ | set `none` trope (broken, 1.4) | **DONE** — `Shows::add_none_trope()` |
| `class-shows.php:391` | `Ways_To_Watch::migrate_ways_to_watch()` | see 8.4 — not a finding |
| ~~`class-characters.php:171`~~ | set `none` cliché (broken, 1.4) | **DONE** — `Characters::add_none_cliche()` |
| ~~`class-actors.php:156`~~ | delete `lezactors_instagram` when it's an IMDb ID | **DONE** — `Actors::remove_imdb_from_social()` |
| ~~`class-actors.php:165`~~ | delete `lezactors_twitter` when it's an IMDb ID | **DONE** — `Actors::remove_imdb_from_social()` |
| ~~`class-actors.php:176-177`~~ | homepage → wikipedia, clear homepage | **DONE** — `Actors::fix_homepage_wikipedia()` |
| ~~`class-actors.php:180`~~ | delete homepage when it equals wikipedia | **DONE** — `Actors::fix_homepage_wikipedia()` |
| ~~`class-actors.php:398`~~ | delete `debug_check` meta on drafts | **DONE** — deleted, see 8.4 |
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

**Actors is done too (2026-08-26).** `fix_actor_data()` dispatches to
`remove_imdb_from_social()` and `fix_homepage_wikipedia()`; the `actors` check has a
`fixer`. All three scanners are now detect-only, and the vestigial `debug_check` delete
from §8.4 is gone. Decisions taken while moving these, since they are not obvious from the
diff:

- **The IMDb-in-social repair still deletes rather than moves.** Recovering the value into
  `lezactors_imdb` was considered and rejected: it is a guess about intent, and a wrong
  guess writes a wrong IMDb ID onto an actor, which is worse than a missing one because
  everything downstream trusts that field. The finding names the value before it goes.
- **Detection got a local `nm` + 6-or-more-digits guard** (`looks_like_actor_imdb()`).
  `Debug_Tool::validate_imdb()` accepts `nm` plus *any* digits, so `nm2020` — a plausible
  Instagram handle — was matching, and a scan was quietly deleting it. Real IMDb person IDs
  run 7–8 digits. The guard is local on purpose; tightening the shared helper would change
  validation of the genuine IMDb fields on both actors and shows and wants its own pass.
- **The repair re-validates before deleting.** A handle that fails `sanitize_social()` is a
  separate finding with no automated repair, and must not be deleted as collateral when the
  fixer runs over an actor flagged for something else.
- **Two new findings appear in the Actors report** — homepage-is-wikipedia and
  homepage-duplicates-wikipedia. The situation isn't new; those two branches repaired
  themselves mid-scan and recorded nothing, so nobody ever saw them.

Still outstanding: everything in §8.3–§8.5, and the §8.4 wikidata cache writes (still
incidental side effects of comparison, still want an explicit TTL).

### 8.3 Finding shape — DONE for Shows, Characters, Actors (2026-08-26)

Landed as `debugger/build/class-issue-registry.php` (the vocabulary),
`debugger/build/class-findings.php` (pure build + grouping) and
`debugger/format/class-rows.php` (the WordPress seam that adds the permalink).
Both build classes are unit-tested in `tests/unit/Debugger/`.

Four decisions differ from the sketch below, all deliberate:

1. **Rows stay one-per-post; findings are one-per-issue.** `Findings::make()`
   emits a typed finding per problem and `Rows::from_findings()` collapses them,
   so `count( $items )` still means "posts needing attention". That matters more
   than it looks: `Status::record( …, count( $items ) )` feeds the tab badges
   (`class-validation.php:160-162`), the dashboard widget, and the nine "N shows
   need your attention" headers. Flattening to one row per issue silently
   redefines every one of those numbers. The per-issue truth lives on the row as
   `issues` and `fixable`, which is all the fixer and any future per-finding link
   need.
2. **No transient key bump, because the shape is a superset.** `url`, `id` and
   `problem` are still there, derived; `post_type`, `issues` and `fixable` are
   added. Nothing was removed, so a week of cached findings stays readable and
   simply has no `issues` key. `Findings::fixable_issues()` returns an empty list
   for those and the CLI falls back to the check-level fixer — cheaper and less
   destructive than discarding every cached scan, which is what §8.5 would have
   had us do.
3. **`fixable`/`fix_label` are not stored per row as booleans**; they come from
   the registry at build time, so retiring a repair cannot leave a row
   advertising a fix that no longer exists. `fixable_issues()` re-checks the
   registry on read for the same reason.
4. **Checks that return prose keep it.** Airdates, duplicate detection and the
   intersectionality cross-check hand back message strings, so
   `Findings::from_messages()` wraps each into one addressable finding under a
   supplied type (`show-airdate`, `show-duplicate`, `show-intersection`). Giving
   those their own vocabulary entries is a follow-up; being individually
   addressable was the point.

Also folded in while the files were open: the fixability prose moved out of the
messages and into `Issue_Registry`, so `Findings::describe()` composes
"— fixable, adds the "none" trope." from data rather than it being typed into
each message; `Actors::flag_shadow_sync_failure()` builds through the same
pipeline so its hook-appended row carries `issues` too; and the two-argument
social repair got per-field wrappers (`remove_imdb_from_instagram()` /
`remove_imdb_from_twitter()`) so every registry fix takes exactly one post ID.

*(At the time: the other eight checks were still on the legacy shape, reading through the
tolerant path. All of them were converted later the same day — see item 14. The tolerant
reads stay, because week-old cached rows can still predate any of this.)*

The original sketch, for reference:

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

**Both halves are done (2026-08-26).** `WP_CLI_LWTV_Debug::fix_item()` repairs each issue
type the row names, so a run fixes exactly what was found instead of calling one blunt
per-post dispatcher. The check-level `fixer` entries stay as the fallback for pre-reshape
cached rows (and for OnAir, which is genuinely check-level).

The admin half is `debugger/class-repair.php` (`admin_post_lwtv_debug_fix`), rendered by
`Validation::problem_cell()`: typed rows now print one issue per line with its own repair
control, and untyped rows fall back to the `problem` blob, which is also what the
unconverted checks still send. Three decisions in it:

- **It is a form POST, not a link.** "Per-finding fix links" was the plan, but a repair
  writes to the database and should not sit behind a URL a browser or crawler can prefetch.
  `Watch_Providers` had already settled this for the same reason.
- **A repair prunes the cached findings; it does not drop the transient.** Deleting it
  would send the next viewer of that tab into a full rescan of every post in the CPT to
  reflect one fixed field. `Findings::without_issue()` rewrites the row without that issue,
  drops the row when it was the only problem, and the handler re-records the count so the
  tab badge agrees with the table.
- **Rows now also store raw `messages`.** This is what made per-issue admin repairs
  possible at all: with only the joined blob there was no way to rebuild a row minus one
  issue without string surgery. `problem` is composed from `issues` + `messages` on the way
  out, so the fixability prose still reaches the CLI while the admin uses the raw message
  next to a button. Additive again, so cached rows stay readable.
- **Capability is `edit_post` on the post**, not the page's `upload_files`. Reading the
  report and changing a post's data are not the same right (§3).

A repair that declines — because the problem was already fixed by hand or by cron — still
prunes the finding and says so, since the row was stale either way.

Worth knowing about the fix counts: `apply_fixes()` still reports a row as unfixed when
none of its issues had a repair, and that is now *accurate* rather than approximate —
`fix_item()` only claims success when a registered repair actually returned true.

**CLI rendering (2026-08-26).** `problem` is composed for the admin, so a CLI table showed
its `</br>` separators literally, and for the unconverted checks the embedded markup too —
BYQ prints an edit link, and `get_the_title()` had already entity-encoded the show name, so
a Thai title arrived as `Girl Rules &#8230;`. `Findings::plain()` now renders a row for a
terminal: typed rows are rebuilt from their raw messages, untyped ones are de-HTML'd, and
either way it comes out as one semicolon-joined line (newlines fight WP-CLI's box drawing).
Applied in `for_display()` to a copy, for every output format — nothing consuming the JSON
wants `</br>` either — so the transient keeps the markup the admin table needs.

Note this was never a BYQ problem: every check joins with `</br>`, and the converted ones
only looked clean because those rows happened to carry a single finding each. Fixing it in
the renderer covers all ten.

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
- ~~**`delete_post_meta( ..., 'debug_check' )`** on drafts (`class-actors.php:398`) references
  a `debug_check` meta key that **nothing else in the codebase reads or writes**. It's
  vestigial — delete it.~~ **DONE (2026-08-26)** — removed, along with the now-empty `else`
  branch. Confirmed first that the key appears nowhere else in the repo.

### 8.5 Transient shape migration — resolved differently (2026-08-26)

Findings live in week-long transients. Changing the shape means new code will read old-shape
payloads until they expire. Either bump the key names (`lwtv_debug_shows_v2`) or add a
`'version'` marker and treat a mismatch as a cache miss. Cheap to do, annoying to debug if
forgotten. `Actors::flag_shadow_sync_failure()` (`class-actors.php:41`) also appends
directly to the transient and will need updating to the new shape at the same time.

**Neither was needed.** Because the new row is a strict superset of the old one (§8.3), old
payloads are still valid rows — they just lack `issues`, which readers treat as "no
per-issue information" rather than as corruption. Bumping the keys would have thrown away
every cached scan on deploy to gain nothing. `flag_shadow_sync_failure()` was updated as
predicted.

This only holds while changes stay additive. The first change that *removes* or repurposes
a key — most likely generalising `id` into `object_id` + `object_type` so `Watch_URLs`
term-shaped findings become first-class — does need the version marker, because
`Validation::table_content()` would call `get_the_title()` on a term ID.

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

4. ~~**`fields => 'ids'`** at every `Post_Type::make()` debugger call site (2.1).~~ **Done**
   as `Post_Type::get_ids()`, across all nine sites plus `cli-shadow.php`. Two adjacent
   cases remain, listed at the end of §2.1.
5. **Debug log rotation + memoised option reads** (§6). `debug_log()` still reads two ACF
   options per call and the log file still grows without bound.
6. ~~**Delete `find_shows_bad_url()` and link `tab_show_urls`** (1.3, 1.6).~~ **Done.**
   `find_shows_bad_url()`, `Shows::TRANSIENT_URL`, `validator/class-show-urls.php` and the
   unreachable `tab_show_urls` case are gone. Host liveness was *not* skipped after all —
   it moved to `wp lwtv debug watchurls` / the Watch Term Check tab, which probes the few
   hundred **term** URLs instead of the thousands of show URLs that resolve to them. Its
   stale `show_url` status entry is pruned by `wp lwtv migrate acf debugstatus`.
7. ~~**Decide `find_actors_incomplete()`** (1.7): wire it up or delete it.~~ **Wired up** —
   tab, CLI entry, cron slot, typed findings. See §1.7.

**The structural chunk (§8) — mostly done as of 2026-08-26:**

8. ~~**Issue registry + typed findings** (§5, §8.3), including the transient shape migration
   (§8.5).~~ **Done** for every check. No migration was ever needed: the shape is a
   superset, and `watchurls` — the one case that did change a key's meaning — bumped its
   transient key instead, since starting that one over costs nothing. See §8.3.
9. ~~**Route findings through `Audit::finalize()`** (§5)~~ — **done differently.** Baselines
   and new/open/resolved shipped as a pure `Build\Baseline` plus `Baseline_Store`, leaving
   `Audit` alone; see the §5 note for why. **Acknowledgements went as far as they need to.**
   The mechanism exists (`acknowledged_by` on a check, `manual` on a repair) and
   `show-no-characters` uses it, backed by a real editorial field rather than
   debugger-private ignore meta. Generalising it per post type was **declined on volume**
   (2026-08-27) — the reports do not carry enough permanent false positives to earn the
   machinery, and the intersectionality case this list previously cited turned out not to be
   one. Adding a second `acknowledged_by` is two lines plus an ACF field if that changes.
10. ~~**Extract pure rule evaluation into `debugger/build/`** with tests (§4).~~ **Done, for
    every check.** Eleven rules classes in `build/`, nine collectors in `collect/`, and every
    scanner reduced to orchestration. `watchurls` needed nothing — its rules were already
    pure in `Watch_Url_Health::classify()`.
11. ~~**Move the writes to a repair layer** (§8.2) behind `--fix-it`, plus per-finding admin
    fix links (§8.1).~~ **Done**, both surfaces. Every write that a scan used to perform is
    now a registered repair, and `on_air` picked up admin buttons when it was converted.
12. ~~**Collapse the duplication** (§7).~~ **Done, all three items.** Ten validator files
    collapsed into `Validator\Report` plus `TOOL_TABS` config (2026-08-26); the two watch
    tabs kept their own classes. Took §3's capability check with it. Then the scanner
    preamble and epilogue went into `Debugger\Scan` (2026-08-27), which is now the only
    caller of `Baseline_Store` — so the "a recheck must not be diffed" rule is stated once
    instead of eleven times.
13. Add the missing checks to the cron rotation — including `waystowatch enrich`, which is
    safe to run weekly since it skips anything already asked.
14. ~~**Convert the remaining checks to typed findings**~~ — **done, all eleven
    (2026-08-26)**: `byq`, `queers`, `dupes`, `on_air`, both IMDb checks, `actor_empty`, and
    finally `watchurls`. Every check now emits typed findings and diffs against a baseline.

    Worth knowing about that pass:

    - **`on_air` gained admin repair buttons for free.** Its two new issue types both point
      at `OnAir::fix_on_air_status()`, the same method that has always backed `--fix-it`,
      so registering them also made it available per finding in wp-admin.
    - **`dupes` is typed per post type** (`show-is-duplicate` / `actor-is-duplicate`),
      because it is the only check spanning two, and a finding's level is what decides which
      cache an admin repair prunes and which tab it returns to. Its `name` key survives —
      `cli-dupes.php` names it as an output column.
    - **Two messages still carry markup deliberately.** BYQ embeds an edit link and `dupes`
      links to the original; both have always rendered in the admin table, and
      `Findings::plain()` strips them for the CLI. The relevant IDs are in `context` so a
      renderer can build the links properly later.
    - **A latent bug in the BYQ reporting gate was found and then fixed** once the intent
      was confirmed — the typed findings are what made it fixable, since the gate can now
      count only the findings it is actually about. See §1.9c.

    And the `watchurls` pass, which needed the shape to stretch:

    - **Findings can now be about a term.** `Findings::make_for_term()` sets
      `object_kind => 'term'`, and `Findings::is_post()` is the test to run before
      dereferencing `id`. `Validation::table_content()` and the CLI fixer both call it and
      skip what is not a post — a term row reaching `get_the_title()` would have rendered a
      plausible empty row with working links to nothing, which is the worst way to be wrong.
    - **`id` was *not* renamed to `object_id`.** The plan called for that; it would have
      meant migrating every stored row and every reader of `id` — `table_content()`, the CLI
      columns, `apply_fixes()`, `Repair::prune()`, the recheck path in ten scanners — to fix
      a name that is correct for the ten post-based checks. `object_kind` buys the actual
      safety property without the churn.
    - **Baseline keys gained an optional third part.** A provider term can have several
      broken URLs, and object + issue type is not unique across them; without the URL in the
      key, fixing one of three would read as resolving all three.
    - **These rows are one per URL, not grouped per term.** The post checks collapse to one
      row per post because the post is both what you triage and what you edit. Here the term
      is what you edit but the URL is what you triage, and the report has a URL column per
      row — so `Rows::from_term_findings()` does not group.
    - **`status` became `health`.** The health verdict (broken / needs review / blocked) and
      the baseline's new/open/resolved both wanted the key. Health moved, since
      `Watch_Url_Health::STATUS_*` is what it holds and `health` reads better in the
      renderer.
    - **No transient migration.** The key is bumped to `lwtv_debug_watch_urls_v2` and old
      rows are simply never read: the tab says the scan has not run until the next sweep,
      which is honest, and Sunday's cron refills it. §8.5's version marker is still unused,
      and now has no pending caller.
    - **One bug caught in the conversion.** The budget path stashed the previous *row* under
      `carry` and pushed it back into what is now a *findings* array. `carried()` rebuilds a
      finding from that row instead, preserving the original issue type and message — so a
      URL that was broken an hour ago is still reported as broken rather than downgraded to
      "not checked", which was the point of `carry` in the first place.
    - **One bug the first live run exposed:** term names arrived entity-encoded, so
      `U&Alibi` printed as `U&amp;Alibi` in the CLI — and the admin was worse, running
      `esc_html()` over an already-encoded name. Decoded at the source now, where the name
      enters the finding, which fixes both surfaces. It also slightly improves
      `Watch_Url_Health::classify()`, which compares the term name against the name the site
      gives itself: it was comparing the encoded form.
15. **Give the wikidata cache writes an explicit TTL** (§8.4). Still incidental side effects
    of a comparison.

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

- **Does anything want per-issue rows in the admin, rather than per-post rows with per-issue
  lines?** The current grouping keeps `count()` meaning "posts needing attention", which is
  what every badge and header string reads. Flattening would give real by-issue filtering
  and sorting, at the cost of redefining every one of those numbers in one go. Nobody has
  asked for it yet, and the typed findings mean it stays possible.
- **Does the ACF repeater shape written by `create_term()` match what ACF expects?** The
  one thing in this pass with a plausible silent-failure mode.
- ~~**What's the real TMDB hit rate?** `--order=random` hasn't been run.~~ The backfill has
  been run and works. The measured hit rate is still worth recording here if you have it.
- **What threshold makes a host "worth registering"?** The tab is unfiltered right now.
  `--min-shows` exists on the CLI; the distribution suggests somewhere around 3–5.
- **Should `enrich` go on the weekly cron?** It's designed for it — skips already-asked
  hosts, so repeat runs are nearly free — but it isn't wired in yet.
- **Is `amazon.com` really Prime Video?** 174 shows, and the term already exists. Still
  worth eyeballing a few before merging them.
