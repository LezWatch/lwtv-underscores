# Meta Storage Quirks

The ways raw post and term meta differ from what the ACF field definitions suggest. Read this before writing a meta query or a check that compares stored values.

Most of the variation comes from the CMB2 → ACF migration (see [cmb2-to-acf.md](../operations/migrations/cmb2-to-acf.md)): some fields were rewritten, some were bridged at load time, and some old rows were never re-saved.

## Booleans

Booleans are not stored the same way everywhere.

| Field | Ticked | Unticked | Match on |
|---|---|---|---|
| Plain ACF `true_false` (most fields, and `lezwatchurls_setting_*` term meta) | `'1'` | a `'0'` row is **kept** | the value `'1'` |
| `lezshows_byq_override`, `lezshows_worthit_show_we_love` | `'on'` | the row is **deleted** | the value `'on'` |
| `lezactors_queer_override` (a select, not a boolean) | any real choice | the literal default `'undefined'` | any value not in `Exclusion_Registry::UNSET_VALUES` |
| Legacy CMB2 checkboxes not yet re-saved | `'on'` | usually no row | depends on the field; see below |

- **Plain `true_false`.** ACF writes a row for every post ever saved, so `EXISTS` on one of these matches the whole catalogue. Always compare the value. Raw meta is `'1'`/`'0'`, never a real boolean (`Watch_Hosts::name_confirmed()` compares `'1' === (string) ...`).
- **The two `'on'` show fields.** `Plugins\ACF::SHOW_LEGACY_ON_FIELDS` lists them. `ACF::save_show_legacy_meta()` (on `acf/save_post`, priority 20) rewrites them to `'on'` when ticked and deletes the row when not, because SQL elsewhere hardcodes `= 'on'`. `ACF::load_legacy_on_as_bool()` turns `'on'` back into `1` so the ACF checkbox shows as ticked.
- **Selects with a default.** ACF writes the select's default (`'undefined'`) for every saved post, so `EXISTS` again returns everything. Query `NOT IN` the unset values, as `Admin_Menu\Exclusions` does.

Getting this wrong does not error. It quietly counts the whole catalogue as matching.

### Where this is encoded

`Admin_Menu\Build\Exclusion_Registry` ([class-exclusion-registry.php](../../plugins/lwtv-plugin/php/admin-menu/build/class-exclusion-registry.php)) is the pure half of the Exclusion Checker. Every check definition has a required `match` value, so a caller cannot forget which storage form a field uses:

- A literal (`'1'`, `'on'`): the stored value must equal it.
- `Exclusion_Registry::MATCH_ANY` (`''`): any value outside `UNSET_VALUES` (`''`, `'0'`, `'undefined'`).

`qualifies()` trims the stored value and re-checks `UNSET_VALUES` in PHP even though the SQL already filters them. A value of `'   '` passes a SQL `NOT IN` but fails after `trim()`, so the two checks complement each other.

`Admin_Menu\Exclusions` queries directly rather than through `Queeries\Post_Meta`, whose cached results can be up to 30 minutes stale (see [caching.md](caching.md#why-post_meta_-is-not-tracked)).

## Show airdates

Airdates exist in two shapes:

- **Current:** separate keys `lezshows_airdates_start` and `lezshows_airdates_finish` (ACF fields).
- **Legacy:** one serialized array `lezshows_airdates` with `start` and `finish` (the CMB2 shape).

Both are kept in sync, and code reads either:

- `ACF::save_show_legacy_meta()` rewrites the legacy `lezshows_airdates` array from the two ACF fields on every save. Several statistics queries (`On_Air_Optimized`, `Scores`, the genre, format and intersection trends) still join on `lezshows_airdates`.
- `ACF::load_airdate_start()` / `load_airdate_finish()` fill empty ACF fields from the legacy array when the edit screen loads.
- `CPTs\Shows\Airdates::get()` reads the separate keys and falls back to the legacy array only when one is missing. Use it for per-show reads.
- `wp lwtv migrate acf airdates` backfilled the separate keys for shows that only had the legacy array.

A show still airing has `finish` set to the "current" marker (`Airdates::is_still_airing()`), not a year.

## Dates

ACF's `date_picker` **stores raw postmeta as `Ymd`** (for example `19760525`), whatever its `display_format` and `return_format` say. All three date pickers (`lezactors_birth`, `lezactors_death`, and the `date` sub-field of the character repeater `lezchars_death_year`) declare `Y-m-d` for display and return, but that applies only to values read through `get_field()`.

Rows migrated from CMB2 and never re-saved are still `Y-m-d`: `wp lwtv migrate acf chardeath` copied each date string verbatim into `lezchars_death_year_<N>_date`.

So any code that reads raw meta, sorts dates, or matches them in SQL must accept both forms:

- SQL: make the separators optional. `Rest_API\BYQ` matches death dates with `^YYYY-?MM-?DD$`.
- PHP: normalise before comparing or keying. Keying on the raw value makes `20160519` and `2016-05-19` two different dates.

Several statistics, This Year and debugger classes carry their own normalisation for this. Search for `Ymd` before adding another.

## Repeaters

An ACF repeater stores a **row count** in its parent key and each value in an indexed sub-key with a reference key beside it:

```
lezchars_show_group             = 3
_lezchars_show_group            = field_lwtv_lezchars_show_group
lezchars_show_group_0_show      = 1607
_lezchars_show_group_0_show     = field_lwtv_lezchars_show_group_show
...
```

- Query the sub-keys (`lezchars_death_year_%_date`), not the parent. Escape the literal underscores with `$wpdb->esc_like()` and bind the pattern, since `_` is a single-character `LIKE` wildcard.
- The row-count meta may not match the rows actually present. `Watch_Hosts::term_url_rows()` reads a few indexes past the claimed count to find stray rows, and `Watch_Hosts::set_term_urls()` rewrites rows contiguously and resets the count.
- `LIKE` queries against the old serialized CMB2 value of `lezchars_show_group` stopped working once it became a repeater.
