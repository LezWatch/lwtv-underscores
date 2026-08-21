# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [7.1.13] - 2026-08-19

### Added

- The airdate calendar gains a sticky header carrying the timezone, a jump-to-today control, and a strip of the seven weekdays that links straight to any day with episodes.
- Each episode's dot now greys out once that episode has actually aired, so an episode earlier in today's schedule reads as past while the rest of the day still reads as upcoming.
- The post link under a single post can now be copied to the clipboard in place, announcing the result to screen readers, and falls back to an ordinary link when the clipboard is unavailable.
- Statistics phrasing gains "Fewer than an eighth", "Fewer than a fifth", and "Nearly all" so the shortfall wording tracks the real figure more closely.

### Changed

- The airdate calendar is now one day-grouped agenda instead of three competing views, with the Last and Next Week pagination and the "Week of…" heading retained.
- Days with no episodes are left out of the calendar rather than listed as empty rows, since the weekday strip already shows which days are quiet.
- The calendar now loads only the week it displays instead of fetching three weeks and rendering one, which removes two schedule parses from every page load.
- Categories and tags under a single post now sit together in a bordered card, categories as accent text and tags as outline pills, with the post link demoted below it.
- Style and script linting now covers every stylesheet and script in the theme and plugin; previously it reached only a handful of files, and the style autofixer could rewrite bundled Bootstrap sources.

### Fixed

- The calendar widget in the sidebar built its show links from an internal ID, which produced links that went nowhere.
- The current day's row in the calendar never picked up its highlight, so "Today" was badged but never tinted.
- A day with more than one episode emitted broken table markup, which was the likely source of odd row spacing.
- Anchor links to individual days used two different naming schemes depending on whether the day had episodes.
- The "Today" marker was a link nested inside a disabled button, so it could not be focused.
- The native-timezone display on the calendar had never rendered at all.
- Menu keyboard navigation leaked two variables into the global scope.
- The shortfall phrase ladder had rungs whose thresholds overstated the fraction they named, which could make the phrasing false.
- Several small style bugs surfaced by the wider lint sweep: a cancelled margin in the search form, a redundant duplicate rule in the statistics styles, and inconsistent spacing on the social share and search scope controls.
- Fixed dangling ACF issue where the custom TVMaze name wasn't linking up to the show.
- Fixed esc_html calls that should have been wp_kses_post.

### Removed

- The calendar's Grid and Calendar views, along with the tab strip and the `tvview` URL parameter that switched between them. Old tab links still land on the agenda.
- The calendar's tab script, and with it a jQuery dependency on that page.
- Dead calendar preloading code that had never run, including the thumbnail machinery only the removed Grid view used.

## [7.1.12] - 2026-08-13

### Added

- A single nation or station page gains a pullstats banner on its Sexuality, Gender, Formats, and Tropes views, counting the distinct terms tracked and the leading term's share.
- Those same four views each gain a leaders section suited to what their data supports: the show with the most characters of a given identity, the highest-scored show of each format, and the single most trope-heavy show.
- The Death → Characters view gains a Standout Numbers row: how many characters have died and come back more than once, which one has done it most often, and the deadliest single day on record.
- The Death → Characters view gains a Deaths by Decade row anchored to when each death was recorded, so a character who died in more than one decade appears in each of them.
- The Death → Shows view gains a Standout Numbers row covering the share of shows that have killed at least one queer character, the most lethal show by body count, and the highest death rate among shows with a real cast.
- The Death → Nations and → Stations views gain a Standout Numbers row with the total deaths on record, the share of countries or networks with any recorded death, and the deadliest of them by rate rather than by raw show count.

### Changed

- The Tropes view on a single nation or station page moves from ranked bars to the same donut its sibling views use, and now states that its shares are of total tags rather than of the shows themselves.
- Show death highlights read each show's own character and death counts instead of relying on the manually applied "dead queers" tag, so a show with recorded deaths that was never tagged is no longer missed.
- Death rate figures only count shows, countries, and networks with at least five tracked characters, so a lone small show can no longer read as totally lethal.
- The decade rows across the statistics section now sit inside a single bordered card rather than a loose grid.

### Fixed

- Tiles on the "by decade" rows drew a second nested box inside their own card.

## [7.1.11] - 2026-08-13

### Added

- The Actors section gains a Roles view, breaking every tagged show appearance down as Regular, Recurring, or Guest, with a most-prolific-actor card for each type.
- The Actors section gains an Unknown Actor view, which turns characters with no confirmed performer into a tracked figure instead of a silent gap: how many characters and shows are affected, their gender, sexuality, and role mix, the oldest and newest still-uncredited character, the shows carrying the most of them, and a dead-or-alive count.
- The Actors → Sexuality and → Gender views each gain a pullstats banner, an "Overlap" callout for actors whose orientation or gender tag alone understates their queerness, and a most-prolific-actor card per tracked term.
- The Actors overview gains a headline index of its own, with the count of actors who have a character on the air this year promoted to the lead spot since it has no page of its own.
- The Characters → Queer IRL view gains a Firsts row naming the oldest and newest character played by a queer actor and the oldest played by a trans actor.

### Changed

- The Characters → Queer IRL view replaces its donut and two progress bars, which showed the same split three times over, with a single waffle chart and legend.
- The Firsts lists on the Characters → Gender, → Sexuality, and → Queer IRL views now render as stat cards with the character named inline beside the year, matching the new prolific-actor cards.
- The "Who Plays the Roles" cards on the Actors overview now show proportional charts of each figure's share of all actors.
- The Actors → Sexuality and → Gender donuts move from pink to amber so the whole Actors section reads as one color family.
- The Actors → Gender donut is now a share of every actor rather than only cisgender ones, and shows Unknown as its own slice.

