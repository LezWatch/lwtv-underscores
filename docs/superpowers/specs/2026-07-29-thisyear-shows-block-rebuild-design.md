# This Year — Shows block rebuild (On Air / New / Canceled)

**Date:** 2026-07-29
**Branch:** `feat/stats-mobile`
**Design source:** `design_handoff_thisyear_shows_block` (Claude Design), build option `2a`.

## Goal

Rebuild the shared block in `templates/partials/show-block.php`. One file drives **three
views** — Shows On Air, New Shows, Canceled Shows — and each view's **three panes** (By Name /
By Format / By Country).

The group **cards** go away. In their place: the group name hangs in a left gutter and its shows
flow in two balanced columns beside it, so every group is exactly as tall as its content. Above
each pane's list sits a **sticky jump bar** — A–Z for By Name and By Country, three chips for By
Format — and every group carries its own **↑ TOP** link back to that bar.

This follows the repo's `build/ → format/ → templates/` contract and mirrors the two prior This
Year rebuilds (Characters On Air, Dead Characters): pure transform under `build/`, unit-tested in
the no-WP PHPUnit harness first; thin template; theme SCSS with `colors.$lwtv-*` tokens only.

## Non-goals / constraints

- **No JavaScript.** Jump links are pure anchors (work with JS off); nothing filters; the count-up
  already exists in this block and is untouched. This is the key difference from the COA/Dead
  rebuilds, which deferred JS filters — here there is no JS to defer.
- **Zero net-new palette values and no new fonts.** Every colour is an existing `$lwtv-*` token;
  every font size is `rem`; nothing renders below `0.75rem` (12px).
- **Data contract unchanged.** Callers still pass `$sb_by_name` / `$sb_by_format` /
  `$sb_by_country` as `[ groupKey => [ showName => {url,name,country,format,airdates} ] ]`,
  pre-sorted by the builder. No builder or query changes.
- **Preserve existing group order.** By Name and By Country are alphabetical with non-letter
  markers first; By Format stays size-ordered (TV Show first). Multi-country shows keep their full
  meta string (e.g. "Mexico, USA").
- Callout derivation, count-up splice, pill markup, subtitle, footnote, and the three callers'
  empty-state guards are **left exactly as they are**.

## Architecture

### 1. Pure transform — `LWTV\This_Year\Build\Shows_Block`

New file `plugins/lwtv-plugin/php/this-year/build/class-shows-block.php`. Pure array-in / array-out;
no WordPress globals, `$wpdb`, meta reads, output, or i18n (those stay in the template). Unit-tested
first via `tests/unit/This_Year/ShowsBlockTest.php`.

**Why a transform:** the jump-bar model is the only non-trivial logic in the view, and it is exactly
the kind of key-in → model-out derivation the `build/` layer exists for. It also isolates the two
correctness traps (marker-bucket id collisions; by-country first-initial mapping) into tested code.

```
initial_of( string $key ): ?string
```
The uppercase Latin initial of a group key, or `null` if the first character is not A–Z (numeric,
punctuation, or non-Latin script). Shared by both A–Z jump modes. Uses `mb_*` + the same
`/^[A-Z]$/` test as `Characters_On_Air::bucket_for()`.

```
jump_bar( array $group_keys, string $mode ): array
```
Returns an **ordered** list of chip entries `{ label, target, struck }`, where:
- `label` — the text shown on the chip.
- `target` — the **zero-based group index** to anchor to (`g<target>`), or `null`.
- `struck` — `true` when the chip is inert (rendered as a struck `<span>`, no href).

`$group_keys` is the ordered list of the pane's group keys, exactly as the pane will iterate them
(so index N in the returned `target` matches the Nth group the template renders). Behaviour by mode:

- **`name`** — first, one chip per **marker bucket that actually exists** in `$group_keys` (`#`,
  `-`, …), in the order they appear in the keys; each points at its group index, never struck.
  Then A–Z: a letter present among the keys → `{ label:letter, target:index, struck:false }`; a
  letter with no group → `{ label:letter, target:null, struck:true }`.
- **`country`** — A–Z only (no marker chips). For each letter, find the **first** group key whose
  `initial_of()` equals that letter (keys are alphabetical, so the first match is the correct
  landing group) → `target:index, struck:false`; no match → `struck:true`.
- **`format`** — one chip per group key in `$group_keys` order (size-ordered: TV Show first), each
  `{ label:"{key} {count}", target:index, struck:false }`. The count travels *with* the label
  here because there are only three; A–Z modes keep counts off the chips (a counted A–Z bar wraps
  to 4–5 rows when pinned).

Format-chip counts are passed in from the template (which already has `count($shows)` per group), or
`jump_bar` accepts the full groups array for `format` — **decision: pass `$group_keys` plus a
parallel `$counts` map** so the transform stays a pure function of scalars/arrays and the template
keeps ownership of `count()`. Concretely: `jump_bar( array $group_keys, string $mode, array $counts = array() )`
where `$counts` is `[ key => int ]`, consulted only in `format` mode.

