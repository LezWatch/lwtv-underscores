# Design — This Year → Dead Characters, By Date rebuild (option 2a)

**Date:** 2026-07-28
**View:** `/this-year/{year}/dead-characters/` — **By Date tab only**
**File changed:** `plugins/lwtv-plugin/php/this-year/templates/dead-characters.php`
**Source of truth:** the design handoff (`design_handoff_thisyear_dead_characters/`, option `2a`).
**Scope for this pass:** full visuals, **JS filter deferred** (month bars are no-JS anchor-jumps).

## Goal

Rebuild the **By Date** tab into a deaths-by-month **graph** (jump control) above a **timeline**
list. The list stays strictly in death-date order. The graph states the shape of the year
(deadliest month, months with none) and doubles as a jump target; the timeline draws months as
waypoints, each death as a row (date · name · show · role chip), and empty months as dashed gaps
in an unbroken rail.

## Non-goals

- **Do not touch the By Show tab.** It was already rebuilt to match Characters On Air; it stays.
- No re-ordering — death-date order is the contract.
- No pagination (a memorial list of ~55 rows is correctly a long page).
- No charting library — the graph is a CSS grid of anchors.
- No client-side filtering **this pass** (deferred with the JS layer). No new fonts.
- Aim for **zero net-new palette values** (see Dark mode); introduce one only if a measured
  contrast failure requires it, and flag it first.

## Data — all already present

`class-display.php` passes everything; `Dead_Characters_Formatter` supplies `$dead_by_date`
(already `ksort`ed) and `$dead_by_show`.

- `$dead_characters_count` — headline.
- `$this_year` — the year.
- `$dead_by_date` — keyed by death-date string (`Y-m-d`, a few legacy `Ymd`) → list of
  `{ slug, name, dead, death_years, shows:[{name,url,type}] }`.
- `$dead_by_show` — used only by the (untouched) By Show tab and the Deadliest Show callout.

**Role chip is free:** `type` is on each character's `shows[0]` — no join. Use the same
`regular` > `recurring` > `guest` label casing as the other views (reuse the translated label map
pattern from `overview.php:512-514`).

---

## New pure transform — `LWTV\This_Year\Build\Dead_Characters`

New file `plugins/lwtv-plugin/php/this-year/build/class-dead-characters.php`, registered in
`tests/bootstrap.php`, unit-tested with the pure harness (no WordPress functions inside). Keeps
the template thin and the interleaving logic testable — mirrors `Characters_On_Air`.

Every method derives from `$dead_by_date` so nothing can drift (no separate month-tally loop in
the template). The template stops computing its own `$lwtv_dc_month_tally`.

- `normalize_date_key( string $key ): string` — the single hoisted `Ymd`→`Y-m-d` guard the
  handoff asks for. Used by every consumer below.
- `months( array $dead_by_date ): array` — 12-column model. Tallies deaths per calendar month
  internally (via `normalize_date_key`). Returns an ordered list (Jan→Dec) of
  `{ num, count, peak:bool, empty:bool }`. `peak`/`empty` computed over the 12 months. Graph
  labels + the footer "recorded none" list come from this model in the template (the template maps
  `num`→localized name via `$GLOBALS['wp_locale']`; locale is a WP concern → stays in the template,
  not the pure class). No separate `empty_months()` method — the `empty` flags carry it.
- `longest_stretch( array $dead_by_date ): ?array` — from the normalized, sorted keys, the largest
  gap in days between consecutive deaths. Returns `{ days:int, from:string, to:string }` (ISO
  dates) or `null`. Rules: fewer than 2 dated deaths → `null` (caller drops the card); ties → the
  earliest stretch; never measure from Jan 1 or to Dec 31.
- `timeline( array $dead_by_date ): array` — the ordered render sequence. Walk the normalized,
  sorted dates and emit:
  - a `waypoint` item when the month changes: `{ type:'waypoint', month:int, count:int }`
    (count = deaths that month);
  - a `gap` item for one or more empty months between two waypoints:
    `{ type:'gap', months:int[] }`;
  - a `death` item per character (same-day deaths each get their own item; the date repeats):
    `{ type:'death', date:string, slug, name, shows:[{name,url,type}], role:string }` where
    `role` = `shows[0]['type']`;
  - a final `{ type:'tail', total:int, empty_month_count:int }`.
  The template renders each item; all ordering/interleaving is here and unit-tested.

