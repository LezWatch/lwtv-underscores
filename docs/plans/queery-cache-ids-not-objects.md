# Handoff: stop caching WP_Query objects in the Queeries layer

**Repo:** `LezWatch.TV` (plugin under `plugins/lwtv-plugin/`).
**Scope:** `plugins/lwtv-plugin/php/queeries/` — the four classes that cache a whole `WP_Query` into a transient. Replace the cached payload with an array of post IDs, which is all any caller actually uses.

## Why

`Queeries\Post_Meta::make()` (and its three siblings) run a `WP_Query`, then:

```php
lwtv_plugin()->set_transient( $cache_key, $query, 30 * MINUTE_IN_SECONDS );
return $query;
```

Serialising a `WP_Query` stores the query vars, the SQL string, **and every matched `WP_Post` object**. Three consequences:

1. **Size.** A result set of a few hundred posts becomes a large blob per cache key. Under Redis every read pulls and unserialises the lot.
2. **Detached objects.** The unserialised `WP_Post` objects bypass WordPress's own post cache, so later `get_post_meta()` / `get_the_title()` calls on them can't benefit from a primed cache.
3. **It can't be invalidated.** The key is an md5 of the call arguments, giving one key **per show** (`lezchars_show_group`), **per actor** (`lezchars_actor`), **per date** (`lezactors_birth`) and **per IMDb ID and per Q-ID** (the two WikiData REST endpoints). `post_meta_*` is therefore deliberately absent from `Transients::get_cache_dependencies()` — see the comment there. Adding it would make every one of those keys *tracked*, and `lwtv_stats_cache_index` is a single option that `clear_cache_tier()` walks in full on every save. The save path would slow down as the catalogue grows.

So the invalidation gap and the blob size are the same problem, and caching IDs fixes both: a small payload, and a keyspace it becomes reasonable to track.

## The finding that makes this safe

**Every caller already reduces the query to IDs immediately.** Verified across all six consumers:

| Caller | Line | Usage |
|---|---|---|
| `theme/class-show-characters.php` | 551 | `wp_list_pluck( $loop->posts, 'ID' )` |
| `theme/class-actor-characters.php` | 158 | `wp_list_pluck( $loop->posts, 'ID' )` |
| `_components/class-of-the-day.php` | 870 | `foreach ( $loop->posts as $actor )` — see caveat |
| `rest-api/class-wikidata.php` | 205, 233 | `wp_list_pluck( $queery->posts, 'ID' )` |
| `rest-api/class-stats-json.php` | 215, 296, 486 | `wp_list_pluck( $queery->posts, 'ID' )` |
| `cpts/class-related-posts.php` | 41, 66 | `wp_list_pluck( $loop->posts, 'ID' )` |

Nobody uses `the_post()`, `have_posts()` as a loop, `max_num_pages`, or the query object for anything but `->posts`. The `have_posts()` calls are all guards, equivalent to `! empty( $ids )`.

**One caveat:** `class-of-the-day.php` iterates post *objects* and reads `$actor->ID` plus `get_post_field( 'post_name', get_post( $actor ) )`. On IDs that becomes `get_post_field( 'post_name', $id )`. Behaviour is identical; it just needs rewriting rather than a mechanical `->posts` swap.

## Phase 1 — add an ID-returning method alongside the existing one ⭐ do first

Do **not** change `make()`'s return type in place. Add a sibling and migrate callers one at a time, so a mistake shows up in one place rather than six.

For each of `Post_Meta`, `Post_Meta_And_Tax`, `Post_Type`, `Related_Posts_By_Tag`:

- Add `get_ids( ... ): array` with the same signature as `make()`, forcing `'fields' => 'ids'` and `'no_found_rows' => true`.
- Cache the **ID array** under a key distinct from `make()`'s, so the two caches can coexist during migration.
- `Post_Type` already has a `get_ids()` (used by `cli-shadow.php:115`, `debugger/class-scan.php:71`, `class-what-happened-json.php:287`) — check whether it can be reused as-is or needs the same treatment.

**⚠️ Verify first:** whether `'fields' => 'ids'` plus `update_post_meta_cache` leaves the *consumers* doing N+1 meta reads. Several of them (`stats-json` especially) call `get_post_meta()` per ID immediately afterwards. If so, prime with `_prime_post_caches( $ids )` at the call site rather than reverting to `'all'` — that keeps the cached payload small while still giving one priming query.

