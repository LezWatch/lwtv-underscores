# Handoff: TMDB watch providers (replacing the JustWatch widget)

Paste this into Claude Code from the repo root. The full plan is `docs/plans/tmdb-watch-providers.md`; read it first. This file is state and next steps.

## Ground rules (from .claude/CLAUDE.md)

- Never commit without explicit approval in the same turn. Never merge or close the branch. Work on a feature branch; PRs target `production`.
- Pure logic goes in tested classes (build/format/templates split). Write the test first.
- PHPUnit is 13: use `#[DataProvider]` attributes, not `@dataProvider` docblocks.
- All strings i18n-ready, text domain `lwtv-underscores`. Prefer `rem`; use `colors.$lwtv-*`; check light and dark (WCAG AA).
- Run `nvm use` before any JS/CSS work. `npm run buildquick` after SCSS changes.
- Do not edit `plugins/lwtv-plugin/php/blocks/build/`.

## What this is

JustWatch never issued a widget API key, so the widget integration (`JUSTWATCH_API_KEY`) never went live. We are replacing it with TMDB's `/tv/{series_id}/watch/providers` (JustWatch data, attribution required).

Design (approved): on a show page, a strip of provider logos. The whole strip is **one** link to the country's TMDB watch page (TMDB gives no deep links). Visible country `<select>`, pre-selected from the browser, US as fallback. The curated `lezshows_waystowatch` links show only when TMDB has nothing displayable for the show or the per-show override is on, and never alongside the strip. Only `flatrate`, `free` and `ads` are shown; `rent` and `buy` are stored only. Provider matching against curated links was dropped.

## State

Done and uncommitted. Check `git status`:

- `plugins/lwtv-plugin/php/_helpers/class-tmdb-providers.php`: `normalise`, `for_country`, `countries`, `default_country`, `payload`
- `plugins/lwtv-plugin/php/cpts/shows/watching/class-ways-mode.php`: `decide( force_curated, has_curated, has_providers )` returns `providers`, `curated` or `none`
- `tests/unit/Helpers/TmdbProvidersTest.php`, `tests/unit/CPTs/WaysModeTest.php`
- `tests/bootstrap.php`: two `require_once` lines added
- `docs/plans/tmdb-watch-providers.md`, this file

The Helpers and CPTs test folders passed, run through a PHP 8.4 WebAssembly build. **The full `vendor/bin/phpunit` and `composer lint` have not been run.** Run both first. Composer's platform check wants PHP 8.5.

Not started: everything in the plan's phases 2 to 4.

## Next steps, in order

1. Run `vendor/bin/phpunit` and `composer lint`; fix anything in the new files.
2. **Real-data check.** If `tests/fixtures/tmdb/watch-providers-1399.json` exists (Game of Thrones, TMDB id 1399), run `Tmdb_Providers::normalise()` against it and look for what it wrongly rejects (links with slugs such as `…/tv/1399-game-of-thrones/watch?locale=ZA`, logo paths) and for plan-variant names. Add a test using a trimmed copy. If the file is missing, create it:
   `wp eval 'echo wp_remote_retrieve_body( wp_remote_get( "https://api.themoviedb.org/3/tv/1399/watch/providers?api_key=" . TMDB_API ) );' > tests/fixtures/tmdb/watch-providers-1399.json`
   The local site is `lwtv.local`; `TMDB_API` is defined there.
3. **Phase 2, data pipeline** (plan section "Fetch and refresh"):
   - Register `lezshows_watch_providers` and `lezshows_watch_providers_checked` in `cpts/class-post-meta.php`, `show_in_rest => false`, like `lezshows_tvmaze_ignore`.
   - `_Components\CPTs::get_tmdb_watch_providers( $post_id )`, mirroring `get_tmdb_info()`'s guards.
   - `Schedulers\Watch_Providers_Task` modelled on `TMDB_Batch_Task` (own throttle, batches of 30, 7-day TTL, Action Scheduler only; success writes the timestamp, transport error does not). Register it where the other tasks are.
   - `wp lwtv watchproviders refresh|status`. Update `docs/architecture/scheduling.md` and `docs/operations/cron-schedule.md`.
   - Check the result on staging against themoviedb.org.
4. **Phase 3, rendering** (plan section "Rendering"): replace `Theme\Ways_To_Watch::render_justwatch()` with the strip; add `js/ways-to-watch-country.js` and styles. Follow the accessibility spec in the plan exactly: one link, decorative `alt=""` logos, `aria-label` listing the providers, visible "See all options on TMDB", label plus native select, live region, cap of 8 logos, 44 px target.
5. **Phase 4, cleanup and docs.** Only after the strip works: remove `JustWatch` (class, `JustWatchTest.php`, bootstrap line, constants, enqueue, `.justwatch*` SCSS), update the ACF override field's instructions and `modified`, move `docs/plans/justwatch-widget.md` to `completed/`, add `docs/integrations/tmdb-watch-providers.md`, and update `docs/architecture/watch-providers.md`, `docs/README.md` and `CHANGELOG.md`.

## Open items and gotchas

- **Click-through test first.** On a real show, click from the TMDB watch page to a provider and count the clicks. Tell me if it is more than one or two; the design assumes it is acceptable.
- **Country names:** server-side uses `\Locale::getDisplayRegion()` if the `intl` extension exists, else the country code. Confirm production has `intl`.
- **Privacy policy and credits page** need a line each (TMDB and JustWatch attribution; `image.tmdb.org` image requests). Site content, not code.
- **Confirm the existing `TMDB_API` key is registered as non-commercial** with TMDB.
- The old widget plan lists a per-show override field (`lezshows_waystowatch_curated`) that is already registered; keep its key and data, change its label and instructions only.
- Language on the page: "Where to watch", "powered by TMDB and JustWatch". JustWatch must be named as the source or TMDB revokes access.