### Fixed

- The longest-running character record counted the calendar span between a character's first and last credited year, so a character written out mid-run was credited for years they were never on screen. It now counts only the years actually credited, which lowers the count for characters with gaps.
- The placeholder "Unknown" actor could win most-prolific and firsts leaderboards; it is now excluded from all of them.
- Stat cards in the characters color family fell back to amber in dark mode instead of their own green.

## [7.1.10] - 2026-08-11

### Added

- The Shows overview gains a headline index: one figure per statistics subpage, ordered to match the subnav and linking through to the full view, so the page doubles as a visual table of contents.
- The Shows overview gains a library band showing total seasons and episodes, displayed only when episode coverage is high enough to be meaningful.
- The Shows → Genres view gains a working Genre by Decade section, with one tile per decade showing that decade's top three genres.
- The Shows → Genres view gains a Genre Load breakdown of how many genres a show carries, a Common Genre Pairings panel, and a "Still Largely Uncharted" section reframing the long tail as genres queer TV has barely reached.
- The Shows → Tropes view gains a Trope Alignment row counting shows that carry at least one good, maybe, bad, or ploy trope, a Trope Load breakdown with a spotlight on the most trope-loaded show, a Mixed Alignment donut, and a Common Pairings list.
- The Shows → Intersectionality view gains an Intersection Load breakdown, a Single vs Multiple donut, and an Intersections by Decade grid.
- The Shows → Formats view gains a Format Mix by Decade row of compact donut tiles and a long tail callout naming the two smallest formats and their combined share.
- The Characters → Gender and → Sexuality views each gain a decade-by-decade identity mix and a "Firsts" list naming the earliest recorded character for every tracked identity.
- The Characters → Clichés view gains a Cliché Load breakdown with a spotlight on the most-clichéd character, and a Common Pairings panel for clichés that appear together on the same character.

### Changed

- The Characters → Most Clichés view becomes "Most" and expands from one leaderboard into five character records: most clichés, most shows, most actors, most resurrected, and longest-running. Each shows its record holder plus a top-five table, and categories with too little data leave ranks blank rather than padding them.
- The Characters overview is rebuilt with a headline index of its own; "Stories We Keep Telling" is replaced by "The Cliché Gap," which pairs the Dead cliché against characters written with no cliché at all, and "Played by Queer Actors" grows into a Casting Gap section stating how many times more often straight or cisgender actors are cast in queer roles.
- The Trope Gap cards on the Shows overview now show proportional charts and a computed callout of how much more likely a queer character is to be killed off than given a happy ending.
- Common Pairings on the Shows → Intersectionality view now link straight to the matching filtered shows archive.
- The Shows → Stars view now reads its gold, silver, bronze, and anti tier descriptions from the star terms themselves instead of hardcoded copy.
- Several headline and description sentences across the statistics views were reworded to read as complete sentences on their own.

### Fixed

- The "subtext" trope was counted in two scoring groups at once, where it canceled itself out in the show score while still counting against the total. It now counts only as a maybe trope, which changes the trope score for shows carrying it.
- Character death and longest-running records no longer double-count characters whose entries have been edited.
- Genre Load's chart legend rendered pink dots instead of matching its own bucket colors.
- Decade grouping miscounted which decade some shows and characters belonged to.
- The donut chart icon and the Trope Gap description text were too low-contrast in dark mode.

### Removed

- The Top Tropes and Top Genres panels and the decorative sparkline are gone from the Shows overview, since the headline index now carries that information.

## [7.1.9] - 2026-08-06

### Added

- Shows statistics gains a Scores tab with a score distribution histogram and median/90+/failing pull-stats, plus a Score Trend band on the main statistics page showing the average score of on-air shows per year.
- The statistics page gains a "Common Pairings" section showing which intersections most often co-occur on the same show.
- The Shows We Love It view now compares loved shows against the rest of the archive on average cast size, happy-ending rate, and death rate.

### Changed

- The Shows → Stars view replaces its donut chart with a medal podium and a callout rail of star totals, leading-tier share, and star rate.
- The Shows → Triggers view replaces its donut chart with a callout rail and true-scale bars showing the flagged-show rate and severity mix.
- The Shows → Worth It view is rebuilt as a hundred-square grid with score bars.
- Intersectionality is visible on show pages again: the single-show sidebar card and excerpt intersection icons now render correctly.
- Nations and stations with only a show or two now show an adaptive narrative and per-show catalog card instead of a near-empty chart.

### Fixed

- A missing or unregistered intersections taxonomy no longer causes a fatal error on the show intersectionality view.

## [7.1.8] - 2026-07-30

### Changed

- Statistics cache warming now collapses a burst of edits into a single, debounced comprehensive warm that trails the last edit, replacing the previous broken and death-only warming; a daily run keeps stats fresh even with no edits.
- The cliche leaderboard is now included in cache warming.
- Statistics and This Year pages load faster thanks to bulk slug resolution and cache priming that eliminate repeated per-row database queries.

### Fixed

- A failed statistics database query now degrades to empty output instead of throwing a fatal error across the nations, stations, on-air, this-year, and shared-format views.
- Canceled shows, new shows, and dead characters no longer silently drop from the current-year views when a year value's type differs.

### Removed

- Hid the not-yet-ready responsive settings from the UI.

## [7.1.7] - 2026-07-30

### Changed

- The Deaths by Month view now summarizes empty months as a count ("X months had no deaths") once more than three months recorded none, instead of listing every month name.

### Fixed

