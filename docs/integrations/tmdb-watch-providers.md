# TMDB Watch Providers

Where a show can be streamed, from TMDB's `/tv/{series_id}/watch/providers`, and how it becomes the provider strip in a show's "Ways to Watch" section. The data is JustWatch's, supplied through TMDB's partnership.

Code: `plugins/lwtv-plugin/php/_helpers/class-tmdb-providers.php` (`LWTV\_Helpers\Tmdb_Providers`, pure), `plugins/lwtv-plugin/php/cpts/shows/watching/class-ways-mode.php` (`LWTV\CPTs\Shows\Watching\Ways_Mode`, pure), `plugins/lwtv-plugin/php/schedulers/class-watch-providers-task.php` (`LWTV\Schedulers\Watch_Providers_Task`), `plugins/lwtv-plugin/php/theme/class-ways-to-watch.php` (`LWTV\Theme\Ways_To_Watch`), `plugins/lwtv-plugin/assets/js/ways-to-watch-country.js`, `plugins/lwtv-plugin/php/wp-cli/cli-watchproviders.php` (`wp lwtv watchproviders`).

## Terms of use

- **Attribution is mandatory.** JustWatch must be named as the source or TMDB revokes access. The strip ends with "Powered by TMDB and JustWatch", one link to the Services section of [Resources](https://lezwatchtv.com/about/resources/#services). That section carries TMDB's approved logo and its required notice, "This product uses the TMDB API but is not endorsed or certified by TMDB."; TMDB wants that attribution on an About or Credits page, not beside the data.
- The key is the existing `TMDB_API`. It must be registered with TMDB for non-commercial use.
- Logos load from `image.tmdb.org`, a third-party origin. The privacy policy should say so.

## What TMDB gives us

Per country: provider ID, name, logo path and display priority, grouped by availability type (`flatrate`, `free`, `ads`, `rent`, `buy`), plus **one** TMDB link (`https://www.themoviedb.org/tv/1399-game-of-thrones/watch?locale=US`). There are no links to the providers themselves; that TMDB page is where they are.

Coverage is JustWatch's. Big network shows are well covered. Web series and small international shows are often missing, which is what the curated links are for.

## Meta keys

| Key | Content |
|---|---|
| `lezshows_watch_providers` | JSON from `Tmdb_Providers::normalise()`: `{ "US": { "link": "…", "flatrate": [ {id, name, logo} ], "free": […], "ads": […], "rent": […], "buy": […] }, … }`. `{}` when TMDB had nothing. |
| `lezshows_watch_providers_checked` | Unix time of the last answer, empty answers included. |

Both are registered in `CPTs\Post_Meta` with `show_in_rest => false`. A show page never makes a request: it reads these.

The JSON is written with `wp_slash()`. `update_post_meta()` unslashes, which would otherwise eat the backslashes in JSON escapes (`\/`, `ó`).

## Normalising

`Tmdb_Providers::normalise()` treats the response as untrusted, because it ends up in post meta and in an `href`:

- Keeps only `{id, name, logo}` per provider, ordered by `display_priority`, names trimmed.
- Drops malformed country codes and providers with no ID or name. A logo path that is not one segment with an image extension becomes `''` (the strip then shows the name instead).
- Drops a country whose `link` is not an https `themoviedb.org` URL.

All five types are stored. Only `flatrate`, `free` and `ads` (`Tmdb_Providers::DISPLAY_TYPES`) are shown.

## Duplicate services

TMDB lists a service once directly and again for each storefront reselling it, ranks the reseller first, and spells brands more than one way. On a strip of logos that reads as one service shown several times. `for_country()` collapses them, at display time only, so changing the rules needs no refetch:

1. **Tiers and packages of a listed service go.** A name that is a listed name plus a space and more is dropped: "Netflix Standard with Ads", "Paramount Plus Premium", "Peacock Premium Plus" under "Peacock Premium". `+` is read as "plus" first, so "AMC Plus Apple TV channel" falls under "AMC+". A whole-word prefix, so "maxdome Store" is not a tier of "Max".
2. **Resellers and spellings of one service become one entry.** The key is the name with one reseller suffix removed (`Tmdb_Providers::RESELLER_PATTERN`: " Amazon Channel", " Apple TV channel", " Roku Premium Channel", any case), `+` as "plus", letters and digits only. So "AMC+ Amazon Channel", "AMC Plus Apple TV channel" and "AMC+" match, as do "AcornTV" and "Acorn TV". The direct service wins when it is listed, else the first (TMDB's priority).

Only those three suffixes count as resellers. "The Roku Channel" and "Plex Channel" are services of their own. "HBO Max on U-Next" (Japan) is left alone: it is often the only entry there.

Checked against every country of 64 real shows: each removal was a reseller, tier or package of a service still shown. The closest call is "PBS Masterpiece Amazon Channel", which goes when "PBS" is listed.

## What a show page renders

`Ways_Mode::decide( $force_curated, $has_curated, $has_providers )`:

| Override (`lezshows_waystowatch_curated`) | TMDB has something to show | Curated links | Renders |
|---|---|---|---|
| off | yes | any | provider strip |
| off | no | yes | curated links |
| on | any | yes | curated links |
| any | any | no, and no strip | nothing |

"Something to show" means at least one country with a `DISPLAY_TYPES` provider. A show whose only data is rent or buy gets its curated links. The curated links are never shown next to the strip.

### The strip

- **The country select is in the `h2`**: "Where to watch in [Canada ▾]". It has `aria-label="Country"` and no `<label>`, so the heading's accessible name is "Where to watch in Canada" (a control inside a heading contributes its value). Without JavaScript the select stays hidden and a static "in Canada" shows.
- Logos are grouped by `Tmdb_Providers::GROUPS`: **Stream** (`flatrate`) and **Free** (`free` and `ads`). A service in both is drawn under Stream. Each group is a `<ul>` labelled by its visible label, and empty groups are not drawn. `group_ids()` does the sorting, so the server and the script agree.
- Logos are not links, and each carries its service's name as `alt`. No `title`: some screen readers read it as well as the alt.
- **One link**, "See all options on TMDB (opens in a new tab)", in the footer next to the credit. Every logo would go to the same URL, and several identical links in a row are noise for screen reader and keyboard users.
- At most `Ways_To_Watch::LOGO_CAP` (8) logos, shared across the groups in order. TMDB's page has the full list.
- The section is labelled by its `h2` (`aria-labelledby="ways-to-watch-title"`; the curated label carries the same id).
- Rendered for the default country, US or else the first, never from the request, so the page stays cacheable.

### Country switcher

The select and its label are rendered `hidden`. Without JavaScript the reader gets the default country's list and "Where to watch in United States".

The script shows the select and hides the "in …" text. It picks a country in this order: the reader's earlier choice (`localStorage`, `lwtv-watch-country`, wrapped in try/catch), the region subtags of `navigator.languages`, then the server's default. A browser cannot know where its reader is, which is why the select is visible.

If the reader's regions have no list, the strip stays on the default and the status line says why, visibly: "Not listed in your country, showing Chile." A change of country redraws the strip with `createElement` and `textContent` (names come from a third party) and announces "Showing 2 providers in Canada." through the `role="status"` line. Country names come from `Intl.DisplayNames`, and on the server from `\Locale::getDisplayRegion()` when intl is loaded.

Strings use `wp-i18n` (`wp_set_script_translations()`, domain `lwtv-underscores`).

### Colours

Contrast was measured, not assumed. The section's existing pink-on-grey links are 3.42:1, under AA, so the strip does not use them.

| | Text | Border | Focus ring |
|---|---|---|---|
| Light: `$lwtv-pink-deep` on `$lwtv-grey-light` | 6.37:1 | `$lwtv-grey-muted`, 5.17:1 | `$lwtv-pink-deep`, 5.25:1 |
| Dark: `$lwtv-pink-light` on `colors.$lwtv-grey-deep` | 8.77:1 | `colors.$lwtv-grey-medium`, 4.43:1 | `$lwtv-pink-medium`, 4.42:1 |

Dark mode borrows the light palette's grey border: the dark palette's own is 1.65:1. Links on this site transition their background over 0.3 s, so read computed colours after a theme change has settled.

## Refresh

`Watch_Providers_Task` keeps the data fresh on Action Scheduler, a week at a time, with no queue: due shows are read from meta each run. Batches, pacing, the retry ceiling and the triggers are in [scheduling.md](../architecture/scheduling.md#stale-sweep-watch-providers).

`CPTs::get_tmdb_watch_providers()` returns the decoded body, `array()` for a 404 (TMDB does not know the ID, which is an answer), or `false` for a transport error or any other status (not an answer; the show stays due).

## Commands

```bash
wp lwtv watchproviders status                 # fresh, stale and never-checked counts
wp lwtv watchproviders status --post=1234     # what one show has stored
wp lwtv watchproviders refresh --post=1234    # fetch one show now
wp lwtv watchproviders refresh --limit=100    # the next 100 due shows
wp lwtv watchproviders refresh --dry-run      # which shows would be fetched; no requests
```

`refresh` runs in the CLI process, not on Action Scheduler, with the task's pause between requests. `--all` ignores the 7-day TTL.

## Background

This replaced a planned JustWatch Partner Widget, which never went live because JustWatch never issued a widget key. See [the widget plan](../plans/completed/justwatch-widget.md) and [the TMDB plan](../plans/tmdb-watch-providers.md).
