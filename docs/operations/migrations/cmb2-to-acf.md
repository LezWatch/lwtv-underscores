# CMB2 to ACF Migration

What `wp lwtv migrate acf <subtype>` changes for each field, the order to run the subtypes in, and the one-shot storage migrations that share the command.

Code: `WP_CLI_LWTV_Migrate` ([plugins/lwtv-plugin/php/wp-cli/cli-migrate.php](../../../plugins/lwtv-plugin/php/wp-cli/cli-migrate.php)). Field groups: [plugins/lwtv-plugin/acf-json/](../../../plugins/lwtv-plugin/acf-json/). Storage quirks the migration left behind: [meta-storage-quirks.md](../../architecture/meta-storage-quirks.md).

## Run order

1. **ACF → Sync first.** Sync the field groups in wp-admin before running anything that writes options or term meta, so the field keys the migration writes into `_<name>` reference rows exist. This matters most for `autoposting` (`group_lwtv_auto_posting.json`), `watchtermurls` (`group_lwtv_term_watch_urls.json`) and `debuglogging` (the logging fields are in `group_lwtv_debugging.json`).
2. **Post meta**, in any order: `shownames`, `similarshows`, `airdates`, `charactor`, `chardeath`, `charshowgroup`, `charimages`.
3. **`charimages-to-gallery` after `charimages`.** It converts the repeater that `charimages` produced.
4. **Term and option meta:** `watchtermurls`, `autoposting`, `debuglogging`.
5. **Once, after deploying `Debugger\Findings_Store`:** `debugfindings`.
6. **Whenever a debugger check is retired:** `debugstatus`.

`waystowatch` finished on 2026-08-21 and is now a no-op that only prints success.

Every post-meta migration is re-runnable. It skips a post that already has the ACF reference key (`_<meta_key>`), or, for `charimages-to-gallery`, whose value is no longer a numeric row count.

## Post meta mappings

All ACF repeaters are written as a row count in the parent key, `<key>_<N>_<sub>` value rows, and `_<key>_<N>_<sub>` reference rows holding the field key. See [meta-storage-quirks.md](../../architecture/meta-storage-quirks.md#repeaters).

| Subtype | Meta key | CMB2 shape | ACF shape |
|---|---|---|---|
| `shownames` | `lezshows_show_names` | `array( array( 'lezshows_alt_show_name' => ..., 'type' => ... ) )` | repeater: `_N_lezshows_alt_show_name`, `_N_type` |
| `similarshows` | `lezshows_similar_shows` | serialized array of **string** post IDs | relationship: array of **integer** IDs, plus reference key |
| `charactor` | `lezchars_actor` | flat array of string IDs | relationship: integer IDs, plus reference key |
| `chardeath` | `lezchars_death_year` | flat array of dates (`'2014-10-08'`) | repeater: `_N_date`. Dates are copied verbatim, so these rows stay `Y-m-d` while ACF writes `Ymd`. |
| `charshowgroup` | `lezchars_show_group` | `array( array( 'show' => array( '1607' ) or '1607', 'type', 'appears' ) )` | repeater: `_N_show` (int), `_N_type`, `_N_appears` (array) |
| `charimages` | `lezchars_character_image_group` | `array( array( 'alt_image_text', 'alt_image_file_id', 'alt_image_file' ) )` | repeater: `_N_alt_image_text`, `_N_alt_image_file` (attachment ID). Rows without an attachment ID are dropped. |
| `charimages-to-gallery` | `lezchars_character_image_group` | the repeater above | Gallery: serialized array of attachment IDs |
| `airdates` | `lezshows_airdates` → `lezshows_airdates_start`, `lezshows_airdates_finish` | one serialized array with `start` / `finish` | two separate keys |

Notes:

- **`charshowgroup`.** CMB2 stored `show` as `array( 0 => 'ID' )` in most records and as a plain string in older ones. Both are handled. After migration, `LIKE` meta queries against the serialized `lezchars_show_group` value stop working.
- **`charimages-to-gallery`.** Gallery images have no per-image label, so the repeater's `alt_image_text` ("Crossover", "Flashback") is copied to the attachment's title, but only when the current title looks like a raw filename (contains no spaces). The front-end image tabs read those titles. The repeater rows are deleted.
- **`airdates`.** The `load_value` filters in `Plugins\ACF` bridge the legacy array on the edit screen, but direct `get_post_meta()` reads (on-air checker, calculations) need the separate keys to exist. A key that already has a value is left alone, and the legacy array stays in place and is rewritten on every save.

## Term and option mappings

| Subtype | Where | CMB2 | ACF |
|---|---|---|---|
| `watchtermurls` | `lez_watch_urls` term meta `lezwatchurls_all` | serialized array of URLs | repeater `lezwatchurls_all_N_url` (values run through `esc_url_raw()`; empty ones dropped) |
| | `lezwatchurls_setting_hide_display` | `'on'` | `'1'` |
| `autoposting` | option `lwtv_auto_posting_options` | one serialized array | one option per field: `lwtv_postiz_api_key`, `_api_url`, `_post_type`, `_triggers`; repeater `lwtv_postiz_channels_N_{name,channel_id,active}` |
| | channel `active` | `'on'` | `'1'` / `'0'` |
| `debuglogging` | option `lwtv_debug_logging_options` | one serialized array | `options_debug_mode` (`'on'` → `'1'`, anything else `'0'`), `options_log_topics` (same values) |

ACF options pages store each field as its own `wp_options` row with an `_<name>` reference row. That is why the single CMB2 option is split.

## Debugger findings to options

`wp lwtv migrate acf debugfindings` moves each check's findings out of transients and into `Debugger\Findings_Store`. Run it once, from WP-CLI, after deploying `Findings_Store`. Without it every check reads as "never run" until its next scan, which for `watchurls` means a few hundred HTTP requests to rebuild a report already sitting in the database.

- **Reads the `_transient_<key>` option rows directly**, not through `get_transient()`. On production WP-CLI does not load the object-cache drop-in, so `get_transient()` asks whichever tier the process has (see [caching.md](../../architecture/caching.md#cli-and-web-cache-tiers)). The option rows are what is being migrated, and `get_option()` sees them from either side. A key with no option row (never ran, already migrated, or only ever in the object cache) is skipped.
- **Keeps the remaining lifetime.** An expired row is dropped, not carried. A live row keeps its `_transient_timeout_` expiry rather than getting a fresh ten days, so a stale report is not re-dated as current. A missing timeout row (which would mean "never expires") is treated as corrupt and given a fresh `Findings_Store::TTL`.
- **Idempotent.** `drop_findings_transient()` deletes the transient and both option rows explicitly, because `delete_transient()` under a persistent object cache leaves the option rows behind and a second run would overwrite the migrated findings with the old snapshot.
- **Keys come from class constants** (`findings_keys()`), not literals, so a renamed key cannot leave the migration pointing at a string nothing writes.

## Retired debugger checks

`wp lwtv migrate acf debugstatus` removes the status entries listed in `WP_CLI_LWTV_Migrate::RETIRED_STATUS_KEYS` (currently `show_url`, whose check was replaced by `wp lwtv debug watchurls`). See [validation-screen.md](../../architecture/validation-screen.md#retired-checks).
