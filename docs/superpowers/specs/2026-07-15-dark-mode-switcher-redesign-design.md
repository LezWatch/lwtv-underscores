# Dark Mode Switcher Redesign — Design Spec

**Date:** 2026-07-15
**Status:** Approved design, pending implementation plan

## Problem

The current dark-mode control (`template-parts/header/dark-mode.php`) is a Bootstrap
dropdown button: a 51px-tall magenta block with a sun icon and a caret, offering
Light / Dark / Auto in a popup menu. It sits in the navbar after the search button.

Two issues:

1. **Placement/feel** — the heavy magenta block with its border reads as bolted-on
   rather than part of the nav.
2. **Interaction** — the current mode is hidden behind a dropdown; you can't see or
   switch state in one glance/tap.

## Goal

Replace the dropdown with an inline **segmented control** that shows all three modes
at once and switches with a single tap, styled to sit naturally in the white navbar.

Keep all three modes: **Light / Dark / Auto** (Auto follows the OS `prefers-color-scheme`).

## Chosen design

A three-segment pill that **floats directly on the white navbar** (no enclosing
grey cell), in the same navbar position it occupies today — after the search button.

- **Segments (left → right):** Light (sun), Dark (moon), Auto (half-circle) — reusing
  the existing SVG sprite icons (`#sun-fill`, `#moon-stars-fill`, `#circle-half`) from
  `template-parts/header/svg.php`.
- **Track:** a light-grey rounded (pill) background so the group reads as one control
  on white.
- **Active segment:** filled **magenta** (`$lwtv-pink`, `#cb3e85`) with a white icon —
  echoing the active nav item's treatment.
- **Inactive segments:** muted dark-grey icons (~`#555`) on the light track, no fill.
- **Height:** aligned to the 50px navbar; the pill itself is smaller and vertically
  centered (not a full-height block like today).

### Behavior

- Clicking a segment sets that theme via the existing color-mode logic
  (`inc/js/bootstrap-color-mode.js`), persists to `localStorage`, and moves the
  filled/active state to the clicked segment.
- First visit with no stored preference: Auto is the effective state (OS preference),
  matching current behavior.
- The switch from OS preference continues to be honored live only while the user is in
  Auto, exactly as the current script does.

### Accessibility

- The control is a group of three `<button>`s, each with an `aria-label`
  (Light / Dark / Auto) and `aria-pressed` reflecting the active state (the current
  script already sets `aria-pressed`).
- Each segment is individually focusable with a visible focus ring consistent with the
  navbar's existing `focus-visible` outline (`3px solid $lwtv-pink`).
- Icons are decorative relative to the labelled buttons.

### Mobile / offcanvas

In the collapsed offcanvas menu (`< lg`), the three segments stack or sit inline with
visible text labels (Light / Dark / Auto), replacing today's single "Toggle Dark Mode"
row. The same buttons/handlers are reused.

## Files in scope

- `template-parts/header/dark-mode.php` — replace dropdown markup with the
  segmented-control markup (three `[data-bs-theme-value]` buttons).
- `inc/js/bootstrap-color-mode.js` — adjust `showActiveTheme()`: it currently swaps a
  `.theme-icon-active use` href and toggles dropdown-menu items. For a segmented
  control there is no single active-icon element, so that lookup must be guarded/removed
  and the active class simply applied to the matching segment. Rebuild
  `bootstrap-color-mode.min.js`.
- `scss/_layout.scss` — replace the `.dark-mode-toggle` block (currently a 51px button)
  with segmented-pill styling (track, active/inactive segments, spacing).
- `scss/partials/_colors-dark.scss` — the `.dark-mode-toggle` dark-mode overrides
  (line ~255) need updating for the new segment markup so the control looks right in
  dark mode too.
- `scss/partials/_responsive.scss` — the `.dark-mode-toggle` / `#search-btn` responsive
  rules (~line 148–160) need review so the segmented control lays out correctly in the
  offcanvas menu.

## Out of scope

- No change to the actual light/dark color palettes (`_colors.scss`,
  `_colors-dark.scss` values) beyond styling the new control itself.
- No change to search button, nav menu, or other header elements.
- No new dependencies; reuse Bootstrap color-mode script and existing SVG sprite.

## Success criteria

- All three modes selectable in one tap; active mode visibly filled magenta.
- Control reads as part of the nav, not a bolted-on block, in both light and dark mode.
- Works in the desktop navbar and the mobile offcanvas menu.
- Keyboard-navigable with visible focus and correct `aria-pressed` state.
- No JS console errors from the removed dropdown/active-icon logic.