- The Deaths by Month graph and timeline no longer show future months of the current year as having recorded no deaths; past years still show the full calendar.
- CSS display for Faceted Search and CloudFlare Turnstile no longer overflows.

## [7.1.6] - 2026-07-29

### Added

- This Year Characters On Air view: an interactive A–Z bar graph that jumps to an alphabetized character directory, plus a By Show tab listing each show's cast.
- This Year Dead Characters (By Date) view: a deaths-by-month graph feeding an ordered death timeline.
- Single Nation and Station Overview fact-sheets, with a narrative summary, ratio and death-rate facts, a best-year callout, composition bars, and a top-show link.
- Google Analytics tracking for statistics CSV downloads, including the nation or station slug so single-term exports stay distinct.

### Changed

- Rebuilt the This Year Overview with cached statistics for faster page loads.
- Reworked the On Air / New / Canceled shows lists into a sticky A–Z jump bar over a two-column list, filing titles under their first significant word.
- "At a glance" metric rows now collapse to a 2×2 grid on tablet widths and a single column on phones.
- Font sizes across the statistics pages scale with the reader's root font size, and dark-mode colors were refreshed.

### Fixed

- Non-Latin character names are now counted in the Characters On Air graph instead of being silently dropped.
- Character listings no longer leak phantom, nameless cast members from draft revisions.

## [7.1.5] - 2026-07-24

### Added

- Improved the death callouts for the list, now including dates.

### Fixed

- Corrected erroneous death count, caused by counting drafts.
- Made indication of multiple deaths on a date more clear.

## [7.1.4] - 2026-07-24

### Added

- Run-to-run tracking for `wp lwtv audit`: each finding is tagged new, open, or resolved against the previous run.
- Acknowledge findings with `wp lwtv audit ignore` so known-acceptable flags stop recurring, and list them with `wp lwtv audit ignores`.
- Triage summary at the end of each audit run, broken down by issue type and status.
- `wp lwtv audit reset` to clear the stored audit baseline.

### Changed

- The audit run summary now prints to STDERR so `--format=csv`/`json` output can be redirected cleanly.

## [7.1.3] - 2026-07-24

- Add "last calculated" stats note with Redis-aware cache eviction
- New wp lwtv cache check and wp lwtv cache verify commands

## [7.1.2] - 2026-07-23

### Security

- Unpublished and privacy-hidden actor, show, and character records no longer leak through the public REST, stats, export, wikidata, or actor birthday endpoints.
- The public wikidata endpoint is now read-only for anonymous callers; live WikiData refreshes and metadata writes require an editor, closing an unauthenticated write and request-amplification path.
- A single blank-slug request can no longer trigger a full-table wikidata scan.
- Untrusted values are escaped across the calendar, author box, author social links, and related-posts archive to prevent stored cross-site scripting.
- Admin-only profile and ACF fields are enforced when saved, not just hidden in the interface.
- Outbound TVMaze and TMDB requests use HTTPS and validate response links against the expected host to prevent server-side request forgery, and stored TMDB ids are restricted to digits.

### Changed

- The anonymous wikidata endpoint response is now keyed by actor ID, matching the authenticated response shape.
- Updated the admin monitoring dashboard link.

## [7.1.1] - 2026-07-22

### Added

- Year-bar charts now reveal each year's value in a corner readout on hover.
- Coverage, average, and median per-show callouts on the intersectionality, stars, and triggers views.

### Changed

- On-air views now highlight the biggest year-over-year drop instead of a meaningless low-count year, and exclude the in-progress current year.
- The "Where queer TV lives" panel is now a proportional stacked-share bar with a legend naming the top networks and aggregating the rest to 100%.
- Year-bar charts label only the peak year on its bar; every other year's count appears in the hover readout.
- Shows with a known future end date are no longer counted as on air in years that haven't happened yet.
- Trope overview card links are restyled as buttons.
- Color tokens are normalized onto one consistent naming scheme, which slightly shifts some greys and reds.

## [7.1.0] - 2026-07-22

### Added

- Complete redesign of the statistics section, covering the Overview, Shows, Characters, Actors, Nations, Stations, Death, and This Year views.
- Most-Clichéd Characters leaderboard view.
- Average and median per-item callouts on the clichés, genres, and tropes views.
- CSV download for supported statistics views.
- Single-nation and single-station profiles alongside all-nations and all-stations counters and leaderboards.
- Light/Dark/Auto dark-mode switcher.

### Changed

- Statistics now render server-side with count-up and bar-grow animations instead of relying on JavaScript charts.
- Donut chart headlines and descriptions are now written from the real underlying data.
- Dark Mode toggle is now a true navbar toggle.

### Fixed

- Nation and station character and death totals are no longer understated when shows share the same counts.
- Statistics counts, cache invalidation, and query-variable escaping are corrected, and the builders now hold up when a query returns nothing.

### Removed

- Chart.js and its trendline and palette assets (replaced with inline SVGs).

### Updated

- NPM and Composer Packages

## [7.0.13] - 2026-07-14

### Added

- New Shows, Characters, and Actors no longer reuse a slug that an active AIOSEO redirect still points away from.

### Changed

- "Of the Day" content is now safe when the same cron runs on more than one server at once, so duplicate entries for the same day are no longer created.

### Fixed

- The actor WikiData debugger now matches birth and death dates regardless of storage format, and copied URLs keep their `https://` scheme so they can be pasted in directly.

### Updated

- @wordpress/scripts: ^32.6.0 -> ^33.0.0
- Bumped terser from 5.48.0 to 5.49.0.

## [7.0.12] - 2026-07-03

### Changed

- `lezactors_saved_wikidata` is no longer exposed via the REST API.

### Fixed

