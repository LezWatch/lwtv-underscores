# Shadow taxonomy vs. link table: lwtv.local diff (2026-11-09)

This is the Phase 0 baseline from `wp lwtv links diff`. It was run on lwtv.local after `wp lwtv links rebuild` and `wp lwtv links rebuild --legacy-characters`, with the read source still set to `shadow`.

## Table contents after rebuild

| object_type | rows |
|---|---|
| show | 7,586 |
| actor | 7,818 |
| character | 101 |

The rebuild synced 7,362 characters in about 2.5 seconds, with peak resident memory around 228 MB. A second run changed nothing (15,505 rows before and after). The legacy import sent 199 `character` rows, which collapsed to 101: 98 legacy links are recorded from both sides.

## Diff results

Every mismatch is a link the table has and the shadow taxonomy is missing. Every show and actor mismatch is one ACF agrees with (`acf=yes`). Nothing is missing from the table.

| Report | Objects | Mismatched | Direction |
|---|---|---|---|
| show | 2,266 | 2 | all extra in table, `acf=yes` |
| actor | 6,109 | 4 | all extra in table, `acf=yes` |
| character | 7,357 | 1 | extra in table |

### Show

| Show | Character missing from the shadow taxonomy |
|---|---|
| 2213 Coronation Street | 2328 |
| 2316 Emmerdale | 89754 Lisa Swain |

### Actor

| Actor | Characters missing from the shadow taxonomy |
|---|---|
| 14080 Unknown | 97025, 97033, 97036, 97071 |
| 24395 María Almudéver | 90496 |
| 71770 Nava Mau | 90615 |
| 90589 Ayesha Harris | 91735 Vanessa Sampson |

### Character

| Character | Linked character missing from the shadow read |
|---|---|
| 89774 Naomi | 89777 Jordan Forster |

Jordan's `lezchars_relationship_chart` lists Naomi's term, but Naomi's term was never attached to Jordan's post. The shadow read of Naomi can only see characters that hold her term, so it misses Jordan. The table stores the link from the chart and returns it from either side.

## Finding during this run: the chart stores term IDs

All 101 `lezchars_relationship_chart` values are shadow **term** IDs, not character post IDs. It was a CMB2 select2 field over `shadow_tax_characters`. Before the fix, the import would have stored term IDs as `object_id`. `Character_Links::legacy_chart_targets()` now maps each one to its character post, and both the read path and the import use it.