## Phase 2 — migrate the six callers

One commit per file, each independently revertable. Pattern:

```php
// before
$loop = ( new Post_Meta() )->make( Characters::SLUG, 'lezchars_actor', $actor_id, 'LIKE' );
if ( ! is_object( $loop ) || ! $loop->have_posts() ) { return; }
$ids = wp_list_pluck( $loop->posts, 'ID' );

// after
$ids = ( new Post_Meta() )->get_ids( Characters::SLUG, 'lezchars_actor', $actor_id, 'LIKE' );
if ( empty( $ids ) ) { return; }
```

`class-of-the-day.php` is the only non-mechanical one, per the caveat above. Do it last, and check the birthday round-up output before and after — it honours `hide_actor_data()`, so a mistake there is a privacy regression, not just a cosmetic one.

## Phase 3 — the `get_count()` helpers

All four are the same shape:

```php
public function get_count( $post_type, $key, $value, $compare = '=' ) {
    $query = $this->make( $post_type, $key, $value, $compare, 1, 1, 'ids' );
    return $query->found_posts;
}
```

They need `found_posts`, which an ID array can't provide — and they deliberately fetch only one row, so the cached blob is small. Two options:

- **(a) Leave them on `make()`.** Simplest. `make()` survives solely for these four, still caching a one-post `WP_Query`. Small blob, no callers to change.
- **(b) Cache the integer.** Give each class a `get_count()` that runs its own query with `'fields' => 'ids'`, `'posts_per_page' => 1`, `'no_found_rows' => false`, and caches `found_posts` as a scalar. Then `make()` can be deleted outright.

**(b) is the endpoint** — it removes the last WP_Query-in-a-transient — but **(a) is a valid stopping point** if Phase 2 has already taken the size problem away. Decide after Phase 2; don't pre-commit.

## Phase 4 — close the invalidation gap

Only once the payload is IDs and the keyspace is decided:

- If keys stay per-entity md5s, the cardinality objection still stands and `post_meta_*` should stay out of the dependency map. Say so in the comment there rather than leaving the omission looking accidental.
- If Phase 1/2 let each call site pass a **cache group** (e.g. `post_meta_chars_by_actor`), then the low-cardinality groups can be added to `get_cache_dependencies()` individually, and the per-entity ones left out.

**⚠️ Note on patterns:** `Transients::key_matches_pattern()` only honours a **trailing** `*`. A mid-string pattern like `post_meta_*_by_actor` matches in the SQL pass (`str_replace( '*', '%' )`) and silently matches nothing in the index walk — and under Redis the index walk is the only pass that runs. Use a trailing wildcard or exact keys.

## Files

- `plugins/lwtv-plugin/php/queeries/class-post-meta.php`
- `plugins/lwtv-plugin/php/queeries/class-post-meta-and-tax.php`
- `plugins/lwtv-plugin/php/queeries/class-post-type.php`
- `plugins/lwtv-plugin/php/queeries/class-related-posts-by-tag.php`
- The six consumers listed in the table above.
- `plugins/lwtv-plugin/php/_components/class-transients.php` — Phase 4 only.

Not in scope: `class-is-actor-queer.php`, `class-is-actor-trans.php`, `class-get-id-from-slug.php`, `class-shadow-taxonomy.php`, `class-taxonomy-optimized.php`. They have their own `make()`/caching and none of them caches a `WP_Query`.

## Verification

No unit tests apply — this is all WP queries and transients, which per CLAUDE.md is verified against a running site. Check on Local:

1. `wp lwtv cache check` before and after, to see the tracked-key count and whether a persistent object cache is active.
2. A show page (`class-show-characters`), an actor page (`class-actor-characters`), the birthday round-up, `/wp-json/lwtv/v1/wikidata/<actor-id>/`, and the `complex` stats endpoint — each should render identically.
3. Query Monitor on a show and an actor page: query **count** should not rise. If it does, Phase 1's priming caveat is the cause.

## One thing to decide before starting

Whether `make()` survives at all. Phase 3(a) keeps it for four count helpers; Phase 3(b) deletes it. Keeping it means the "don't cache a WP_Query" rule has a documented exception, which invites the pattern back. Deleting it means four more small changes. Worth choosing deliberately rather than drifting into (a) because Phase 2 felt like enough.
