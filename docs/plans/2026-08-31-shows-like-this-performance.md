# Shows Like This — Performance Plan

Target file: `plugins/lwtv-plugin/php/cpts/shows/class-shows-like-this.php`

Integrates with the third-party `related-posts-by-taxonomy` plugin (RPBT, not bundled in this repo) via its filters: `related_posts_by_taxonomy_posts_meta_query`, `related_posts_by_taxonomy`, `related_posts_by_taxonomy_cache` (forced true), `related_posts_by_taxonomy_wp_rest_api` (forced true).

## Review findings (verified)

**F1 — `alter_results()` / `reciprocity()` run on every request; RPBT's own cache does not help them.**
Verified against the RPBT plugin source: on a cache hit, `class-cache.php:366` still re-applies the `related_posts_by_taxonomy` filter to the cached IDs; on a miss, `query.php:433` applies it before `set_cache()` stores the result. Forcing `related_posts_by_taxonomy_cache` true (line 24) caches only the underlying taxonomy query — `alter_results()`, including the expensive `reciprocity()` `WP_Query`, executes on **every show page view and every RPBT REST call**. Highest-priority target. (RPBT auto-flushes its own cache on any post save/trash/delete — `class-cache.php:88-102` — so RPBT-side cache misses are routine on an actively-edited site anyway.)

**F2 — Every hook is registered twice, so `reciprocity()` runs 2x per request.**
A boot instance is created at `plugins/lwtv-plugin/php/cpts/class-shows.php:67`, and the template facade `get_shows_like_this_show()` at `plugins/lwtv-plugin/php/_components/class-cpts.php:179-180` constructs a **second** `new Shows_Like_This()` per call. Object-method callbacks hash per instance, so `add_filter` does not dedupe: `alter_results` and `meta_query` are each attached twice, doubling the reciprocity query and duplicating the meta_query JOIN clause.

**F3 — `reciprocity()` (lines 139-192) is maximally expensive per run.**
Uncached `WP_Query` with `meta_query LIKE` over ~2,266 shows' serialized `lezshows_similar_shows` meta, full post-object hydration via `the_post()` (only IDs are used), `update_post_term_cache => true` (terms never read), `orderby title` (order is irrelevant — results get merged/sliced), one `get_field()` per matched row, a redundant `get_post_status()` check (the query already filters `publish`), and a `wp_reset_query()` that's only needed because of the `the_post()` loop.

**F4 — `meta_query()` correctness bug that also wastes query work (lines 119-124).**
`'compare' => $worthit` passes a rating value ("Yes"/"Meh"/"No") where an operator belongs, and there is no `'value'` key. `WP_Meta_Query` discards the invalid operator, and with no value the clause degrades to a bare key-EXISTS JOIN. The documented intent ("match up the worth-it value") never executes — the clause just adds a JOIN matching virtually every show. Fix: `'value' => $worthit, 'compare' => '='`. This is a **behavior change** (finally narrows related candidates to same-rating shows) — see Risks.

**F5 — `make()` shortcode-attribute quoting bug defeats the include/exclude logic (line 82).**
`'include_terms=""' . $include . '"'` emits `include_terms=""123" …` — the shortcode parser reads `include_terms` as **empty** and the IDs as stray positional atts. The whole primary-genre / tagged-terms restriction built in lines 39-79 never reaches RPBT, so its term query runs broader than designed. Also a dead condition at line 47 and a stray leading space in `taxonomies=" …"`.

**F6 — Mixed int/string storage constrains query tightening.**
`cli-migrate.php:261` wrote integer IDs (`i:1234;`) while ACF UI saves write strings (`s:4:"1234"`). A single tightened `LIKE` pattern would miss migrated rows, so the broad `LIKE` + exact-match verification loop must stay (cheapened per Task 2).

## Goal

Make the "Shows Like This" feature effectively free on warm requests: eliminate the double hook registration, cache `reciprocity()` in a Redis-backed transient with save-time invalidation, cheapen its cold-path query, and fix the two latent bugs that make RPBT's own queries broader than designed.

## Scope

**In:**
- `plugins/lwtv-plugin/php/cpts/shows/class-shows-like-this.php` (all four methods)
- `plugins/lwtv-plugin/php/_components/class-cpts.php` (the `get_shows_like_this_show` facade, one line)

