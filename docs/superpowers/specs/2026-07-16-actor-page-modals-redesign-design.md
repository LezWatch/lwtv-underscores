# Actor-page Modals Redesign — Design Spec

**Date:** 2026-07-16
**Status:** Approved (design), pending implementation plan
**Scope:** The two Bootstrap modals in the "Additional Information" section of an individual actor page (`/actor/<slug>/`): **Character Statistics** and **Related Articles**. Extends the statistics-redesign visual language (server-rendered donuts, count-up, family colors, card surfaces) onto the actor page overlays, replacing the old Chart.js pies + bare article list.

## Summary

Rebuild the *bodies* of the two actor-page modals to match the `/statistics/` redesign, keeping all Bootstrap modal plumbing:

- **Character Statistics** — a caption row + two compact donuts side by side (Roles, Status) + the existing footnote. Server-rendered SVG rings (reusing `partials/donut.php`) with count-up on open.
- **Related Articles** — an intro line + a vertical list of styled article cards (thumbnail + category tag + pink title + clamped excerpt + date) + a footer link.

These modals are the last Chart.js consumers on actor pages, so the Chart.js enqueue is removed from actor singular pages as part of this work (verified in the plan that nothing else on `/actor/` needs it). `/statistics/nations|stations|death` keep Chart.js until they are migrated separately.

## Reuse mandate

Reuse the components + tokens from the statistics rounds. NO hardcoded hex; do NOT revert the user's committed color/size tweaks. Reuse `partials/donut.php` (extended, see below), the `.lwtv-donut-*` / `.lwtv-donut-seg--*` classes, the count-up/bar-grow JS, the `.lwtv-stats-eyebrow` type, and the `$lwtv-*` / `$lwtv-stats-*` tokens. Keep the theme font stack — do not import Inter/Lucide.

## Non-goals

- No change to the Bootstrap modal structure (`.modal`/`.modal-dialog.modal-lg`/`.modal-content`), the trigger cards, `data-bs-toggle`/`data-bs-target`, or Esc/backdrop close.
- No new statistics data layer; no scoring, routing, or CPT-relationship changes.
- No redesign of the `/statistics/` pages (the shared `donut.php` default layout stays byte-identical).
- The empty/error fallback ("statistics will be right back!" with `rose.gif`) is preserved as-is.

---

## Architecture

Render path unchanged: the actor single template includes `template-parts/overlays/statistics-actors.php` and `template-parts/overlays/related-articles.php`, each rendering a trigger card + a Bootstrap modal. Only the modal-body markup + styling change.

### 1. `partials/donut.php` gains a `layout` option

Add `layout => 'full' | 'compact'` to the `$donut` contract, default `'full'`.

- **`full` (default):** the current horizontal card (ring left; eyebrow + headline + description + legend right). Rendered output is **byte-identical** to today so every `/statistics/` page is unchanged.
- **`compact`:** a vertical card — eyebrow (top) → centered ring → legend below the ring. No headline/description. Used two-up in the Statistics modal.

Add an optional **percent-centre** for the Status donut (only meaningful in `compact`): when `center_pct` is set, the centre shows a large percentage (e.g. `100%`) + `center_sub` (`alive`/`dead`) tinted by `center_family` (`green` when all alive, else `red`), instead of the count-up integer. When `center_pct` is unset the centre behaves as today (count-up integer from `center`). The Roles donut uses the integer centre (total roles); the Status donut uses `center_pct`.

Ring geometry, the segment loop, `.lwtv-donut-seg--*` classes, legend rows (swatch · name · bar · `count · pct%`), and the `data-count-to`/`data-grow-to` hooks are unchanged and shared across both layouts. Zero-value segments are omitted from the *ring* but kept in the *legend* at `0 · 0.0%` (this rule applies in both layouts; confirm the current `full` output already drops 0-pct arcs — a 0-pct arc is invisible anyway, so this is not a behavior change).

### 2. Character Statistics modal body (`statistics-actors.php`)

