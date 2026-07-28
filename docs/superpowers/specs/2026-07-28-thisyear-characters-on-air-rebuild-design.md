# Design — This Year → Characters On Air rebuild (option 2a)

**Date:** 2026-07-28
**View:** `/this-year/{year}/characters-on-air/`
**File replaced:** `plugins/lwtv-plugin/php/this-year/templates/characters-on-air.php`
**Source of truth:** the design handoff (`design_handoff_thisyear_characters_on_air/`, option `2a`).
**Scope for this pass:** full visuals, **JS filter deferred** (anchor-jump baseline only).

---

## Goal

Make 333+ characters navigable. Replace the three non-actionable trend-callout cards on the
By Name tab with an **A–Z graph that is also the navigation**, followed by a **filterable
directory** with sticky letter subheads. Rework the **By Show** cast lists from trailing-role
pills into scannable rows. The page stays **strictly alphabetical in both tabs** — nothing sorts
by count, popularity, or cast size.

## Non-goals

- No re-ranking. Alphabetical order is the contract.
- No pagination or lazy loading. 333 rows at ~32px is a long page and that is correct for a
  reference list; the graph is what makes it navigable.
- No charting library. The graph is a CSS grid of anchors.
- No new fonts. No new palette values **except** the one dark-mode pink foreground below.

---

## Data — no new join required

`class-display.php` already passes everything:

- `$characters_on_air_count` — headline count.
- `$characters_on_air` — flat list, from `Characters_Builder::get_characters_with_shows_for_year()`.
  Each entry is `{ slug, name, dead, death_years, shows:[ { name, url, type } ] }`.
- `$characters_on_air_by_show` — from `Shows_Builder::get_shows_with_characters_for_year()`.
  Each entry is `{ slug, name, started, ended, characters:[ { character_id, type, dead,
  last_death, name, url } ], nations:[{name}], formats:[{name}] }`.

**Key finding:** the flat list's `shows[n]` already carries `type` (set in
`class-characters-builder.php:479`), even though the current template docblock documents only
`{name,url}`. The handoff assumed `type` lived *only* on the By-Show structure and flagged a
"role join" as the sole risk. That risk does not exist — role data for the directory is derived
directly from `$char['shows'][n]['type']`. **Update the template docblock to document `type`.**

Role `type` values are exactly `regular`, `recurring`, `guest`. Translated labels already exist
(`overview.php:512-514`); reuse that map rather than `ucfirst()`.

---

## By Name tab

### 1. Section head + subtitle — unchanged

`.lwtv-ty-section-head` with `.lwtv-ty-coa-count` (`data-count-to` count-up), the
`_n( 'character on air in %s', ... )` string, the By Name / By Show pills, and the
`.lwtv-ty-section-subtitle`. All kept as-is.

### 2. The A–Z graph — replaces the three callout cards

One full-width card (`border: 1px solid $lwtv-grey-border; border-radius: 14px; padding: 16px
18px 14px`). A **27-column** CSS grid (`A`–`Z` + a trailing `#` bucket), `align-items: end`.

Each column is a real anchor, top to bottom: **count · bar · letter**.

- **Bar height** = `max( 3px, round( count / maxCount × 54px ) )`. Tallest = 54px; graph ~90px
  incl. labels.
- **Fill:** `$lwtv-ltpink` normal; `$lwtv-pink` for peak letter(s); `$lwtv-grey2` for empty
  letters. (No "selected" state this pass — selection is a JS-filter concern.)
- **Empty letters:** count renders as an em dash; label gets `text-decoration: line-through` and
  `$lwtv-medgrey`.
- **Type:** count `9px/700` `$lwtv-medgrey`; letter `11px/700`; bars `border-radius: 3px 3px 0 0`.
  Tabular numerals on counts.
- **Header row:** `JUMP TO A LETTER` as `.lwtv-stats-eyebrow`, and right-aligned `Bar height is
  that letter's share of the {count}` at 11px muted.
