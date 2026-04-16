# SQL optimization: taxonomy character counts

This document describes the **Phase 3** options for further improving performance of bulk character counts by taxonomy term (`lez_stations`, `lez_country`, etc.). Phases 1–2 are implemented in code (see [`plugins/lwtv-plugin/php/statistics/build/class-taxonomy-optimized.php`](../../plugins/lwtv-plugin/php/statistics/build/class-taxonomy-optimized.php)).

---

## Background

### What is being counted?

For each term slug, the site needs:

- **`total`**: `COUNT(DISTINCT character_id)` for published characters who appear on at least one **published show** tagged with that term.
- **`dead`**: Same set, restricted to characters with a non-empty `lezchars_last_death` meta.

The canonical source of truth for “which shows a character appears on” is **`lezchars_show_group`** post meta on the character: a **PHP-serialized** structure stored in `wp_postmeta.meta_value`. The statistics query matches a show ID embedded in that blob using a `LIKE` pattern derived from `shows.ID` (serialized string length + quoted ID).

You **cannot** replace this with a simple `SUM(lezshows_char_count)` across shows in a term: one character on two shows under the same network would be **double-counted** if you summed per-show cached counts.

### What Phase 1 fixed

The original query accidentally **cross-joined** all published characters with all term–show rows before applying the `LIKE`, producing tens of millions of examined rows. Phase 1 reordered joins so `wp_postmeta` rows are found **per show** first, then joined to the character post—eliminating the Cartesian product.

### What still costs CPU after Phase 1

Even with correct joins, each candidate row still evaluates:

```sql
meta_value LIKE CONCAT('%s:', LENGTH(CAST(shows.ID AS CHAR)), ':"', CAST(shows.ID AS CHAR), '";%')
```

That pattern has a **leading wildcard** (`%` before the serialized segment). Standard B-tree indexes on `meta_value` cannot narrow the scan in the general case; the engine may still scan many `meta_key = 'lezchars_show_group'` rows or rely on filtering after index prefix lookups, depending on version, optimizer, and data distribution.

**Phase 3** is about making the relationship **queryable with equality or narrow range predicates** so MySQL can use indexes and/or tiny precomputed aggregates.

---

## When to consider Phase 3

Treat Phase 3 as justified if **production evidence** shows one or more of:

| Signal | Rough interpretation |
|--------|----------------------|
| Slow query log still lists `get_bulk_character_counts` SQL with **high `Rows_examined`** or **multi-second** runtime after Phase 1 deploy | Join fix was necessary but not sufficient |
| **High concurrent cold-cache** traffic on statistics URLs (many PHP workers blocked on the same query) | Need stronger caching or precomputation |
| Plans to add **more taxonomies** or **heavier dashboards** using the same serialized match | Cost scales with use of `LIKE` on `postmeta` |

If Phase 1 reduced runtime enough and transients absorb traffic, Phase 3 can remain deferred.

---

## Phase 3 options (detailed)

Below are four approaches, from “most relational / flexible” to “most operational / denormalized.” You can combine pieces (e.g. link table for truth + nightly aggregate table for reads).

### Option A — Custom link table (character ↔ show)

**Idea:** Maintain a dedicated table of edges, one row per `(character_post_id, show_post_id)` (optionally with extra columns for role, sort order, or “appears” year if needed later).

**Example shape:**

| Column | Type | Notes |
|--------|------|--------|
| `character_id` | `BIGINT UNSIGNED` | `wp_posts.ID` where `post_type = post_type_characters` |
| `show_id` | `BIGINT UNSIGNED` | `wp_posts.ID` where `post_type = post_type_shows` |
| Primary key | `(character_id, show_id)` | Prevents duplicates |
| Secondary index | `(show_id, character_id)` | Reverse lookups by show |

**Query for bulk counts by taxonomy** (conceptual):

1. Join `terms` → `term_taxonomy` → `term_relationships` → `shows` (published shows in scope).
2. **Inner join** the link table on `link.show_id = shows.ID`.
3. Join `characters` on `link.character_id = characters.ID` (published).
4. `LEFT JOIN` death meta for `dead` counts.
5. `GROUP BY term.slug`, `COUNT(DISTINCT character_id)`.

**Pros:**

- **Index-friendly**: equality joins, no `LIKE` on serialized blobs for this path.
- Clear **source of truth** for reporting and future features (graphs, exports).
- Easier to reason about than scanning all of `postmeta`.

**Cons:**

- **Dual maintenance**: every save/update of `lezchars_show_group` must update the link table (or a synchronous rebuild hook).
- Requires **migration** and **backfill** from existing serialized meta.
- Another table to backup and document.

**Implementation notes (WordPress):**

- Create the table with `dbDelta()` on plugin activation or a one-time migration; use `$wpdb->prefix` (e.g. `wp_lwtv_char_show`).
- On `save_post` for characters (and possibly when a show is deleted/trashed), parse or diff `lezchars_show_group` and upsert/delete rows.
- Optional: WP-Cron or WP-CLI job to **reconcile** link table vs meta weekly to catch drift.

