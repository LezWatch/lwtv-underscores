# Search Settings Status tab redesign: implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task by task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the Status tab as Direction F. On the left is a connection rail (`<dl>`); on the right is an Indexes card (`<ul>`) with share-of-total bars, a facts line and per-row actions.

**Architecture:** This is a rewrite of the `StatusTab` render, plus its `.lwtv-settings__status` styles. The state, the polling, `act()`, `refresh()` and the announcements stay as they are. The only new logic is two small pure helpers in `StatusTab.js`: `share()` and `sourceText()`.

**Tech Stack:** React through `@wordpress/element`, `@wordpress/components` (`Button`, `Notice`, `Spinner`), `@wordpress/icons` (the `update` icon), SCSS, and `wp-scripts` (the workspace `@lwtv/meili-admin`).

**Spec:** `docs/superpowers/specs/2026-11-27-meilisearch-status-tab-design.md`

## Global Constraints

- Touch only `admin/src/settings/StatusTab.js`, the `.lwtv-settings__status` block of `admin/src/settings/style.scss`, and the Status paragraph of `docs/architecture/meilisearch.md`. Another session is editing the rest of the files.
- Never edit `admin/build/`. Never commit, stash, reset or check out; Mika commits at the end.
- Run `nvm use` (Node 24) before any npm command.
- Text domain: `'lwtv-underscores'`. All copy goes through `__()` and `sprintf()`.
- No key values are ever shown. The REST response carries booleans only.
- Text colours, on white: success `#007017`, warning `#996800`, error `#d63638`, secondary `#646970`. The fill is `var(--wp-admin-theme-color, #2271b1)`.
- The breakpoint is 782px.

## Review Focus

1. **Server down:** every index is `unknown` with null documents. Expect total 0, no bars, a "—" count and the red rail header, with no maths on null.
2. **Every index at 0 documents** (fresh setup): there must be no divide by zero. Expect "0 documents" and empty bars.
3. **A job finishes while its Cancel button has focus:** focus stays in that `<li>`, on its first button or else its `<h3>`.
4. **375px:** the rail stacks above the card, the facts wrap, the buttons drop below the facts, and the page doesn't scroll sideways.
5. **A dark admin colour scheme (Midnight):** the bar fill follows the scheme's colour. Text stays on the white cards and passes AA.

Each of these is checked live in Task 2. There's no JS unit harness.

---

### Task 1: Rail, Indexes card and styles

**Files:**
- Modify: `plugins/lwtv-plugin/php/meilisearch/admin/src/settings/StatusTab.js` (the render, from the `if ( error && ! status )` check to the end; `sourceLabel` is replaced)
- Modify: `plugins/lwtv-plugin/php/meilisearch/admin/src/settings/style.scss` (only the `.lwtv-settings__status { … }` block)

**Interfaces:**
- `share( documents: ?number, total: number ) → number`: the percentage, 0–100. It returns 0 when `documents` is null, 0 or below, or when `total` is 0 or below. Otherwise it returns `Math.max( 2, documents / total * 100 )`.
- `sourceText( source: string ) → string`: `constant` gives "wp-config.php", `saved` and `option` give "set on this page", `default` gives "default", and anything else gives `''`.

- [ ] **Step 1: Write the helpers.** Add `share` and `sourceText` at the bottom of `StatusTab.js`, replacing `sourceLabel`.
- [ ] **Step 2: Build the notices above the columns.** Keep the existing dismissible `notice`. Add `Notice status="error" isDismissible={false}` with `status.server.message` when `! status.server.ok`. Move the Action Scheduler text into `Notice status="warning" isDismissible={false}`.
- [ ] **Step 3: Build the rail.** Use `<section className="lwtv-settings__rail">` with a header `<h2>` holding a dot span (`aria-hidden`) and the words "Server available" or "Server unavailable". Then a `<dl>` with the rows Host, Prefix, Indexing key, Search key, Keys differ, Search and Timeout, as in the spec. Sources go in `<span className="lwtv-settings__source">` inside the `<dd>`. The Search mode labels are `{ off: Off, editors: Editors, everyone: Everyone }`. When `search_key_differs` is false, add a `<p className="lwtv-settings__note">`. The footer is `<Button variant="secondary" icon={ update } isBusy={ refreshing } disabled={ busy || refreshing } accessibleWhenDisabled>`, labelled "Refresh status". `refreshing` is new state set only around a manual refresh, which calls `say( 'Status refreshed.' )` on success. The poll's own loads don't.
- [ ] **Step 4: Build the Indexes card.** Use `<section className="lwtv-settings__indexes">`. The header is `<h2>Indexes</h2>` plus `<p>` with `sprintf( '%s documents · bar shows share of total', total.toLocaleString() )`. Then `<ul ref={ list }>`, with one `<li id="lwtv-settings-row-{type}" aria-busy={ jobRunning } onFocus>` per index:
  - **Head:** `<h3 tabIndex={-1}>` with the label, the `<code>` uid, and the count.
  - **Bar:** an `aria-hidden` `<div>` with an inner `style={{ width: share(...) + '%' }}`.
  - **Facts:** a `<ul className="lwtv-settings__facts">` in the order state, "Indexing…", settings, synonyms, job, with modifier classes `is-ok`, `is-warn` or `is-error`.
  - **Actions:** `<div className="lwtv-settings__actions">` with the four existing Buttons and today's conditions. Set up and Reindex add `|| ! status.keys.index_key` to `disabled`. Drop `size="compact"`.
- [ ] **Step 5: Update the focus effect.** It now uses `list.current?.ownerDocument`, and its fallback is `row?.querySelector( 'button' ) || row?.querySelector( 'h3' )`.
- [ ] **Step 6: Write the styles.** Replace the inside of `.lwtv-settings__status` with the spec's layout:
  - `__cols` flex, collapsing to a column at 782px.
  - The postbox cards.
  - A `dl` grid of `9px 12px` rows.
  - The `#f6f7f7` footer.
  - `14px 12px` index rows.
  - A 6px bar.
  - The facts in flex wrap with a 14px gap.
  - The modifier colours.
  - `tabular-nums` on the counts.
  - `rem` font sizes.
- [ ] **Step 7: Lint and build.**
  - Run: `nvm use && npm run lint:js && npm run lint:css && npm run build:workspace`
  - Expected: no errors in this task's files. Errors in other files belong to the parent session; note them and leave them alone.

### Task 2: Live check and docs

**Files:**
- Modify: `docs/architecture/meilisearch.md` (only the `**Status.**` paragraph)

- [ ] **Step 1: Desktop, Default scheme.** Open `https://lwtv.local/wp-admin/admin.php?page=lwtv_meili_settings`. Compare it with the mockup, check that the console has no errors, and confirm that Refresh status announces.
- [ ] **Step 2: Review Focus 1–3.**
  - **Server down:** a fetch override if it's practical; otherwise reason from the code, and say which.
  - **Focus:** start Apply settings or Reindex on Content, focus Cancel, wait for it to finish, and check `document.activeElement`.
- [ ] **Step 3: 375px.** `document.documentElement.scrollWidth <= 375`.
- [ ] **Step 4: A dark admin scheme.** Override `--wp-admin-theme-color` in devtools rather than changing Mika's profile. Check the contrast.
- [ ] **Step 5: Update the docs.** Rewrite the Status paragraph to describe the rail, the Indexes card, the four actions with the new indexing-key rule, and the notices, and link the spec.