- **Footer row** (below a 1px rule): the two tie sentences, now **naming the letters** (e.g.
  "**A** and **M** tie for the most, 35 each" · "**Q**, **Y** and **Z** tie for the fewest, 2
  each"), each `white-space: nowrap`. Right-aligned: the static state line — `{n} letters in use ·
  {list} are empty this year` (no JS "Showing X only — clear" variant this pass).

**Anchors:** every bar is `<a href="#coa-letter-M">` with `aria-label="Jump to M, 35
characters"`. Empty letters get `aria-disabled` and no `href`. This is the whole interaction for
this pass — click jumps to the subhead. Keep a minimum 24px-wide clickable area per column.

**Mobile (<768px):** a 27-column grid is unreadable. Below the breakpoint, `display`-swap the same
anchors into a wrapping row of letter+count chips (`height: 28px; padding: 0 10px; border-radius:
8px`). Same markup, media-query swap only.

#### The `#` bucket (behavior change, approved)

Today's tally skips any name whose first character is not `A`–`Z` (the `preg_match( '/^[A-Z]$/' )`
guard in the template). With callout cards that was invisible; with a graph whose bars should sum
to the headline count, it is not. Add a **`#` bucket** as the 27th column capturing every skipped
initial, so the bars total the headline count and those characters stay reachable via
`#coa-letter-hash` (or similar). If the bucket is empty, render it as a struck tick like any empty
letter. This requires a small addition to the tally loop: alongside `$lwtv_coa_letters`, count
non-Latin initials into a `#` total, and give those characters a `#` subhead in the directory.

**Reuse existing tally vars:** `$lwtv_coa_letters`, `$lwtv_coa_max`, `$lwtv_coa_min`,
`$lwtv_coa_top`, `$lwtv_coa_bottom`, `$lwtv_coa_unused` all stay. Only the markup below them
changes, plus the new `#` count.

#### Removed: the three `.lwtv-trend-callout` cards (approved)

The "Most/Least popular starting letter" and "Unused letters" cards are removed from **this view**.
The graph states all three facts at once and is also the navigation. **Leave the
`.lwtv-trend-callout*` SCSS in place** — other views use it.

### 3. The directory

One card, `overflow: hidden` so subheads clip to the radius.

- **Column head:** `CHARACTER · SHOW · ROLE` at `10px/700/0.08em` uppercase muted on `$lwtv-grey`,
  grid `1.1fr 1fr 108px`, `gap: 14px`, `padding: 9px 18px`.
- **Letter subhead:** `$lwtv-ltpink` bg, `$lwtv-dkpink` text, `11px/700/0.08em` uppercase, letter
  then count. `id="coa-letter-{X}"`, `position: sticky` at the app-header offset. The `#` bucket
  gets its own subhead at the end.
- **Row:** same grid, `padding: 7px 18px`, `border-top: 1px solid $lwtv-grey-border`, ~32px tall.
  - **Character:** `13px/600` link to `/character/{slug}/`, ellipsised.
  - **Show:** `12px` italic muted. Multi-show characters join shows with ` · `, each its own link
    (matches current `.lwtv-ty-charname-shows`).
  - **Role:** a 7px dot + label at `11px/600` muted. Dot colours: regular `$lwtv-green`, recurring
    `$lwtv-dkblue`, guest `$lwtv-medgrey`. **Strongest role shown** (see below), others in the
    row's `title`.
- **Dead characters:** the existing skull symbolicon —
  `lwtv_plugin()->get_symbolicon( svg: 'skull.svg', icon: 'svg-skull', max_size: '15' )` —
  immediately after the name, plus visually-hidden "Died this year" for screen readers.
