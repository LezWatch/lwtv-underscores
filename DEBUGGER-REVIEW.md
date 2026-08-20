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

## 1. Actual bugs (fix these first)

### 1.1 The Shows airdate check is reading a legacy meta key — false positives everywhere

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

### 1.2 Two transient key mismatches — CLI never hits cache, always full-scans

| Writer | Admin reader | CLI reader |
|---|---|---|
| `lwtv_debug_show_url` (`class-shows.php:452`) | `lwtv_debug_show_url` ✓ | `lwtv_debug_show_urls` ✗ (`cli-debug.php:333`) |
| `lwtv_debug_on_air_problems` (`class-onair.php:83`) | `lwtv_debug_on_air_problems` ✓ | `lwtv_debug_on_air` ✗ (`cli-debug.php:353`) |

`wp lwtv debug show_urls` therefore *always* falls through to `find_shows_bad_url()`, which
is the most expensive check on the site (see 1.3). Same for `on_air`.

Fix: centralise the keys as constants on the scanner classes and reference them from both
readers. There is no reason for three files to spell the same string independently.

### 1.3 `find_shows_bad_url()` will hang or time out at current scale

`class-shows.php:386-449` loops every published show and does a synchronous
`wp_remote_get( $url, array( 'timeout' => 10 ) )` for **every** Ways-to-Watch URL, inline,
in whatever request triggered it.

At a few thousand shows with multiple URLs each, worst case is measured in hours. It also:

- runs on a **plain page load** if the transient is cold (`Show_URLs::make()` does
  `false === $items → full scan`), so simply visiting the tab kicks it off;
- has **no per-URL result cache** — the same Netflix/Hulu URL is fetched once per show;
- has **no dedupe**, no concurrency, no batching, no `set_time_limit` handling;
- treats `301`/`308` as a problem, which for streaming services is normal and generates
  permanent noise.

This is also the check that is **not in the cron rotation at all** (`cli-generate.php:227-277`
covers mon–sun; `find_shows_bad_url` and `find_actors_incomplete` appear nowhere), so it's
never warm.

Fix: move it to the existing scheduler pattern (`php/schedulers/`, alongside
`class-tmdb-batch-task.php`) as a batched background job, cache per-URL results keyed by
URL hash for ~30 days, dedupe the URL set across shows before fetching, and drop
301/308 from the problem list (or demote to "info").

### 1.4 The "add NONE term" auto-fixes silently do nothing, and can fatal — in TWO places

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

### 1.5 `$option['x'] = ...` on a `false` option — deprecated on PHP 8.1+

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

### 1.6 Two admin tabs are unreachable

`Validation::TOOL_TABS` has 9 entries; the `switch` in `settings_page()` handles 11 cases.
`tab_show_urls` and `tab_actor_wiki` render fine but **have no nav tab** — you can only get
there by hand-typing the query string. Given 1.3, the Show URLs one being hidden is
arguably load-bearing right now, but it should be a deliberate decision, not an oversight.

### 1.7 `lwtv_debug_actor_empty` is a dead end

`find_actors_incomplete()` (`class-actors.php:219`) writes `lwtv_debug_actor_empty` and an
`actor_empty` status entry. Nothing reads either — no validator view, no CLI type, no cron
day. It shows up in `current_status()` counts and nowhere else. Either wire it up or delete it.

### 1.8 CLI exits 0 on failure

`cli-debug.php:104`: `\WP_CLI::error( $exception->getMessage(), false )` — the `false`
suppresses the exit. Cron (`cron/debug.sh`) can't distinguish a crashed check from a clean
one. Drop the `false`, or `WP_CLI::halt( 1 )`.

### 1.9a `Validation::admin_notices()` has never worked (found while fixing 1.5)

Two separate breakages in `admin-menu/class-validation.php`:

- `add_action( 'load-$page_id', ... )` — single-quoted, so the hook name is the
  literal string `load-$page_id`. It matches nothing.
- `add_action( 'admin_notices', $message )` passes an **HTML string** where a callable
  is expected. Even if the hook fired, this would be a `TypeError`, not a notice.

