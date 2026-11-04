# TMDB watch providers for Ways to Watch

Date: 2026-11-03
Status: Built. All four phases written and verified on lwtv.local, uncommitted. Reference doc: [tmdb-watch-providers.md](../integrations/tmdb-watch-providers.md). Still open: the click-through count, privacy policy and credits page lines, and confirming the TMDB key is registered as non-commercial.
Branch context: work happens on a feature branch; PRs target `production`. Nothing is committed without approval.
Supersedes: [justwatch-widget.md](completed/justwatch-widget.md).

## Goal

Replace the JustWatch widget (never got a key) with data from TMDB's
`/tv/{series_id}/watch/providers`, which is JustWatch data under TMDB's
partnership. Show a row of provider logos on each show page, with the reader's
country pre-selected, and fall back to the curated links when TMDB has nothing.

## Decisions

- **Design A, TMDB with curated fallback.** One strip of provider logos, and the
  whole strip is one link to that country's TMDB watch page. Curated links are
  shown only when TMDB has no displayable provider for the show, or when the
  per-show override is on. They are never shown alongside the strip.
- **Why one link.** TMDB returns no deep links, so every logo would go to the same
  URL. Several identical links in a row are bad for screen reader and keyboard
  users. One link says honestly where the click goes.
- **No provider matching.** Because the curated links no longer sit beside the
  strip, there is nothing to match them against. The matcher and the
  provider-ID mapping idea (old phase 4) are dropped.
- **Country selector is visible**, pre-selected from the browser, with the US as
  the fallback.
- **Logos from TMDB** (`image.tmdb.org`). Third-party image requests: add a line to
  the privacy policy.
- **Types shown:** `flatrate`, `free`, `ads`. `rent` and `buy` are stored, not shown.
- **Refresh TTL:** 7 days.
- **Remove the widget code:** `JustWatch`, `JUSTWATCH_API_KEY`, the script
  enqueue, tests and the old plan doc.

## Facts that shape the design

- TMDB gives provider id, name, logo path and one TMDB URL per country
  (`https://www.themoviedb.org/tv/1399-game-of-thrones/watch?locale=ZA`). It does
  not give provider URLs. Per TMDB, that page is where the deep links are.
  Verify on staging how many clicks it really takes to reach a provider.
- Coverage is JustWatch's coverage. Web series and small international shows will
  often be empty, which is what the curated fallback and the override are for.
- **No HTTP at render time.** Data is fetched in the background and read from
  post meta.
- Attribution is mandatory. JustWatch must be named as the source, or TMDB
  revokes access. TMDB's own notice also goes on the credits page.
- Confirm the existing `TMDB_API` key is registered as non-commercial.
- `get_tmdb_info()` does not cache and is not changed.

## Data model

Post meta on shows, registered in `class-post-meta.php` with `show_in_rest => false`:

| Key | Content |
|---|---|
| `lezshows_watch_providers` | JSON from `Tmdb_Providers::normalise()`: `{ "US": { "link": "…", "flatrate": [ {id, name, logo} ], "free": […], "ads": […], "rent": […], "buy": […] }, … }` |
| `lezshows_watch_providers_checked` | Unix time of the last successful fetch. |

