# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [7.0.8] - 2026-06-XX

### Fixed

- Restored Summary
- Administrators can now actually enable ACF field group
- ACF Number Slider
- Accessibility colors

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
