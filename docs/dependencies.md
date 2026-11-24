# Plugin dependencies

Which third-party plugins the site needs, which are gated at boot, and why the others are not.

## The requirements gate

`inc/requirements.php` is loaded at the very top of `functions.php`, before `plugins/index.php` loads the LWTV plugin. `lwtv_theme_check_requirements()` runs straight away, so a missing dependency never reaches CPT registration or show-score calculation.

### ACF Pro is the only hard dependency

Every CPT's meta comes from ACF, and templates call `get_field()` without a guard throughout. `lwtv_theme_missing_requirements()` checks `class_exists( 'ACF' )`, the same check as `plugins/lwtv-plugin/php/plugins/class-acf.php`.

When ACF Pro is missing:

- **Front-end requests** get the static maintenance page from `inc/maintenance.php` with a 503.
- **Everywhere**, an error admin notice that can't be dismissed (`lwtv_theme_requirements_admin_notice()`) lists what is missing.
- **Exempt requests** go through untouched, so the site can still be repaired: wp-admin (`is_admin()`), `wp-login.php` / `wp-register.php`, WP-CLI, cron, AJAX, XML-RPC and the REST API. REST is detected from the request **path**, because `REST_REQUEST` isn't defined yet this early. The query string is ignored, so `/?x=/wp-json/` can't be used to skip the gate.

### Deliberately not gated

| Plugin | Why it isn't gated |
|---|---|
| Action Scheduler | `_Components\Scheduler` falls back to WP-Cron by design (`is_action_scheduler_available()`, `schedule_task()`, `cache_queue()`). Don't add a gate for it without also removing that fallback. |
| FacetWP, SearchWP (plus Modal Form and Live Ajax), AIOSEO, Gravity Forms, MonsterInsights, Related Posts By Taxonomy, Jetpack sharing | Their call sites are guarded (for example `function_exists( 'FWP' )`), so the site loses a feature rather than fataling. |

Any new call into these plugins must be guarded the same way.

### Escape hatch

To skip the gate entirely (for example while debugging a partial install), add this to `wp-config.php`:

```php
define( 'LWTV_SKIP_REQUIREMENTS_CHECK', true );
```

## Bundled third-party code

Code that ships inside the theme rather than as a separate plugin. None of it is gated, because it's always present.

| Code | Where | Notes |
|---|---|---|
| ICS Parser (`johngrogg/ics-parser`) | `plugins/lwtv-plugin/php/calendar/ICal/` | Required in `plugins/lwtv-plugin/composer.json`. Composer's `post-update-cmd` copies `src/ICal/` into the plugin, and the copy is what's deployed. Don't edit it by hand; run `composer update` in `plugins/lwtv-plugin/` instead. Excluded from linting. |
| Forks of abandoned or single-purpose plugins | `plugins/class-comment-probation.php`, `plugins/gravity-forms/class-gf-approvals.php`, `plugins/acf/class-number-slider.php`, `features/class-dashboard-posts-in-progress.php`, `_components/class-admin-color-schemes.php` | Maintained here. Upstream fixes won't arrive on their own. |
| WP Bootstrap Navwalker | `inc/class-wp-bootstrap-6-navwalker.php` | Forked from 5.0.0 for Bootstrap 6 menu markup. |
| Bootstrap 6 | `inc/bootstrap/` | Copied from `node_modules/bootstrap` by `_build_scripts/postbuild.js`. Build output, not hand-edited. |

### Removed: the shadow taxonomy library

Before 7.5.0 the theme bundled a modified shadow-taxonomy library at `plugins/shadow-taxonomy/`. It kept a hidden taxonomy term in step with each show and actor so characters could be queried by relationship. It's gone, along with `cli-shadow.php` and `queeries/class-shadow-taxonomy.php`. Character ↔ show/actor/character links now live in the `{$wpdb->prefix}lwtv_char_links` table, installed by `_components/class-relationships.php`. Read them through `Relationships\Character_Links`, and rebuild the table with `wp lwtv links rebuild`. Background: [plans/completed/shadow-taxonomy-removal.md](plans/completed/shadow-taxonomy-removal.md).

Don't reintroduce a taxonomy-as-index for these relationships. The table is the only index, and `Relationships\Sync` writes it on save.