Post meta, not a transient or option: per show, survives the CLI and web cache
tier split ([caching.md](../architecture/caching.md#cli-and-web-cache-tiers)), no
index needed. A successful empty answer is stored as `{}` with a timestamp so
"TMDB has nothing" is not re-asked every run. A transport error does not write the
timestamp, so the show is retried.

The `lezshows_waystowatch_curated` override keeps its key and data. Only its label
and instructions change.

## Pure logic (phase 1, done)

All in unit tests; no WordPress.

- `LWTV\_Helpers\Tmdb_Providers`
  - `normalise( $response )`: keeps `{id, name, logo}` per provider, ordered by
    `display_priority`; drops malformed country codes, providers without an id or
    name, bad `logo_path` (becomes `''`), and any country whose `link` is not an
    https themoviedb.org URL (it goes in an `href`).
  - `for_country( $data, $country, $types )`: one country's list, one entry per
    service, streaming first. Drops resold or plan variants of a listed service
    (a name that is a listed name plus a space and more: "HBO Max Amazon
    Channel", "Netflix basic with Ads"); a variant that is the only entry for its
    service ("HBO Max on U-Next" in JP) stays. Then resellers ("… Amazon
    Channel", "… Apple TV channel", "… Roku Premium Channel") and spellings
    ("AMC+" / "AMC Plus", "AcornTV" / "Acorn TV") of one service collapse to one
    entry: the direct service if listed, else the first. "The Roku Channel" and
    "Plex Channel" are not resellers. Display only; storage keeps all.
    Checked against a trimmed real response, `tests/fixtures/tmdb/watch-providers-1399.json`.
  - `countries( $data, $types )`: countries that have something to show.
  - `default_country( $data, $types, $detected )`: detected, else US, else first.
  - `payload( $data, $types )`: each provider once plus per-country link and
    provider IDs, for the browser script.
- `LWTV\CPTs\Shows\Watching\Ways_Mode::decide( $force_curated, $has_curated, $has_providers )`
  returns `providers`, `curated` or `none`. The override beats providers; with no
  providers, curated if there are links, else none.

`has_providers` is `default_country() !== ''`: a show whose only data is rent or
buy falls back to the curated links.

## Fetch and refresh (phase 2)

- `_Components\CPTs::get_tmdb_watch_providers( $post_id ): array|false`: same
  guard clauses as `get_tmdb_info()` (needs `TMDB_API` and `lezshows_tmdb_id`),
  `wp_remote_get`, decoded body or `false`. Shows with no TMDB ID are skipped;
  `TMDB_Batch_Task` already backfills IDs on save.
- `Schedulers\Watch_Providers_Task` (Action Scheduler, group `lwtv`, hook
  `lwtv_watch_providers_task`): processes shows whose `…_checked` is missing or
  older than 7 days, oldest first, batches of 30, 0.25 s between requests,
  reschedules until nothing is stale. Own throttle; the limiter in
  `TMDB_Batch_Task` is private and working, so it is not refactored here.
  Registered only when Action Scheduler is available.
- Triggers: first fetch when a show gains or changes its TMDB ID, and a **daily**
  kick from cron (changed from weekly: the kick only schedules, and daily with a
  7-day TTL spreads the requests over the week). A 404 is stored as an empty
  answer; the sweep stops rescheduling when a run gets no answers at all.
  Built and verified on lwtv.local; see [scheduling.md](../architecture/scheduling.md#stale-sweep-watch-providers).
- CLI: `wp lwtv watchproviders refresh [--post=ID] [--all] [--dry-run]` and `status`.
- Docs: add to [scheduling.md](../architecture/scheduling.md) and
  [cron-schedule.md](../operations/cron-schedule.md).

## Rendering (phase 3)

`Theme\Ways_To_Watch::ways_to_watch( $id )` reads `Ways_Mode::decide()` and
branches: `none` returns `''`, `curated` is today's path, `providers` renders the
strip. The template gate already returns early on `''`.

### Markup and accessibility

- `<section aria-labelledby>` with an `h2` "Where to watch" (matching the site's
  heading level for this section), then "powered by TMDB and JustWatch" as plain
  text with both names linked to their sites. The attribution is not inside the
  strip link.
- A visible `<label>` and native `<select>` for the country. The select is
  rendered `hidden` and shown by the script, so with no JavaScript the reader sees
  the default country's list and a country name in the heading.
- The strip is **one** `<a>` to the country's TMDB URL, `target="_blank"`,
  `rel="noopener"`.
  - Logos are `<img alt="" width height loading="lazy">`: decorative, because the
    link carries the name.
  - The link has a visible text part, "See all options on TMDB", so that sighted
    users know where it goes and the accessible name contains the visible label.
  - `aria-label`: "Netflix, Hulu, and Max. See all options on TMDB (opens in a new
    tab)". The provider list is joined with a localised "and".
- Touch target at least 44 px; visible `:focus-visible` ring; hover state is not
  the only indicator that it is a link (the text and an external-link icon).
- Cap the strip at 8 logos with "and N more" in the label and visible text; the
  TMDB page has the full list.
- A polite live region announces a country change: "Showing 4 providers in
  Canada." The link's `aria-label` is updated with the list.
- Country names via `Intl.DisplayNames` in the browser; server-side via
  `\Locale::getDisplayRegion()` when the intl extension exists, else the code.
- Light and dark mode: logos carry their own square background, so the strip needs
  only a border and hover state from `colors.$lwtv-*`. Check contrast of the text
  link in both modes (WCAG AA).
- Logo URL: `https://image.tmdb.org/t/p/w92` + `logo`, base kept as a constant.

### Script (`plugins/lwtv-plugin/assets/js/ways-to-watch-country.js`)

- Lives with the plugin's other front-end scripts (linted by `lint:js:plugin`),
  versioned with the theme version so it busts caches on release. Strings via
  `wp-i18n`. The link label's last item is "2 more" while the visible text is
  "and 2 more": the list formatter adds its own "and".

- Enqueued only when the strip renders; no jQuery.
- Data from `<script type="application/json">` holding `Tmdb_Providers::payload()`.
  Built with `createElement` / `textContent`, never `innerHTML`.
- Country order of preference: a stored choice (`localStorage`, try/catch), else
  the region subtag of `navigator.languages`, else US, else the first available.
  This is a heuristic; a browser cannot know a visitor's country without a geo
  service, so the visible select is the real control.
- If the guessed country has no list, the select falls back to US and the status
  line says "Not listed in your country, showing United States." (so the visitor
  knows why).

## Cleanup (JustWatch removal)

- Delete `class-justwatch.php`, `tests/unit/CPTs/JustWatchTest.php`, and its
  `require_once` in `tests/bootstrap.php`.
- Remove from `Ways_To_Watch`: `JUSTWATCH_*` constants, `justwatch_key()`,
  `render_justwatch()`, theme-sync script and widget enqueue.
- `scss/_layout.scss`: drop `.justwatch*`, add strip styles (rem, `colors.$lwtv-*`,
  light and dark). `npm run buildquick`.
- ACF group `group_lwtv_shows_details.json`: update the override instructions;
  bump `modified`.
- Move `justwatch-widget.md` to `docs/plans/completed/`.
- `JUSTWATCH_API_KEY` can leave `wp-config.php` everywhere.

## Implementation order

1. **Pure logic, tests first.** Done.
2. **Data pipeline.** Meta registration, fetch method, task, CLI. Test with a few
   known shows on staging and compare with themoviedb.org.
3. **Rendering and script.** Strip, selector, accessibility, styles.
4. **Cleanup and docs.** Remove JustWatch code; new
   `docs/integrations/tmdb-watch-providers.md`; update `watch-providers.md`,
   `scheduling.md`, `cron-schedule.md`, `docs/README.md`, `CHANGELOG.md`; credits
   page notice and privacy-policy line (site content, not code).

## Verification

- `vendor/bin/phpunit`, `composer lint`, `npm run lint`.
- Staging, after a refresh:
  - A big network show: strip, correct country, attribution, one link to
    `…/watch?locale=XX`.
  - Click through to a provider from the TMDB page and count the clicks.
  - A web series TMDB does not know: curated links only.
  - Show with rent/buy-only data: curated links.
  - Override on: curated only, no strip, no script.
  - Change country: logos, link and label update; status announced.
  - JavaScript off: default country renders; select is hidden.
  - Screen reader pass (VoiceOver): the strip reads as one link with the provider
    names; the country change is announced.
  - Keyboard only: tab order, focus ring.
  - Light and dark mode; narrow phone width.
  - View source: no `justwatch` script; only `image.tmdb.org` as a new origin.
- `wp lwtv watchproviders status` reports expected counts after a full sweep.
