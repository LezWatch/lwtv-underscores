# Handoff Spec: Dark Mode Segmented Control

**Source design spec:** Dark Mode Switcher Redesign, 2026-07-15 (approved)
**Repo:** `LezWatch/lwtv-underscores` · branch from `production`
**Status legend:** ✅ = approved in design spec · ⚠️ = proposed here, not in the approved spec — confirm or adjust during implementation

---

## Overview

Replace the Bootstrap dropdown dark-mode toggle in the site navbar with an inline three-segment pill control (Light / Dark / Auto). All three modes are visible and switchable in one tap; the active mode is filled magenta. The control reuses the existing Bootstrap color-mode script (`data-bs-theme-value` buttons, `localStorage` persistence, live `prefers-color-scheme` tracking in Auto) — this is a markup + CSS + minor JS-cleanup change, not a behavior change.

**Why:** the current 51px magenta dropdown block reads as bolted-on, and the active mode is hidden behind a popup. The segmented control makes state glanceable and one-tap switchable.

---

## Layout

- **Position:** unchanged — inside `.dark-mode-toggle` in `navbar.php`, after `#search-btn`. ✅
- **Navbar context:** `.navbar.main-nav` is 50px tall, white (`$white`), `padding: 0`. The pill floats directly on the white navbar with **no enclosing grey cell** and **no left border** (unlike nav `li` items, which have `border-left: 1px solid $lightgrey`). ✅
- **Vertical alignment:** pill is shorter than the navbar and vertically centered. ⚠️ Proposed: pill height **36px**, so `margin: 7px 0` within the 50px bar (or flex-center the wrapper).
- **Horizontal spacing:** ⚠️ Proposed: **12px** margin between the search button and the pill, **16px** right margin to the container edge.
- **Structure:**

```html
<div class="dark-mode-toggle" role="group" aria-label="Color mode">
  <button type="button" class="dark-mode-segment" data-bs-theme-value="light" aria-label="Light" aria-pressed="false">
    <svg class="bi"><use href="#sun-fill"></use></svg>
    <span class="segment-label d-lg-none">Light</span>
  </button>
  <button type="button" class="dark-mode-segment" data-bs-theme-value="dark" aria-label="Dark" aria-pressed="false">
    <svg class="bi"><use href="#moon-stars-fill"></use></svg>
    <span class="segment-label d-lg-none">Dark</span>
  </button>
  <button type="button" class="dark-mode-segment" data-bs-theme-value="auto" aria-label="Auto" aria-pressed="false">
    <svg class="bi"><use href="#circle-half"></use></svg>
    <span class="segment-label d-lg-none">Auto</span>
  </button>
</div>
```

(Class names illustrative. `get_template_part( 'template-parts/header/svg' )` must still be output before the control so sprite refs resolve — keep the existing call in `dark-mode.php`.)

---

## Design Tokens Used

All from `scss/partials/_colors.scss` unless noted. Reference tokens in SCSS, not hex.

| Token | Value | Usage |
|-------|-------|-------|
| `$lwtv-pink` | `#cb3e85` | Active segment fill ✅; focus ring ✅ |
| `$white` | `#fff` | Active segment icon/label ✅; navbar background (light) |
| `$lwtv-medgrey` | `#555` | Inactive segment icon color, light mode ✅ (spec says "~#555" — use the token) |
| `$lightgrey` | `#dedede` | ⚠️ Proposed track background, light mode ("light grey" in spec; alternative `$lwtv-grey` `#f3f3f3` is likely too faint on white) |
| `$lwtv-dkgrey` | `#333` | ⚠️ Proposed track background, dark mode |
| `$lwtv-medgrey` (dark override) | `#404040` | Navbar background in dark mode (`_colors-dark.scss` local override — the track must contrast with this) |
| `$lwtv-grey2` | `#d6d6d6` | ⚠️ Proposed inactive icon color, dark mode |

**Dimensions (all ⚠️ proposed — the design spec gives no measurements):**

| Property | Value |
|----------|-------|
| Pill (track) height | 36px |
| Segment width, desktop | 44px (icon-only; meets 44px touch-target width) |
| Track border-radius | 999px (full pill) |
| Active segment border-radius | 999px, inset 2px from track edge (2px track padding) |
| Icon size | 16px × 16px (current `.bi` is `1em`; fix explicitly) |

---

## Components

| Component | Variant | Props / attributes | Notes |
|-----------|---------|--------------------|-------|
| Segment button ×3 | Light / Dark / Auto | `data-bs-theme-value`, `aria-label`, `aria-pressed`, `type="button"` | `data-bs-theme-value` is the contract with `bootstrap-color-mode.js` — the existing click handlers bind to it unchanged ✅ |
| Segment icon | per mode | sprite refs `#sun-fill`, `#moon-stars-fill`, `#circle-half` from `template-parts/header/svg.php` ✅ | Decorative (`aria-hidden` implied via labelled button); no `<title>` needed |
| Text label | mobile only | `.d-lg-none` span | Visible below `lg` per approved mobile decision (see Responsive) |
| Group wrapper | — | `role="group"` `aria-label="Color mode"` ⚠️ | Replaces the `.form-check.form-switch` wrapper, which should be removed |

