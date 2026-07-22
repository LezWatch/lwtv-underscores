# Statistics on Actors Redesign — Design Spec

**Date:** 2026-07-16
**Status:** Approved (design), pending implementation plan
**Scope:** The Statistics **Actors** section — `/statistics/actors/` and its two sub-views (Sexuality, Gender). A lean mirror of the Characters/Shows redesigns.

## Summary

Replace the pie-chart-per-page pattern for `/statistics/actors/` with the shared shell
(primary tab bar already in the page shell + an Actors sub-nav) and one server-rendered
visualisation per view:

- **Overview** — 3 metric cards + 2 representation callouts + Top Sexual Orientations / Top
  Gender Identities leader/tail panels.
- **Sexuality** — donut (grey Straight + queer raspberry ramp + a labeled Unknown segment + Other).
- **Gender** — donut (grey Cisgender + trans/non-binary ramp + Other).

Driven by existing data (`generate_actors_statistics`, the `lez_actor_sexuality` /
`lez_actor_gender` term counts, `generate_growth_series('actors')`). Server-rendered SVG +
the existing count-up JS; no Chart.js.

## Reuse mandate

Reuse the components + tokens from the Overview/Shows/Characters rounds. NO hardcoded hex; do
NOT revert the user's committed color/size tweaks. Reuse `partials/{donut,sparkline}.php`,
`.lwtv-stats-subnav`, `.lwtv-metric-card`, `.lwtv-tropegap`/`.lwtv-pullstats`,
`.lwtv-panel`+`.lwtv-panel-head`+`.lwtv-leaders`/`.lwtv-leader-*`+`.lwtv-tail`/`.lwtv-tail-*`,
the donut `.lwtv-donut-*`/`.lwtv-donut-seg--*` ramp, and tokens (`$lwtv-stats-*`, `$lwtv-pink`
/`$lwtv-ltpink`/`$lwtv-medgrey`/`$lwtv-bordergrey`, `$ramp-1…$ramp-5`).

## Family map (note the flip vs Characters)

| Dimension | Family / color | Classes to use |
|---|---|---|
| Actors | **yellow** | `.card-header.actors` + `.lwtv-metric-icon.actors` |
| Sexual Orientations | **blue** | `sexuality` family (`.card-header.sexuality`, `.lwtv-metric-icon.sexuality`, `.lwtv-bars--sexuality`, `.lwtv-panel-icon.sexuality`) |
| Gender Identities | **green** | reuse the existing `characters`/green family classes (`.card-header.characters`, `.lwtv-metric-icon.characters`, `.lwtv-bars--characters`, `.lwtv-panel-icon.characters`) — actor gender == green, and the `characters` classes already provide green in light + dark. (Avoids new `actor_gender` family SCSS.) |

## Non-goals

- No redesign of the other sections. No routing/URL/query-var/scoring changes. No new data
  layer. The `actors.php` `roles` switch case stays untouched (not in `$valid_views`, never
  renders).

---

## Architecture

Render path unchanged: `statistics.php` shell → `actors.php` `switch($view)` → per-view
partial.

### Shell

- **Primary tab bar** — already in `page-templates/statistics.php`; marks **Actors** active
  automatically. No change.
- **Actors sub-nav → `actors/subnav.php`** (new; replaces `actors/navbar.php`). Reuse the
  generic `.lwtv-stats-subnav` / `.lwtv-stats-subnav-item` classes. Items: Overview + the two
  `$valid_views` (`sexuality`, `gender`). URLs unchanged.
- **`actors.php`** wraps output in `.lwtv-stats-overview`, includes the sub-nav, keeps the
  `switch($view)` routing; the overview pre-compute already loads `$actor_*` datasets +
  `$top_*` (sliced 10) + `$count_*`.

### Term grouping (needed by donuts + callouts)

Explicit stable-slug groups:
- **Cisgender** = `cis-woman` + `cis-man` + `cisgender`; **gender-unknown** = `unknown` + `undefined`.
- **Straight** = `heterosexual`; **sexuality-unknown** = `unknown`.
- **Openly LGBTQ+** (callout) = `total_actors − straight − sexuality-unknown`.
- **Trans & non-binary** (callout) = `total_actors − cisgender − gender-unknown`.
- Donut ramp slices = the top remaining (non-grouped) terms by count; the rest → "Other".

### Overview view

- Eyebrow "ACTORS AT A GLANCE" + **3 metric cards**:
  - Actors → yellow (`actors` family), icon `user.svg`, **real** sparkline from
    `generate_growth_series('actors')`.
  - Sexual Orientations → blue (`sexuality` family), icon `heart.svg`, representative sparkline.
  - Gender Identities → green (`characters` family classes), icon `venus-double.svg`,
    representative sparkline.
