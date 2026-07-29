# CLAUDE.md — LezWatch.TV

## Project Purpose

LezWatch.TV documents TV shows with queer female, non-binary, and transgender characters to help people find fully developed LGBTQ+ stories. The site is a WordPress-based database covering international shows and web series.

**Three interconnected CPTs drive everything:**

- **Shows** (`post_type_shows`) — the primary entity; scored via `class-calculations.php`
- **Characters** (`post_type_characters`) — linked to shows and actors
- **Actors** (`post_type_actors`) — linked to characters and shows

Design decisions must preserve and strengthen these relationships. Cross-CPT data integrity is load-bearing.

---

## Architecture

This repo is a **WordPress theme** (`lezwatch/lwtv-underscores`) that bundles the main plugin at `plugins/lwtv-plugin/`. Everything lives together in one repo.

```
lwtv-underscores/
├── plugins/lwtv-plugin/php/
│   ├── cpts/           # CPT registration, metaboxes, calculations
│   │   ├── shows/      # class-calculations.php is the show scoring engine
│   │   ├── actors/
│   │   └── characters/
│   ├── statistics/     # Stats views (build/ → format/ → templates/)
│   ├── this-year/      # Year-in-review views (build/ → format/ → templates/)
│   ├── features/       # Site features (missed schedule, etc.)
│   ├── queeries/       # Reusable WP_Query wrappers
│   ├── rest-api/       # REST endpoints
│   ├── grading/        # Show grading components
│   └── blocks/         # Gutenberg blocks (built via npm workspaces)
├── cron/               # Shell scripts for scheduled WP-CLI tasks
├── inc/                # Theme includes
├── scss/               # Theme styles (partials/ + addons/)
├── tests/              # PHPUnit pure-unit tests (no WP bootstrap)
└── style.scss          # Theme stylesheet entry (compiles to style.css / style.min.css)
```

**Namespace:** `LWTV\` — all plugin PHP uses PSR-4 autoloading under this namespace.

### build → format → templates pattern (important)

The newer view modules (`statistics/`, `this-year/`) are split into three layers, and this separation is load-bearing:

- **`build/`** — **pure transforms.** Classes take in arrays/scalars and return arrays. They must not touch WordPress globals, `$wpdb`, or output. Because they are pure, they are unit-testable without a WP runtime (see **Testing**). New display logic belongs here.
- **`format/`** — turns built data into presentation-ready structures (labels, phrasing, formatted numbers).
- **`templates/`** — render only. Markup + escaping; no data crunching.

When adding a feature to these modules: put the logic in `build/`, write a test for it first (TDD), then wire `format/` and `templates/`. Live WordPress glue (queries, meta reads) stays at the edges and is verified against the running site, not in unit tests.

### Show Score (`class-calculations.php`)

The scoring system in `plugins/lwtv-plugin/php/cpts/shows/class-calculations.php` is the core data product. It weighs:

- Realness + Quality + Screentime ratings (×3 multiplier, max 30)
- Worth It rating (Yes=10, Meh=5, No=−10)
- Star rating (gold=20, silver=10, bronze=5, anti=−15)
- Trigger warning level (high=15, med=10, low=5)
- Shows We Love bonus (+40)
- Character counts, diversity, dead/alive ratios, and more

**Do not alter scoring weights without understanding the downstream effects on all existing show scores.** The score is stored in post meta and regenerated via cron/WP-CLI.

### Statistics & Charts

Statistics live in `plugins/lwtv-plugin/php/statistics/`. They consume the interconnected CPT data and follow the build → format → templates split above. Key classes:

- `class-stats-generator.php` — builds the raw data arrays
- `class-stats-handler.php` — routes requests
- `class-gutenberg-ssr.php` — server-side rendered stat blocks
- `class-csv-download.php` — `?download=csv` export on stats views

Do not assume every view emits a chart.

---

## Coding Standards

### PHP

- **Standard:** WordPress-Extra via `phpcs.xml.dist`
- **PHP version:** 8.1+ minimum (enforced via PHPCompatibilityWP); `composer.json` targets 8.5
- **WordPress minimum:** 6.5
- **Lint:** `composer lint` (runs `phpcs`)
- **Fix:** `composer lint-fix` (runs `phpcbf`)

**Active exclusions** (do not re-add these rules):

- `WordPress.Files.FileName.InvalidClassFileName` / `NotHyphenatedLowercase` — we use `class-*.php` names
- `WordPress.PHP.DisallowShortTernary.Found` — short ternary (`?:`) is allowed
- `WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents` — allowed
- `WordPress.WP.DiscouragedFunctions.wp_reset_query_wp_reset_query` — allowed

**Custom auto-escaped functions** (do not wrap these in `esc_*`):
`lwtv_plugin`, `get_symbolicon`, `lwtv_symbolicons`, `LWTV_Features`, `LWTV_Statistics`, and several pagination helpers — see `phpcs.xml.dist` for the full list.

### JavaScript / CSS / SCSS

Always run `nvm use` first so you're on the version pinned in `.nvmrc` (**Node 24**; `engines` requires Node ≥24 and npm ≥11).

- **Lint JS:** `npm run lint:js`
- **Lint CSS:** `npm run lint:css`
- **Autofix CSS/JS:** `npm run fix`
- Blocks are built via `@wordpress/scripts` with a custom webpack config at `_build_scripts/webpack.config.js`
- Built block output goes to `plugins/lwtv-plugin/php/blocks/build/` — never edit files there

**SCSS conventions:**

- Styles live in `scss/`, split into `partials/` (core: `_colors.scss`, `_fonts.scss`, `_mixins.scss`, etc.) and `addons/` (feature styles: `_stats.scss`, `_calendar.scss`, etc.). The entry point is `style.scss`.
- **Prefer `rem` over `px` for font sizes.** Use `rem` for typography so text scales with the user's root font size; reserve `px` for hairline borders and other truly fixed details.
- **Dark mode:** overrides live in `scss/partials/_colors-dark.scss` via the `mixins.color-mode(dark)` mixin. Use the SCSS color variables (`colors.$lwtv-*`) rather than hardcoding hex values, and verify new colors in both light and dark modes (aim for WCAG AA contrast).

### Excluded from linting

`vendor/`, `node_modules/`, `inc/dist/`, `blocks/build/`, and several bundled third-party libraries (`shadow-taxonomy`, `ICal`).

---

## Build & Dev Commands

```bash
nvm use              # match .nvmrc (Node 24) before any JS/CSS work