**Removed:** `#bd-theme` trigger button, `#bd-theme-text`, `.theme-icon-active`, the `<ul class="dropdown-menu">`, the `#check2` checkmark SVGs, and all `data-bs-toggle="dropdown"` wiring. ✅

---

## States and Interactions

| Element | State | Behavior |
|---------|-------|----------|
| Segment | Active | Magenta fill (`$lwtv-pink`), white icon/label ✅. Exactly one segment active at all times. |
| Segment | Inactive | Transparent on track; icon `$lwtv-medgrey` ✅ |
| Segment | Inactive + hover | ⚠️ Proposed: icon darkens to `$darkgrey` (`#222`); no background change (keeps active state unambiguous) |
| Segment | Active + hover | No change (already selected) ⚠️ |
| Segment | Focus-visible | `outline: 3px solid $lwtv-pink; outline-offset: 2px` ✅ ring per spec; ⚠️ offset 2px proposed (navbar-brand uses 4px — 4px may collide with adjacent segments; verify visually). Ring must be visible on both the grey track and the magenta active fill — if pink-on-pink fails, use a white inner + pink outer double ring ⚠️ |
| Segment | Click/tap | Sets theme via existing script: `setStoredTheme()` → `setTheme()` → `showActiveTheme()`. Active fill moves to clicked segment. ✅ |
| Control | First visit, no stored pref | Auto is the effective state; ✅ **and the Auto segment shows as active** (current script already resolves `getPreferredTheme()` on `DOMContentLoaded` — verify it returns `'auto'` semantics correctly; today with no stored theme it returns `'light'`/`'dark'` from the media query, which would highlight Light or Dark, *not* Auto. This is a behavior decision the spec glosses over — see Edge Cases) |
| OS theme change | While in Auto | Page theme follows live; active segment stays on Auto ✅ |
| OS theme change | While in Light/Dark | Ignored ✅ |

### JS changes (`inc/js/bootstrap-color-mode.js`) ✅

`showActiveTheme()` currently:

1. Early-returns if `#bd-theme` is missing — **that guard will now always fire and break everything.** Re-key the guard on `document.querySelector('[data-bs-theme-value]')` or the group wrapper.
2. Reads `.theme-icon-active use` and swaps its `href` — element no longer exists; **remove** (unguarded, this throws and halts the function).
3. Builds an aria-label from `#bd-theme-text` — element no longer exists; **remove** (each segment now carries its own static `aria-label`).
4. The `active` class + `aria-pressed` toggling across `[data-bs-theme-value]` buttons — **keep as-is**; this is the whole mechanism now.
5. `focus` behavior: currently focuses `#bd-theme` after selection. ⚠️ Proposed: focus the clicked segment instead (or drop the explicit focus — the clicked button already has focus).

Rebuild `bootstrap-color-mode.min.js` after edits. ✅

---

## Responsive Behavior