- Eyebrow "WHO PLAYS THE ROLES" + **2 callouts** (`.lwtv-pullstats` 2-up of `.lwtv-tropegap`):
  - **Openly LGBTQ+** → green, icon `rainbow.svg`, big number = LGBTQ+ count (count-up), copy
    naming the share.
  - **Trans & non-binary** → blue, icon `group.svg`, big number = trans/NB count (count-up).
- **2 leader/tail panels** in a 2-equal grid (`.lwtv-panels lwtv-panels--2`):
  - Top Sexual Orientations → blue, icon `heart.svg`, rows from `$top_sexualities`, links
    `/actor_sexuality/{slug}`.
  - Top Gender Identities → green, icon `venus-double.svg`, rows from `$top_genders`, links
    `/actor_gender/{slug}`.
  - Each = 5 leader bars (`data-grow-to` = pct of total actors, guarded) + 5-row tail table
    + footer "View all → {view}". (Straight/Cisgender lead their respective panels.)

### Sexuality donut (`/statistics/actors/sexuality/`)

`donut.php`. Segments (of **all** actors):
- **Straight** = `heterosexual`, class `grey`.
- Top queer terms by count (excluding straight + unknown) → raspberry ramp
  (`dkpink`/`pink`/`mid`/`mid2`/`ltpink`).
- **Unknown** = `unknown`, class `bordergrey` (its own labeled segment — ~34%).
- **Other** = remaining queer tail, class `bordergrey` (or fold into the last ramp step;
  finalize in plan — Unknown stays distinct regardless).
- Centre = total actors. Headline "More than half the actors are straight."

### Gender donut (`/statistics/actors/gender/`)

`donut.php`. Segments (of all actors):
- **Cisgender** = `cis-woman`+`cis-man`+`cisgender`, class `grey`.
- Top trans/non-binary terms by count → raspberry ramp.
- **Other** = remaining tail (incl. the negligible gender-unknown), class `mid2`.
- Centre = the cisgender count. Headline "Nine in ten actors are cisgender."

---

## New SCSS (only two additions)

- **`.lwtv-donut-seg--bordergrey`** — stroke + background `$lwtv-bordergrey`, added to both the
  seg-color group and the `.lwtv-donut-legend-track .progress-bar` override group (for the
  sexuality Unknown/Other faint-grey segment).
- **`.lwtv-panels--2`** — `grid-template-columns: 1fr 1fr` (+ narrow stack), so the two
  overview panels are equal width (the base `.lwtv-panels` is `1.5fr 1fr`).

No new family SCSS (gender reuses `characters`/green; actors=yellow and sexuality=blue
already exist).

## Data / behavior / testing

- `pct = round(count / total_actors * 100, 1)`; guard divisors; harden the single-key unwrap
  (`is_array && ! empty`). Escape all output; `get_symbolicon` echoes carry the `phpcs:ignore`;
  i18n `'lwtv'`; `number_format_i18n()`.
- Count-up + bar/legend grow on the existing JS (donut rings static); reduced-motion → finals;
  dark mode flips via the family classes. Extend the stats-JS enqueue gate to `actors`.
- Gate: `composer lint`; `npm run lint:css`; `npm run buildquick` (**Node 24**). Browser
  verification of all 3 views in light + dark against the handoff screenshots; confirm the
  primary tab bar shows Actors active and other sections still render.

## Files

**New:** `actors/subnav.php`.
**Modified:** `actors.php` (container + sub-nav + overview data: growth series, cis/straight/unknown sums); `actors/{overview,sexuality,gender}.php` (rewrites); `class-stats-enqueues.php` (enqueue on `actors`); `scss/addons/_stats.scss` (`.lwtv-donut-seg--bordergrey`, `.lwtv-panels--2`); `scss/partials/_colors-dark.scss` (if a dark tweak is needed).
**Removed:** `actors/navbar.php`.

## Open items resolved in the plan

- Exact wrapper keys/shapes of `generate_actors_statistics('array', $type)` (verified live:
  wrapper `actors`; slug-keyed `['count','name','url']`).
- Confirm the four metric-card / callout / panel Symbolicon ids render as real `<use>` (user,
  heart, venus-double, rainbow, group) — not `<i>` fallback.
- The representative-sparkline point set (reuse the Shows/Characters one).
- Whether the sexuality "Other" tail merges with Unknown or stays a separate tiny segment.