# Install everything
npm install          # runs composer install (preinstall) + JS/workspace deps
composer install     # PHP deps only

# Full build (composer + assets + workspaces + mover)
npm run build

# Quick asset rebuild only (assets + mover, skips composer/workspaces)
npm run buildquick

# Linting
npm run lint         # all linters (CSS, JS, PHP)
npm run fix          # autofix CSS + JS
composer lint        # PHP only
composer lint-fix    # PHP autofix

# Symbolicons
npm run symbolicons:dev   # development branch
npm run symbolicons:prod  # production branch
```

---

## Testing

A PHPUnit 11 harness covers the **pure-transform** logic under `build/` (currently `this-year/`).

```bash
vendor/bin/phpunit                 # run the unit suite
vendor/bin/phpunit --filter Trends # run a single test class
```

- Config: `phpunit.xml.dist`; bootstrap: `tests/bootstrap.php`; tests: `tests/unit/`.
- The bootstrap **does not load WordPress.** It defines `ABSPATH` so `if ( ! defined( 'ABSPATH' ) )`-guarded class files load, then `require`s the classes under test directly.
- Only test pure transforms here — code that reads WP globals, meta, or runs queries is **not** unit-tested; verify that against the running site instead.
- New `build/` logic should be added test-first (see the build → format → templates pattern above).

---

## Scheduled Tasks / Cron

- Server crontab drives `wp lwtv generate cron daily`, which rotates debuggers, refreshes FacetWP cache, and runs other maintenance.
- Shell scripts live in `cron/`.
- The plugin registers scheduled actions that map to WP-CLI commands.

---

## Deployment & Branching

- Long-lived branches: **`development`** (staging/integration) and **`production`** (live). PRs target **`production`**.
- Helper scripts exist (`npm run merge-to-develop`, `npm run merge-to-prod`) but **only run them when the human explicitly asks** — see the workflow constraints below.
- CI/CD via GitHub Actions handles build and deploy to servers.
- **Do not force-push to `production`.**

### Workflow constraints (must follow)

- **Never commit** to a branch without explicit human approval in that same turn. Always ask first.
- **Never auto-merge or close** a development branch — leave it open as-is so the human can do manual testing.

---

## Key Conventions

- Class files named `class-*.php` (e.g., `class-calculations.php`).
- One class per file; namespace mirrors directory path under `LWTV\`.
- Meta keys for shows are prefixed `lezshows_`; for characters `lezchars_`; for actors `lezactor_`.
- Taxonomy slugs: `lez_stars`, `lez_triggers`, `lez_tropes`, `lez_genres`, `lez_stations`, etc.
- Do not use `wp_reset_query()` alternatives — direct `wp_reset_query()` is intentionally allowed.
- Avoid introducing new direct `file_get_contents()` calls for remote URLs; use `wp_remote_get()` instead, even though local file reads are permitted.
- All user-facing strings must be i18n-ready (`__()`, `_e()`, `_n()`, etc.) with the `'lwtv'` text domain.
- ACF `date_picker` raw postmeta is stored as `Ymd` (not `Y-m-d`); account for the mixed formats when sorting/parsing dates.

---

## What to Protect

1. **Show score integrity** — the calculation in `class-calculations.php` feeds the site's core value proposition. Changes here are high-stakes.
2. **CPT relationships** — shows ↔ characters ↔ actors linkages are the backbone of statistics and cross-referencing.
3. **Statistics accuracy** — data visualizations reflect real counts; don't short-circuit queries in ways that silently drop data.
4. **Accessibility & representation** — the site serves a community. UI text, labels, taxonomy terms, and color contrast (light *and* dark mode) should reflect current inclusive language and a11y norms.
