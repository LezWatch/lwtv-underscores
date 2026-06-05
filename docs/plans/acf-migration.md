# ACF Pro Migration Plan

Migrate all custom field definitions from CMB2 to ACF Pro. CMB2 is no longer actively maintained; ACF Pro is already licensed and in use on other properties.

**Status:** Planning
**Risk Level:** High — `lezchars_show_group` is load-bearing for all character↔show calculations, statistics, REST API, and WP-CLI commands.

---

## Background

CMB2 is used to define all custom metaboxes across three primary CPTs: Shows (`post_type_shows`), Characters (`post_type_characters`), and Actors (`post_type_actors`), plus a small TVMaze CPT. Four bundled CMB2 extension plugins support layout, Select2 enhancement, post relationships, and FacetWP integration.

ACF Pro supports JSON-based field group sync, which will replace the three `class-cmb2-metaboxes.php` files as the source of truth for field definitions.

---

## Key Principle: Meta Keys Stay the Same

Simple fields (`text`, `select`, `checkbox`, `textarea`, `radio`, URL, etc.) store data directly in `wp_postmeta` under their field IDs. ACF can target the **same meta keys** — no data changes needed for these. The majority of fields fall into this category.

---

## Field Type Mapping Reference

| CMB2 Type | ACF Equivalent | Data Migration? |
|---|---|---|
| `text`, `text_small`, `text_url` | Text / URL | No |
| `textarea`, `textarea_small` | Textarea | No |
| `select` | Select | No |
| `radio_inline` | Radio Button | No |
| `checkbox` | True / False | No |
| `multicheck` / `multicheck_inline` | Checkbox | No |
| `wysiwyg` | WYSIWYG Editor | No |
| `file` | File | No |
| `text_date` (single) | Date Picker | Format differs — CMB2 uses `m/d/Y`, ACF uses `Ymd`. Verify stored values. |
| `taxonomy_select` | Taxonomy (single, save to taxonomy) | No — both use `wp_set_object_terms` |
| `taxonomy_multicheck_inline` | Taxonomy (multiple, save to taxonomy) | No — same as above |
| `pw_multiselect` / `pw_select` | Taxonomy (save term IDs to post meta) | Verify serialized format matches before go-live |
| `custom_attached_posts` | Relationship | Verify — both save serialized post ID arrays, but confirm |
| `group` (repeatable) | Repeater | **Yes — format incompatible** |
| `text_url` (repeatable) | Repeater > URL sub-field | **Yes — format incompatible** |
| `text_date` (repeatable) | Repeater > Date sub-field | **Yes — format incompatible** |
| `date_year_range` | Two Number fields (`_start`, `_finish`) | No — already stored as two separate meta keys |

---

## Fields Requiring Data Migration

CMB2 groups/repeatable fields store data as a serialized PHP array in a single meta row. ACF Repeaters store each row as individual meta rows using a `fieldname_N_subfield` naming pattern. A WP-CLI migration script is required for each of these.

| Field | CPT | Risk | Used In |
|---|---|---|---|
| `lezchars_show_group` | Characters | **Critical** | Calculations, stats, REST API, WP-CLI, FacetWP |
| `lezchars_actor` | Characters | High | Character↔actor linking, calculations |
| `lezchars_death_year` | Characters | High | Dead character stats, REST API |
| `lezchars_character_image_group` | Characters | Medium | Display only |
| `lezshows_waystowatch` | Shows | Medium | Ways to Watch display |
| `lezshows_show_names` | Shows | Medium | Display, search |
| `lezshows_similar_shows` | Shows | Medium | Shows Like This feature |
| `leztvmaze_our_show` | TVMaze | Low | Calendar/TVMaze sync |

---

## Bundled Plugins to Remove

After migration is complete, these four plugins can be deleted:

| Plugin | Path | Replacement |
|---|---|---|
| CMB2 Grid | `plugins/cmb2-grid/` | ACF handles layout natively |
| CMB2 Field Select2 | `plugins/cmb-field-select2/` | ACF uses Select2 natively |
| CMB2 Attached Posts | `plugins/cmb2-attached-posts/` | ACF Relationship field |
| FacetWP CMB2 | `plugins/facetwp-cmb2/` | FacetWP has native ACF support — no replacement needed |

