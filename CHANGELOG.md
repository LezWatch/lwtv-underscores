# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [7.2.0] - 2026-08-28

### Added

- Add IMDb staleness detection that flags stored IMDb IDs disagreeing with TVMaze/TMDB, with a new `wp lwtv imdb` command for review.
- Add an opt-in longevity-weighted character scoring preview, not yet applied to live scores.
- Add per-finding repair buttons and a `--fix-it` CLI flag so debugger findings can be fixed directly instead of just reported.
- Add new/open/resolved tracking and debug log rotation with a CLI viewer to the debugger.
- Add a background "Run Scan" option for the Watch Term Check URL sweep, so it no longer requires the command line.
- Add existing-term suggestions to the Watch Providers tab before offering to create a duplicate term.

### Changed

- Watch-provider terms are now matched by normalized host instead of exact URL, and host collisions between terms are now detected.
- Hosts that repeatedly fail to answer are now retried a limited number of times instead of being checked forever.
- Consolidated character scoring into a single implementation shared by the live calculator and the CLI preview tool.

### Fixed

- Fixed trigger warnings adding to a show's score instead of subtracting from it.
- Fixed several long-standing copy-paste bugs in the admin validator tabs, including stale labels, missing translations, and mismatched security tokens.

## [7.1.15] - 2026-08-24

### Added

- Add a term-scoped watch-URL health check that classifies each provider link as ok, broken, blocked, or needing review (parking pages, off-domain redirects, or a mismatched provider name).
- Add a `wp lwtv debug watchurls` command that runs a full watch-URL health sweep.
- The Watch Term Check admin tab can now re-check just the flagged URLs instead of re-running a full scan.

### Changed

- Watch-URL health checks now run once per provider instead of once per show, cutting the number of requests needed to validate links.

### Removed

- Removed the old show-by-show URL validator and its unreachable tab.

## [7.1.14] - 2026-08-21

### Added

- Add a "Watch Providers" tool that lists hosts not yet registered in the provider taxonomy, ranked by real show usage, and registers them as terms in one click.
- Add a `wp lwtv waystowatch enrich` command that discovers a provider's display name directly from the host.
- Add `wp lwtv tmdb status` and `wp lwtv tmdb backfill` commands to fill in missing TMDB IDs for shows and actors from their existing IMDb IDs.

### Changed

- Ways to Watch link matching now trusts the provider taxonomy as the source of truth for display names, instead of a hardcoded guess table, and matches host/www/scheme variants in priority order so the most specific match always wins.
- The Watch Providers taxonomy admin screen now shows a per-term URL count.
- The validator's tab row is now a dropdown instead of a row of links.
- Shows with a score of 0 now render that score instead of hiding it.

### Fixed

- Fixed provider hostname matching that mangled names like watch.amazon.com into "Mazon".
- Fixed the show airdate check only reading the legacy meta key, which incorrectly flagged migrated shows as missing airdates.
- Fixed duplicate-show and duplicate-actor detection treating two entries with no IMDb ID as a match, and a dupe-override comparison that could never be true.
- Fixed a PHP deprecation warning from the debugger status option on fresh installs.
- Fixed escaping gaps in debugger and Ways to Watch link output.
- Fixed grammar in the actor/show IMDb "all clear" messages.

### Removed

- Removed unused actor wiki code and a dead admin hook.

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

## [6.x.x] - 2024-03-18 to 2026-06-03

- See [v6 Changelog](docs/changelogs/v6.md)

## Prior to 2024-03-18

*Versions prior to 6.0.2 are not covered in this changelog. See git history for full detail.*
