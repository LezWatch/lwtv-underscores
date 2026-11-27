# Search Settings → Typo tolerance tab redesign

Date: 2026-11-27 · Issue #749 · Branch `release/8.0.0`
Source: `typo-tolerance-handoff.md` (mockups G1 resting, G2 unsaved, G3 applying), outside the repo. It pairs with `2026-11-27-meilisearch-status-tab-design.md`.

## Intent

Make the Typo tolerance tab read like the Status tab. An Indexes rail on the left picks the index. On the right, one settings card (with a sticky Save bar) and a Test card. Every edit stays visible and safe until it's saved.

## Goals / non-goals

- **Goals:** match G1–G3; reuse the Status card, rail and button treatments; keep every behaviour that earlier reviews fixed (listed under Keep); WCAG 2.1 AA; 375px.
- **Non-goals:**
  - No PHP or REST changes.
  - No apply-all.
  - No preview of unsaved settings in Test.
  - No new design for the test results.
  - No change to the page tabs (`SettingsApp.js`).

## Approaches

The rail can get its status in two ways:
- **(a) The typo endpoint's last job per index.** Cheap, already in `GET …/typo`.
- **(b) The Status endpoint's live `typoTolerance` drift.** Truthful, but it makes several Meilisearch calls.

Mika picked **(a)**. The rail and the pill therefore describe the last job, not a live comparison:
- **Running:** clock icon; pill "Applying…" (or "Reindexing…" / "Setting up…" for those job kinds).
- **Failed:** error icon "Apply failed"; red pill "Failed".
- **Done:** check icon "Applied"; green pill "Applied". Set up and Reindex also send the typo settings, so any finished job counts.
- **No job, or cancelled:** no icon and no pill. Nothing is claimed.

## Server rules (they override the handoff)

- **One typo:** a whole number from 1 to 8.
- **Two typos:** a whole number above the one-typo length (and above 1), at most 20. It's checked even while typos are off.
- **Exact words:** up to 100 words of up to 100 characters. The server collapses whitespace, lowercases and de-duplicates them, so the chip input does the same as you type.
- **Exact fields:** chosen only from the index's `attributes`. Fields the index no longer searches show as "removed".
- **Reset** saves the defaults **and applies them immediately**, so it confirms first.
- **Save** stores, then applies; `applied` is `started`, `job_running`, `unreachable`, `not_set_up` or `not_applied`.

## Decisions

- **Layout:**
  - An intro line.
  - A notice slot at the top of the main column.
  - Two columns: the rail (240px) and the main column (`min-width: 0`), with a 20px gap, stacking below 782px.
  - Cards share the Status card styles (moved to top-level `.lwtv-settings__card…` rules).
- **Rail:**
  - A card titled "Indexes", with one button per index (`aria-current="true"` on the selected one).
  - Second line: "Unsaved changes" (for any index with edits, not only the selected one), "Customised" or "Using defaults".
  - The icon is an inline SVG with `role="img"` and an accessible name.
  - The selected index is kept in the URL (`typo_index`) with `replaceState`, so a reload returns to it once the Typo tolerance tab is opened.
- **Switching index never discards edits.** Every index's block stays mounted (the others are `hidden`), so each draft survives. The `beforeunload` guard covers any dirty index.
- **Settings card:**
  - **Header:** the name, the pill, and on the right "Using the defaults" or "Customised".
  - **Sections:** 150px heading plus content. Native checkboxes styled with `accent-color: var(--wp-admin-theme-color)`.
  - **Typo sentences:** inline 4rem number inputs with `aria-label`, checked as you type. Errors appear under the inputs, linked by `aria-describedby` with `aria-invalid`, and they block Save. Server errors show the same way.
  - **Typos off:** the number inputs are disabled and the example is hidden.
  - **Example:** built from the two numbers: (one − 1) letters must be exact, ⌊(one + two) / 2⌋ letters can have one typo, (two + 1) letters can have two. When one typo is 1, it reads "any word can have one typo".
  - **Exact fields:** a `<fieldset>` and `<legend>`, a 3-column grid, monospace labels.
  - **Exact words:** `FormTokenField` with the placeholder "Type a word, then press Enter or a comma", plus a "n of 100" counter. An over-length word isn't added, and an inline message says so.
- **Action bar:**
  - Sticky to the bottom of the viewport.
  - **Status text:** "No changes to save", "{n} unsaved change(s)" (`_n`, counted per field), or "Applying settings to {Index}…". It carries `aria-live="polite"`.
  - **Reset to defaults:** shown when the index is customised or has edits. A `Modal` confirms it.
    - Customised: saves the defaults and applies them now.
    - Defaults with only unsaved edits: just restores the form, with no request.
  - **Save and apply:** primary, enabled only when there are edits and they pass the checks.
- **While a job runs on the index:** the form is disabled at 60% opacity and the card is `aria-busy`. Test is disabled, with "Available once {Index} finishes …". Reset and Save are disabled.
- **Test card:**
  - Titled "Test a search in {Index}".
  - It posts to `lwtv/v1/meili-rules/test` and shows the top three titles, as today.
  - Help text: "Tests what is live on the index." or, with unsaved edits, "Tests the saved settings. Your unsaved changes are not included."
- **Announcements** go through `say()`: save and reset results as today, and "Typo settings applied to {Index}." (or failed) when a polled job ends.

## Keep (from earlier reviews)

- `comparable()` sorts lists before comparing.
- After a save, the draft resets from `next.index.settings`.
- The `beforeunload` guard.
- A reload every time the tab is activated.
- Polling while a job runs; a reload mid-apply restores the Applying state.
- The `not_applied`, `job_running`, `unreachable` and `not_set_up` notices.
- The test route and its results list.

## Left open

- The page tabs don't read the URL, so a reload always opens Status first. The rail remembers the index, not the tab. That needs `SettingsApp.js`.
- No up-front "server unavailable" state (it would need the Status check Mika declined). A save still reports `unreachable`.
