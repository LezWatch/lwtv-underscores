# Meilisearch search rules admin: design

Status: approved for planning (Mika, 2026-11-26). Issue: [#749](https://github.com/LezWatch/lwtv-underscores/issues/749).

## Why

Testing found 19 searches that fail because people type an abbreviation or a different spelling: "tlw", "ds9", "faq", "enby", "kdrama" and so on. Meilisearch fixes these with **synonyms**. Synonyms are editorial knowledge, not code, so Mika wants to manage them from the WordPress admin instead of through deploys. Pinned results and search redirects may come back as needs later, and they belong on the same page. This project builds the page and synonyms, and leaves room for the other two.

## Goals

- A **Search Rules** admin page (LezWatch.TV → Search Rules) for **admins only** (`manage_options`), built as a **React app** with WordPress's own components.
- It edits two kinds of synonym:
  - **Two-way groups:** every word means the same thing ("nonbinary, non-binary, non binary, enby").
  - **One-way:** typing the left side also finds the right side, not the reverse ("tlw" → "the l word").
- **Save and apply** stores the list and applies it to all four indexes (characters, shows, actors, content), then shows the result per index.
- **Export and import** as JSON, so a list built on staging can be copied to production.
- A **starter list** covering the 19 tested cases, offered when the list is empty.
- The storage and page layout have room for **pins** and **redirects** sections later.

## Non-goals

- Pins and redirects themselves. The storage reserves their keys, but nothing reads or renders them yet.
- Editing ranking, searchable fields, stop words or other index settings from the admin. They stay in the repo's `config/*.json`.
- Editor access. It's admins only for now; widening it later is one capability check.
- Different synonyms per index. One list applies to all four, because show names also appear on characters, actors and posts.

## Anti-goals

- A save that silently fails to reach the search server, or that reports success when it didn't.
- `wp lwtv meili settings` or `reindex --fresh` wiping the admin's synonyms. That would happen today, because the repo files have none.
- A bad entry quietly corrupting search. For example, the same word in two two-way groups merges them.
- The indexing key reaching the browser. The app talks to WordPress over REST, and WordPress talks to Meilisearch.

## Approaches considered

1. **A classic PHP admin form.** Smallest, and it matches Search Compare. Not chosen.
2. **An ACF options page with repeaters.** A two-way group needs nested repeaters, the save hooks would be custom anyway, and export is awkward. Rejected.
3. **A React admin app (`@wordpress/scripts`) over a small REST API.** **Chosen by Mika.** It gives the best editing experience, and it's a better base for richer pins and redirects editors later.

## Decided behavior

### Storage

- **One option, `lwtv_meili_search_rules`**, not autoloaded:

  ```
  { version: 1,
    synonyms: { two_way: [ [ "faq", "frequently asked questions" ], … ],
                one_way: [ { from: "tlw", to: [ "the l word" ] }, … ] },
    pins: [], redirects: [],
    applied: { at: <unix>, indexes: { <type>: { status: "applying" | "ok" | "not set up" | "<log-safe error>", task: <taskUid|null> } } } }
  ```

- `pins` and `redirects` are reserved: empty, kept on save, and not shown.
- `applied` records the last attempt to apply the list, with each index's Meilisearch task ID, so the page's status line can be refreshed later.

### Validation and normalizing

This is pure code in `build/`, written test-first. One function takes the raw input and returns the cleaned rules plus a list of errors.

- **Cleaning words:** every word is trimmed, gets internal whitespace collapsed, and is lowercased with `mb_strtolower`. Empty words and empty groups are dropped, and duplicates inside a group are removed.
- **Shape errors:**
  - A two-way group needs at least 2 words.
  - A one-way entry needs a `from` and at least 1 `to`, and `from` can't also appear in its own `to`.
- **Conflicts:**
  - A word that appears in two two-way groups is an **error**, naming both groups.
  - The same one-way `from` used twice is an error.
  - A word that is a stop word in any index is a **warning**, not an error. The repo's stop words are "from", "on", "with" and "in". Meilisearch ignores stop words, so that synonym won't do anything.
- **Limits:** each word is at most 100 characters, with at most 500 groups and 500 one-way entries. Over the limit is an error.
- **Errors block saving. Warnings don't.** Both are returned to the app with the row they belong to.
- **Converting to Meilisearch's format:** this is also pure. A two-way group of n words gives n keys, each mapping to the others. A one-way entry gives one key mapping to its `to` list. Merged keys are de-duplicated, and output is sorted for stable comparisons.

### Applying

- **Apply only `synonyms`.** `Client::update_settings( $uid, array( 'synonyms' => $map ) )` runs for each of the four indexes. Meilisearch merges a partial settings update, so the rest of each index's settings stay untouched.
- **Applying never blocks the request** (*builder's-check call*). `wait_for_task()` is CLI-only.
  - For each index, the save sends one settings update and stores the returned `taskUid` with status "applying". Each update is a single request with a short timeout.
  - `GET` refreshes any "applying" entries with one `get_task()` call each: "succeeded" becomes "ok", "failed" or "canceled" becomes Meilisearch's error code and message (never a key), and an unfinished task stays "applying". The new status is stored.
  - The app polls `GET` every 2 seconds while anything is "applying", for up to 60 seconds. After that it shows "still applying; reload to check".
- **Missing indexes are skipped** (*builder's-check call*). Before applying, each index's `state()` is checked. An index that doesn't exist on this environment gets the status "not set up" and **no** settings update. Meilisearch would otherwise create a bare index with only synonyms, which would defeat the CLI's missing-index guard and let a later plain `reindex` fill an index that lacks the repo's settings. The status line counts only indexes that exist ("Applied to 3 of 3 set-up indexes; content isn't set up here"). Once `settings` or `reindex --fresh` creates the index, it picks up the synonyms through the merge described below.
- **Saving the option doesn't depend on the search server.** If the server can't be reached, the list still saves. The page then says it wasn't applied and offers **Apply again**.
- **The repo settings now merge in the admin synonyms.** `wp lwtv meili settings` and `reindex --fresh` apply the repo file plus the option's synonyms, so neither wipes them.
- **Drift is checked against the option.** `wp lwtv meili status` compares each index's `synonyms` to the option's converted map and reports drift as it does for other settings.
- **A new CLI command,** `wp lwtv meili synonyms apply`, reapplies the saved list to every index, for runbooks and for after a restore.

### REST API

Namespace `lwtv/v1`, route `meili-rules`. Every method requires `manage_options` and the standard REST nonce, which `@wordpress/api-fetch` sends.

- **`GET`** returns `{ rules, applied, warnings, stop_words, indexes: [ { type, uid, label } ] }`.
- **`POST`** takes `{ synonyms }`. It validates, saves if there are no errors, applies the list, and returns `{ rules, applied, errors, warnings }`. With errors it returns HTTP 400, the errors, and saves nothing.
- **`POST /apply`** reapplies the saved list without changing it.
- **`GET /test?q=`** runs one query per set-up index (`limit: 3`, `matchingStrategy: 'frequency'`, only the name or title retrieved), using the search key on the server, and returns the top hits for each. The app uses this for "Test a search".
- **Import** uses the same `POST` with the imported `synonyms`, so it goes through the same validation. **Export** happens in the browser: it downloads `{ version, synonyms }` as `lwtv-search-rules-<env>-<date>.json`. That has no keys or `applied` data in it.

### The app

```
Search Rules
┌ Synonyms ───────────────────────────────────────────────┐
│ Two-way: all of these mean the same thing               │
│  [nonbinary] [non-binary] [non binary] [enby] [+]   🗑  │   ← FormTokenField per group
│  [faq] [frequently asked questions] [+]             🗑  │
│  [ + Add a group ]                                      │
│                                                         │
│ One-way: typing the first also finds the others         │
│  [tlw        ] → [the l word] [+]                   🗑  │
│  [ds9        ] → [star trek deep space nine] [+]    🗑  │
│  [ + Add one ]                                          │
└─────────────────────────────────────────────────────────┘
 [ Save and apply ]  Applied to 4 of 4 indexes · 2 minutes ago
 [ Export ] [ Import… ]   Test a search: [ tlw ] [Test]  → Shows: The L Word · …
```

- **Built from `@wordpress/components`:** Panel, FormTokenField, TextControl, Button, Notice, Spinner, and Modal for confirming an import. Strings go through `@wordpress/i18n` with the `lwtv-underscores` text domain.
- **Errors and warnings show next to the row they belong to.** A summary Notice appears at the top, and its links move focus to the first problem row.
- **Leaving with unsaved changes** gets the browser's "leave site?" prompt.
- **"Test a search"** runs inside the app through `GET /test`, showing the top 3 per index right under the box. It doesn't depend on Search Compare, which calls itself a throwaway page, so nothing breaks if Compare is removed (*builder's-check call*).
- **Starter list:** when the saved list is empty, a Notice offers **Load starter list**. That loads the bundled `config/synonyms-starter.json` into the editor, unsaved, so the admin reviews it and saves. The starter list is the 19 tested cases:
  - **One-way:** tlw, oitnb, pll, svu, ds9, tng, snw, b99, htgawm, ahs, btvs, "the hundred" → their full titles.
  - **Two-way groups:** nonbinary / non-binary / non binary / enby; faq / frequently asked questions; kdrama / k-drama / korean drama; byg / bury your gays / dead lesbian syndrome.
- **Import** reads a JSON file. It shows a summary in a Modal ("12 groups, 30 one-way. This replaces the current list."), and confirming it saves through the same validation.
- **Accessibility:** WCAG 2.1 AA. Every control has a visible label, the delete buttons name their row ("Remove the group faq, frequently asked questions"), and save results and errors are announced through a polite live region. The whole page works with the keyboard alone. Colors come from the WordPress admin's own components.

### Build

- **A new npm workspace,** `plugins/lwtv-plugin/php/meilisearch/admin/`, with its own `package.json` (`@lwtv/meili-admin`). Sources go in `src/`, and `wp-scripts build` output goes in `build/` with `index.asset.php` for dependency extraction. Follow the blocks build exactly: add `build/` to `.gitignore` next to `/plugins/lwtv-plugin/php/blocks/build/*`, and let CI's existing `npm run build:workspace` step build it for deploys (*builder's-check correction*: the blocks build isn't committed).
- **WordPress's bundled packages are externals** (`wp-element`, `wp-components`, `wp-api-fetch`, `wp-i18n`), so no React is bundled.
- **Root `package.json`:** the workspace is added to `workspaces`, and `lint:js:meili` and `fix:js:meili` are added. `build:workspace` already builds every workspace.
- **The page enqueues the build only on its own screen,** with `wp-components` styles. It passes its starting data (REST path and nonce, indexes, stop words) through `wp_add_inline_script` as JSON, so numbers stay numbers.

### Where things go

- **Pure code in `meilisearch/build/`, with PHPUnit tests:**
  - normalizing and validating the rules
  - converting them to Meilisearch's format
  - comparing for drift
  - parsing an import
- **WordPress glue:** an `Admin\Search_Rules` page class (menu, enqueue, the `#root` element), a REST controller, the `apply` helper the REST controller and CLI share, and the CLI changes (`settings` merge, `status` drift, `synonyms apply`).
- **Docs:** architecture and runbook sections for the page (applying, export/import between environments, `synonyms apply`), plus `docs:structure`.

## Left to the builder

- How the components are split inside the app, and how state is handled (`useState` or `useReducer`; no `@wordpress/data` store is needed).
- The exact wording of the error messages.
- Whether the starter list ships as a JSON file or a PHP constant. JSON is preferred.

## Verification

- **PHPUnit** for every pure piece:
  - each validation rule
  - the format conversion (a two-way group of 3 words gives 3 keys, and a one-way entry gives 1 key)
  - drift compares equal regardless of order
  - import parsing rejects files that aren't JSON or have the wrong shape
- **Static checks:** `composer lint`, PHPStan, `npm run lint:js` (including the new workspace), and `npm run build:workspace` building the app.
- **Live on lwtv.local:**
  - Load the starter list, save, and check every set-up index goes from "applying" to "ok" within a few seconds.
  - Check that "tlw", "ds9", "faq", "enby" and "kdrama" now find the right thing with the app's Test box and in the modal.
  - With one index missing (a throwaway filter giving it a uid that doesn't exist), the save reports that index as "not set up", and no index with that uid gets created.
  - `wp lwtv meili status` shows no drift.
  - `wp lwtv meili settings` keeps the synonyms.
  - Put the same word in two groups: the save is refused, and the error shows on both rows.
  - Use "from" in a group: you get a warning, and the save still goes through.
  - With the search server unreachable (a throwaway filter), the save keeps the list and reports "not applied", and **Apply again** works once the server is back.
  - Export a list, then import that file: the list is unchanged.
  - Logged in as an editor, the page isn't in the menu, and the REST route returns 403.
  - Use the whole page with the keyboard alone. The accessibility tree should show labelled controls and announce each save.