- **Footnote row:** last row of the card, 12px muted, states the total (e.g. "333 characters, A
  to Z — no pagination").

**Deferred (ships with the JS filter, not now):**

- The **search input** — hidden entirely without JS per the handoff; omit this pass.
- The **role filter chips** (`All roles / Regular / Recurring / Guest`) — inert without JS; omit
  this pass.
- The graph "selected" column state, the "Showing X only — clear" state line, scoped chip counts,
  and the filter empty-state row.

**Forward-compatibility requirement:** emit the markup so the JS layer drops in without
re-architecting — stable `id="coa-letter-{X}"` subheads, a per-row `data-role` (and `data-letter`)
attribute, and a row structure the filter can show/hide. No behavior depends on these this pass;
they are seams.

#### Strongest-role derivation (the one judgment call)

A character on two shows can hold different roles (regular on one, guest on another). The flat
list gives all of a character's shows with their `type`. Derive one role to display, strongest
first, and put the full picture in the row `title`.

- Precedence: `regular` > `recurring` > `guest`.
- Scaffold: a small helper in the template (mirroring `class-breakdowns.php`'s `ROLE_TYPES`
  ordering) that takes `$char['shows']` and returns `[ 'role' => <strongest slug>, 'title' =>
  <human string> ]`. **This 5–10-line body is a user contribution** — precedence + tooltip
  wording ("Regular on Station 19, guest on Grey's Anatomy") is a representation/UX call.

---

## By Show tab

- **Show order unchanged.** Keep the existing `usort` + `$lwtv_ty_coa_sort_key` article-stripping.
  Add a pill above the grid stating it — `Shows A–Z, articles ignored` in `$lwtv-ltpink` /
  `$lwtv-dkpink` — plus the plain-language note that "The Beast in Me" files under B and numeric
  titles like 9-1-1 lead.
- **Card chrome unchanged:** `.lwtv-ty-charshow-head`, count badge (`.lwtv-ty-charshow-count`,
  blue family), meta line (`.lwtv-ty-charshow-meta`, `Nation · Format`).
- **Inside each card — rows, not chips:**
  1. Sort the cast alphabetically by `name` with `strnatcasecmp()`.
  2. One row per character: name link `13px/600` left, role dot + label right-aligned,
     `border-top: 1px solid $lwtv-grey-border` between. `.lwtv-ty-chip` / `.lwtv-ty-chip-role` are
     no longer used by this view — **leave the SCSS**, other views use it.
  3. Dead characters get the same skull + visually-hidden text as the directory.
- **Nameless entries — defensively filtered (deviation from handoff, approved).** The handoff's
  "N more characters have no name recorded" collapse line is **dropped**. Nameless published
  characters were a revision-leak bug already fixed by the `post_status = 'publish'` join in
  `class-shows-builder.php` (lines 318 + 440; see comment at 305-307). The only remaining crack is
  the enhance loop (lines 390-400), which sets `name`/`url` conditionally without dropping an entry
  on a lookup miss. Guard it in the template: `array_filter` the cast on `'' !== trim( name )`
  before rendering. If one ever appears it is a data anomaly to fix at source, not a visitor-facing
  state.
- Two columns desktop (`repeat(2, 1fr)`, 10px gap), one below 768px — as today.

---

## Dark mode — one net-new value (approved)

`_colors-dark.scss` already flips greys, card surfaces, and callout backgrounds. The letter
subheads and the sort pill pair `$lwtv-ltpink` bg / `$lwtv-dkpink` fg; in dark mode the background
inverts to deep plum but `$lwtv-dkpink` stays dark (~2.3:1). Add a **dark-mode pink foreground**
(`#e86bac`, ~5.44:1, matches the existing dark "new shows" family) and apply it to the letter
subhead text and the sort-pill text inside `color-mode(dark)`. The dark overrides for
`.lwtv-ty-charname-row` / `.lwtv-ty-chip` live around `_colors-dark.scss:686-711`. Graph bars keep
`$lwtv-ltpink` (reads as a light tint on the dark card); empty ticks use the dark `--track` grey.

---

## Accessibility & motion

- Every bar, row link keeps a visible focus ring. The graph is a row of small targets, so the ring
  matters; mobile chips give a 28px hit box, desktop columns keep ≥24px clickable width.
- Empty-letter anchors are `aria-disabled` with no `href`.
- Dead marker has visually-hidden "Died this year" text.
- Links hover `$lwtv-purple` per theme.
- `prefers-reduced-motion: reduce` → final count immediately, no count-up (existing behavior).
  There is no other motion in this view; add none.
- All new strings i18n-ready with the `lwtv` text domain.

---

## Files touched

- `plugins/lwtv-plugin/php/this-year/templates/characters-on-air.php` — rebuilt. Keep the
  empty-state guard, the letter tally (+ `#` addition), and the show-sort helper. Update the
  docblock to document `shows[n].type`.
- `scss/addons/_stats.scss` — new `.lwtv-ty-coa-graph*` / directory / By-Show-row styles near the
  existing `.lwtv-ty-charname*` (~L1014) and `.lwtv-ty-charshow*` (~L1054) regions. Do **not**
  remove `.lwtv-trend-callout*` (~L2620) or `.lwtv-ty-chip*` (~L1102).
- `scss/partials/_colors-dark.scss` — add the dark pink foreground (~L686-711).
- `style.css` / `style.min.css` — rebuilt from SCSS via the theme build.

Unchanged: `class-display.php`, `navigation.php`, the builders (data already sufficient).

## Success criteria

1. Graph bars sum to the headline count (the `#` bucket accounts for non-Latin initials).
2. Clicking any in-use letter jumps to its subhead; empty letters are struck and non-interactive.
3. Directory rows show character → show(s) → strongest role, alphabetical under sticky subheads,
   with dead-marker skulls and screen-reader text.
4. By Show cards render alphabetized cast as rows; no bare role pills; nameless entries filtered.
5. Dark-mode subheads and sort pill meet AA contrast via the new foreground.
6. No JS required for any of the above; markup carries the seams for the later filter layer.
7. `composer lint`, `npm run lint:css`, and `npm run lint:js` pass.
