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

This repo is a **WordPress theme** (`lezwatch/lwtv-underscores`) that includes the main plugin at `plugins/lwtv-plugin/`. Everything lives together in one repo.

```
lwtv-underscores/
├── plugins/lwtv-plugin/php/
│   ├── cpts/           # CPT registration, metaboxes, calculations
│   │   ├── shows/      # class-calculations.php is the show scoring engine
│   │   ├── actors/
│   │   └── characters/
│   ├── statistics/     # Chart.js-backed stats (class-stats-generator.php, etc.)
│   ├── features/       # Site features (missed schedule, etc.)
│   ├── queeries/       # Reusable WP_Query wrappers
│   ├── rest-api/       # REST endpoints
│   ├── grading/        # Show grading components
│   └── blocks/         # Gutenberg blocks (built via npm workspaces)
├── cron/               # Shell scripts for scheduled WP-CLI tasks
├── inc/                # Theme includes
└── scss/               # Theme styles
```

**Namespace:** `LWTV\` — all plugin PHP uses PSR-4 autoloading under this namespace.

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

Statistics live in `plugins/lwtv-plugin/php/statistics/`. They consume the interconnected CPT data and render via Chart.js. Key classes:

- `class-stats-generator.php` — builds the raw data arrays
- `class-stats-handler.php` — routes requests
- `class-gutenberg-ssr.php` — server-side rendered stat blocks

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

### JavaScript / CSS

Always remember to run `nvm use` so you're on the correct version as set by `.nvmrc`.

- **Lint JS:** `npm run lint:js`
- **Lint CSS:** `npm run lint:css`
- Blocks are built via `@wordpress/scripts` with a custom webpack config at `_build_scripts/webpack.config.js`
- Built block output goes to `plugins/lwtv-plugin/php/blocks/build/` — never edit files there

### Excluded from linting

`vendor/`, `node_modules/`, `inc/dist/`, `blocks/build/`, and several bundled third-party libraries (`shadow-taxonomy`, `ICal`).

---

## Build & Dev Commands

```bash
# Install everything
npm install          # JS deps + workspace deps
composer install     # PHP deps

# Full build (composer + assets + workspaces + mover)
npm run build

# Quick asset rebuild only
npm run buildquick

# Linting
npm run lint         # runs all linters (CSS, JS, PHP)
composer lint        # PHP only
composer lint-fix    # PHP autofix

# Symbolicons
npm run symbolicons:dev   # development branch
npm run symbolicons:prod  # production branch
```

---

## Scheduled Tasks / Cron

- Server crontab drives `wp lwtv generate cron daily`, which rotates debuggers, refreshes FacetWP cache, and runs other maintenance.
- Shell scripts live in `cron/`.
- The plugin registers scheduled actions that map to WP-CLI commands.

---

## Deployment

- PRs target the **`production`** branch.
- CI/CD via GitHub Actions handles build and deploy to servers.
- **Do not force-push to `production`.**

---

## Key Conventions

- Class files named `class-*.php` (e.g., `class-calculations.php`).
- One class per file; namespace mirrors directory path under `LWTV\`.
- Meta keys for shows are prefixed `lezshows_`; for characters `lezchars_`; for actors `lezactor_`.
- Taxonomy slugs: `lez_stars`, `lez_triggers`, `lez_tropes`, `lez_genres`, etc.
- Do not use `wp_reset_query()` alternatives — direct `wp_reset_query()` is intentionally allowed.
- Avoid introducing new direct `file_get_contents()` calls for remote URLs; use `wp_remote_get()` instead, even though local file reads are permitted.
- All user-facing strings must be i18n-ready (`__()`, `_e()`, `_n()`, etc.) with the `'lwtv'` text domain.

---

## What to Protect

1. **Show score integrity** — the calculation in `class-calculations.php` feeds the site's core value proposition. Changes here are high-stakes.
2. **CPT relationships** — shows ↔ characters ↔ actors linkages are the backbone of statistics and cross-referencing.
3. **Statistics accuracy** — Data visualizations reflect real counts; don't short-circuit queries in ways that silently drop data.
4. **Accessibility & representation** — the site serves a community. UI text, labels, and taxonomy terms should reflect current inclusive language norms.