Replace the `echo $stats;` (Chart.js block) + spinner with:

- **Caption row:** "Statistics are updated daily." + a small green status dot + `N characters` (bold), where `N` = character count.
- **Two compact donuts** in a two-up grid (`.lwtv-actor-stats-grid` — flex/grid, `1fr 1fr`, stacking to one column on narrow screens):
  - **Roles** — eyebrow "ROLES"; segments Regular (`pink`), Recurring (`blue`), Guest (`amber`); centre = **total roles** (count-up); legend `count · pct%`.
  - **Status** — eyebrow "STATUS"; segments Alive (`green`), Dead (`red`); centre = dominant share as a big **%** (`center_pct`) in the family color (green if all alive, red otherwise) + `alive`/`dead` sublabel; legend as above.
- **Footnote** (existing italic text) preserved.
- Spinner (`#lwtv-stats-spinner`) removed — the donuts render instantly.

Roles pct = `round(count / total_roles * 100, 1)`; Status pct = `round(count / total_characters_status * 100, 1)`. Guard every divisor.

### 3. Related Articles modal body (`related-articles.php`)

Keep `get_cpt_related_posts()`. Replace the current card markup with:

- **Intro line:** "`N` articles tagged with this actor on the LezWatch.TV blog." (`N` = total tagged; guard singular/plural via `_n()`).
- **Vertical list of article cards** (`.lwtv-article-card`, `.card` surface, ~10px radius), each:
  - a **132×88 featured-image thumbnail** (`the_post_thumbnail`, rounded, `object-fit:cover`) with a **category corner-tag** (primary category) top-left; when there is no thumbnail, a neutral placeholder tile with an image icon;
  - **title** as a pink permalink (16/700);
  - **excerpt** clamped to two lines (`-webkit-line-clamp:2`);
  - a **date** line with a small calendar icon.
  - Whole-card hover lift (`translateY(-1px)` + soft shadow); the title remains the semantic link.
- **Footer link:** "See all related coverage →" (the existing tag-archive "Read More" link, restyled) when `total > max`.

### 4. Count-up JS (`statistics-overview.js`)

Generalize the animation IIFE to expose a scoped runner, e.g. `window.lwtvStatsCountUp( root )`, that animates `[data-count-to]`/`[data-grow-to]` **within `root`** (default `document`). Preserve the existing `DOMContentLoaded` self-run for `/statistics/` pages (call with `document`). On actor pages, hook Bootstrap's `shown.bs.modal` for the Statistics modal to call the runner scoped to that modal, resetting numbers to 0 first so the animation **replays on every reopen**. `prefers-reduced-motion` → jump to final values (as today).

### 5. Enqueues

- Enqueue the count-up JS on actor singular pages (it is not currently loaded there). Follow the existing enqueue pattern; scope to the actor CPT single view.
- Remove the Chart.js enqueue from actor singular pages once the modals no longer emit charts (plan verifies no other `/actor/` feature needs Chart.js). Do not touch the `/statistics/` Chart.js enqueue.

---

## Colors / SCSS

