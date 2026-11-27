# Meilisearch FacetWP search engine: design (short)

Status: approved (Mika, 2026-11-26). Issue: [#749](https://github.com/LezWatch/lwtv-underscores/issues/749).

## Why

The keyword facets on the shows, characters and actors archives (`search_shows`, `search_characters`, `search_actors`) run on SearchWP engines (`swp_*`, set in FacetWP's settings in the database). They're the last code-side blocker before SearchWP can be deactivated. Pointing them at FacetWP's built-in WordPress search would lose typo tolerance, alternate show names, actors' other names and ranking, so they get Meilisearch instead, matching the modal and the results page.

## Goals, non-goals, anti-goals

- **Goal:** three new engines in FacetWP's search-facet dropdown, "Meilisearch – Shows", "Meilisearch – Characters" and "Meilisearch – Actors" (keys `meili_shows`, `meili_characters`, `meili_actors`). A keyword box set to one of them returns matching posts from that type's index, and the archive's other facets keep narrowing as they do today.
- **Non-goals:** changing FacetWP's settings in code, a content (posts) engine, FacetWP on the search page, and index changes.
- **Anti-goals:**
  - visitors not covered by the switch seeing different results before Mika chooses
  - an error, or an empty list, because Meilisearch is down
  - the indexing key used or printed anywhere

## Approaches

1. **FacetWP's built-in WordPress search.** No code, but noticeably worse results (plain `LIKE` on title and content). Rejected.
2. **A Meilisearch engine through FacetWP's own hooks.** Chosen. It uses `facetwp_facet_search_engines` to add the choices and `facetwp_facet_filter_posts` to answer them, the same pattern as FacetWP's bundled SearchWP integration (`facetwp/includes/integrations/searchwp/searchwp.php`).
3. **Send the archive's posts to Meilisearch as a filter** (`id IN [...]`). Rejected: the characters archive can list about 7,000 IDs, which makes a huge filter string on every keystroke. Filtering against the archive's posts in PHP is cheaper.

## Decided behavior

- **Request:** one `POST /multi-search` holding a single query, through `Client::multi_search()` with the **search key** and a 2-second timeout. The query is `{ indexUid: <type uid>, q, matchingStrategy: 'all', attributesToRetrieve: [ 'id' ], limit: 1000 }`.
  - **Every word must match** (Mika, 2026-11-26). "alex danvers" narrows to Alex Danvers, not every Alex.
  - 1,000 is Meilisearch's default `maxTotalHits`. A broader keyword gets the top 1,000.
- **Result:** the hit IDs, in Meilisearch's order, limited to `FWP()->unfiltered_post_ids` (the posts the archive already lists).
  - If that list is empty, return the hits unchanged.
  - With "order by relevance" on, FacetWP sorts by this order. With it off, return the same IDs but add `facetwp_use_search_relevancy` → false, as SearchWP does.
  - An empty keyword returns `'continue'`, as FacetWP expects.
- **Switch:** the engines respect `LWTV_MEILI_MODAL` through `Search_Modal::enabled()`. A request the switch doesn't cover (for example, logged out while it's `'editors'`) is answered by the same-type SearchWP engine (`swp_<type>`), exactly as today. Mika can therefore set the facets to `meili_*` and test as an editor first.
- **Fallback:**
  - **When:** the request fails (WP_Error, non-2xx, timeout, or a malformed response).
  - **What runs:** the same-type SearchWP engine while SearchWP is active. Otherwise FacetWP's default WordPress search: return `$return` unchanged so FacetWP runs its own query.
  - **Visibility:** the failure is logged to the `meilisearch` topic, and visitors see no error.
- **Where:**
  - Pure parts in `meilisearch/build/`, unit-tested first: the request body (`matchingStrategy: 'all'`, the limit, and only `id` retrieved), and limiting hit IDs to the allowed set while keeping order.
  - The hooks in a new `LWTV\Meilisearch\FacetWP_Engine` class, registered from the component only when the module is configured and FacetWP is active (`function_exists( 'FWP' )`).
- **Docs:**
  - `docs/architecture/meilisearch.md` gets a FacetWP section.
  - `docs/runbooks/meilisearch.md` gets a section on switching each facet in FacetWP → Facets → search_* → Search engine.
  - `docs/runbooks/retiring-searchwp.md` gets its FacetWP item updated to "switch each facet to its Meilisearch engine".

## Verification

- PHPUnit for the pure parts, plus `composer lint` and PHPStan.
- **Live on lwtv.local, as an editor,** with `search_characters` temporarily set to `meili_characters` (tell Mika first, and set it back unless told to keep it):
  - "alex danvers" narrows the characters archive to Alex Danvers.
  - A typo ("alex danverz") still finds her. Words under 5 letters get no typo tolerance by Meilisearch default, so "alx" doesn't match.
  - Another facet (for example, a show) narrows further.
  - Relevance on and off both work.
- **Logged out:** the same facet returns what `swp_characters` returns today.
- **Fallback:** with a bad uid through a throwaway filter, the archive still filters (through SearchWP), and the failure is logged.
- **The other two facets** are spot-checked the same way, using an alternate show name and an actor's other name.
