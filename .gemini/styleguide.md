# LezWatch.TV Code Review Style Guide

Context for automated review of `lezwatch/lwtv-underscores`.

This repository is a **WordPress theme that bundles its own plugin** at
`plugins/lwtv-plugin/`. It is not a general-purpose PHP application, and several
things that look like defects in isolation are deliberate. Read this file before
flagging a convention.

**Stack:** PHP 8.1+ (composer targets 8.5), WordPress 6.5+, Node 24, SCSS,
`@wordpress/scripts` for blocks. PHP style is enforced by `phpcs.xml.dist`
(WordPress-Extra) and run in CI via `composer lint`.

---

## What the project is

Three custom post types are the whole product, and they cross-reference each
other:

- **Shows** (`post_type_shows`) — scored by `plugins/lwtv-plugin/php/cpts/shows/class-calculations.php`
- **Characters** (`post_type_characters`) — the source of truth for show and actor links
- **Actors** (`post_type_actors`)

Shadow Taxonomies mirror those relationships as terms so they can be queried
quickly. Statistics and the year-in-review views are built entirely on top of
these links.

---

## Flag these strongly

1. **Show score changes.** Any edit to the weights, multipliers, or thresholds in
   `class-calculations.php` changes every show's stored score sitewide and needs
   an explicit migration story. Flag it as high severity even when the change
   looks like a cleanup.
2. **Anything that weakens the CPT relationships.** Dropping a meta write,
   skipping a shadow-term sync, or changing a link's storage shape without
   updating both sides. `lezchars_show_group` in particular is read by SQL in
   several places.
3. **Statistics that can silently under-count.** Early `return`s, `LIMIT`s, or
   `posts_per_page` caps added to a stats query change published numbers without
   erroring. Say so plainly.
4. **Unprepared SQL.** Direct `$wpdb` calls must use `$wpdb->prepare()`. This
   codebase has hand-written SQL by necessity; that is not a licence to
   interpolate.
5. **Remote HTTP via `file_get_contents()`.** New remote fetches must use
   `wp_remote_get()`. Local file reads with `file_get_contents()` are fine and
   are explicitly allowed in `phpcs.xml.dist`.
6. **Missing text domain.** All user-facing strings must be translatable:
   `'lwtv'` inside `plugins/lwtv-plugin/`, `'lwtv-underscores'` in theme files.
7. **Unescaped output** — except for the self-escaping helpers listed below.
8. **New display logic put in the wrong layer.** See "build → format →
   templates" below.
9. **Accessibility and inclusive language.** This site serves a queer community.
   Flag colour contrast below WCAG AA (in *both* light and dark mode), missing
   labels, and outdated terminology in UI strings, taxonomy labels, or comments.

---

## Deliberate conventions — do not flag these

### Trust declared types and explicit casts

The most common false positive on this repo is a suggestion to defensively
re-cast a value that is already constrained. **Before suggesting a `(array)`,
`(string)`, `(int)`, or null check, read the declaration of the thing being
called.**

If a method declares `: array`, it cannot return `null` or `false` — PHP would
have thrown inside that method first. If a value was already cast on the way
into an array, its elements do not need re-casting on the way out. For example,
`Watch_Hosts::term_url_rows()` declares `: array<int, string>`, casts each value
with `(string)`, and stores only non-empty strings, so callers such as
`array_map( 'trim', array_values( Watch_Hosts::term_url_rows( $id ) ) )` are
correct as written. Do not ask for `(array)` around it or `(string)` inside the
callback.

Only raise a null-safety or type concern when you have actually traced the value
to a source that can produce the type you are worried about. Say where.

### Self-escaping template helpers

These functions escape their own output. Wrapping them in `esc_html()` or
`esc_attr()` is wrong and would double-escape. The full list lives in
`phpcs.xml.dist` under `customAutoEscapedFunctions`; the common ones are:

`lwtv_plugin()`, `get_symbolicon()`, `lwtv_symbolicons()`, `LWTV_Features`,
`LWTV_Statistics`, `facetwp_display()`, and the `lwtv_pagination_*` helpers.

### PHPCS rules that are switched off on purpose

Do not re-litigate these; the exclusions are in `phpcs.xml.dist`:

- `class-*.php` filenames (`WordPress.Files.FileName.*`) — every class file is named this way
- Short ternary `?:` is allowed
- `wp_reset_query()` is used intentionally; do not suggest `wp_reset_postdata()` as a blanket replacement
- `error_log()` / `trigger_error()` are permitted
- `file_get_contents()` for local reads

