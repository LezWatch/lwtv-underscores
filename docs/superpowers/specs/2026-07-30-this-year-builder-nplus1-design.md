# This Year Builders N+1 / Full-Table-Walk Cleanup — Design

**Date:** 2026-07-30
**Branch:** `feat/continued-stats`
**Status:** In progress — approach revised (see Revision note)

> **Revision 2026-07-30 (during execution):** The base builders `get_shows_for_year()`
> / `get_characters_for_year()` are returned **verbatim by a public REST endpoint**
> (`rest-api/class-this-year-json.php`), so design **A** ("carry an `id` key through the
> returned array") would have changed that public API response and leaked internal post
> IDs. Design A and its `_v2_` cache-key bump are therefore **withdrawn**. Replaced by: a
> private `map_slugs_to_ids()` helper that resolves all slugs in ONE
> `WHERE post_name IN (…)` query (same N+1 elimination, **zero output change, no cache
> versioning**). Sections A and "Cache-shape safety" below are retained for the record
> but no longer describe the implementation. Sections B, C, D are unchanged. The golden
> diff now also captures the REST `one_year()` output so the public surface is protected.

## Context

The `this-year/build/` builders are transient-cached, so on a warm cache they cost
nothing. The problem is the **cold rebuild** (and the background warm that now runs
it): each rebuild does far more DB work than needed. Two patterns dominate, both in
`class-characters-builder.php` and `class-shows-builder.php`:

1. **The ID is fetched then thrown away.** `get_characters_for_year()` and
   `get_shows_for_year()` `SELECT` the post `ID` in their SQL but return arrays keyed
   on `slug`. Every downstream method then re-resolves `slug → ID` with
   `get_page_by_path()` (a DB query) inside a loop:
   - `class-shows-builder.php`: `get_show_id_by_slug()` at `:220`, `:263`, `:545`,
     `:659`, `:753` (5 loops).
   - `class-characters-builder.php`: `get_character_id_by_slug()` at `:377` **and**
     `:465` — the same character resolved **twice** per `get_characters_with_shows_for_year()`.

2. **Per-row meta/term/permalink reads with no primed cache.** Inside the loops:
   - `get_characters_for_year()` (`:77`, `:89`, `:91`): `get_field('lezchars_show_group')`
     per character over **every** character with a show relationship, plus death meta.
   - `get_overview_character_stats()` (`:195`, `:202`, `:226`): `get_field()` ×2 +
     `get_the_terms()` per character over the same full set.
   - `enhance_characters_with_shows()` (`:415`) + `get_show_data_by_ids()` (`:531`):
     `get_field()` per character, `get_permalink()` per show.
   - `get_characters_for_shows_in_year()` (`:334`, `:394`, `:395`) +
     `get_character_data_by_ids()` (`:452`): `get_field()` + `check_character_dead()`
     (`get_post_meta` + `has_term`) + `get_post_meta` + `get_permalink()` per character.
   - `get_shows_for_year_by_name/_by_format/_by_nation()` (`:554-566`, `:668-680`,
     `:762-774`): `get_post_meta` ×2–3 + `get_the_term_list`/`get_the_terms` +
     `get_permalink()` per show.

Two adjacent `statistics/build/` items ride along (folded in per the scope decision):

3. **`get_bulk_first_years()`** (`class-taxonomy-optimized.php:488`) runs two multi-join
   queries with **no transient**, unlike every sibling. Called from nations/stations
   `all.php` with **every** ranked term slug.
4. **`get_data_version_hash()`** (`class-dead.php:1347`) runs `SELECT MAX(post_modified)`
   on **every** call, and is called to build the cache keys at `:362` and `:509` — so
   it fires even on a cache *hit*.

## Goals

Cut the DB round-trips of a cold stats rebuild without changing any output. Every
change is behavior-preserving; correctness is proven by a golden-output diff plus a
query-count measurement on the running site.

## Non-goals

- No SQL-level year pre-filtering on the serialized `appears` / `airdates` meta — that
  would risk silently dropping rows (violates the "don't short-circuit queries" rule).
  We keep the exact PHP filtering and only remove redundant queries around it.
- No TTL changes, no output-shape changes visible to templates, no touching the other
  `get_data_version_hash()` copies in `byq`/`otd`/`export`.
- No new caching strategy — reuse the existing transient wrapper and WP core cache
  priming.

## Design

### A. Carry the ID through (kills all 7 `get_page_by_path` loops)

- `get_characters_for_year()` and `get_shows_for_year()` add `'id' => (int) $row['ID']`
  to each returned element. Purely additive to the array shape.
- Every downstream `get_*_id_by_slug( $x['slug'] )` becomes `$x['id']`.
- `get_character_id_by_slug()` and `get_show_id_by_slug()` become unused → **delete**
  them.

#### Cache-shape safety (why the base keys must be versioned)

A transient decouples the code that *writes* a value from the code that *reads* it, in
time. This deploy changes **both** sides of that boundary at once: the producer
(`get_characters_for_year` / `get_shows_for_year`) starts emitting an `id` key, and the
consumers start *requiring* `$x['id']` while the `get_page_by_path()` fallback that used
to derive it is deleted. So immediately after deploy, new consumer code can read an
array the old producer wrote **before** deploy — one with no `id` key.

Concretely, if `lwtv_characters_year_2024` was built an hour before deploy it still lives
~23h (TTL `DAY_IN_SECONDS`), and under Redis a code deploy does **not** evict it. A
visitor then hits:

```
get_characters_with_shows_for_year(2024)
  └─ get_characters_for_year(2024)        → HIT: OLD-shape array (no 'id')
  └─ foreach character:
        $character_ids[] = $character['id'];   // undefined key → null (PHP warning, not error)
  └─ array_filter($character_ids)          // drops every null
  └─ enhance_characters_with_shows(…, [], …)  // no IDs → no shows attached
```

For shows it is even quieter: the `_by_name/_by_format/_by_nation` loops have
`if ( ! $show_id ) { continue; }`, so a null ID doesn't warn — it just **skips the show**.
The page renders looking normal but **silently incomplete**, and self-corrects only when
each year's base transient expires or is invalidated. The new warm re-warms only the
*current* year, so a visitor browsing an older year could see stale, lossy output for up
to a day. This is a silent correctness regression on a site whose priorities explicitly
include statistics accuracy — the worst failure class, because nothing pages you.

**Fix:** bump only the two base cache keys — `lwtv_characters_year_` →
`lwtv_characters_year_v2_` and `lwtv_shows_year_` → `lwtv_shows_year_v2_`. New consumers
look up a key that does not exist yet → guaranteed miss → the new producer rebuilds it →
writes new-shape data. There is no instant at which new code reads old-shape data,
because it never reads the old key. Both new keys still start with the tracked prefixes
(`lwtv_characters_year_*`, `lwtv_shows_year_*` in `Transients::extra_stats_patterns()`),
so invalidation and warming still cover them; orphaned old keys are read by nobody and
expire within a day.

Rejected alternatives: a `$x['id'] ?? get_page_by_path(...)` fallback keeps the method we
mean to delete and leaves a transition-only code path forever; flushing transients on
deploy works but is an operational step that a single forgotten deploy turns back into
the silent bug. Versioning needs no deploy action and cannot be forgotten. Note the
golden-output diff below does **not** cover this: it runs on a controlled dev cache and
proves output-equivalence of the new code, not the deploy-time transition — the key bump
is what closes that gap.

Derived caches (`lwtv_shows_characters_year_*`, `lwtv_shows_year_by_name_*`, etc.) are
**not** bumped: their output shape is unchanged, and a stale one holds correct (old) data
until it refreshes.

### B. Prime caches before each N+1 loop

Before each loop, collect the relevant IDs and prime the WP object cache once, so the
per-row `get_field` / `get_post_meta` / `get_the_terms` / `get_permalink` calls become
in-memory hits instead of individual queries. Use the **minimal** primer per loop:

| Loop | Reads | Primer |
|------|-------|--------|
| `get_characters_for_year` | meta only | `update_meta_cache( 'post', $ids )` |
| `get_overview_character_stats` | meta + gender terms | `update_meta_cache(...)` + `update_object_term_cache( $ids, 'post_type_characters' )` |
| `enhance_characters_with_shows` (char loop) | meta | `update_meta_cache( 'post', $character_ids )` |
| `get_show_data_by_ids` | permalinks | `_prime_post_caches( $show_ids, false, false )` |
| `get_characters_for_shows_in_year` (candidate loop) | meta | `update_meta_cache( 'post', $candidate_character_ids )` |
| `get_characters_for_shows_in_year` (matched loop) | meta + `dead` term | `update_meta_cache(...)` + `update_object_term_cache( $matched_ids, 'post_type_characters' )` |
| `get_character_data_by_ids` | permalinks | `_prime_post_caches( $character_ids, false, false )` |
| `get_shows_for_year_by_name/_by_format/_by_nation` | posts + meta + terms | `_prime_post_caches( $show_ids, true, true )` |

`$ids` for the two full-set character loops come straight from the SQL result rows
(the queries already return `c.ID`). No behavior changes — the same reads happen, just
served from a warmed cache.

### C. `get_bulk_first_years()` — add caching

Wrap in a transient exactly like its siblings (`get_bulk_top_shows`, etc.):
`$key = 'bulk_first_years_' . $taxonomy . '_' . md5( wp_json_encode( $slugs ) )`,
`DAY_IN_SECONDS`, early-return on hit. The result is a deterministic map of
`slug => year`, so it caches cleanly.

### D. `get_data_version_hash()` (class-dead.php) — request-memoize

Function-static memo so the `SELECT MAX(post_modified)` runs once per request instead
of per call, and null-harden the hash:

```php
public function get_data_version_hash() {
    static $hash = null;
    if ( null !== $hash ) {
        return $hash;
    }
    global $wpdb;
    $last_modified = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT MAX(post_modified) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
            'post_type_characters'
        )
    );
    $hash = md5( (string) $last_modified );
    return $hash;
}
```

Within a single render this value is stable, so memoizing it does not change any cache
key. (A save mid-request is irrelevant — the whole point is a stable per-request key.)

## Components touched

- `plugins/lwtv-plugin/php/this-year/build/class-characters-builder.php` (A + B)
- `plugins/lwtv-plugin/php/this-year/build/class-shows-builder.php` (A + B)
- `plugins/lwtv-plugin/php/statistics/build/class-taxonomy-optimized.php` (C)
- `plugins/lwtv-plugin/php/statistics/build/class-dead.php` (D)

## Error handling / edge cases

- `update_meta_cache` / `update_object_term_cache` / `_prime_post_caches` accept an
  array of ints and no-op on an empty array — guard each with an `empty()` check so we
  never call them with `array()`.
- IDs already flow through `array_filter()` / `array_unique()` where nulls are possible;
  keep those guards.
- `get_data_version_hash()` static memo is per-request/per-process, correct for both web
  requests and the WP-CLI warm.

## Testing

These are WordPress-glue methods (ACF, meta, terms, `$wpdb`), so they are **not** unit-
testable in the pure harness. Correctness is proven live on `lwtv.local`:

1. **Golden-output diff (the safety net).** Before any change, dump the output of every
   affected public method for a representative year (e.g. 2024) — and the nations/stations
   `get_bulk_first_years()` and the dead `generate_years_data`/`build_list` outputs — to a
   baseline JSON. After the change, dump again and assert **byte-identical** (order
   included; these methods sort deterministically).
2. **Query-count drop.** With `SAVEQUERIES`, count queries for a cold rebuild of the same
   methods before vs after; expect a large reduction (no `get_page_by_path` per row, no
   per-row meta/term/permalink queries).
3. **Full existing suite** (`vendor/bin/phpunit`) stays green; **`composer lint`** clean.

## Rollout

Pure code; the only migration concern is the two bumped base cache keys, which self-heal
via the existing warm/invalidation. Deploy to `development` first; confirm the golden
diff and query drop there.
