# LezWatch.TV Docs

Reference docs for how the theme and plugin work. Code comments point here with `See docs/<path>.md#<anchor>.` Start with [`.claude/CLAUDE.md`](../.claude/CLAUDE.md) for the project overview and conventions.

## Scoring

- [Show Score](scoring/show-score.md): how `lezshows_the_score` is built, stored and regenerated.
- [Character Score](scoring/character-score.md): the longevity-weighted, saturating character component.
- [Score Calibration](scoring/calibration.md): how the tuning constants were set, and how to re-derive them.

## Statistics

- [Data model](statistics/data-model.md): storage quirks every statistics query has to handle.
- [Pages](statistics/pages.md): the `build/` contract, which build classes feed which view, and page layouts.
- [Presentation rules](statistics/presentation-rules.md): precision, rounding, pictogram allocation, ties and adaptive wording.

## Architecture

- [Actor identity](architecture/actor-identity.md): WikiData QIDs, trust levels, the write-lock and the death audit.
- [Duplicate detection](architecture/duplicate-detection.md): name keys, the duplicate scan, unique IMDb IDs and the editor warning.
- [Caching](architecture/caching.md): transients, cache vs store, invalidation and warming.
- [Scheduling](architecture/scheduling.md): Action Scheduler, the WP-Cron fallback and queue patterns.
- [Watch Providers](architecture/watch-providers.md): host matching, provider names and the admin tools.
- [Validation Screen](architecture/validation-screen.md): how the Data Validation screen reports each check.
- [Meta Storage Quirks](architecture/meta-storage-quirks.md): where raw meta differs from what ACF suggests.
- [Calendar](architecture/calendar.md): the TVMaze ICS feed and the Eastern-time timestamp trap.
- [FacetWP Indexing](architecture/facetwp-indexing.md): index changes and keeping it current.
- [Meilisearch](architecture/meilisearch.md): the characters search prototype, its constants, commands and sync.

## Integrations

- [TVMaze](integrations/tvmaze.md): rate limits, matching, inclusion policy and cast data limits.
- [IMDb IDs](integrations/imdb.md): stale IDs, the services we check them against, and TMDB response shapes.
- [TMDB Watch Providers](integrations/tmdb-watch-providers.md): the provider strip on show pages, duplicate-service rules, the country switcher and attribution.
- [Content to Add sheet](integrations/content-to-add-sheet.md): how contact form submissions about new shows and characters reach the Google Sheet, the wp-config constants, held entries and `wp lwtv triage`.

## Operations

- [Cron Schedule](operations/cron-schedule.md): what the scheduled WP-CLI jobs run, and when.
- [API Usage](operations/api-usage.md): the admin page for lwtv/v1 traffic, the daily log rollup it reads, and the `LWTV_API_USAGE_CSV` constant.
- [PHP 8.6](operations/php-8.6.md): what the 8.6 checks found, what was fixed or deferred, and the staging checklist before moving the site.
- [CMB2 to ACF Migration](operations/migrations/cmb2-to-acf.md): field mappings and run order.
- [Bootstrap 6 Content Migration](operations/migrations/bootstrap-6.md): moving stored Bootstrap 5 classes to v6, and retiring the legacy stylesheet.
- [Shadow Taxonomy Purge](operations/migrations/shadow-taxonomy-purge.md): deleting the retired `shadow_tax_characters` data with `wp lwtv links purge-shadow`, and the staging/production runbook.

## Runbooks

Step-by-step procedures for setting things up, fixing them at 3am, and retiring them.

- [Meilisearch](runbooks/meilisearch.md): the self-hosted server, minting API keys per environment, the wp-config constants, first run, troubleshooting and upgrades.
- [Retiring SearchWP](runbooks/retiring-searchwp.md): what SearchWP does today, engine trims that are safe now, what Meilisearch needs before cutover, the cutover and rollback, and cleanup.

## Design

- [Statistics CSS](design/stats-css.md): layout and cascade rules for `_stats.scss`.
- [Statistics colours](design/colors.md): colour families and ramps.
- [Dark mode](design/dark-mode.md): how overrides work, and the gotchas.
- [Colour accessibility](design/accessibility.md): contrast rules that limit the palette.
- [theme.json](design/theme-json.md): the classic-theme `theme.json`, its token mapping, and what it switches on in core.

## Development

- [Testing](testing.md): the PHPUnit suite, test fixtures and the shim policy.
- [Plugin dependencies](dependencies.md): required plugins, the boot gate, and the third-party code bundled in the theme.
- [Plugin structure](plugin-structure.txt) and [Theme structure](theme-structure.txt): an annotated tree of every file. They're generated from disk: run `npm run docs:structure` after adding or removing files, then replace each `TODO: describe` it leaves. `npm run docs:structure:check` reports drift without writing.
- [SQL optimization](servers/sql-optimization.md): taxonomy character counts.
- [GitHub](github/): actions, secrets and workflows.
- [nginx security headers](servers/nginx-security-headers.conf): response headers for cached pages served straight from disk.
- [Changelogs](changelogs/): per-major-version release notes.

## Plans

- `plans/`: open feature plans and handoffs. `plans/completed/` holds plans that have shipped; `plans/maybe-later/` holds parked ideas.
- `superpowers/`: agent plans and specs.

Plans are background only. Once a plan ships, the docs above are the source of truth, so check the code before relying on a plan.