- Legacy "Ways to Watch" URLs now migrate correctly into the ACF repeater format instead of being dumped into the repeater's raw count field.
- Actor birth/death dates in invalid or unparsable formats no longer break the actor profile page.
- Death-date lookups (death count shortcode, "What Happened Today", Born/Died on This Day, and dead-character-by-year statistics) now match dates regardless of legacy or current storage format.
- Show alternate names now read correctly from ACF.

### Updated

- @wordpress/api-fetch: ^7.49.0 -> ^7.50.0
- @wordpress/babel-plugin-import-jsx-pragma: ^5.49.0 -> ^5.50.0
- @wordpress/block-editor: ^15.22.1 -> ^15.23.0
- @wordpress/blocks: ^15.22.0 -> ^15.23.0
- @wordpress/components: ^36.0.1 -> ^36.1.0
- @wordpress/data: ^10.49.0 -> ^10.50.0
- @wordpress/editor: ^14.49.1 -> ^14.50.0
- @wordpress/element: ^8.1.0 -> ^8.2.0
- @wordpress/i18n: ^6.22.0 -> ^6.23.0
- @wordpress/icons: ^15.0.0 -> ^15.1.0
- @wordpress/plugins: ^7.49.1 -> ^7.50.0
- @wordpress/scripts: ^32.5.1 -> ^32.6.0
- @wordpress/server-side-render: ^6.25.1 -> ^6.26.0

## [7.0.11] - 2026-06-30

### Changed

- Server side blocks (`author-box`, `glossary`, `tvshow-calendar`, `private-note`) now correctly declare `api_version` at the block-registration level rather than as a block attribute.

### Fixed

- Character/Show of the Day no longer runs twice during the daily cron.
- Saving only the "None!" trope no longer silently clears trope data; mixed selections correctly drop "None!" and keep the real tropes.
- Glossary block no longer throws a JavaScript error due to incorrect `this.props` usage.

### Removed

- Stale Gutenberg meta-box CSS workarounds that are no longer needed.

### Updated

- @wordpress/block-editor: ^15.22.0 -> ^15.22.1
- @wordpress/components: ^36.0.0 -> ^36.0.1
- @wordpress/editor: ^14.49.0 -> ^14.49.1
- @wordpress/plugins: ^7.49.0 -> ^7.49.1
- @wordpress/scripts: ^32.5.0 -> ^32.5.1
- @wordpress/server-side-render: ^6.25.0 -> ^6.25.1

## [7.0.10] - 2026-06-26

### Fixed

- Dead character role-type counts (regular/recurring/guest) now return correct results after ACF migrated repeater data from a single serialized meta value to individual sub-field meta rows.
- Taxonomy statistics pages now correctly count characters and deaths per show.
- "This Year" character lists now populate for shows airing in the current year.
- Actor excerpt cards on taxonomy archive pages now render with the correct post context.

## [7.0.9] - 2026-06-25

### Changed

- WP-CLI weekly debug cron consolidates actor checks (problems + iMDB) on Thursdays and shows checks (problems + iMDB) on Saturdays; Sundays now trigger a FacetWP force re-index instead.

### Fixed

- "Shows We Love" FacetWP facet now correctly indexes shows flagged as loved; ACF stores the `true_false` field as `1` rather than the legacy `on` value, which had caused all loved shows to be indexed as `no`.

## [7.0.8] - 2026-06-19

### Fixed

- Restored Summary
- Administrators can now actually enable ACF field group
- ACF Number Slider
- Accessibility colors
- Prevent two characters of the day.

### Removed

- Unused references to Postiz Plugin
- Unused integration with WP Rocket

## [7.0.7] - 2026-06-18

### Fixed

- Updated character details ACF group and improve show linking logic
- Improve Accessibility colors

## [7.0.6] - 2026-06-18

### Added

- Administrators can now enable ACF field group editing on production via a new "Enable ACF UX" toggle in Debugging Tools

### Changed

- Debugging Tools options page consolidates debug mode, log topics, and the new ACF UX toggle into a single ACF field group

### Removed

- Separate character queerness ACF field group removed; fields now live within the consolidated Characters field group

## [7.0.5] - 2026-06-17

### Added

- ACF field groups for Actors, Characters, and Shows consolidated into unified tabbed layouts, replacing multiple separate metabox panels
- Improved show search in the Character Appearances field: relevance ordering on search, exact-title matching for short terms

### Changed

- Post editor meta boxes restored to visible and scrollable layout under WordPress 7.0's revised block editor

### Removed

- CMB2 compatibility shim removed now that the ACF migration is complete

## [7.0.4] - 2026-06-17

### Fixed

- Failed Action Scheduler actions now respect the configured 3-day retention period after the Action Scheduler update introduced a dedicated `action_scheduler_retention_period_for_failed` filter

## [7.0.3] - 2026-06-16

### Fixed

