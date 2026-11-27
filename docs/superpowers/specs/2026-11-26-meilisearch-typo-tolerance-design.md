# Meilisearch typo tolerance tab: design

Status: approved by Mika (2026-11-26). Next: the implementation plan (superpowers:writing-plans). Issue: [#749](https://github.com/LezWatch/lwtv-underscores/issues/749). Builds on the Search Settings page (`2026-11-26-meilisearch-settings-page-design.md`). The earlier notes for this tab are in the session memory "Typo tolerance panel".

## Why

Mika wants **the control to hand**: to loosen or tighten how forgiving each index is with misspellings from wp-admin, without a deploy, whenever a search turns up a miss. There's no specific complaint driving it today. The case that started the discussion still stands, though: "alex canvers" doesn't find Alex Danvers. Meilisearch counts a wrong first letter as two typos, and "canvers" is under the 9 letters that two typos need. Also, "bufy" finds nothing, because words under 5 letters get no typos at all.

Mika is fine with the database, not the repo's settings files, being where typo settings live.

## Goals

- A **Typo tolerance** tab on Search → Settings, admins only, with one block of controls **per index** (characters, shows, actors, content). Each block covers:
  - **Allow typos**, on or off;
  - **One typo from N letters** and **Two typos from M letters**;
  - **Never allow typos in these fields** (the index's searchable fields, as checkboxes);
  - **Never allow typos in these words** (a word list);
  - **No typos in numbers**.
- **Save and apply:** saving an index's block stores it and applies it to that index in the background, with no reindex.
- **Test a search** on the tab: after saving, try a query against that index and see its top hits. That's save, then test (Mika, 2026-11-26).
- **Reset to defaults** per index.
- **Shipped defaults match today exactly:** Meilisearch's own defaults on every index, plus shows ignoring typos on `nations`. An environment with nothing saved, or a reset, behaves as it does now.
- The CLI and the Status tab treat the saved settings as part of what each index should have, so they are never reported as drift and never wiped.

## Non-goals

- **A preview before saving.** Meilisearch has no per-search typo option. A true before-and-after would need a throwaway copy of the index for every preview, so save-then-test it is.
- **History or undo** beyond Reset to defaults.
- **Exporting typo settings** with the Rules export. They live in the database, which already travels with a database copy.
- **Other index settings** on the page (ranking rules, searchable fields, stop words). They stay in the repo files.
- **A CLI command to edit typo settings.** The CLI applies them (see below) but doesn't change them.
- **Changing the defaults.** This design ships today's behaviour. Whether to loosen "alex canvers" is Mika's call on the page afterwards.

## Anti-goals

- A deploy, a `wp lwtv meili settings` or a `reindex --fresh` quietly wiping saved typo settings, or putting back the repo's old `nations` rule after Mika changed it.
- A saved override showing up as drift on the Status tab or in `wp lwtv meili status`.
- A save that says "applied" when it wasn't, or a save that's lost because a job happened to be running.
- A bad value (twos before ones, a field that isn't searchable, a 0-letter minimum) reaching Meilisearch.
- Any key reaching the browser.

## Approaches considered

1. **Another tab in the existing Settings screen**, on the same app bundle, the same background Apply job and the same settings merge the CLI already uses for synonyms. **Chosen** (Mika handed over the how, and this is the one that reuses everything).
2. **A separate admin page.** That would duplicate the app shell, the job wiring and the status line for no gain.
3. **Keep the repo file as the source and save only overrides on top.** This was the earlier proposal. Mika chose the database instead, which removes the "which one wins?" question for `typoTolerance` entirely.

## Decided behaviour

### Storage and defaults

- **One option, `lwtv_meili_typo`,** not autoloaded (the front end never reads it):

  ```
  { version: 1,
    indexes: { <type>: { enabled, oneTypo, twoTypos, disableOnWords: [], disableOnAttributes: [], disableOnNumbers } } }
  ```

- **An index with no saved block uses the shipped defaults.** Those defaults are pure code, the source of truth now that `typoTolerance` leaves `config/shows.json`:
  - every index: `enabled: true`, `oneTypo: 5`, `twoTypos: 9`, `disableOnWords: []`, `disableOnAttributes: []`, `disableOnNumbers: false`;
  - shows only: `disableOnAttributes: [ "nations" ]`.
- **`typoTolerance` is removed from `config/shows.json`.** `IndexSettingsTest`'s assertion on it moves to the new defaults test.
- **Reset to defaults** deletes that index's block, then applies.
- **"Using the defaults" compares values, not storage** (*builder's-check call*). A save whose values equal the defaults deletes the block instead of storing it. `is_default` is true whenever the effective values equal the defaults. So a block is only ever stored when it differs, and a later change to the shipped defaults reaches every index that hasn't been changed.

### Validation (pure, test-first)

Bad values are refused, never clamped, with one error per field.

| Field | Rule |
| --- | --- |
| `enabled` | boolean |
| `oneTypo` | integer, 1–8 |
| `twoTypos` | integer, greater than `oneTypo`, at most 20 |
| `disableOnWords` | Strings, each trimmed, inner whitespace collapsed and lowercased, as Rules does. Empties and duplicates dropped. At most 100 words, each at most 100 characters. |
| `disableOnAttributes` | Each must be one of that index's `searchableAttributes` in its repo file; anything else is refused. |
| `disableOnNumbers` | boolean |

- Unknown keys are ignored. A type that isn't one of the four is refused.
- **The Meilisearch payload is built in pure code,** as the full object, so the comparison is exact:

  ```
  { enabled, minWordSizeForTypos: { oneTypo, twoTypos }, disableOnWords, disableOnAttributes, disableOnNumbers }
  ```

### Applying, and staying applied

- **Settings payload:** `Reindexer::settings_payload()`, used by `wp lwtv meili settings`, `reindex --fresh`, Set up and Apply settings, becomes **repo file + synonyms + this index's typo payload**. A deploy or a fresh build therefore keeps what was saved.
- **Drift:** `wp lwtv meili status` and the Status tab compare the live index against that same combination. `typoTolerance` is checked against the saved (or default) settings, not the repo file, so a saved change isn't drift. A change made by hand on the server is.
- **Saving an index's block** stores it, then starts the existing background **Apply settings** job on that index.
  - **The index isn't set up here:** the block is saved, and the response says it will apply when the index is set up (Set up sends it).
  - **A job is already running on that index:** the block is saved, and the response says so. The Status tab then shows that index's `typoTolerance` as differing, with **Apply settings**, so nothing is lost.
  - **The server is unreachable:** the block is saved, and the response says `applied: unreachable`. A job can't start while the index's state can't be read, so the tab shows "Saved, but not applied: couldn't reach the search server. Use Apply settings on the Status tab once it's back." (*builder's-check call*)
- **The tab's status line** is that index's latest job, as the Status tab shows it ("Applied · 2 minutes ago", "Applying…", or the failure).

### REST

Under the existing `lwtv/v1` namespace, with `manage_options` and the REST nonce:

| Route | Does |
| --- | --- |
| `GET meili-settings/typo` | `{ indexes: [ { type, label, settings, defaults, is_default, attributes, job } ], limits: { one_min: 1, one_max: 8, two_max: 20, words_max: 100 } }`. `attributes` is that index's searchable fields from its repo file. |
| `POST meili-settings/typo` `{ type, settings }` | Validate, save, apply. 200 `{ index, applied: started \| not_set_up \| job_running \| unreachable }`; 400 `lwtv_meili_typo_invalid` with `data.errors` by field. |
| `POST meili-settings/typo/reset` `{ type }` | Delete the block, apply. Same response as POST. |

The Test box reuses the existing `GET meili-rules/test?q=` and shows only the selected index's results.

### The tab

```
Search Settings   [ General ] [ Status ] [ Typo tolerance ]

  [ Characters ] [ Shows ] [ Actors ] [ Content ]

  Shows · using the defaults                    [ Reset to defaults ]
  [✓] Allow typos
  One typo from   [ 5 ] letters     Two typos from  [ 9 ] letters
  Never allow typos in these fields:
    [ ] name  [ ] qualifier  [ ] alt_names  [ ] characters
    [ ] actors  [ ] years  [✓] nations
  Never allow typos in these words:   [ token field ]
  [ ] No typos in numbers
  [ Save and apply ]    Applied · 2 minutes ago
  Test a search: [ alex canvers ] [Test]  → Alex Danvers · …
```

- **Index tabs** use the shared `Tabs` component (a second, labelled tablist). Each index's block stays mounted, so unsaved edits survive switching tabs.
- **A one-line explanation** of each control sits under it in plain words. For example: "A wrong first letter counts as two typos, so 'alex canvers' needs two typos at 7 letters or fewer."
- **Errors show by the field;** the save result and the apply are announced in the polite live region.
- **"Using the defaults"** or **"Changed from the defaults"** shows next to the index name. Reset to defaults is disabled when the defaults are already in use.
- **Unsaved changes** get the browser's leave-page prompt, as Rules does.
- **The status line polls** while that index's job runs, with the shared `usePoll`.
- **Strings:** `@wordpress/i18n`, with the `lwtv-underscores` text domain. API text is rendered as text only.
- **Accessibility:** WCAG 2.1 AA, keyboard-only use, visible labels, focus kept when a control disappears.

## Constraints

- Everything the Settings page design lists still holds: PHP 8.1+, WordPress 6.5+, Meilisearch v1.57; admins only; writes go only to this environment's prefixed indexes; SearchWP stays untouched; the app build stays gitignored; nothing is committed without Mika's approval in that turn.
- **On each environment, after deploy:** nothing saved means defaults, and the defaults equal today's settings, so no index changes until someone saves. The runbook says so. `wp lwtv meili status` must show no drift right after the deploy, on an environment that already ran `settings --type=shows` for the nations change.

## Left to the builder

The builder's check raised these, and they're settled as follows (*builder's-check calls*):
- **Comparing the two word lists:** `disableOnWords` and `disableOnAttributes` are compared order-insensitively (sorted), because Meilisearch returns them sorted. Check the order against the live server.
- **When "Allow typos" is off,** the other fields are kept and still sent. Meilisearch ignores them while typos are off, and they come back as they were when typos are turned on again.
- **A saved field that's no longer searchable** (the repo file changed) is left out of the payload. The tab shows it as removed.
- **The CLI `status` line** for a `typoTolerance` mismatch names the Search Settings page, not "the repo file".

- Class and component names, and how the pure code is split.
- The exact copy, including each control's one-line explanation.
- Whether the index tabs show a "changed" marker.
- The phone layout of the tab (stacked is fine).

## Verification

- **PHPUnit** for every pure piece:
  - validation: each rule, including `twoTypos <= oneTypo`, an attribute that isn't searchable, words over the limits, non-booleans and hostile shapes;
  - defaults, including shows' `nations`;
  - the payload shape;
  - `IndexSettingsTest` no longer expecting `typoTolerance` in `shows.json`.
- **Static checks:** the full gate from the Settings plan.
- **Live on lwtv.local** (local_* indexes only):
  - With nothing saved, `wp lwtv meili status` shows no drift, and the tab shows "using the defaults" for all four.
  - Save characters with `twoTypos` 7: "alex canvers" now finds Alex Danvers in the Test box and in the header modal. Reset to defaults: it doesn't, and "using the defaults" is back.
  - Each refused value gets 400, and nothing is saved.
  - Run `wp lwtv meili settings --type=all`, then `reindex --type=content --fresh`. Saved settings survive, and `status` shows no drift.
  - A hand change to an index's `typoTolerance` on the server shows as drift on the Status tab and in `status`, and Apply settings clears it.
  - Save while a job runs on that index: the block is saved, the response says "job running", and Status shows the drift.
  - Keyboard-only use, plus an accessibility-tree check.
  - Editors get 403 from every route.