Label/locale, i18n, and `home_url()` stay in the template.

---

## Structure

Page chrome, headline count-up, pills, and subtitle: unchanged.

### 1. Callouts (`.lwtv-trend-callouts`, keep markup)

Two cards; only the facts change (the graph now states the deadliest month):

| Eyebrow | Copy | Icon |
|---|---|---|
| `DEADLIEST SHOW` | `<em>{show}</em> lost {n} queer character(s).` `_n()` on the count. Existing fallback kept: `No show stands out above the rest.` | `skull.svg` |
| `LONGEST STRETCH` | `{days} days passed without a death, from {Month d} to {Month d}.` | `calendar-alt.svg` |

Keep the existing Deadliest Show derivation (`$lwtv_dc_show_top`/`_max`/`_standout`). The old
**Deadliest Month** card is retired *because the graph replaces it* — if the graph is ever cut,
restore it. **Longest Stretch fallback:** when `longest_stretch()` is `null` (fewer than 2 dated
deaths), drop the stretch card and render Deadliest Show full-width — never print "0 days". Both
icons already exist in the sprite; no new symbolicons.

### 2. Deaths-by-month graph (net-new `.lwtv-ty-dc-graph*`)

12-column CSS grid, `align-items:end`, in a card (`border-radius:14px; padding:16px 18px 14px`).
Each column top→bottom: count · bar · month abbr.

- Bar height `max( 3px, round( count / maxCount × 42px ) )`; graph ~76px with labels.
- Fill: `$lwtv-pink-light` normal; `$lwtv-pink` for peak month(s). Empty months: em-dash count,
  `line-through` + `$lwtv-grey-medium` label, **inert** (`aria-disabled="true"`, no `href`).
- Type: count `10px/700` muted; label `11px/700`; bars `border-radius:3px 3px 0 0`; tabular nums.
- Header row: `DEATHS BY MONTH` `.lwtv-stats-eyebrow`; right, `Click a month to filter the
  timeline` 11px muted.
- Footer row (1px rule above), 11px muted: `{Month} was the deadliest month, {n} deaths` ·
  `{list} recorded none` (each `white-space:nowrap`), then right-aligned the **inert** state as a
  `<span>`: `Showing all 12 months`. (The filtered "Showing April only — clear" state is JS-layer,
  deferred.)
- **Each bar** = `<a href="#lwtv-ty-dc-month-{n}">` with `aria-label="Jump to {Month}, {n}
  deaths"`. No-JS jump to the month waypoint; empty months not focusable. Min 24px hit area.
- **Mobile (<768px):** keep all 12 bars (they fit to ~360px); below 480px drop the count labels if
  they crowd; keep the 24px hit area. (No chip swap — unlike the COA letter graph.)

Uses **new `.lwtv-ty-dc-*` classes**, not the COA 27-column graph classes (different grid + mobile
behavior). Reuse the role-dot color values.

### 3. Timeline (net-new `.lwtv-ty-dc-timeline*`, replaces `.lwtv-ty-deathdate*`)

One card, `padding:6px 20px 18px`. Every row is a `78px 20px 1fr` grid, `gap:12px`
(gutter · rail · content). **The 1px rail must be unbroken from the first waypoint to the tail** —
dashed (not absent) through gap markers. Verify at both zooms and in dark.