So the `?message=success|warning|error|rerun` notices are dead code. Left alone in this
pass because fixing it properly means deciding where those notices should come from now —
it's not a one-line change, and nothing currently depends on it.

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

### 1.9 No way to force a fresh CLI run

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

See §8 — this is now a planned piece of work rather than just an observation.

---

## 3. Security & correctness hygiene

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
acknowledgements across all eleven checks for roughly the cost of reshaping the finding
arrays. The tab badges become "3 new / 41 open" instead of a raw count that nobody can act on.

Two things to sort out when generalising `Audit`:

- `IGNORE_META` is hardcoded to `lezchars_audit_ignore` and `is_ignored()` only applies to
  findings with a `char_id`. Needs to become per-post-type (`lezshows_`/`lezactor_`) to
  cover show- and actor-level findings.
- Baselines are stored one option per scope (`lwtv_audit_baseline_{scope}`). Full-site
  scopes at current scale will be large options; check the size before scaling this to
  eleven checks, and consider a custom table or per-post meta if it gets heavy.

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
- **`php/validator/*.php`** is eleven files of 102–107 lines that differ only in the
  transient key, the nonce name, the tab slug, and three strings. `class-actor-wiki.php`
  (62 lines) is the odd one out.

All three collapse into a config array + one runner + one renderer. The `TOOL_TABS`
constant in `Validation` is already 80% of the config you'd need — extend it with the
transient key, the scanner callable, and the empty/error copy, then delete the eleven files.
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
| `class-shows.php:147` | `lezshows_worthit_rating` → `'TBD'` when thumb empty | repair `show-missing-thumb` |
| `class-shows.php:153` | set `none` trope (broken, 1.4) | repair `show-missing-trope` |
| `class-shows.php:391` | `Ways_To_Watch::migrate_ways_to_watch()` | see 8.4 — not a finding |
| `class-characters.php:171` | set `none` cliché (broken, 1.4) | repair `char-missing-cliche` |
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

## Suggested order of work

**Now — correctness, small diffs:**

1. Airdates key fix (1.1) — actively producing wrong output.
2. `$term->ID` fatal + no-op (1.4).
3. Transient key mismatches (1.2).
4. `false`-to-array deprecations + `last_run()` guard + `! array()` typo (1.5).
5. Twitter/Instagram message mixup, `isset( $pos_imdb )` dupe check (§3).
6. CLI exit code (1.8), `--force` flag (1.9).
7. "Debuging" typo (§6).

**Next — stop the bleeding at scale:**

8. `fields => 'ids'` at every `Post_Type::make()` debugger call site (2.1).
9. Move `find_shows_bad_url()` to a batched scheduler task with per-URL caching (1.3);
   demote 301/308.
10. Log file rotation + memoised option reads (§6).

**Then — the structural work. Order matters here, because the fix-it decisions depend on it:**

11. **Issue registry + typed findings** (§5, §8.3). One `issue_type` per finding, one row per
    issue, `fixable`/`fix_label` from the registry. Includes the transient shape migration
    (§8.5). Everything below depends on this.
12. **Route findings through `Audit::finalize()`** (§5) — baselines, new/open/resolved,
    acknowledgements. Needs `IGNORE_META` generalised per post type first.
13. **Extract pure rule evaluation into `debugger/build/`** with unit tests (§4). Start with
    the show rules so 1.1 gets a regression test that would have caught it.
14. **Move the writes to a repair layer** (§8.2) behind `--fix-it`, plus the per-finding
    admin fix links (§8.1). Relocate the four non-fix writes per §8.4.
15. **Collapse the eleven validator files** into a config-driven runner (§7) — much easier
    once findings are typed and the renderer is uniform.
16. Add the two missing checks to the cron rotation; decide the fate of
    `find_actors_incomplete()` (1.7), `tab_show_urls`, and `tab_actor_wiki` (1.6).

Steps 11–14 are one coherent chunk — worth doing together rather than shipping half a
finding shape. Steps 1–10 are all independent and can land in any order.