Note the mismatch: the offcanvas collapses at Bootstrap's `lg` (992px), but `_responsive.scss` rules for this area live in a `@media (max-width: 990px)` block. There's a 2px dead zone; put the new rules at 991.98px (`max-width: 991.98px`, Bootstrap's own `lg` boundary) or accept the existing 990px convention consciously.

| Breakpoint | Changes |
|------------|---------|
| Desktop (≥ 992px) | Icon-only pill, inline in navbar after search button, vertically centered in 50px bar ✅ |
| Offcanvas (< 992px) | **Inline pill with visible text labels** (confirmed decision). Same three buttons/handlers, labels shown via `.d-lg-none`. Replaces the current "Toggle Dark Mode" row. ⚠️ Proposed: segments grow to fit icon + label with 12px horizontal padding; pill left-aligned in the offcanvas body with 16px margin |
| Existing rules to replace | `_responsive.scss` ~148–160: `.dark-mode-toggle .dropdown-menu { position: static }` and `button { padding: 1rem !important }` — both target removed markup; delete and restyle ✅ |

Also check `#masthead .offcanvas-body { height: 50px; margin-top: -1px }` in `_layout.scss` doesn't clip the labeled pill in the open offcanvas.

---

## Dark Mode Appearance

`scss/partials/_colors-dark.scss` (existing `.dark-mode-toggle` overrides at ~line 255 target the old markup — replace). Navbar background in dark mode is `#404040`.

| Element | Light mode ✅/⚠️ | Dark mode (all ⚠️ proposed) |
|---------|------------------|------------------------------|
| Track | `$lightgrey` #dedede | `$lwtv-dkgrey` #333 |
| Active fill | `$lwtv-pink` | `$lwtv-pink` (unchanged — spec's "echo the active nav item" holds in dark mode too) |
| Inactive icon | `$lwtv-medgrey` #555 | `$lwtv-grey2` #d6d6d6 |
| Active icon | `$white` | `$white` |

Contrast check ⚠️: #555 icon on #dedede track ≈ 4.6:1 — passes WCAG graphical-object 3:1. #d6d6d6 on #333 ≈ 8.5:1 — passes. White on #cb3e85 ≈ 3.9:1 — passes 3:1 for icons; note it would *fail* 4.5:1 if the mobile **text labels** ever sit on the magenta fill, but at label sizes ~14px+ bold it also fails AA. Mitigation: labels on the active segment are white-on-magenta only below `lg`; verify final label font size, or bump active fill to `$lwtv-dkpink` (#9e2968, ≈ 6.7:1) on mobile only.

---

## Edge Cases

- **No stored preference (first visit):** spec says Auto is the effective state "matching current behavior" — but the current `getPreferredTheme()` returns `'light'`/`'dark'` (never `'auto'`) when nothing is stored, so the **Light or Dark segment would light up, not Auto**. Decide: either (a) highlight the resolved mode (literal current behavior), or (b) change `getPreferredTheme()` to return `'auto'` when nothing is stored so the Auto segment is active (matches the spec's *intent* and the control's mental model). ⚠️ Recommend (b); it's a two-line change and `setTheme('auto')` already resolves correctly.
- **`localStorage` unavailable** (some private-browsing/embedded contexts): current script doesn't guard `getItem`/`setItem`. Unchanged behavior — out of scope, but don't make it worse.
- **JS disabled / pre-DOMContentLoaded flash:** buttons render with no active state until `showActiveTheme()` runs on `DOMContentLoaded`. Acceptable (matches current). Theme itself is set at parse time, so no color flash.
- **Loading / empty / error states:** N/A — static control, no async data.
- **Long text / i18n:** the strings Light/Dark/Auto are currently hardcoded English (as today). If they're ever run through `__()`, the mobile pill must tolerate longer labels — hence padding-based segment widths, not fixed widths, below `lg`. ⚠️
- **Admin bar:** navbar gets `margin-top: 32px` under `.admin-bar`; no control-specific impact, but include logged-in view in QA.

---

## Animation / Motion

All ⚠️ proposed — the design spec specifies none.

| Element | Trigger | Animation | Duration | Easing |
|---------|---------|-----------|----------|--------|
| Active fill | Segment click | Background-color swap on segments (simple fill toggle — **no** sliding-thumb animation; the fill just moves) | 150ms | ease-out |
| Inactive icon | Hover | Icon color darken | 150ms | ease-out |

Site convention elsewhere is `background 0.3s ease-in-out` (links) — 150ms proposed here because a selection control should feel snappier than a link, but 300ms is fine if consistency wins. Wrap transitions in `@media (prefers-reduced-motion: reduce) { transition: none; }`. ⚠️

---

## Accessibility Notes

- **Focus order:** three tab stops, Light → Dark → Auto, positioned after the search button in DOM order (unchanged position). One tab stop *more* than today's single dropdown trigger — acceptable for three items; do **not** convert to roving-tabindex/radiogroup, the spec explicitly calls for three `aria-pressed` buttons. ✅
- **ARIA:** each button: `aria-label` (Light/Dark/Auto) + `aria-pressed` ✅. Wrapper: `role="group"` + `aria-label="Color mode"` ⚠️. On mobile, visible label text duplicates the `aria-label` — that's fine (label matches accessible name, satisfies WCAG 2.5.3).
- **Keyboard:** Enter/Space activate (native buttons). No arrow-key handling required. ✅
- **Focus ring:** `3px solid $lwtv-pink`, visible in both color modes and on both track and active fill (see States). ✅
- **Icons:** decorative; buttons carry the accessible name. ✅
- **Screen reader announcement:** state change is conveyed by `aria-pressed` flipping on the pressed button — no live region needed. ✅

---

## Files in Scope ✅

| File | Change |
|------|--------|
| `template-parts/header/dark-mode.php` | Replace dropdown markup with segmented-control markup (keep the `svg.php` include) |
| `inc/js/bootstrap-color-mode.js` | Fix `showActiveTheme()` guard, remove `.theme-icon-active` / `#bd-theme-text` logic, keep active-class toggling; rebuild `.min.js` |
| `scss/_layout.scss` | Replace `.dark-mode-toggle` block (51px button, radius 0) with pill styling |
| `scss/partials/_colors-dark.scss` | Replace `.dark-mode-toggle` dark overrides (~line 255) for new markup |
| `scss/partials/_responsive.scss` | Replace `.dark-mode-toggle` rules in the ≤990px block (~148–160); check `#search-btn { display: none }` interaction — search hides below 990px but the pill remains |

## Out of Scope ✅

No palette changes beyond the control itself; no changes to search button, nav menu, or other header elements; no new dependencies.

## Acceptance Criteria ✅

1. All three modes selectable in one tap; active mode filled magenta.
2. Control reads as part of the nav (no bolted-on block) in light **and** dark mode.
3. Works in desktop navbar and mobile offcanvas (inline pill with labels).
4. Keyboard-navigable, visible focus ring, correct `aria-pressed`.
5. No console errors from removed dropdown/active-icon logic (test: click each segment, reload, toggle OS theme while in Auto).
6. ⚠️ Plus, from this doc: first-visit active-segment behavior decided and implemented (Edge Cases), and dark-mode contrast spot-checked on the mobile labeled variant.
