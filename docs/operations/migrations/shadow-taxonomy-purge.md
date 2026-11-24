# Shadow Taxonomy Purge

Phase 6 of [retiring the shadow taxonomy](../../plans/completed/shadow-taxonomy-removal.md). 7.5.0 stopped writing `shadow_tax_characters`, but its rows are still in the database. `wp lwtv links purge-shadow` deletes them.

**This can't be undone.** Back up the database first.

## What it deletes

`shadow_tax_characters` is the only shadow taxonomy. The command deletes, in batches with one transaction per batch:

| Data | Rows on lwtv.local |
|---|---|
| `term_taxonomy` rows for the taxonomy, and their `terms` rows | 7,363 |
| `term_relationships` on those terms (shows, actors, characters, missing posts) | 15,502 |
| `termmeta` on those terms (`shadow_shadow_tax_characters_post_id`) | 7,362 |
| `postmeta` `shadow_shadow_tax_characters_term_id` on characters | 7,360 |
| `aioseo_terms` rows for those terms (skipped if the table doesn't exist) | 282 |

It reports these too, but doesn't change them:

- `lezchars_relationship_chart` postmeta. It holds shadow **term** IDs, which mean nothing once the terms are gone. Only the legacy import reads it, and the links it held are already in `lwtv_char_links` as `character` rows.
- `term_relationships` rows whose taxonomy row is already missing. These have nothing to do with the shadow taxonomy (125 on lwtv.local).

## Safety checks

- **Dry run by default.** Without `--execute`, it only reports.
- **Legacy links.** Before deleting anything, it rebuilds the character-to-character rows that `wp lwtv links rebuild --legacy-characters` would import and compares them with the link table. If any are missing, `--execute` refuses, because the shadow terms are the only copy. Fix it with `wp lwtv links rebuild --legacy-characters`, then run the purge again. The check needs the taxonomy registered, which it still is in 7.5.1.
- **Shared terms.** If any shadow term shares a `term_id` with another taxonomy, it refuses. That would need a manual look.
- **Confirmation.** It asks before deleting unless you pass `--yes`.

If a batch fails, that batch is rolled back. Batches before it stay deleted. Re-run the command to carry on.

## Runbook

Run it on staging first, then production. On each server:

1. **Back up the database.**

   ```bash
   wp db export ~/backups/pre-shadow-purge-$(date +%Y%m%d-%H%M).sql
   ```

2. **Dry run.** Save the output. The legacy line must say `all present in the link table`, and `shared term_ids` must be `0`.

   ```bash
   wp lwtv links purge-shadow | tee ~/shadow-purge-dry-run.txt
   ```

   If it lists missing legacy links, run `wp lwtv links rebuild --legacy-characters` and dry-run again.

3. **Snapshot scores** so you can compare them afterwards.

   ```bash
   wp lwtv score-preview --all > ~/score-before.txt
   ```

4. **Purge.**

   ```bash
   wp lwtv links purge-shadow --execute
   ```

   On lwtv.local this took about 3 seconds. The report it prints afterwards should show `0` for everything marked `delete`.

5. **Check.**

   ```bash
   wp lwtv links purge-shadow
   ```

   ```bash
   wp lwtv score-preview --all > ~/score-after.txt && diff ~/score-before.txt ~/score-after.txt
   ```

   The first should print `No shadow taxonomy data left.`, and the diff should be empty. Then check these pages: a show, an actor, a character with legacy relationships (Ashley Davies, which should still link to Spencer Carlin), `/statistics/` and its Characters, Shows and Actors tabs, and `/characters/` with a FacetWP filter applied. Nothing reads the shadow terms, so nothing should change.

6. If you use an object cache, the command clears the term caches it touched. You don't need a full flush.

**Rollback:** restore the backup from step 1. The code doesn't read the shadow data, so a rollback only matters if a later step turns out to need it.

## Verified on lwtv.local (2026-11-23)

- The dry run matched the counts above. The legacy check passed (101 `character` rows).
- With one legacy row deleted from the table, the dry run listed it (`245 → 292`) and `--execute` refused. `rebuild --legacy-characters` restored it.
- The real run deleted everything in the table above. A second run reported nothing left.
- `score-preview --all` and the FacetWP index row counts were identical before and after. A show, an actor, a character, the four stats pages, `/characters/` (with and without a facet), `/shows/`, `/actors/` and `/this-year/` all returned 200 with the same text. The only differences were a timestamp and Gravity Forms' honeypot label, which changes on every load. `characters_referencing( 245 )` still returns 292.

## After it has run on production

Once production is clean, a later release can finish Phase 6: unregister the taxonomy (`Characters::create_shadow_taxonomies()` and `SHADOW_TAXONOMY`), then remove `rebuild --legacy-characters`, `Character_Links::legacy_holders()`, `legacy_chart_targets()` and their meta constants, and `Link_Rows::legacy_characters()`. After that the legacy check can't run, so `purge-shadow` needs `--skip-legacy-check`, or it can be removed too.
