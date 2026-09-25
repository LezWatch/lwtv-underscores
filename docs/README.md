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

## Integrations

- [TVMaze](integrations/tvmaze.md): rate limits, matching, inclusion policy and cast data limits.
- [IMDb IDs](integrations/imdb.md): stale IDs, the services we check them against, and TMDB response shapes.

## Operations

- [Cron Schedule](operations/cron-schedule.md): what the scheduled WP-CLI jobs run, and when.
- [CMB2 to ACF Migration](operations/migrations/cmb2-to-acf.md): field mappings and run order.

## Design

- [Statistics CSS](design/stats-css.md): layout and cascade rules for `_stats.scss`.
- [Statistics colours](design/colors.md): colour families and ramps.
- [Dark mode](design/dark-mode.md): how overrides work, and the gotchas.
- [Colour accessibility](design/accessibility.md): contrast rules that limit the palette.

## Development

- [Testing](testing.md): the PHPUnit suite and the shim policy.
- [Plugin dependencies](dependencies.md): required plugins and the boot gate.
- [SQL optimization](sql/optimization.md): taxonomy character counts.
- [GitHub](github/): actions, secrets and workflows.
- `plans/` and `superpowers/`: historical plans and specs. They are background only and may be out of date.
