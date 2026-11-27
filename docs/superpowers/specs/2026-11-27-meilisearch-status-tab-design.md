# Search Settings → Status tab redesign

Date: 2026-11-27 · Issue #749 · Branch `release/8.0.0`
Source: design handoff "Direction F" (`search-settings-status-handoff.md`, outside the repo) and its mockup.

## Intent

The Status tab shows the same facts as before, but in a form you can read at a glance. A connection rail on the left says whether search can work at all. An Indexes card on the right shows how big each index is relative to the others and whether it's healthy, with that row's actions next to it. Stock WordPress admin look throughout.

**Reader:** admins (`manage_options`), not editors. No progressive disclosure is needed, so the whole rail is shown.

## Goals / non-goals / anti-goals

- **Goals:** match Direction F; keep every current action and state; WCAG 2.1 AA; work in the admin colour schemes (`--wp-admin-theme-color`) and at 375px.
- **Non-goals:** no new REST data (the total and the percentages are derived in the browser); no changes to the tabs (`shared/Tabs.js`), the General tab or the Typo tolerance tab.
- **Anti-goals:** status shown by colour alone; losing Apply settings, Set up or Cancel; a custom button style.

## Approaches

1. **Buttons:** we picked `@wordpress/components` `<Button>` (B) over the core `button button-secondary` classes (A). The other two tabs use it, so the screen stays one app, and `accessibleWhenDisabled` keeps disabled buttons focusable and announced. The cost is a small visual difference from the mockup (32px high, white fill).
2. **Index rows:** we picked a `<ul>` with one `<li>` per index (each with an `<h3>`) over a `<table>`. The row isn't a grid (the full-width bar, a facts line that wraps), so a table would need the current phone hack that turns cells into blocks.

## Decisions

- **Layout:** `.lwtv-settings__status-cols` is a flex row with a 20px gap. The rail is `flex: 0 0 300px`; the indexes card is `flex: 1; min-width: 0`; tops align. Below 782px the two stack, rail first. Cards look like `.postbox`: white, 1px `#c3c4c7` border, no radius, a 14px/600 header with a bottom border.
- **Notices above the columns:** a request or job error (`notice-error`, dismissible, as today), server unreachable (`notice-error` with the server's message), and Action Scheduler missing (`notice-warning`, today's text).
- **Rail header:** a decorative dot plus words: "Server available" (dot `#00a32a`) or "Server unavailable" (dot `#d63638`).
- **Rail `<dl>`, in order:**
  - **Host:** a `<dd>` with the source as a grey second line: "saved on this page", "wp-config.php" or "default". **No Source row.**
  - **Prefix:** the same treatment as Host.
  - **Indexing key**, **Search key:** "Set" (`#007017`, 600) or "Not set" (`#d63638`).
  - **Keys differ:** "Yes" (`#007017`) or "No" (`#996800`), with a short note under it on why it matters: the search key reaches browsers, so it must not be the indexing key.
  - **Search:** Off / Editors / Everyone, with its source line ("set on this page" or "wp-config.php").
  - **Timeout:** "2000 ms".
- **Rail footer:** a full-width secondary "Refresh status" button with a refresh icon. While it runs, the button is disabled and `isBusy`.
- **Indexes header:** "Indexes", then on the right "{total} documents · bar shows share of total". The total sums indexes with a known count.
- **Index row (`<li>`; while a job runs, `aria-busy` sits on the job item only, so the row and its Cancel stay available to screen readers):**
  - **Line 1:** an `<h3>` name (it takes focus as the fallback, `tabIndex -1`), the `<code>` uid, and the count on the right (tabular-nums; "—" when unknown).
  - **Bar:** `aria-hidden`, 6px, a `#dcdcde` track and a `var(--wp-admin-theme-color)` fill. Width = docs ÷ total, with a 2% floor when the count is above 0, and none at 0 or unknown.
  - **Facts line:**
    - State: "Set up" (`#007017`/600), "Not set up" (`#996800`) or "Unknown".
    - "Indexing…" while Meilisearch reports it's indexing.
    - Settings: "Settings match", or "Settings differ: a, b" (`#996800`).
    - Synonyms: "Synonyms match" or "Synonyms differ" (`#996800`).
    - Job: "No active job", or `jobText()` (red when failed, with a spinner while running).
    - Settings and synonyms are hidden when the index doesn't exist.
  - **Actions (right, wrapping under the facts on phones):** Apply settings (on drift), Reindex (when it exists), Set up (primary, when missing), Cancel (while running). Each keeps today's `aria-label` ("Reindex Characters") and disabled rules. **New:** setup and reindex are also disabled when the indexing key isn't set.
- **Announcements:** `say()`, as today (job started, cancelled, finished, failed; poll timeout). **New:** "Status refreshed." after a manual refresh.
- **Focus:** today's rule moves from the table row to the `<li>`. When the focused button disappears, focus goes to the row's first button, otherwise to its heading.
- **Colours:** text colours are checked at 4.5:1 or better on white (`#007017` 6.2, `#996800` 5.6, `#d63638` 4.6, `#646970` 5.7). The bar fill follows the admin colour scheme, with its lightness capped (`oklch(from var(--wp-admin-theme-color) min(l, 0.55) c h)`, plain var as fallback) so it stays at least 3:1 against the track in every core scheme; darker scheme colours, like the default, are untouched.

## Left to the builder

- Exact spacing inside the rows (follow the handoff's `9px 12px` and `14px 12px`).
- Class names under `.lwtv-settings__status`.
- Whether the share maths sits in a small helper inside `StatusTab.js`. There's no JS test harness, so check it live.