---

### Option B — One postmeta row per character–show pair

**Idea:** Keep using `wp_postmeta`, but store **one row per relationship** with a deterministic key or value that supports equality.

Examples:

- **Key pattern:** `lezchars_show_link_{show_id}` with meta value `1` or a small JSON blob.
- **Or** a single meta key `lezchars_show_ids` with a **comma-separated list** of IDs (worse for indexing than separate rows; prefer multiple rows or a normalized table).

**Query:** Join `postmeta` on `meta_key = 'lezchars_show_link_' || show_id` or `meta_value = show_id` with a dedicated key—paired with an index on `(meta_key, meta_value)` or `(post_id, meta_key)` as appropriate.

**Pros:**

- No custom table; stays inside familiar WordPress APIs (`update_post_meta`, etc.).
- Equality predicates possible; avoids full serialized `LIKE` for counts.

**Cons:**

- **Many rows** per character if shows list is large; `postmeta` table grows.
- Must **migrate** from serialized `lezchars_show_group` and keep **both** in sync during transition, or switch editors to new storage.
- Risk of **orphan meta** if not cleaned when shows are removed.

---

### Option C — JSON meta + functional index (MySQL 8.0+)

**Idea:** Store show IDs in a **JSON** column (custom table or future core patterns). Use generated columns and/or **functional indexes** on `CAST(JSON_UNQUOTE(...) AS UNSIGNED)` for show IDs, or `MEMBER OF` / `JSON_CONTAINS` with appropriate indexing.

**Pros:** Modern MySQL can index JSON paths in some workloads.

**Cons:**

- WordPress core still centers on **longtext** `meta_value`; true JSON columns usually mean a **custom table** anyway.
- Higher operational and testing burden; team must standardize on MySQL 8+ and backup/restore behavior.

This is usually **less attractive** than Option A unless you already committed to JSON elsewhere.

---

### Option D — Pre-aggregated statistics table (materialized counts)

**Idea:** A table (or serialized option) keyed by **`taxonomy` + `term_id` or `term_slug`** storing `{ total_chars, dead_chars, updated_at }`, refreshed by:

- **Cron** (hourly/daily), or
- **Hooks** on character save, show save, and term relationship changes.

**Read path:** Single `SELECT` from the aggregate table; no join from characters to shows at request time.

**Pros:**

- **Fastest reads**; predictable load on hot pages.
- Good when statistics pages are read-heavy and slightly stale numbers are acceptable.

**Cons:**

- **Stale data** unless invalidation is perfect or cron is frequent.
- **Complex invalidation**: any change to a character’s shows, death status, or a show’s terms must bump the right term rows (possibly many terms per edit).
- Duplicates logic: must match the **exact** distinct-character semantics across shows in a term.

Often used **together with** Option A: the link table makes recomputation queries cheap and testable.

---

## Comparison matrix

| Criterion | A: Link table | B: Multi meta rows | C: JSON + index | D: Pre-aggregate |
|-----------|---------------|--------------------|-----------------|------------------|
| Read query simplicity | High | Medium | Medium | Highest (read) |
| Index use | Strong | Strong | Depends | N/A (lookup) |
| Write complexity | Medium | Medium | High | Low read / high invalidation |
| Migration effort | Medium–high | Medium | High | Medium |
| Drift risk | Low if hooks solid | Medium | Medium | Medium–high |

---

## Suggested migration outline (Option A + optional D)

1. **Schema:** Add `{$wpdb->prefix}lwtv_char_show` with PK `(character_id, show_id)` and index on `show_id`.
2. **Backfill:** WP-CLI command: iterate characters with `lezchars_show_group`, parse array (reuse PHP `maybe_unserialize` / existing parsing helpers), insert ignore/upsert edges.
3. **Dual-write:** On character save, update serialized meta (existing) **and** link table; run for a release cycle.
4. **Switch reads:** Change `get_bulk_character_counts()` to query via link table; keep `LIKE` path behind a filter flag for rollback.
5. **Cleanup:** Remove dual-write once stable; optional periodic reconcile job.
6. **Optional:** Nightly job to refresh `lwtv_term_char_stats` aggregate table if you add Option D.

---

## Instrumentation and validation

Before and after any Phase 3 rollout:

- Capture **`EXPLAIN ANALYZE`** (or at least `EXPLAIN`) for the new query.
- Compare **per-slug** `total` / `dead` against Phase 1 query output on a **copy** of production data.
- Monitor **slow query log** and **PHP max execution time** on statistics routes.

---

## Related code

- Bulk character counts: `LWTV\Statistics\Build\Taxonomy_Optimized::get_bulk_character_counts()`
- Serialized show group meta: `lezchars_show_group` (see `plugins/lwtv-plugin/php/cpts/class-post-meta.php` and character save flows)
- Show-level cached counts (`lezshows_char_count`) are **not** substitutes for distinct characters per taxonomy term (see plan rationale).

---

## Document history

- **Phase 3** detailed options added for long-term SQL strategy if Phase 1 join fix and caching are insufficient.