**Out:**
- No changes to `class-calculations.php` or anything score-related.
- No reverse-index meta key (`lezshows_similar_shows_reverse`) — noted as a future option; at 2,266 shows the transient gives the same warm-path performance without a backfill migration.
- No changes to `class-acf.php` — the invalidation hook lives with the feature it serves.
- No new abstractions; use the existing `lwtv_plugin()->set_transient()/get_transient()/delete_transient()` facade (`class-transients.php:128,174,192`).
- Leave `order="RAND"`, the RPBT cache/REST force-true filters, and the `posts_per_page => 100` cap as-is.

## Tasks

All tasks touch the same short file (except Task 1's one-line facade edit), so run them sequentially on one branch — do not parallelize Build across worktrees here. Order matters: Task 1 first (otherwise timing measurements are doubled), Tasks 2→3 are one logical sequence, Tasks 4 and 5 are independent of 2/3 and of each other.

### Task 1 — Register hooks once
- What: Stop the per-call `new Shows_Like_This()` at `class-cpts.php:180` from re-adding filters. Simplest minimal diff: guard the constructor's `add_filter` block with a `private static bool $hooked` flag (set true after first registration). Alternative: give the class a `make()` path that doesn't need hooks re-registered and have the facade construct without hooking — the static guard is the smaller diff.
- Files: `class-shows-like-this.php`, optionally `class-cpts.php`.
- Verify: on lwtv.local with Query Monitor (or `SAVEQUERIES`), a single show page runs the reciprocity SQL once, not twice; `has_filter( 'related_posts_by_taxonomy', ... )` reports one callback.

### Task 2 — Cheapen `reciprocity()`'s cold path
- What: in the `WP_Query` args add `'fields' => 'ids'`, set `'update_post_term_cache' => false`, add `'update_post_meta_cache' => false`, change `orderby` to `'none'`. Replace the `have_posts()/the_post()` loop with a plain `foreach` over the returned IDs; call `update_meta_cache( 'post', $ids )` once, then read each row with `get_post_meta( $id, 'lezshows_similar_shows', true )` instead of `get_field()` (same raw ID array, no ACF formatting layer). Drop the now-redundant `get_post_status()` check (the query already restricts to `publish`) and the `wp_reset_query()` (no global loop is touched anymore). Keep the loose `==` exact-match verification loop — required because of F6's mixed int/string storage and the substring-matching `LIKE`.
- Files: `class-shows-like-this.php` (`reciprocity()`).
- Verify: query count for the function drops to 2 (main query + one meta-cache prime); results for a show with known reciprocal picks are identical before/after (compare `reciprocity( $id )` output in `wp shell`).

### Task 3 — Cache `reciprocity()` in a transient with save-time invalidation
(Depends on Task 2 — same function.)
- What: wrap the body: `lwtv_plugin()->get_transient( 'lwtv_similar_reciprocity_' . $post_id )`; on miss, compute and `set_transient( …, $reciprocity, WEEK_IN_SECONDS )` (mirroring `statistics/build/class-actors.php`). Cache an empty array too (distinguish "no entry" from "empty result" — the Transients component returns `false` on miss, so store the array unconditionally and treat only `false` as a miss). Invalidation, registered in the (now single-run) constructor: `add_action( 'acf/save_post', …, 5 )` to stash the old `lezshows_similar_shows` value (ACF writes new values at priority 10), and a second callback at priority 20 to read the new value, take the union of old + new ID lists plus the saved show's own ID, and `delete_transient( 'lwtv_similar_reciprocity_' . $id )` for each. Both callbacks bail unless `get_post_type( $post_id ) === 'post_type_shows'` (mirrors the `class-acf.php:55,108` precedent of checking inside the callback). Staleness from unpublish/trash needs no invalidation: `alter_results()` already re-checks `get_post_status()` per merged ID at line 230, and the TTL backstops everything else.
- Files: `class-shows-like-this.php`.
- Verify: (a) warm request runs zero reciprocity SQL (Query Monitor); (b) edit show A on lwtv.local to add show B to Similar Shows, load show B's page → A appears immediately (transient was deleted); remove it, B's page drops A; (c) `wp shell`: time `lwtv_plugin()->get_shows_like_this_show( $id )` cold vs warm.

### Task 4 — Fix the worth-it meta_query clause
(Logically independent of 2/3.)
- What: lines 120-123 become `array( 'key' => 'lezshows_worthit_rating', 'value' => $worthit, 'compare' => '=' )`.
- Files: `class-shows-like-this.php` (`meta_query()`).
- Verify: flush RPBT's cache first (`km_rpbt_flush_cache()` in `wp shell`, or just re-save a post — RPBT flushes on any save), then on a cache-miss show page confirm via Query Monitor that the RPBT SQL contains `meta_value = 'Yes'` (or the show's actual rating); spot-check that a "Yes"-rated show's taxonomy-derived results are all "Yes"-rated.