**Group-id assignment stays in the template** as the loop index (`g0`, `g1`, …). Do **not** slugify
keys into ids: `#` and `-` both sanitise to empty and collide. `jump_bar`'s `target` is that same
index, so bar and groups stay in lockstep.

### 2. Template — `templates/partials/show-block.php`

Replace the markup emitted by the `$lwtv_sb_render_pane` closure; add a jump bar. Everything outside
the pane rendering (count-up splice, section head + pills, subtitle, callouts, footnote) is unchanged.

The closure needs the **pane slug** (`byname` / `byformat` / `bycountry`) to prefix ids, so its
signature gains that argument. It calls `Shows_Block::jump_bar()` with the pane's group keys (+ counts
for format).

Per pane:

1. **Jump bar** — `<nav class="lwtv-ty-sb-jump" id="{slug}-jump">`:
   - Eyebrow (`.lwtv-stats-eyebrow`): `JUMP TO A LETTER` / `JUMP TO A COUNTRY` / `JUMP TO A FORMAT`.
   - Chips from `jump_bar()`: present → `<a class="lwtv-ty-sb-chip" href="#{slug}-g{target}">label</a>`;
     struck → `<span class="lwtv-ty-sb-chip lwtv-ty-sb-chip--empty">label</span>`.
2. **List** — `<div class="lwtv-ty-group-grid lwtv-ty-group-grid--{accent}">` wrapper kept for the
   accent hook and dark rules; inside, one **row per group** (replaces `.lwtv-ty-group-card`):
   - `<div class="lwtv-ty-sb-row" id="{slug}-g{i}">`
   - **Left gutter** `.lwtv-ty-sb-gutter`: group key (`.lwtv-ty-group-key`, kept class), count
     (`.lwtv-ty-group-count`, kept class, `_n()` "5 shows" / "1 show"), and a bottom-anchored
     `↑ TOP` link `.lwtv-ty-sb-top` → `href="#{slug}-jump"`.
   - **Right column** `.lwtv-ty-sb-shows`: one `.lwtv-ty-sb-item` per show — format dot
     (`.lwtv-ty-sb-dot lwtv-ty-sb-dot--{tv|mini|web}`), wrapping title link, and meta on its own
     line (`.lwtv-ty-group-meta`, kept class). Meta content is the **existing `$lwtv_sb_meta_mode`
     logic**, moved out of the parentheses (name → `Country · Format`; format → country; country →
     format).
3. **Empty state** — unchanged `.lwtv-ty-group-empty` line.

**Per-pane id prefixing is the one real correctness trap:** all three panes are server-rendered into
the DOM simultaneously (Bootstrap pills over `tab-pane`s), so a bare `g0` would exist three times and
a bare `#lwtv-ty-sb-jump` three times. Every group row id, every jump-bar id, and every `href`
target is prefixed with the pane slug so anchors never resolve into a hidden pane.

Format-dot class ↔ format-name mapping: derive from the show's `format` string
(`TV Show`→`--tv`, `Mini-Series`→`--mini`, `Web Series`→`--web`); unknown/empty → no dot. This is a
small presentation map; keep it in the template beside the meta switch.

### 3. SCSS — `_stats.scss` + `_colors-dark.scss`

Within the existing `.lwtv-ty-shows` (or current enclosing) block:

- **Remove** `.lwtv-ty-group-card`, `-head`, `-list`, `-item` (the card grid). Nothing else in the
  repo references them (verified: only this partial + its SCSS use the `lwtv-ty-group-*` family).