---

## Phase 1 — ACF Setup & JSON Configuration

**Files:** new `plugins/lwtv-plugin/php/plugins/class-acf.php`, new `plugins/lwtv-plugin/acf-json/` directory

1. Enable ACF Pro in the plugin.
2. Add JSON sync configuration so field groups are saved/loaded as JSON files:

```php
add_filter( 'acf/settings/save_json', function() {
    return plugin_dir_path( __FILE__ ) . '../acf-json';
} );
add_filter( 'acf/settings/load_json', function( $paths ) {
    $paths[] = plugin_dir_path( __FILE__ ) . '../acf-json';
    return $paths;
} );
```

3. Create `plugins/lwtv-plugin/acf-json/` and add it to git.
4. Confirm ACF Pro license is active on the site.

---

## Phase 2 — Build ACF Field Groups

Build field groups in ACF UI, export JSON, commit. Proceed one CPT at a time in this order (lowest relationship risk first).

For every field, explicitly set the **Field Name** (ACF's meta key) to match the existing CMB2 field ID so stored data is compatible.

For every field group, enable **Show in REST API**.

### 2a — Actors

Source: `plugins/lwtv-plugin/php/cpts/actors/class-cmb2-metaboxes.php`

**Field Group: Actor Details**
Location: Post Type = `post_type_actors`

| CMB2 Field ID | ACF Field Name | ACF Type | Notes |
|---|---|---|---|
| `lezactors_gender` | `lezactors_gender` | Taxonomy | `lez_actor_gender`, single, save to taxonomy |
| `lezactors_sexuality` | `lezactors_sexuality` | Taxonomy | `lez_actor_sexuality`, single, save to taxonomy |
| `lezactors_romantic` | `lezactors_romantic` | Taxonomy | `lez_actor_romantic`, single, save to taxonomy |
| `lezactors_queer_override` | `lezactors_queer_override` | Select | Options: `undefined`, `is_queer`, `not_queer` |
| `lezactors_pronouns` | `lezactors_pronouns` | Taxonomy | `lez_actor_pronouns`, multiple, save to taxonomy |

**Field Group: Actor Life Data**

| CMB2 Field ID | ACF Field Name | ACF Type | Notes |
|---|---|---|---|
| `lezactors_birth` | `lezactors_birth` | Date Picker | Verify stored date format |
| `lezactors_death` | `lezactors_death` | Date Picker | Verify stored date format |

**Field Group: Actor Social Media / Links**

| CMB2 Field ID | ACF Field Name | ACF Type |
|---|---|---|
| `lezactors_imdb` | `lezactors_imdb` | Text |
| `lezactors_wikipedia` | `lezactors_wikipedia` | URL |
| `lezactors_homepage` | `lezactors_homepage` | URL |
| `lezactors_twitter` | `lezactors_twitter` | Text |
| `lezactors_bluesky` | `lezactors_bluesky` | Text |
| `lezactors_instagram` | `lezactors_instagram` | Text |
| `lezactors_has_threads` | `lezactors_has_threads` | True / False |
| `lezactors_tumblr` | `lezactors_tumblr` | Text |
| `lezactors_mastodon` | `lezactors_mastodon` | Text |
| `lezactors_facebook` | `lezactors_facebook` | Text |
| `lezactors_tiktok` | `lezactors_tiktok` | Text |
| `lezactors_youtube` | `lezactors_youtube` | Text |
| `lezactors_twitch` | `lezactors_twitch` | Text |

**Field Group: Actor Admin / Misc**

| CMB2 Field ID | ACF Field Name | ACF Type | Notes |
|---|---|---|---|
| `lezactors_wikidata_qid` | `lezactors_wikidata_qid` | Text | |
| `excerpt` | `excerpt` | Textarea | Maps to `post_excerpt` |
| `lezactors_make_option_private` | `lezactors_make_option_private` | Checkbox | Options: `hide_all`, `hide_dob`, `hide_socials` |
| `lezactors_make_option_private_notes` | `lezactors_make_option_private_notes` | Textarea | |

### 2b — Shows

Source: `plugins/lwtv-plugin/php/cpts/shows/class-cmb2-metaboxes.php`

**Field Group: Show Summary**

| CMB2 Field ID | ACF Field Name | ACF Type |
|---|---|---|
| `excerpt` | `excerpt` | Textarea |

**Field Group: Show Details**

| CMB2 Field ID | ACF Field Name | ACF Type | Notes |
|---|---|---|---|
| `lezshows_airdates` | *(two fields)* | — | See below — stored as `lezshows_airdates_start` + `lezshows_airdates_finish` |
| `lezshows_airdates_start` | `lezshows_airdates_start` | Number | Year only |
| `lezshows_airdates_finish` | `lezshows_airdates_finish` | Number | Year only; allow blank for ongoing shows |
| `lezshows_seasons` | `lezshows_seasons` | Number | |
| `lezshows_tvstations` | `lezshows_tvstations` | Taxonomy | `lez_stations`, multiple, save term IDs to post meta |
| `lezshows_tvnations` | `lezshows_tvnations` | Taxonomy | `lez_country`, multiple, save term IDs to post meta |
| `lezshows_tvtype` | `lezshows_tvtype` | Taxonomy | `lez_formats`, single, save to taxonomy |
| `lezshows_imdb` | `lezshows_imdb` | Text | |
| `lezshows_tvgenre` | `lezshows_tvgenre` | Taxonomy | `lez_genres`, multiple, save term IDs to post meta |
| `lezshows_tvgenre_primary` | `lezshows_tvgenre_primary` | Select | Dynamic options from `lez_genres` — populate via `acf/load_field` filter; verify JS targeting ACF DOM, not CMB2 DOM |
| `lezshows_stars` | `lezshows_stars` | Taxonomy | `lez_stars`, single, save to taxonomy |
| `lezshows_triggerwarning` | `lezshows_triggerwarning` | Taxonomy | `lez_triggers`, single, save to taxonomy |
| `lezshows_intersectional` | `lezshows_intersectional` | Taxonomy | `lez_intersections`, multiple, save term IDs to post meta |
| `lezshows_tropes` | `lezshows_tropes` | Taxonomy | `lez_tropes`, multiple, save term IDs to post meta |

**Field Group: Worth Watching**

| CMB2 Field ID | ACF Field Name | ACF Type | Notes |
|---|---|---|---|
| `lezshows_worthit_rating` | `lezshows_worthit_rating` | Select | Options: `Yes`, `Meh`, `No`, `TBD` |
| `lezshows_worthit_details` | `lezshows_worthit_details` | Textarea | |

**Field Group: Editorial (Admin Only)**
Location condition: User Role = Administrator

| CMB2 Field ID | ACF Field Name | ACF Type |
|---|---|---|
| `lezshows_worthit_show_we_love` | `lezshows_worthit_show_we_love` | True / False |
| `lezshows_byq_override` | `lezshows_byq_override` | True / False |

**Field Group: Ways to Watch**
`lezshows_waystowatch` → Repeater field. **Requires data migration.**

Sub-field:
- `url` → URL field (meta key: `url`)

`lezshows_similar_shows` → Relationship field (post type: `post_type_shows`). **Verify format; may need migration.**

`lezshows_show_names` → Repeater field. **Requires data migration.**

Sub-fields:
- `lezshows_alt_show_name` → Text
- `type` → Select (language)

**Field Group: Plots & Relationships**

| CMB2 Field ID | ACF Field Name | ACF Type |
|---|---|---|
| `lezshows_ships` | `lezshows_ships` | Text |
| `lezshows_plots` | `lezshows_plots` | WYSIWYG Editor |
| `lezshows_episodes` | `lezshows_episodes` | WYSIWYG Editor |

**Field Group: Ratings**

| CMB2 Field ID | ACF Field Name | ACF Type | Notes |
|---|---|---|---|
| `lezshows_realness_rating` | `lezshows_realness_rating` | Radio Button | Options: 0–5 |
| `lezshows_realness_details` | `lezshows_realness_details` | Textarea | |
| `lezshows_quality_rating` | `lezshows_quality_rating` | Radio Button | Options: 0–5 |
| `lezshows_quality_details` | `lezshows_quality_details` | Textarea | |
| `lezshows_screentime_rating` | `lezshows_screentime_rating` | Radio Button | Options: 0–5 |
| `lezshows_screentime_details` | `lezshows_screentime_details` | Textarea | |

### 2c — Characters (highest risk — do last)

Source: `plugins/lwtv-plugin/php/cpts/characters/class-cmb2-metaboxes.php`

**Field Group: Sexuality & Orientation**

| CMB2 Field ID | ACF Field Name | ACF Type | Notes |
|---|---|---|---|
| `lezchars_gender` | `lezchars_gender` | Taxonomy | `lez_gender`, single, save to taxonomy |
| `lezchars_sexuality` | `lezchars_sexuality` | Taxonomy | `lez_sexuality`, single, save to taxonomy |
| `lezchars_romantic` | `lezchars_romantic` | Taxonomy | `lez_romantic`, single, save to taxonomy |

**Field Group: Character Details**

| CMB2 Field ID | ACF Field Name | ACF Type | Notes |
|---|---|---|---|
| `lezchars_cliches` | `lezchars_cliches` | Taxonomy | `lez_cliches`, multiple, save term IDs to post meta |
| `lezchars_death_year` | `lezchars_death_year` | Repeater | **Requires data migration.** Sub-field: Date Picker |
| `lezchars_actor` | `lezchars_actor` | Relationship | Post type: `post_type_actors`. **Verify format.** |
| `lezchars_character_image_group` | `lezchars_character_image_group` | Repeater | **Requires data migration.** Sub-fields: Text (`alt_image_text`), File (`alt_image_file`) |

**Field Group: Show Appearances** ← Most critical field in the database

`lezchars_show_group` → Repeater. **Requires data migration.**

Sub-fields:
- `show` → Relationship (post type: `post_type_shows`, max 1)
- `type` → Select (options: `regular`, `recurring`, `guest`)
- `appears` → Checkbox / multi-select (dynamic year range)

### 2d — TVMaze

**Field Group: TVMaze Show Link**
Location: Post Type = `post_type_tvmaze`

| CMB2 Field ID | ACF Field Name | ACF Type | Notes |
|---|---|---|---|
| `leztvmaze_our_show` | `leztvmaze_our_show` | Relationship | Post type: `post_type_shows`, max 1. **Verify format.** |

---

## Phase 3 — Data Migration Scripts (WP-CLI)

Write one WP-CLI subcommand per group/repeater field. Run on staging first. Back up the database before running on production.

**Batch size:** Process in chunks of 500 with a short pause between batches to avoid PHP execution timeouts. 3,000–6,000 posts is manageable but worth being careful with.

```php
$posts = get_posts( [
    'post_type'      => 'post_type_characters',
    'posts_per_page' => 500,
    'paged'          => $batch,
    'fields'         => 'ids',
] );
// ...after each batch:
sleep( 1 );
```

**General pattern for CMB2 group → ACF Repeater:**

```php
// Example: lezchars_show_group
$posts = get_posts( [ 'post_type' => 'post_type_characters', 'numberposts' => -1, 'fields' => 'ids' ] );
foreach ( $posts as $post_id ) {
    $rows = get_post_meta( $post_id, 'lezchars_show_group', true );
    if ( empty( $rows ) || ! is_array( $rows ) ) {
        continue;
    }

    // Delete old CMB2 meta
    delete_post_meta( $post_id, 'lezchars_show_group' );

    // Write ACF Repeater format
    update_post_meta( $post_id, 'lezchars_show_group', count( $rows ) );
    foreach ( $rows as $i => $row ) {
        update_post_meta( $post_id, "lezchars_show_group_{$i}_show",    $row['show'] );
        update_post_meta( $post_id, "lezchars_show_group_{$i}_type",    $row['type'] );
        update_post_meta( $post_id, "lezchars_show_group_{$i}_appears", $row['appears'] );

        // Write ACF reference keys so admin UI pre-populates without requiring a resave.
        // Replace field_XXXX values with actual ACF field keys from the exported JSON.
        update_post_meta( $post_id, '_lezchars_show_group',              'field_XXXX' );
        update_post_meta( $post_id, "_lezchars_show_group_{$i}_show",    'field_YYYY' );
        update_post_meta( $post_id, "_lezchars_show_group_{$i}_type",    'field_ZZZZ' );
        update_post_meta( $post_id, "_lezchars_show_group_{$i}_appears", 'field_AAAA' );
    }
}
```

> **Why reference keys matter:** Without `_fieldname` reference rows, `get_field()` still works but ACF's admin UI shows blank fields on existing posts until each post is manually re-saved. With 3,000–6,000 posts and non-developer editors on the team, the blank-fields experience will read as data loss. Write these rows in the migration scripts. The field key values come from the exported ACF JSON files after Phase 2 is complete.

### Date format conversion

CMB2 stores dates as `m/d/Y`; ACF expects `Ymd`. This must be converted explicitly in the migration scripts — ACF will silently display nothing for unconverted values.

```php
function convert_cmb2_date_to_acf( string $value ): string {
    if ( empty( $value ) ) {
        return $value;
    }
    $dt = DateTime::createFromFormat( 'm/d/Y', $value );
    if ( ! $dt ) {
        // Log unparseable values for manual review rather than silently dropping them.
        error_log( "ACF migration: could not parse date value '{$value}'" );
        return $value;
    }
    return $dt->format( 'Ymd' );
}
```

Apply to: `lezactors_birth`, `lezactors_death`, and each row of `lezchars_death_year`.

### Migration checklist

- [ ] `lezchars_show_group` (Characters)
- [ ] `lezchars_actor` (Characters) — verify if format change needed
- [ ] `lezchars_death_year` (Characters) — includes date format conversion
- [ ] `lezchars_character_image_group` (Characters)
- [ ] `lezshows_waystowatch` (Shows)
- [ ] `lezshows_show_names` (Shows)
- [ ] `lezshows_similar_shows` (Shows) — verify if format change needed
- [ ] `leztvmaze_our_show` (TVMaze) — verify if format change needed
- [ ] `lezactors_birth` / `lezactors_death` — date format conversion (`m/d/Y` → `Ymd`)

**Canary test after migration:** Run `wp lwtv calc <show_id>` on a representative sample and confirm scores match pre-migration values. This validates that `lezchars_show_group` and all downstream calculations are intact.

---

## Phase 3.5 — Rewrite FacetWP Hooks ⚠️ Blocking Step

**This phase must be completed before running a FacetWP re-index.** The existing hooks read `$params['facet_value']` expecting CMB2's serialized array format. After migration, repeater fields return ACF's format instead. Running a re-index with the old hooks wipes valid index data and replaces it with nothing.

### `facetwp_index_row_characters_roles`

**Old hook (CMB2):** Receives `lezchars_show_group` as a serialized array where each element has a `type` key.

**Problem:** After migration, `get_post_meta( $id, 'lezchars_show_group', true )` returns an integer (the row count). FacetWP receives that integer as `facet_value` — the loop finds no `type` keys and indexes nothing.

**New hook (ACF):** Read the row count, then fetch each row's `type` sub-field directly.

```php
public function facetwp_index_row_characters_roles( $params, $facet_class ) {
    $post_id   = $params['post_id'];
    $row_count = (int) get_post_meta( $post_id, 'lezchars_show_group', true );

    if ( $row_count < 1 ) {
        $params['facet_value'] = '';
        return $params;
    }

    for ( $i = 0; $i < $row_count; $i++ ) {
        $type = get_post_meta( $post_id, "lezchars_show_group_{$i}_type", true );
        if ( empty( $type ) ) {
            continue;
        }
        $params['facet_value']         = $type;
        $params['facet_display_value'] = ucfirst( $type );
        $facet_class->insert( $params );
    }

    // Skip default indexing.
    $params['facet_value'] = '';
    return $params;
}
```

---

### `facetwp_index_row_shows_airdates`

**Old hook (CMB2):** Receives `lezshows_airdates` as an array with `start` and `finish` keys — a compound CMB2 field that no longer exists.

**Problem:** The plan splits this into two separate meta keys (`lezshows_airdates_start` and `lezshows_airdates_finish`). The old hook's `$params['facet_value']` will never be an array with those keys again. The entire hook needs to be rewritten, not just adapted.

**New hook (ACF):** Read the two separate meta keys directly. Everything else — on-air logic, inserting start/end/on-air — stays the same.

```php
public function facetwp_index_row_shows_airdates( $params, $facet_class ) {
    $post_id = $params['post_id'];

    // Read the two separate ACF fields directly.
    $start = get_post_meta( $post_id, 'lezshows_airdates_start', true );
    $raw_finish = get_post_meta( $post_id, 'lezshows_airdates_finish', true );
    $end   = ( ! empty( $raw_finish ) && strtolower( $raw_finish ) !== 'current' )
        ? $raw_finish
        : gmdate( 'Y' );

    // Build default params.
    $params_start  = $params;
    $params_end    = $params;
    $params_on_air = $params;

    // Add start date.
    $params_start['facet_value']         = $start;
    $params_start['facet_display_value'] = $start;
    $facet_class->insert( $params_start );

    // Add end date.
    $params_end['facet_value']         = $end;
    $params_end['facet_display_value'] = $end;
    $facet_class->insert( $params_end );

    // Determine on-air status.
    $on_air      = 'no';
    $on_air_meta = get_post_meta( $post_id, 'lezshows_on_air', true );

    // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
    if ( isset( $on_air_meta ) && in_array( $on_air_meta, array( 'yes', 'no' ) ) ) {
        $on_air = $on_air_meta;
    } elseif ( empty( $raw_finish ) || strtolower( $raw_finish ) === 'current' || $end > gmdate( 'Y' ) ) {
        $on_air = 'yes';
    }

    // Add on-air status.
    $params_on_air['facet_name']          = 'show_on_air';
    $params_on_air['facet_value']         = $on_air;
    $params_on_air['facet_display_value'] = ucfirst( $on_air );
    $facet_class->insert( $params_on_air );

    // Skip default indexing.
    $params['facet_value'] = '';
    return $params;
}
```

> **Note:** This hook no longer relies on FacetWP passing the CMB2 field value at all — it reads `lezshows_airdates_start` and `lezshows_airdates_finish` directly. Verify that FacetWP's facet configuration for airdates is pointed at a field that triggers this hook; it may need to be re-mapped in the FacetWP UI after Phase 5 removes the old CMB2 integration.

### FacetWP hook checklist

- [ ] Audit all other `facetwp_index_row_*` hooks for any that read CMB2 group/array values
- [ ] Deploy rewritten hooks to staging
- [ ] Run a full FacetWP re-index on staging
- [ ] Verify character role facets return correct results
- [ ] Verify show airdate facets return correct results
- [ ] Verify on-air facet returns correct results

---

## Phase 4 — Update PHP Code

### Maintenance window

Put the site in a read-only state (or warn the editorial team) for the window between Phase 3 completing and Phase 4 deploying. Code that reads `lezchars_show_group` and expects an array will silently return nothing once migration scripts have run — it will get an integer instead and fail without errors. An editor saving a post during this window could overwrite data.

### Remove CMB2-specific helpers

| File | Action |
|---|---|
| `plugins/lwtv-plugin/php/plugins/cmb2/class-taxonomies.php` | Remove — ACF handles taxonomy saves natively |
| `plugins/lwtv-plugin/php/plugins/cmb2/class-year-range.php` | Remove — `lezshows_airdates_start`/`_finish` read directly |
| `plugins/lwtv-plugin/php/cpts/shows/class-cmb2-metaboxes.php` | Remove — replaced by ACF JSON |
| `plugins/lwtv-plugin/php/cpts/characters/class-cmb2-metaboxes.php` | Remove — replaced by ACF JSON |
| `plugins/lwtv-plugin/php/cpts/actors/class-cmb2-metaboxes.php` | Remove — replaced by ACF JSON |

### Update group/repeater reads

After migration, `get_post_meta( $id, 'lezchars_show_group', true )` returns an **integer** (row count), not an array. All code reading group/repeater fields must switch to ACF's API or direct indexed meta reads.

Files that read `lezchars_show_group` (highest impact):

- `plugins/lwtv-plugin/php/cpts/characters/class-calculations.php`
- `plugins/lwtv-plugin/php/cpts/characters/class-custom-columns.php`
- `plugins/lwtv-plugin/php/rest-api/class-stats-json.php`
- `plugins/lwtv-plugin/php/rest-api/class-export-json.php`
- `plugins/lwtv-plugin/php/wp-cli/cli-shadow.php`
- `plugins/lwtv-plugin/php/plugins/cmb2/class-taxonomies.php` *(removed in this phase)*
- `plugins/lwtv-plugin/php/plugins/class-cache.php`
- `plugins/lwtv-plugin/php/statistics/build/class-dead.php` (reads `lezchars_death_year`)

Files that read `lezchars_actor`:

- `plugins/lwtv-plugin/php/cpts/actors/class-calculations.php`
- `plugins/lwtv-plugin/php/cpts/characters/class-calculations.php`
- `plugins/lwtv-plugin/php/wp-cli/cli-shadow.php`
- `plugins/lwtv-plugin/php/plugins/class-cache.php`

### lezshows_tvgenre_primary dynamic select

CMB2 populates this field's options via a callback at render time. ACF Select fields support a `acf/load_field` filter to populate choices dynamically — replicate this logic there. Also verify that the existing JS targeting CMB2 DOM selectors (`#lezshows_tvgenre_primary` or similar CMB2-specific markup) is updated to target ACF's rendered DOM for this field.

---

## Phase 5 — FacetWP

1. Remove `plugins/facetwp-cmb2/`.
2. In the FacetWP settings UI, re-map any facets currently using CMB2 data sources to use ACF data sources. FacetWP detects ACF fields automatically — no additional plugin needed.
3. Run a full FacetWP re-index after remapping. The rewritten hooks from Phase 3.5 must be deployed before this step.

---

## Phase 6 — Remove CMB2

Once all field groups are ACF-managed, migration scripts have run and been verified on production, and all PHP reads have been updated:

1. Remove CMB2 core (check `plugins/lwtv-plugin/vendor/` for a bundled copy).
2. Delete: `plugins/cmb2-grid/`, `plugins/cmb-field-select2/`, `plugins/cmb2-attached-posts/`, `plugins/facetwp-cmb2/`.
3. Remove any `require` or autoload references to the above.
4. Run `composer lint` and `npm run lint` to confirm no regressions.

---

## Testing Checklist

Before declaring the migration complete on production:

- [ ] Scores produced by `class-calculations.php` match pre-migration values for a sample of shows
- [ ] Character show appearances display correctly on character pages
- [ ] Actor character lists display correctly
- [ ] Dead character stats match pre-migration counts
- [ ] FacetWP facets return correct results (run a full re-index after Phase 5)
- [ ] Character role facets return correct results
- [ ] Show airdate facets return correct results
- [ ] On-air facet returns correct results
- [ ] REST API endpoints (`/wp-json/wp/v2/post_type_shows/`, `post_type_characters/`, `post_type_actors/`) return all expected meta fields
- [ ] WP-CLI commands (`wp lwtv calc`, `wp lwtv generate`) complete without errors
- [ ] Admin metaboxes display correctly and save correctly for all three CPTs
- [ ] Existing posts show pre-populated field values in admin (validates ACF reference key rows)
- [ ] Shows We Love, BYQ Override (admin-only) visible only to admins
- [ ] Privacy controls on actors function correctly
- [ ] Ways to Watch links render correctly
- [ ] Similar Shows feature works
- [ ] `lezshows_tvgenre_primary` dynamic select populates correctly in admin
- [ ] Date fields (`lezactors_birth`, `lezactors_death`, `lezchars_death_year`) display correct values

---

## Effort Estimate

| Phase | Estimate |
|---|---|
| Phase 1 — ACF setup & JSON config | 1–2 hours |
| Phase 2a — Actors field groups | 2–3 hours |
| Phase 2b — Shows field groups | 3–4 hours |
| Phase 2c — Characters field groups | 2–3 hours |
| Phase 2d — TVMaze field group | 30 minutes |
| Phase 3 — Migration scripts | 4–6 hours |
| Phase 3.5 — FacetWP hook rewrites | 2–3 hours |
| Phase 4 — PHP code updates | 4–8 hours |
| Phase 5 — FacetWP remapping & re-index | 1–2 hours |
| Phase 6 — CMB2 removal | 1–2 hours |
| Testing & verification | 4–8 hours |
| **Total** | **~24–41 hours** |
