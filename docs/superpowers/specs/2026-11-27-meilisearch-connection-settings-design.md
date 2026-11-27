# Meilisearch connection settings: design

Status: approved by Mika (2026-11-27). Next: the implementation plan. Issue: [#749](https://github.com/LezWatch/lwtv-underscores/issues/749). Builds on the Search Settings page (`2026-11-26-meilisearch-settings-page-design.md`).

## Why

So a new environment works without Meilisearch lines in `wp-config.php` beyond the two keys (Mika, 2026-11-27). The defaults do most of the work; the fields are for exceptions.

## Goals

- A **Connection Settings** section, the top section of Search → Settings → General:
  - **Meilisearch URL** `[text field]`
  - **Index prefix** `[text field]`
- Each field is greyed out while its define (`LWTV_MEILI_URL`, `LWTV_MEILI_INDEX_PREFIX`) exists, the same way the switch is greyed out by `LWTV_MEILI_MODAL`.
- Defaults when there's neither a define nor a saved value:
  - **URL:** `https://search.` + the site's host, so lwtv.local gets `https://search.lwtv.local`.
  - **Prefix:** `wp_get_environment_type()`: `local`, `development`, `staging` or `production`.

## Non-goals

- **The keys stay in `wp-config.php`.** `LWTV_MEILI_INDEX_KEY` and `LWTV_MEILI_SEARCH_KEY` are not on the page and not in the database. That holds until WordPress has a secrets API (Mika, 2026-11-27).
- Moving or copying indexes when the prefix changes. Use Set up or Reindex on the Status tab.

## Anti-goals

- **A copied database carrying another environment's connection.** A staging copy of production must not keep pointing at production's URL or prefix just because production saved them.
- **A key reaching the page, a REST response or a log.**

## Approaches

1. **Both fields in the existing `lwtv_meili_settings` option. Define, then saved value, then default; only changed values are saved. Chosen** (Mika, 2026-11-27).
2. A separate option. Rejected: more moving parts and nothing gained.
3. Defaults only, shown read-only. Rejected: Mika asked for fields for the exceptions.

## Decided behavior

- **Where the values come from:** the define wins. If there's no define, a saved value is used. If neither, the default. `Config` builds its URL and prefix this way for everything that uses it: the modal, the results page, post syncs, FacetWP and WP-CLI.
- **Only overrides are saved.** An empty field means "use the default", and its placeholder shows the default in grey. Saving a value equal to the default saves nothing. That's what keeps copied databases safe: with nothing saved, each copy works out its own defaults.
- **Required settings:** only the two keys. `Config::missing()` and the admin notice stop listing `LWTV_MEILI_URL`, because there's always a default.
- **An existing `LWTV_MEILI_INDEX_PREFIX` define set to `''`** still means no prefix (bare index names), as it does today. Only the page's empty field means "default".
- **On save:** the page calls `/health` at the URL that's now in effect and reports it. If the server doesn't answer, the value is still saved, with a warning. The Status tab then shows whether that prefix's indexes exist, offering **Set up** if they don't.
- **Section order on General:** Connection Settings, then Who gets Meilisearch search, then Fallback. Each section is a row with its heading beside its controls, as the other sections already are.

## Calls I made (correct any)

- **Default URL:** `https://`, the host from `home_url()`, a leading `www.` removed, no port and no path.
- **`development` stays `development`:** no mapping to another prefix.
- **URL validation:** `http` or `https` with a host, and no query or fragment. A trailing `/` is dropped.
- **Prefix validation:** lowercase letters, digits, `-` and `_` only, 1 to 40 characters, with a trailing `_` dropped. Meilisearch index names allow only those characters.
- **No connection change while a background job is running** on any index. Saving the connection fields is refused with "Wait for the running job to finish". A reindex shouldn't switch servers halfway through. The switch and timeout can still be saved.
- **Status tab:** it shows the host and prefix in use, with "(default)", "(saved)" or "(wp-config.php)" next to each.
- **Pure logic goes in `build/`** (defaults, validation, picking the source) with PHPUnit tests written first, following the existing `Search_Settings` pattern.

## Verification

- Unit tests for the pure resolver and validation.
- `composer lint`, PHPStan, `npm run lint:js` and `npm run lint:css`, plus the workspace build.
- On lwtv.local, with the defines in place:
  - Both fields are greyed out and show the define's value.
  - Search and syncing are unchanged.
- On lwtv.local, with the defines removed from a copy of the config, using a throwaway check:
  - The defaults show `https://search.lwtv.local` and `local`.
  - A save reports the health check result.
  - A bad URL saves with a warning.
  - A bad prefix is refused.
- No key in any REST response.