### PHP syntax style

New PHP should use brace syntax (`if () { }`, `foreach () { }`). Existing
alternate syntax (`if: … endif;`) in older template files is not a defect and
does not need converting as a drive-by change — but don't suggest adding more of
it either.

### WP-CLI files

Everything under `plugins/lwtv-plugin/php/wp-cli/` is loaded only from inside a
`defined( 'WP_CLI' )` guard in `_components/class-wp-cli.php`. Individual CLI
files therefore do not need their own `WP_CLI` guards, and classes extending
`\WP_CLI_Command` there are safe. The `if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) )`
header in those files is a direct-access check, not a CLI gate.

### ACF date storage

ACF `date_picker` raw postmeta is stored as `Ymd`, **not** `Y-m-d`, and older
rows can hold either. Code that handles both formats when sorting or parsing
dates is correct, not redundant.

### Bundled third-party code

`plugins/shadow-taxonomy/` and the bundled `ICal` library are vendored and
excluded from linting. Do not review them, and treat Shadow Taxonomy's API as
always available — it is `require_once`'d unconditionally by the theme.

---

## Architecture rules

### build → format → templates

Newer view modules (`plugins/lwtv-plugin/php/statistics/`, `.../this-year/`) are
split into three layers, and the separation is load-bearing:

- **`build/`** — **pure transforms only.** Arrays and scalars in, arrays out. No
  WordPress globals, no `$wpdb`, no `get_post_meta()`, no output. This purity is
  what makes them unit-testable without a WordPress bootstrap. New display logic
  belongs here, added test-first.
- **`format/`** — turns built data into presentation-ready values (labels,
  phrasing, number formatting).
- **`templates/`** — render only. Markup and escaping, no data crunching.

Flag WordPress calls introduced into `build/`. Flag data crunching introduced
into `templates/`. Do *not* flag `build/` classes for "not handling WordPress
data" — that is the design.

### Dependency guards

ACF Pro is a hard requirement, gated at load time by `inc/requirements.php`, so
unguarded `get_field()` in theme templates is expected. Every *other* plugin
integration must degrade rather than fatal, and is guarded at each call site —
FacetWP, SearchWP (and its Modal Form / Live Ajax add-ons), AIOSEO, Gravity
Forms, MonsterInsights, Related Posts By Taxonomy, Jetpack sharing, and Action
Scheduler, which falls back to WP-Cron via
`_Components\Scheduler::is_action_scheduler_available()`.

Flag a *new* unguarded call into any of those. Do not flag a call that sits
inside a hook the third-party plugin itself fires — that hook cannot run when the
plugin is inactive.

Also: `is_plugin_active()` and `get_plugin_data()` live in
`wp-admin/includes/plugin.php` and are **not** available on front-end, REST, or
`wp-cron.php` requests. Flag any new use of them outside admin-only code paths.

### Statistics and caching

Stats are expensive and heavily cached through transients. Note that WP-CLI on
production skips the object-cache drop-in, so transients written from CLI are not
visible to web requests — flag code that assumes CLI and web share a cache.

---

## SCSS and CSS

- Styles live in `scss/`, split into `partials/` (core) and `addons/` (features);
  the entry point is `style.scss`.
- **Use `rem` for font sizes**, not `px`. Reserve `px` for hairline borders and
  genuinely fixed details.
- Use the SCSS colour variables (`colors.$lwtv-*`). Flag hardcoded hex values.
- Dark mode overrides live in `scss/partials/_colors-dark.scss` via the
  `mixins.color-mode(dark)` mixin. A new colour that has not been checked in both
  modes is worth flagging.
- Never review or suggest edits to `plugins/lwtv-plugin/php/blocks/build/` —
  it is compiled output.

---

## Testing

`vendor/bin/phpunit` runs a PHPUnit 11 suite over the pure transforms in
`build/`. The bootstrap deliberately **does not load WordPress**; it defines
`ABSPATH` and requires the classes under test directly.

- New `build/` logic should arrive with tests.
- Do not ask for unit tests on code that reads WP globals, post meta, or runs
  queries — that is verified against a running site, by design, and mocking it
  here is explicitly not the approach.

---

## Review tone

Be concrete and cite the file and line you are reasoning from. When flagging a
possible bug, state the input that triggers it. If a concern depends on a value's
type, name the declaration you checked. Prefer one well-evidenced comment over
several speculative ones.
