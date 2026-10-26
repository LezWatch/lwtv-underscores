# Bootstrap 6 Content Migration

Moves the Bootstrap 5 class names stored in post content to their Bootstrap 6 equivalents, so [scss/addons/_bootstrap-legacy.scss](../../../scss/addons/_bootstrap-legacy.scss) can be deleted.

Templates, shortcodes and server-rendered blocks build their markup at render time and were already moved to v6 with the theme. This covers only what is saved in `post_content`.

## What's stored, and which tool handles it

| Source | Count (production copy, Oct 2026) | Tool |
|---|---|---|
| Spoiler, Grade and Screener blocks | 299 blocks in 254 records (253 posts, 1 synced pattern) | Browser re-save: [bootstrap-6-resave-blocks.js](bootstrap-6-resave-blocks.js) |
| Hand-written alerts, buttons and badges (classic posts, Custom HTML) | about 40 posts | `wp lwtv migrate bootstrap6` |

The two can't share a tool. The blocks' stored HTML has to match their `save()` exactly or the editor flags them as invalid, and Screener also needs markup changes beyond class names (see [Screener history](#screener-history)). So the blocks are re-serialized by Gutenberg itself, and the WP-CLI command skips them. Either can run first.

Class mapping, the same one the legacy stylesheet styles, so pages look the same afterwards:

| Bootstrap 5 | Bootstrap 6 |
|---|---|
| `alert-{c}` | `theme-{c}` (keeps `.alert`) |
| `btn-{c}` | `btn-solid theme-{c}` (keeps `.btn`) |
| `btn-outline-{c}` | `btn-outline theme-{c}` |
| `text-bg-{c}` | `theme-{c}` |

`{c}` is primary, secondary, success, danger, warning or info. `dark` becomes `inverse`. v6 has no light theme, so `*-light` classes are reported, not changed; fix those by hand.

## Run order

1. **Back up the database.** Run everything on a copy (`lwtv.local`) first.
2. **Deploy** the blocks build with the v6 `save()` and deprecations (release 7.4.0) and the theme.
3. **Re-save the blocks.** On any block editor screen, as an administrator, paste [bootstrap-6-resave-blocks.js](bootstrap-6-resave-blocks.js) into the browser console, then:
   ```js
   await lwtvResaveBlocks();                  // report: what would be re-saved, and anything skipped
   await lwtvResaveBlocks( { apply: true } ); // save
   ```
   It refuses to run unless the new block code is loaded. Only the three blocks' segments change; the rest of each post stays byte-for-byte. Posts with a block that doesn't validate are skipped and listed: open and fix those in the editor. Saves go through the REST API, so revisions and modified dates update as for an editor save.
4. **Migrate hand-written markup.**
   ```bash
   wp lwtv migrate bootstrap6 --dry-run   # table of posts, classes changed, and unmapped classes
   wp lwtv migrate bootstrap6             # asks to confirm; --yes skips the prompt
   ```
   Only class attributes in real tags change, plus `"className"` in block comments so core blocks stay valid. Text, other attributes and escaped code samples are left alone. It writes `post_content` directly: under WP-CLI there's no user with `unfiltered_html`, so `wp_update_post()` would run kses and strip markup. A revision of each post is saved first, and `post_modified` is bumped.
5. **Check nothing is left.** This should return zero in every column:
   ```bash
   wp db query "SELECT post_type,
     SUM(post_content REGEXP 'alert-(primary|secondary|success|danger|warning|info|dark)') AS alerts,
     SUM(post_content REGEXP 'btn-(outline-)?(primary|secondary|success|danger|warning|info|dark)') AS btns,
     SUM(post_content REGEXP 'text-bg-(primary|secondary|success|danger|warning|info|dark)') AS badges
   FROM $(wp db prefix)posts
   WHERE post_type NOT IN ('revision','nav_menu_item') AND post_status NOT IN ('trash','auto-draft')
   GROUP BY post_type;"
   ```
6. **Delete** `scss/addons/_bootstrap-legacy.scss` and its `meta.load-css` line in `style.scss`, then rebuild.

## Rolling back

Both tools leave a revision, so a single post can be restored from the editor's Revisions screen. For a full rollback, restore the backup from step 1.

## Screener history

Screener's saved markup changed three times, and each is a deprecation in [screener/js/deprecated.js](../../../plugins/lwtv-plugin/php/blocks/src/screener/js/deprecated.js), with the variants produced by [components/markup.js](../../../plugins/lwtv-plugin/php/blocks/src/screener/js/components/markup.js):

- **Earliest (2018).** `queer` was stored as a number. The string-only attribute rejected it and fell back to the default, so these migrate it to a string.
- **Bootstrap 4.** `data-toggle="tooltip"`. When the Bootstrap 5 migration changed `save()` to `data-bs-toggle`, every stored screener stopped validating.
- **Bootstrap 5.** `data-bs-toggle`, but the star and trigger icons were missing: the components rendered `{ Star }` (a component function, which React drops) instead of `<Star />`.

The current `save()` writes v6 classes and renders the icons, so re-saving also restores the missing icons.

## Code

- `WP_CLI_LWTV_Migrate::migrate_bootstrap6()` in [plugins/lwtv-plugin/php/wp-cli/cli-migrate.php](../../../plugins/lwtv-plugin/php/wp-cli/cli-migrate.php)
- The class transform: `LWTV\_Helpers\Bootstrap6_Markup` in [plugins/lwtv-plugin/php/_helpers/class-bootstrap6-markup.php](../../../plugins/lwtv-plugin/php/_helpers/class-bootstrap6-markup.php), tested in [tests/unit/Helpers/Bootstrap6MarkupTest.php](../../../tests/unit/Helpers/Bootstrap6MarkupTest.php)
- Block saves and deprecations: [plugins/lwtv-plugin/php/blocks/src/](../../../plugins/lwtv-plugin/php/blocks/src/) `spoiler/`, `grade/`, `screener/`