### Task 5 — Fix `make()`'s include_terms quoting
(Logically independent of 2/3/4.)
- What: line 82 → `'include_terms="' . $include . '" exclude_terms="' . $exclude . '"'` (drop the stray `""`). While there: remove the always-true line 47 condition, and drop the leading space in `taxonomies=" ' . $taxonomies . '"`.
- Files: `class-shows-like-this.php` (`make()`).
- Verify: for a show with a primary genre plus secondary genres, confirm (RPBT debug or Query Monitor on a flushed cache) that the term query is restricted to the primary genre's term ID and excludes the others; for a `lez_showtagged` show, that only its tag terms are used.

### Task 6 — Lint + site verification pass
- What: `composer lint` on the touched files; then a full manual pass on lwtv.local: one show page cold (RPBT cache + transient flushed), same page warm, one REST request to the RPBT endpoint, and the invalidation round-trip from Task 3(b). Record before/after query counts and timing. No PHPUnit additions — nothing here is a pure transform, and repo policy says WP-touching code is verified against the running site, not unit-tested.
- Verify: zero phpcs violations; warm show page shows no `lezshows_similar_shows LIKE` SQL and exactly one RPBT filter callback.

**Post-deploy note:** flush RPBT's cache once on production (`km_rpbt_flush_cache()`; it also self-flushes on the next post save) so the corrected meta_query/include_terms results replace the baked-in old ones.

## Risks and open questions

1. **Tasks 4 and 5 change visible results** — that's the point (they implement documented intent that never executed), but "Shows Like This" listings across the site will shift: Task 4 narrows taxonomy candidates to same-worth-it shows; Task 5 finally applies the primary-genre restriction. Handpicked + reciprocity entries are unaffected. Recommend eyeballing a handful of shows on staging and explicitly signing off on shipping 4/5 together with the perf work — or splitting them into a separate PR if editorial review is wanted first. If Task 4's narrowing proves too aggressive (few same-rating genre-mates → sparse results), the fallback is dropping the clause entirely rather than keeping the accidental EXISTS.
2. **Programmatic saves bypass `acf/save_post`** (WP-CLI `update_post_meta`, imports). The WEEK_IN_SECONDS TTL plus `alter_results()`'s runtime publish check bound the staleness window; if bulk edits become common, hook `updated_post_meta` for the key as a follow-up.
3. **Transients component semantics** — Task 3 assumes `get_transient()` returns `false` on miss (standard WP behavior per `class-transients.php`); Build should confirm the component doesn't coerce return types before relying on the empty-array distinction.
4. **`posts_per_page => 100` cap** in `reciprocity()` is fine at 2,266 shows (a show would need >100 other shows picking it); left as-is deliberately.
5. **Reverse-index alternative** (`lezshows_similar_shows_reverse` meta maintained on save; `reciprocity()` becomes one `get_post_meta`): strictly faster cold path and no TTL staleness, but needs a WP-CLI backfill for 2,266 shows, delete/trash bookkeeping, and mixed-format handling. Not justified at current scale; revisit if transient churn ever shows up in Redis metrics.
6. **RPBT self-flushes its entire cache on any post save/trash/delete** (verified in its `class-cache.php:88-90`), so cache misses on the RPBT side are routine on an actively-edited site — one more reason Tasks 4/5 (which shape the miss-path query) are worth fixing, and why our transient (which survives RPBT's flush) does the heavy lifting.