- Cron Scripts (`--verbose` isn't a thing)
- OTD now knows how to handle ACF fields.

## [7.0.2] - 2026-06-16

### Added

- Draggable number slider for show Quality, Realness, and Screentime ratings, replacing radio buttons (0–5 scale)
- Actor Gender and Sexuality fields default to "Cisgender" and "Unknown" on new actor posts

### Fixed

- JSON export SQL queries now use prepared statements instead of raw value interpolation
- Taxonomy term shortcode output now escapes slugs, URLs, and term names against XSS
- Actor statistics overlay restored with loading spinner
- NPM Update

### Removed

- Deprecated IP-based Gravity Forms location population, superseded by NGINX

## [7.0.1] — 2026-06-15

### Added

- WP-CLI `migrate acf airdates` subcommand to backfill legacy CMB2 airdate data into ACF-compatible separate meta keys for all shows

### Changed

- ACF field editor is now hidden on production environments, preventing accidental field group edits outside of development

### Fixed

- Shows with a `current` finish date were incorrectly marked as off-air due to the value being cast to an integer (`0`) before the string check
- BYQ death list endpoint returned an empty array on page load and REST API requests due to an overly broad `wp_current_filter` guard

## [7.0.0] — 2026-06-15

### Added

- SearchWP integration with a search scope selector in the search modal, allowing users to choose which search engine to query
- Linear and exponential trendlines on charts via chartjs-plugin-trendline
- Post editor single-scroll layout for small screens in the block editor
- WP-CLI commands to migrate CMB2 data to ACF for show names, similar shows, auto-posting settings, watch term URLs, and debug logging

### Changed

- All CPT custom fields for actors, characters, and shows migrated from CMB2 to ACF Pro
- Plugin settings (auto-posting, debug logging, watch URLs) migrated from CMB2 to ACF
- TVMaze integration refactored to use ACF fields instead of CMB2 metaboxes

### Fixed

- Duplicate posts appearing in character and show queries
- Character image display: rounded styling is now configurable, alt text uses attachment IDs directly, and tab navigation IDs are consistent

### Removed

- CMB2 metaboxes for actors, characters, shows, and TVMaze (fully replaced by ACF Pro)
- Deprecated `Taxsync_Task` class

## [6.6.0] — 2026-06-03

### Added

- On Air debugger and validator: flags shows whose on-air metadata disagrees with computed airdate logic
- Shadow taxonomy drift check: runs daily via Action Scheduler; queues a repair task when character count exceeds shadow term count
- `shadow_tax_drift_check()` scheduled via Action Scheduler to catch sync gaps automatically
- `lwtv_shadow_tax_sync_failed` action hook; fires after 3 consecutive shadow taxonomy sync failures and appends affected actors to the debug problems transient
- Cron: `--verbose` flag added to `ontheten.sh`

### Fixed

- BYQ `/last-death` endpoint intermittently returning Frankie (character ID 83580, the first dead character ever entered) instead of the actual most recent death. Four compounding bugs fixed:
  - Broken type comparison (`'83580' !== $id` was always `true` due to string/int mismatch) — corrected to `83580 === (int) $id`
  - On detection of stale Frankie data, only `byq_last_death_*` was cleared; `byq_death_list_*` was not, causing immediate re-caching of stale data
  - `list_of_dead_characters()` returned empty on REST API calls (empty filter stack) and deferred to Action Scheduler, leaving the endpoint with nothing; REST requests now bypass the guard and regenerate in-request
  - Duplicate `$wpdb->get_results()` call in `get_bulk_death_meta_data()` — first result was silently overwritten; redundant call removed
- Draft/unpublished characters no longer appear on show pages, in widgets, or in character counts (fixes case where a draft character was showing up unexpectedly)
- Clipboard copy in WikiData block now surfaces an error alert on failure instead of silently doing nothing
- Show score calculation: guarded sub-calculations against `null` returns before arithmetic; logs incomplete scores instead of writing zero silently
- `wp_get_post_terms()` and `get_the_terms()` calls had incorrect second argument (`true`) — removed
- Shadow taxonomy sync: replaced single-shot retry with a transient-backed failure counter (3 strikes before action fires)
- Debugger: `has_term()` was missing `$char_id` argument, always checking the current post in the loop instead of the correct character
- Debugger: `update_post_meta` was writing `$check['wiki']` (empty) instead of `$check['home']` when migrating homepage to Wikipedia field
- Debugger: dupe-slug query expanded from `LIKE '%-2'` to `REGEXP '-[0-9]+$'` to catch `-3`, `-4`, and beyond
- `class-onair.php` was missing `update_option` call for `lwtv_debugger_status`
- Wikidata `wp_remote_get()` calls upgraded to HTTPS with a 15-second timeout

### Improved

- Build stack modernized: Grunt retired in favor of npm scripts and `_build_scripts` tooling
- Theme/package renamed from “YIKES Starter” to “LWTV Underscores” throughout
- PHP requirement raised to **8.5** across `composer.json` and CI workflows
- Composer asset copying replaced (SlowProg `copy-file` hooks → `_build_scripts/copy-composer-assets.sh`)
- Git hooks migrated from Husky to `.githooks/pre-commit`
- Show score: `get_terms()` now runs once per request during bulk recalculation via `$tax_scaffold` static cache
- Show score: `prime_character_caches()` batch-primes post meta and term object caches before character loops
- Debugger: all actor post meta fetched in one `get_post_meta($id)` call instead of per-key queries
- Cron scripts: output redirected to temp file; only appended to log on failure, keeping logs clean on success; tmpfile cleaned up on exit
- Shadow taxonomy sync: `self::` static calls in `Calculations::do_the_math()` corrected to `$this->`
- Dependabot target branch changed to `development`; versioning strategy simplified

## [6.5.6] — 2026-04-16

### Added

- AI agent blocking: `BlockAgents` class added to prevent AI crawlers from accessing actor data (liability concern re: hallucinations)
- WordPress Collaboration feature explicitly disabled (too resource-heavy)
- REST API `Broom` component initialized for data management endpoint
- New `wp lwtv sweep-death` WP-CLI command to manually invalidate the BYQ death list cache and flush object cache

### Fixed

- Cache invalidation for the BYQ death list now correctly fires when a character is saved; invalidation deferred 10 minutes via Action Scheduler to avoid blocking saves
- OTD posts to Postiz: fixed hook registration occurring on wrong class instance (parent instead of child `Of_The_Day`/`New_Post`), which caused posts to silently not send
- Infinite recursion bug in `create_tag()` — method was calling itself instead of returning tag arrays
- Statistics cache key collisions in Nations and Stations (`nation_meta_{slug}_{type}` and `station_meta_{slug}_{type}` formats now used)
- Actor privacy status only written to DB when it actually changes (removes unnecessary `wp_update_post()` calls)
- Cache now flushed before OTD generation in cron to ensure fresh data
- Postiz: deprecated constants-based API config removed in favor of options-only approach
- Bluesky posts now truncated to 296 characters with ellipsis (platform limit compliance)
- CMB2 text boxes for Notable Episodes and Timeline were uneditable due to CMB2Grid incompatibility — WYSIWYG fields replaced with `textarea_small`
- CMB2 notable episodes and timeline fields fixed (gridding was preventing editing)
- SQL query in taxonomy optimization improved: LEFT JOINs → INNER JOINs; cache duration extended from 1 hour to 24 hours

### Added (infrastructure)

- Postiz social media API integration for automated posting of “Of The Day” content to configured channels
- “Of The Day” format renamed from `tweet` to `socialmedia` (backward-compatible deprecation in place)
- `lwtv_otd_added` action hook fires after OTD entry is created/found, enabling integrations
- Debug Logging admin page: toggle debug mode and select which topics to log
- Action Scheduler integration for `Missed_Schedule` and `Of_The_Day` with WP cron fallback
- BYQ daily cache refresh: runs automatically during updates via WP-CLI
- `ping.sh` centralized health check script for all cron jobs; endpoint moved to `health.ipstenu.com`
- GitHub Actions workflows refactored to three-job structure (build → deploy → post-deploy) with artifact upload/download
- Object cache invalidation for related posts when post meta changes
- `lwtv_last_postiz_post` and `lwtv_was_last_otd` post meta added to track posting history and prevent duplicates

## [6.5.5] — 2025-11-11

### Fixed

- Excessive looping when saving a character: death stats were recalculating for ALL characters on every actor or show save. Now only fires when saving a character post directly
- Queer Checker no longer caches its result (was causing significant lag and stale data)
- SVG icon positions corrected in SCSS for proper rendering and alignment
- Cron scripts: centralized health-check pings into a reusable `ping.sh` script

### Improved

- GitHub Actions deployment refactored: three-job pipeline (build, deploy, post-deploy) with explicit artifact steps for theme and cron files, making deployments more modular
- Symbolicons build integrated into deployment: deployment now pulls down and copies symbolicon files to theme directory
- `CODE_OF_CONDUCT.md` and `SECURITY.md` moved to `.github/`
- PHP version bumped to match environment across composer and workflow configs
- Dependency updates: `dealerdirect/phpcodesniffer-composer-installer`, `phpcsstandards/phpcsutils`, and various npm packages

## [6.5.4] — 2025-10-31

### Added

- WikiData sidebar is now clickable (links out from the metabox)
- Note added to character edit page clarifying qualifying characters (not cis-men), surfaced more prominently in the UI
- Copy-to-clipboard button with toast notification added to WikiData Actor block
- Deployment workflow updated to use staging directory for atomic updates
- New `ontheten.sh` cron script

### Fixed

- Death caching removed entirely for `last_death` — the underlying post meta is too mutable for reliable caching; was causing stale data
- Attached posts JS fixed
- Romantic partners field removed from characters CPT (was causing performance issues at save time)
- Queer actor detection logic improved
- Shows now use air start date correctly (not publish date)
- Internal health check pings removed from cron jobs (replaced in 6.5.5 with centralized ping)
- WikiData check made case-agnostic
- Uptime monitor links fixed
- Weird death calculation loop fixed

### Improved

- “Is actor queer” logic improved
- Dev scripts made smarter about which environment they target
- Library updates

## [6.5.2] — 2025-10-09

### Added

- Checker added for BYQ and characters to catch data issues proactively

### Fixed

- Last Death tracking overhauled: was using character names as sort keys, which could break ordering. Now uses timestamps with a `+1` bump for same-second duplicates — fixes #455
- Three characters had death dates missing entirely; debugger now catches this case
- Death data always cast to `int` (timestamps are always integers)
- Stats dark mode display fixed
- Characters missing from list views — fixes #452
- Lists not listing content — fixes #452
- Death cache not persisting correctly between requests

### Improved

- Licence files updated
- Library version bumps

## [6.5.1] — 2025-09-29

### Fixed

- Characters missing from dead character list view — fixes #452
- Stats dark mode broken
- URL fix snuck in for a broken link

### Improved

- Library updates

## [6.5.0] — 2025-09-05

This version is a major performance refactor of the statistics system and post-save pipeline.

### Added

- **Action Scheduler integration**: TMDB API calls, cache invalidation, taxonomy sync, and `fix_shows` all moved from synchronous save-time execution to scheduled background tasks
- **Stats system refactor** (resolves long-standing N+1 query problems):
  - Character count queries in table loops consolidated
  - Multiple show count queries batched
  - Transient cache improved and extended
  - Stations and Nations fully optimized
  - Shows, Actors, Characters, Dead characters, Dead shows, This Year — all rebuilt
  - Death overview and “list your dead” pages added
  - Basic Stats views completed
- Cache warming added
- `transition_post_status` hook used to improve new character flagging
- Front page “Loved Shows” moved to a direct SQL call (faster for meta queries)
- MonsterInsights Site Notes added for shows, characters, etc.
- Action Scheduler menu link added to Tools (several plugins remove it; this restores it)
- AIOSEO stub added then removed (decided against it)
- Alexa Skills sunset — zero usage in 3 years

### Fixed

- Sync restored to on-demand after scheduling caused unexpected issues — scheduling reverted for taxonomy sync specifically
- Character sync issues fixed
- Dead character flag no longer shows on the dead characters page itself
- CSS fix for stats pages
- Scores improved and recalculation logic corrected
- Actor/character query fixes

### Improved

- Cron backup copy kept server-side in case of deploy issues
- Component architecture: more functions moved to components
- Twitter linking fixed; HTTPS enforced
- Various API deprecations cleaned up

## [6.4.3] — 2025-08-29

### Added

- First pass at Action Scheduler integration (prep work before full adoption in 6.5.0):
  - New scheduling component added
  - TMDB API calls scheduled instead of running at post-save time
  - Cache invalidation moved to `shutdown` hook (runs after everything else completes)
- Calendar performance improvements:
  - Pre-processes calendar data once to eliminate redundant processing across views
  - Batch post meta queries replace individual `get_post_meta()` calls
  - Shared instances of calendar objects to reduce redundant creation
  - Fallback display if calendar file is missing or invalid

### Fixed

- Private note filter that stopped working (broken upstream); improved to also auto-alert on character pages when the actor is marked private
- Calendar grid layout tweaked

### Improved

- WP Block Libraries bumped
- SVGO updated
- Linting applied
- Unused code removed

## [6.4.2] — 2025-07-08

### Added

- Privacy notes now auto-generated on character pages when the associated actor is marked private

### Fixed

- A filter for private actor notes that had stopped working upstream — restored and improved

### Improved

- WP Block Libraries updated
- SVGO bumped

## [6.4.0] — 2025-05-26

### Added

- **Symbolicons migrated to SVG sprite**: Font Awesome dependency removed for custom icons; build now generates and integrates an SVG sprite from the private symbolicons repo
- Symbolicon admin page updated with improved display
- Build process: new commands for local and staging environments; build now auto-updates stylesheet version from `package.json` and auto-updates `functions.php` file versions from source files
- Icon sizes forced in contexts where they need to be explicit; icon colors default to black
- Actor page layout improved
- Max size attributes standardized across character, home, post, single, and author templates

### Fixed

- CSS output corrected following symbolicon changes
- Symbolicon path resolution fixed

### Improved

- Build scripts moved to their own folder
- Default symbolicon shape set to square via mixin
- Package updates

## [6.3.1] — 2025-05-14

### Added

- Calendar widget added to the sidebar
- Cursor rules file added (`.mdc`) so AI tooling assists with WordPress conventions correctly

### Fixed

- SpecLoading disabled — was degrading site performance because it can’t predict user behaviour on this site
- Timezone math corrected in calendar calculations
- Avatar speed improved; gravatar detection made more reliable
- TMDB logic and caching improved
- Scores now saved to transients to reduce database load

## [6.3.0] — 2025-03-31

### Added

- HealthChecks.io integration: cron jobs now auto-create and ping health check instances; scheduling math improved to use intervals correctly
- Transient support added to HealthChecks to prevent excess pings on creation
- Avatars moved from CSS-generated to SVG

### Fixed

- Calendar not showing previous/next week links — date math was wrong; fixed
- iPad layout: “Loved” list bumped to 4 columns on iPad, 3 elsewhere; CSS overflow fixed; button border removed
- Calendar fixed after HealthChecks changes
- Gravatar detection improved for speed
- TMDB logic improvements
- Scores: only saved when not empty; matcher now includes scores correctly

### Improved

- Library version bumps
- Debugging improvements across several components
- Unused code removed from shows component

## [6.2.8] — 2024-12-23

- Updated FontAwesome to latest release
- Bumped NPM audit dependencies

## [6.2.7] — 2024-12-10

### Added

- Distinct letter-based avatars for visiting (non-gravatar) commenters — color generated from username, initials displayed

### Fixed

- Dark mode CSS for comment sections was broken
- Theme update blocker was not functioning correctly
- Mobile CSS layout fixes

### Improved

- WikiData checker improvements and QID saving
- Admin display fixes for avatar feature

## [6.2.6] — 2024-12-02

### Added

- WikiData sidebar metabox for actors, converted to Gutenberg block with a refresh button
- New WikiData API endpoint for checking actor data
- Support for displaying QID on actor edit pages
- Auto-draft check improvements: page now auto-refreshes when a name is entered

### Fixed

- Deprecated SASS `@import` rules replaced with `@use` (breaking change in SASS; this resolves build warnings and CSS output inconsistencies)
- WikiData metabox: fixed rare bug where characters aren’t an array
- Facebook check made smarter; metabox now only runs on actor post type

### Improved

- Version references consolidated and cleaned up across files
- Bootstrap TableSorter renamed to remove version from filename
- Block version bumped
- NPM security audit fixes

## [6.2.5] — 2024-12-02

### Added

- Actor modals now update the browser URL so individual stats/articles sections are directly linkable; URL resets on close
- QID displayed on actor WikiData edit panel

### Fixed

- CLI output when running the daily cron
- Actor debugger call corrected
- TVMaze updater now properly flushes cache after update

### Improved

- CSS build corrected so webpack output is cleaner
- Added `SECURITY.md` and `CONTRIBUTING.md`
- JS search box documented

## [6.2.4] — 2024-11-25

### Added

- Weekly grid view and monthly calendar view for the TV calendar
- Tab-based view switching with URL persistence via JavaScript (view survives page navigation)
- Subnav to jump to days within the weekly view
- Responsive and dark mode support for calendar
- TVMaze IDs now generated and saved on show/actor save for faster calendar lookups
- FacetWP: shows can now be sorted by newest/oldest air date (fixes long-standing request)
- TMDB ID auto-generation for shows and actors from IMDB data
- Threads support added to actor and author social links
- Editor tool: “Queer Flag” field replaced post type dropdown with a simpler Yes/No field
- Header search input now auto-focuses when the magnifying glass is clicked (keyboard accessibility fix)
- OTD (On This Day): cache flusher added to reduce latency
- CLI: new command to flush any page cache on demand
- Added `CODE_OF_CONDUCT.md` and `SECURITY.md`

### Fixed

- Nginx Helper cache purge order corrected — connected pages (e.g. actor ↔ character) now cross-flush properly; WP Rocket purge moved to after Nginx
- SVGs now have enforced max-size to prevent occasional large-image rendering bug
- Graceful failure when no actor is listed on a character
- Post type check for overlay display corrected
- CLI output improved for cron jobs
- Calendar cache now flushed when TVMaze data is downloaded daily
- Redundant queries and stats calculations cleaned up

### Improved

- Calendar renamed internally from “Blocks” to “Display” for clarity
- CSS broken apart into partials to ease future migration off `@import`
- Build time improved by excluding unused files
- Non-minified CSS build restored (partial)
- Main icon preloading removed from pages that don’t use it
- Editor title labels improved for mobile data validation
- FontAwesome updated (minor release)
- Console.log removed from search form fix
- Dependabot: webpack 5.96.1, @types/node 22.8.7, and several other NPM packages

## [6.2.0] — 2024-10-29

### Added

- Plugin code merged into theme repository — lwtv-plugin is now a one-stop-shop alongside the theme
- Parallel build system: wp-scripts and Grunt now run together to build JS and CSS
- On-air detector made smarter with improved logic
- FontAwesome updated to 6.6.0
- PHPCS linting added and applied across codebase
- Gutenberg category fix for WordPress 6.6
- FacetWP file moved to group folder for better organization
- Self-hosted copies of third-party plugins added to `/plugins/`

### Fixed

- Auto-population of primary genre on show save
- On-air detection edge cases
- CMB2 Grid pathing corrected after plugin move
- Build scripts updated to use `postbuild` instead of `postinstall`

### Improved

- Functions moved to components architecture
- JS moved to consolidated location
- Linting commands updated
- Chart.JS updated to 4.4.6
- minimist updated to 1.2.8 (security)
- CMB2 Grid updated; CSS updated accordingly

## [6.1.1] — 2024-06-24

### Added

- Dark mode support (full implementation with Bootstrap dark mode, icons, and FacetWP)
- CSS for Bootstrap dark mode base
- JS flicker prevention during dark mode toggle
- Death SVG icon added
- CSS `@import` → `@use` migration begun

### Fixed

- Responsive dark mode compatibility
- Background colors for dark mode display
- Multiple layout fixes following PageSpeed work

### Improved

- Sidebars and partials broken out into their own directories
- Sidebars updated to call card templates (easier to maintain)
- Character cliché card added as layout experiment
- Icon size increased for accessibility
- CSS version bumped to force cache refresh

## [6.1.0] — 2024-06-24

### Added

- PageSpeed improvements: LCP (Largest Contentful Paint) optimized across CPTs, search, and 404 page
- First image added to LCP preload on search
- Aria labels added for intersection taxonomy

### Fixed

- A11Y: header order corrected
- A11Y: Green Thumb contrast ratio fixed
- A11Y: author box accessibility fixes
- A11Y: show page accessibility fixes
- Social name display fix
- `show-appears` sticky positioning fixed
- Fallbacks added for missing actor/show data

### Improved

- Styles cleaned up and reorganized
- Intersection taxonomy treated as its own entity (no longer grouped under tropes)
- Mobile age display fixed
- Package dependencies updated

## [6.0.5] — 2024-06-19

### Added

- Support for updated “Ways to Watch” taxonomy
- Overlays for stats sections and related posts/articles
- Overlay for suggestions adjusted

### Fixed

- Post missing from TikTok feed
- Google SEO: prohibited ARIA attribute on elements
- LCP tweaks for Google scoring

### Improved

- CSS improvements for overlays and layout
- Style polish pass

## [6.0.4] — 2024-04-04

### Added

- Bluesky support in actor social links and navigation menu
- Font Awesome updated to 6.5.2

### Fixed

- Character photo linking restored
- Character photo width corrected
- Actor social link URLs (props Carmel)
- IMDB URL handling in actor socials
- Alt text on character images (props Nikki)
- CSS spacing

### Improved

- Card image cleanup
- Minor fixes pass

## [6.0.3] — 2024-04-02

### Fixed

- Related articles display
- Character template rendering corrected
- Data loading reduced by passing data to templates instead of re-fetching

## [6.0.2] — 2024-03-18

### Added

- Bootstrap 5 upgrade (replaces Bootstrap 4)
- Shadow taxonomy support — characters, shows, and actors now use shadow terms for performance
- LCP applied to custom post type pages
- Actors and Shows display as accordions when 2+ entries exist (e.g. Sara Lance)
- Templates broken apart into reusable partials (DRY)
- Responsive fixes for mobile navigation (Bootstrap 5 navmenu)

### Fixed

- JavaScript errors from Bootstrap 5 migration
- Navmenu on mobile
- Responsive layout on phone
- Related posts header
- Years display on actor pages
- Character priority in display logic

### Improved

- Load times improved via LCP and shadow taxonomy queries
- CSS minified for Bootstrap
- Code that was duplicated across actors and shows shared into common templates
- Actor and show page speed improvements

-----

## Prior to 2024-03-18

*Versions prior to 6.0.2 are not covered in this changelog. See git history for full detail.*