- **Month waypoint** — gutter: month name `11px/700/0.08em` uppercase `$lwtv-pink-deep`,
  right-aligned. Rail: 7px pink **square** (2px radius) centred on the line. Content: `{n} deaths`
  / `1 death` 11px muted. `id="lwtv-ty-dc-month-{n}"` here (the graph's jump target).
- **Death row** — gutter: `{Mon d}` `12px/600` muted, tabular, right-aligned. Rail: 9px pink
  **circle** with a 3px card-coloured ring. Content: character link `15px/600/-0.01em` to
  `/character/{slug}/`; beneath, `·`-joined show link(s) in `<em>` 12px muted; then the role chip.
  - **Role chip:** 19px tall, `padding:0 8px`, 6px radius, `$lwtv-grey` fill, `10px/700/0.04em`
    uppercase, 6px dot before the label — regular `$lwtv-green`, recurring `$lwtv-blue-deep`,
    guest `$lwtv-grey-medium`. Role from `shows[0]['type']`.
  - Same-day deaths: one row each, date repeats.
- **Gap marker** — a 44px row between two waypoints spanning ≥1 empty month: rail segment
  `1px dashed $lwtv-grey-border`, content `No deaths in {list}` 11px italic muted. List up to three
  months (`March`, `May or June`, `March, May or June`, Oxford-free `or`); four+ →
  `No deaths for the next {n} months`.
- **Tail row** — rail ends in a grey dot; content the total: `{n} characters, in the order we lost
  them — {m} months recorded none`. (The filtered tail variant is JS-layer, deferred.)
- **No pagination.**
- **Mobile (<640px):** gutter → 62px, name → 14px, keep the rail; date stays in the gutter.

### 4. Deferred JS seams

Emit so the filter drops in with no re-architecting: `id="lwtv-ty-dc-month-{n}"` on waypoints,
`data-month="{n}"` on death rows and gap markers, bars as real anchors. No behavior depends on
these this pass.

---

## Dark mode — measure first, prefer existing tokens

The handoff requests net-new `#e86bac` (month labels) and `#6b2a4c` (unselected bars). In the COA
rebuild the analogous `#e86bac` proved unnecessary (the element was already readable) and we landed
at **zero net-new palette values**; its light-pink bars read fine on the dark card. So:

- **Verify in dark mode in the browser** before adding anything.
- Month waypoint labels (`$lwtv-pink-deep`) on the dark timeline gutter: if contrast is poor,
  prefer an existing lighter token (e.g. `$lwtv-pink-light`, or the dark `$link-color`) over a new
  hex. Only add a value if no existing token clears AA, and flag it.
- Bars: check whether `$lwtv-pink-light` reads on the dark card (it did for COA). Keep peak/pink
  bars as-is.
- Timeline row links follow the theme's dark `$link-color` (`$lwtv-pink-light`, hover
  `$lwtv-ltpurple`) as `_colors-dark.scss` already sets — don't let light-mode `$lwtv-pink` leak
  into dark rows where the character name is primary text.

Any dark override goes in `scss/partials/_colors-dark.scss` inside `@include mixins.color-mode(dark)`
using `colors.$lwtv-…` tokens.

---

## Accessibility & motion

- Every bar and link keeps a visible focus ring; empty months are `aria-disabled`, not focusable.
- The rail/pips are decorative; the meaningful text (date, name, show, role, gap copy) is real text.
- Count-up unchanged (`data-count-to`); `prefers-reduced-motion: reduce` → final count immediately;
  no other motion.
- Links hover `$lwtv-purple`. All new strings i18n-ready, `lwtv` text domain.

---

## Files touched

- `plugins/lwtv-plugin/php/this-year/build/class-dead-characters.php` — **new** pure transform.
- `tests/unit/This_Year/DeadCharactersTest.php` — **new** unit tests.
- `tests/bootstrap.php` — require the new class.
- `plugins/lwtv-plugin/php/this-year/templates/dead-characters.php` — By Date tab rebuilt; `use`
  the transform; callouts updated; keep the empty-state guard and the Deadliest Show derivation.
  Month tally + date normalization now come from the transform (drop the inline tally loop).
  **By Show pane untouched.**
- `scss/addons/_stats.scss` — new `.lwtv-ty-dc-graph*` / `.lwtv-ty-dc-timeline*` styles; leave
  `.lwtv-ty-deathdate*` and `.lwtv-trend-callout*` in place.
- `scss/partials/_colors-dark.scss` — only if the dark measurement requires it.
- `style.css` / `style.min.css` — rebuilt (Node 24.15 via `nvm use`, sourced in-shell).

Unchanged: `class-display.php`, `class-dead-characters-formatter.php`, `navigation.php`, the By
Show pane.

## Success criteria

1. Month bars sum to the headline count; each in-use bar jumps to its month waypoint; empty months
   are struck + inert.
2. Timeline renders in date order with month waypoints, one row per death (role chip from
   `shows[0]`), dashed gap markers for empty months, and a tail total — **rail unbroken throughout**.
3. Callouts: Deadliest Show + Longest Stretch; stretch card dropped (Deadliest Show full-width)
   when fewer than 2 dated deaths.
4. By Show tab visually unchanged from its current state.
5. Dark mode legible; no net-new palette value unless a measured failure forced one (flagged).
6. No JS required; seams present for the later filter.
7. `vendor/bin/phpunit`, `composer lint`, `npm run lint:css`, `npm run lint:js` all pass.