- **Keep** `.lwtv-ty-group-grid` (now a vertical stack of rows, not a 2-col grid),
  `.lwtv-ty-group-grid--{blue,pink,amber}` (accent hook), `.lwtv-ty-group-key`,
  `.lwtv-ty-group-count`, `.lwtv-ty-group-meta`, `.lwtv-ty-group-empty` — so the existing
  **dark-mode rules keep applying unchanged** (handoff: reuse, don't add). Restyle their light-mode
  values (e.g. group key `1.45rem` → `1.25rem` Oswald 500).
- **Add**: `.lwtv-ty-sb-jump` (sticky card: `$white` fill, `$lwtv-grey-border` ring, soft shadow,
  `position: sticky`, `z-index: 5`), `.lwtv-ty-sb-chip` (+ `--empty` struck, `$lwtv-grey-medium`),
  `.lwtv-ty-sb-row` (grid `150px 1fr`, gap 22px, bottom rule, `scroll-margin-top`),
  `.lwtv-ty-sb-gutter` (flex column, space-between, right-aligned), `.lwtv-ty-sb-top`,
  `.lwtv-ty-sb-shows` (`columns: 2`), `.lwtv-ty-sb-item` (grid `6px 1fr`),
  `.lwtv-ty-sb-dot--{tv,mini,web}` (dots: `$lwtv-blue-deep` / `$lwtv-purple` / `$lwtv-green`).
- **`scroll-margin-top`**: measure the built bar's height in the browser and use `height + 8px`
  (the handoff's ~132px is a reference for the tallest By Name state at 900px; measure, don't copy).
- **Mobile (<768px)**: `.lwtv-ty-sb-row` → single column (`grid-template-columns: 1fr`), gutter
  collapses to a full-width left-aligned label row above the list, `↑ TOP` moves to the group's end;
  `.lwtv-ty-sb-shows` → `columns: 1`; jump bar scrolls horizontally
  (`overflow-x: auto; scrollbar-width: none`) rather than growing to four rows.
- **Dark mode** (`_colors-dark.scss`): reuse existing `lwtv-ty-group-*` dark rules. Add only:
  jump-bar fill `$lwtv-grey-deep`, chips `rgba($white, 0.16)`, `↑ TOP` hover `$lwtv-pink-medium`,
  format dots → `$lwtv-blue-light` / `$lwtv-purple-light` / `$lwtv-green-light`, and struck jump
  chips scoped to `.lwtv-ty-sb-jump span` keep the light `$lwtv-grey-medium` (`#999`) because the
  dark grey (`#6a5d5d`) is unreadable on `$lwtv-grey-deep`.

## Type & token summary (from handoff)

| Element | Size | Family / weight |
|---|---|---|
| Group key | `1.25rem` | Oswald 500 |
| Show title | `0.95rem` | Open Sans 600 |
| Meta · count · eyebrow · ↑ TOP | `0.75rem` | Open Sans (700 uppercase / 400 meta) |
| Jump chip | `0.812rem` (`font-13`) | Open Sans 700 |

Radii: list + jump bar 14px; jump chips 6px. Chips 28px tall, `min-width: 28px`, `padding: 0 8px`.
Structural values (padding, gap, grid tracks, radii, dot sizes) stay in px per the file's convention.

## Testing

`tests/unit/This_Year/ShowsBlockTest.php`, written **before** the transform (TDD), covering:

- `initial_of`: A–Z letters; numeric key `#` → null; punctuation `-` → null; non-Latin
  (`รักสุดท้าย`) → null; multi-byte lead handled by `mb_*`.
- `jump_bar('name', …)`: marker buckets emitted first, only when present, in key order;
  present letters anchor to the right index; absent letters struck; a bar built from keys
  `['#','-','A','C']` yields `#`→0, `-`→1, `A`→2, `B` struck, `C`→3, `D`–`Z` struck.
- `jump_bar('country', …)`: A–Z; each initial → **first** group with that initial (e.g. keys
  `['Australia','Austria','Belgium']` → `A`→0 not 1, `B`→2, `C` struck); initials with no country
  struck; no marker chips.
- `jump_bar('format', …, $counts)`: one chip per key in given order, label carries the count, none
  struck; empty groups → empty bar.
- Edge cases: empty `$group_keys` → all-struck A–Z (name/country) / empty (format);
  target indices always match iteration order.

Template output, dark-mode contrast, sticky-anchor landing, and the three-view size-independence are
verified **live** in real Chrome (`mcp__claude-in-chrome__*` against `https://lwtv.local/...`), not in
unit tests — per the repo's "pure transforms only" testing rule.

## Verification checklist (before calling it done)

- [ ] `vendor/bin/phpunit` — full suite green, including new `ShowsBlockTest`.
- [ ] `composer lint` clean.
- [ ] `npm run buildquick` (Node 24 via in-shell nvm), then `npm run lint:css` + `npm run lint:js` clean.
- [ ] Live: all three views (Shows On Air ~225, New Shows ~30, Canceled ~30) at real counts —
      layout holds at every size (the size-independence claim).
- [ ] Light / dark / mobile.
- [ ] Jump anchors land the group's key + first rows *below* the sticky bar, not behind it.
- [ ] By Name `#` and `-` chips both present and land on the right groups (no id collision).
- [ ] By Country initials land on the first country of each letter.
- [ ] Anchors never resolve into a hidden pane (per-pane id prefixes hold).

## Files

- **New:** `plugins/lwtv-plugin/php/this-year/build/class-shows-block.php`,
  `tests/unit/This_Year/ShowsBlockTest.php`.
- **Modified:** `plugins/lwtv-plugin/php/this-year/templates/partials/show-block.php`,
  `scss/addons/_stats.scss`, `scss/partials/_colors-dark.scss`, and the generated
  `style.css` / `style.min.css`.
- **Unchanged:** `shows-on-air.php`, `new-shows.php`, `canceled-shows.php` (callers).