- **New donut segment:** `.lwtv-donut-seg--blue` — `stroke` + `background-color` = `colors.$lwtv-stats-blue` (#0c5460), added to the seg-color group and the `.lwtv-donut-legend-track .progress-bar` legend-override group (mirroring the other seg colors). Recurring uses it. Dark-mode blue flips via the existing `sexuality`/blue dark variants or a matching dark override if the token doesn't already read well on dark (decide in plan against the dark screenshot).
- **Compact donut layout** styles (`.lwtv-donut-card` compact modifier or a `.lwtv-donut--compact` class): vertical stack, centered ring, legend below; the two-up grid `.lwtv-actor-stats-grid`. Percent-centre tint via `center_family` (reuse green/red tokens).
- **Article card** styles (`.lwtv-article-card`, thumbnail, `.lwtv-article-tag`, excerpt clamp, date row, hover lift) using existing tokens. Category-tag colors: News → `$lwtv-pink`, Site → `$lwtv-stats-blue`, Queer Beats → `$lwtv-dkpink` (dark raspberry); a neutral default for other categories.
- All additions use `colors.$lwtv-*` tokens (no hex except deliberate neutral `rgba()` in dark, matching existing patterns). Dark mode via the theme's `color-mode(dark)` / `_colors-dark.scss` `.statistics`-style blocks; extend the dark selector to also cover the modal/article scopes.

## Icons

- Triggers keep `presentation-alt.svg` (Statistics) and `newspaper.svg` (Related Articles).
- Article date → a Symbolicon **calendar** equivalent; empty-thumbnail placeholder → a Symbolicon **image** equivalent. Verify the exact sprite ids exist in the plan; if a needed glyph is missing, fall back to the FA equivalent via `get_symbolicon`'s `icon:` arg — do **not** import Lucide.

## Data / behavior / testing

- Data via the existing builders (`generate_individual_actors($id, 'array', 'roles'|'dead')` unwrapped, or `Build_Actors` `generate_roles`/`generate_dead` directly — finalize the public-facade access in the plan); character count from `lezactors_char_list`. Harden any single-key unwrap (`is_array && ! empty`) and guard every divisor.
- Escape all output; `get_symbolicon` echoes carry the `phpcs:ignore`; i18n `'lwtv'`; `number_format_i18n()`; `_n()` for the article count.
- Rings render at final proportions immediately; numbers/bars count up (~900–1100ms, `easeOutCubic`) on modal open and replay on reopen; `prefers-reduced-motion` → finals.
- Empty char-list / render failure → existing fallback preserved. No characters → 0-value donuts + legends. No articles → 0 intro + empty list, no footer link.
- Gate: `composer lint`; `npm run lint:css`; `npm run buildquick` (**Node 24**). Browser QA on a real actor page (e.g. `/actor/ali-liebert/`): open both modals in light + dark against the four handoff screenshots; confirm count-up replays on reopen, reduced-motion, narrow-screen stacking, Chart.js no longer requested on actor pages, and that `/statistics/` pages (which reuse `donut.php`) are visually unchanged.

## Files

**Modified:**
- `template-parts/overlays/statistics-actors.php` — modal body → caption + two compact donuts + footnote; drop spinner.
- `template-parts/overlays/related-articles.php` — modal body → intro + article cards + footer link.
- `plugins/lwtv-plugin/php/statistics/templates/partials/donut.php` — `layout` option + optional percent-centre (default output unchanged).
- `plugins/lwtv-plugin/assets/js/statistics-overview.js` — expose a scoped count-up runner; keep the `DOMContentLoaded` self-run.
- `scss/addons/_stats.scss` — `.lwtv-donut-seg--blue`, compact donut layout, `.lwtv-actor-stats-grid`, article-card styles.
- `scss/partials/_colors-dark.scss` — dark coverage for the modal/article scopes + blue seg if needed.
- The enqueue class(es) — add count-up JS on actor singular; remove Chart.js there.

**New:** none required (compact donut is a mode of the existing partial). A small helper partial for the compact donut card MAY be introduced in the plan if it keeps `donut.php` clean, but the default is one file with a `layout` branch.

## Open items resolved in the plan

- Exact public-facade access for the roles/dead counts + character count (confirm `generate_individual_actors('array', …)` shape vs. calling `Build_Actors` directly), and where the character-count comes from.
- The exact enqueue hooks: which class registers Chart.js on actor pages, and the actor-single conditional for the count-up JS.
- Confirm the calendar / image Symbolicon sprite ids (or FA fallbacks).
- Whether `donut.php`'s current `full` output already omits 0-pct arcs (it renders a 0-length dash, which is invisible — confirm no legend/ring regression when a segment is 0).
- Whether the compact layout is a `layout` branch inside `donut.php` or a thin `donut.php` → shared ring/legend include; pick the one that keeps the default path byte-identical.
