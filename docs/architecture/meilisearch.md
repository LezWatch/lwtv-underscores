# Meilisearch

Prototype Meilisearch indexes of **characters**, **shows**, **actors** and **content** (posts and pages), running next to SearchWP so the two can be compared on real queries ([#749](https://github.com/LezWatch/lwtv-underscores/issues/749)). Only the header search modal uses them, and only when `LWTV_MEILI_MODAL` switches it on (see [Search modal](#search-modal)). SearchWP, its engines, FacetWP's search facets and the live search UI are untouched.

Code: `plugins/lwtv-plugin/php/meilisearch/`. Setup and 3am fixes: [docs/runbooks/meilisearch.md](../runbooks/meilisearch.md). Designs: `docs/superpowers/specs/2026-11-24-meilisearch-characters-design.md`, `docs/superpowers/specs/2026-11-25-meilisearch-shows-design.md`, `docs/superpowers/specs/2026-11-25-meilisearch-actors-design.md`, `docs/superpowers/specs/2026-11-26-meilisearch-search-modal-design.md`. The content index's decisions are recorded here (Mika, 2026-11-25).

## Configuration

Set in `wp-config.php`. The module registers nothing (no hooks, no admin page) until the first three are set.

| Constant | Required | What it is |
| --- | --- | --- |
| `LWTV_MEILI_URL` | yes | Server URL, e.g. `https://search.lezwatchtv.com` |
| `LWTV_MEILI_INDEX_KEY` | yes | Server-side key: documents, settings, tasks, index create/delete. Never leaves PHP. |
| `LWTV_MEILI_SEARCH_KEY` | yes | Search-only key. Public by design: the browser uses it. |
| `LWTV_MEILI_INDEX_PREFIX` | no | Per-environment prefix: `local`, `staging`, `production`. |
| `LWTV_MEILI_MODAL` | no | Header search modal: `'editors'` (users who can `edit_posts`) or `'everyone'`. Anything else, or unset, is off. Ignored until the three required constants are set. |

Index uids are `{PREFIX}_{type}`, or just `{type}` with no prefix (supported, but no environment uses it): production uses `production_*` (prefix `production`), lwtv.local uses `local_characters`, `local_shows`, `local_actors` and `local_content` (prefix `local`), staging `staging_*`. Keys can be scoped by pattern (`local_*`), so one indexing key and one search key per environment is enough.

Keys are never logged or printed. Errors show the HTTP status and Meilisearch's error code and message only.

A site that sets `WP_HTTP_BLOCK_EXTERNAL` (lwtv.local does) must list the search host in `WP_ACCESSIBLE_HOSTS`, or every request is refused.

## How the module is shaped

Each index is a class extending `Index` (`abstract-index.php`): `Characters_Index`, `Shows_Index`, `Actors_Index`, `Content_Index`. An index knows its type, post type(s) (`post_types()`, `holds()`; only content holds two), settings file and how to build documents, and answers one question, `affected_by( $post_type, $post_id )`: "this post changed; which of my documents does that touch?" `Indexes` is the registry, the only list of types. Sync, the CLI, the admin notice and Search Compare all go through it, so adding an index is one new class plus a settings file.

| Change to… | characters index | shows index | actors index |
| --- | --- | --- | --- |
| a character | that character | its shows | its actors |
| a show | its characters | that show | its characters' actors |
| an actor | their characters | their shows (via their characters) | that actor |

The content index is outside this table: its documents quote no other post, and no other index quotes a post or page, so a post or page change only ever touches its own content document.

Every cell is a lookup on `lwtv_char_links` (`Store::characters_for_object()`, `Store::objects_for_character()`, published posts only).

Pure decisions live in `build/` and are unit-tested: the documents, title splitting, sync routing, index state, the `--type` option, settings loading, the prune filter, and the search modal's switch (`Modal_Switch`) and browser config (`Modal_Config`).

## The documents

One per **published** post. Linked shows, characters and actors are named only when published; an actor with `hide_all` privacy is left out, as a document and as a name in others, even if their status hasn't caught up.

**Character** (`Build\Character_Document`):

```json
{ "id": 241, "name": "Adam Torres", "shows": ["Degrassi: The Next Generation"], "actors": ["Jordan Todosey"], "role": "regular", "role_rank": 3, "url": "…", "thumb": "…" }
```

`role` is the character's best role across their published shows; `role_rank` is regular 3, recurring 2, guest 1, none 0.

**Show** (`Build\Show_Document`):

```json
{ "id": 32399, "name": "Charmed", "qualifier": "2018", "alt_names": [], "characters": ["Mel Vera", "…"], "actors": ["Melonie Diaz", "…"], "years": "2018 2022", "worth_it": "Yes", "worth_it_rank": 3, "score": 64.45, "stations": ["The CW"], "nations": ["USA"], "url": "…", "thumb": "…" }
```

- `name` / `qualifier`: the title split on one trailing parenthetical (`Build\Show_Title`). Same-name shows then tie on `name`, instead of Meilisearch's exactness rule always ranking the plain title (*Charmed*) above the qualified one (*Charmed (2018)*). "charmed 2018" and "skins us" still match the qualifier.
- `alt_names`: from the `lezshows_show_names` repeater. SearchWP's `shows` engine doesn't index the repeater, though it can still match an alternate name the write-up mentions (it finds *Terra e Paixão* for "land of desire").
- `characters`: published characters on the show; `actors`: their visible actors. So "charmed mel" finds Charmed (2018).
- `years`: start and finish years (via `Shows\Airdates::get()`), `"2024"` for a show still airing.
- `worth_it_rank`: Yes 3, Meh 2, TBD 1, No 0. `score`: `lezshows_the_score`.
- `stations`, `nations`: stored for filtering and display, not searched.

**Actor** (`Build\Actor_Document`):

```json
{ "id": 14089, "name": "Jorja Fox", "other_names": [], "characters": ["Sara Sidle", "…"], "shows": ["CSI: Crime Scene Investigation", "…"], "years": "1968", "bio": "…", "queer": 1, "character_count": 4, "url": "…", "thumb": "…" }
```

- `other_names`: the **Other Names** repeater (`lezactors_other_names`): former names including dead names, married names, stage names. Searchable, so "Ellen Page" finds Elliot Page, but **never displayed**: the index's `displayedAttributes` leave it out of hits, highlights and match positions, and the public search key can't fetch documents. Search Compare cuts SearchWP's rows to the same fields. A name equal to the current title is dropped. These used to live in the excerpt; `wp lwtv migrate othernames` moves them (runbook).
- `characters`: the actor's published characters; `shows`: those characters' published shows.
- `years`: birth and death years only, never full dates (`lezactors_birth` / `lezactors_death`, stored as `Y-m-d`, `Y-n-j` or `Ymd`). **No birth year when the actor has Hide Date of Birth**; the death year stays.
- `bio`: post content as plain text (shortcodes and markup stripped), searched last.
- `queer`: `lezactors_queer` as 1/0; `character_count`: `lezactors_char_count`. Both written by `Actors\Calculations::do_the_math()`, which fires `lwtv_actor_calculated` afterwards so the document picks them up.
- Socials, IMDb/Wikidata IDs and privacy notes are never in the document, so Hide Social Media needs no rule.

**Content** (`Build\Content_Document`), posts and pages in one index:

```json
{ "id": 84567, "type": "post", "type_rank": 0, "title": "2023 Queer TV Predictions from Team LezWatchTV", "excerpt": "…", "body": "…", "categories": ["Commentary and Reviews"], "tags": ["New Year", "Predictions"], "date": 1672444800, "url": "…", "thumb": "…" }
```

- Only **published posts and pages with no password** (`Content_Document::eligible()`). Private, draft, scheduled and password-protected posts are left out; a post that gains a password loses its document.
- `type`: `post` or `page`, filterable. `type_rank`: page 1, post 0, the first tie-breaker.
- `excerpt`: the written excerpt; a post without one gets the first 55 words of its body.
- `body`: the **whole** post content as plain text (shortcodes, block comments, scripts and tags stripped; a space goes in before each tag so neighbouring blocks don't run together). Searchable, but **never displayed**: results show the excerpt. Posts average about 9k characters (max ~48k); the whole index is a few MB.
- `categories` (filterable) and `tags`: term names. Post tags are mostly show names, so they rank second.
- `date`: publish time, Unix seconds; sortable and the second tie-breaker.

## Index settings

`config/{type}.json` is the source of truth. Apply with `wp lwtv meili settings --type=<type>`; `wp lwtv meili status --type=<type>` says whether the server matches.

**Characters:** searchable `name`, `shows`, `actors`; ranking v1.57 defaults then `role_rank:desc` (a name match wins, then the bigger role, then show-name matches); stop words `from`, `on` ("Mel from Slo-Pitch" searches as "Mel Slo-Pitch"); filterable `role`, `id`.

**Shows:** searchable `name`, `qualifier`, `alt_names`, `characters`, `actors`, `years`; ranking v1.57 defaults then `worth_it_rank:desc`, `score:desc`; stop words `from`, `on`, `with`; filterable `id`, `worth_it`, `stations`, `nations`.

**Content:** searchable `title`, `tags`, `excerpt`, `categories`, `body`; displayed: everything except `body` and `type_rank`; ranking v1.57 defaults then `type_rank:desc`, `date:desc` (Mika, 2026-11-25: on a tie, evergreen pages beat articles, then newer articles beat older); filterable `id`, `type`, `categories`; sortable `date`; no stop words. So "batwoman" finds the articles tagged or titled Batwoman before roundups that mention it, "show scores" and "contact" find their pages first.

**Actors:** searchable `name`, `other_names`, `characters`, `shows`, `years`, `bio`; displayed: everything except `other_names`; ranking v1.57 defaults then `queer:desc`, `character_count:desc` (Mika, 2026-11-25: when names tie, queer actors first, then more characters); stop words `from`, `on`, `in`; filterable `id`, `queer`.

The shows tie-breakers are Mika's call (2026-11-25). Plain "skins" should find the UK original (Worth It Yes beats the US remake's No), "charmed" and "night court" the new ones, "shameless" and "ghosts" the UK ones, "mistresses" the 2008 UK one, "gossip girl" the 2021 one. Score alone gets Skins wrong; recency gets Skins wrong; character count gets Night Court wrong.

`id` is filterable on every index so `reindex` can delete documents that are no longer published.

Every search sends `matchingStrategy: "frequency"` (a per-request option, see the characters design).

## When an index isn't set up

Sync never creates an index. Meilisearch would create one on the first document add, with default settings, so the first editor save after a deploy would otherwise build a production index nobody approved.

- A queued task first checks the index exists (`Index::state()`, cached in transient `lwtv_meili_state_{uid}`: `exists` for an hour, `missing` for five minutes; any other error is `unknown`, not cached, and also skips). If it isn't, the task logs one line and returns.
- Admins (`manage_options`) see a warning on the Dashboard and LezWatch.TV screens naming each missing index and the command that creates it (`Admin_Notice`).
- Search Compare says "Index not set up on this environment".
- Only `wp lwtv meili settings` and `reindex --fresh` create an index. Running `settings --type=shows` is what switches shows on for an environment. A plain `reindex` (no `--fresh`) stops with an error on a missing index rather than creating one with default settings.
- While the server is unreachable, the admin notice re-checks at most every five minutes per index, so admin pages don't wait on a timeout each load.

When a front-end search is built on Meilisearch, it must fall back to regular WordPress search when the server or an index is unavailable (Mika, 2026-11-25).

## Commands

```
wp lwtv meili status   [--type=characters|shows|actors|content|all]
wp lwtv meili settings [--type=…]                     # create the index if missing, apply config/{type}.json
wp lwtv meili reindex  [--type=…] [--dry-run] [--fresh] [--batch=500]
wp lwtv meili sync <id>                               # one post, in every index it affects
wp lwtv migrate othernames [--dry-run] [--show-review] [--clear=<ids>] [--yes]  # actor excerpts → Other Names (runbook)
```

`--type` defaults to `characters`, so commands from before the shows index do what they always did; a typo stops with the list of valid types. Every command prints `Index: <uid> on <host>` before touching an index. `reindex --fresh` deletes and recreates the index, then applies settings: that is also the upgrade path after a Meilisearch version change, since indexes are fully derived from WordPress and never backed up.

## Sync

Hooks only queue work through `lwtv_plugin()->schedule_task()` (Action Scheduler, 5-second delay, one pending task per post per index), so the search server can't slow or break a save. Which indexes an event queues is `Build\Sync_Routes`; which documents in each is worked out when the task runs.

| Change | Hook | Queues |
| --- | --- | --- |
| Character saved | `acf/save_post` (priority 30, after the link table) | characters, shows, actors |
| …and the shows and actors it had before the save (so one it left drops its name) | `acf/save_post` priority 5 records them | shows / actors, for each |
| Show saved (Worth It, alternate names, airdates, terms) | `acf/save_post` | shows only |
| Actor saved (privacy and other names are ACF meta) | `acf/save_post` | characters, shows, actors |
| Any of the three renamed while published (any path: quick edit, REST, CLI) | `post_updated` + `Build\Change_Filter` | characters, shows, actors |
| Any of the three in or out of publish (trash, unpublish, private, scheduled publish) | `transition_post_status` | characters, shows, actors |
| Character deleted | `before_delete_post` priority 5 (before the link table drops its rows at 10) | characters; shows and actors, for each of its shows and actors |
| Show deleted | `before_delete_post` | shows |
| Actor deleted | `before_delete_post` | actors |
| Show score recalculated (save, calculation task, `wp lwtv calc`) | `lwtv_show_score_updated`, fired in `Calculations::do_the_math()` | shows |
| Actor counts / queer flag recalculated (after an actor or character save, `wp lwtv calc`) | `lwtv_actor_calculated`, fired in `Actors\Calculations::do_the_math()` | actors |
| Post or page saved (any edit: the body is indexed; posts aren't ACF-driven). Revisions, autosaves and auto-drafts are skipped | `save_post` | content |
| Post or page renamed while published, or in or out of publish (trash, restore, unpublish, private) | `post_updated` / `transition_post_status` | content |
| Post or page deleted | `before_delete_post` | content |

Tasks are `lwtv_meili_characters_task`, `lwtv_meili_shows_task`, `lwtv_meili_actors_task` and `lwtv_meili_content_task`, each with the changed post's ID. A task re-syncs the post's own document in its own index (added if published, deleted otherwise, deleted if the post no longer exists), or fans out to `affected_by()` in 100-document requests.

Failures are logged to the `meilisearch` debug topic (tick it under LezWatch.TV → Debugging) and dropped. To repair drift, run `wp lwtv meili reindex --type=all`: it re-sends every published post, then deletes every document whose `id` isn't in that set (`POST /indexes/{uid}/documents/delete` with `id NOT IN [...]`, built by `Build\Prune_Filter`, which refuses an empty set). Search keeps working throughout; `--fresh` empties the index first.

Known gaps: force-deleting a published show or actor (skipping the trash) drops its link rows before any fan-out can read them, so dependent documents keep the old name until the next `reindex`. Deleting an actor removes their own document but doesn't update the characters and shows that named them. Renaming a tag or category doesn't re-sync the posts that carry it (no term hook): run `wp lwtv meili reindex --type=content`.

The content task reads terms 5 seconds after `save_post`, so terms and meta the block editor saves after that hook fires are picked up.

## Search Compare

LezWatch.TV → Search Compare (`manage_options`). A Characters / Shows / Actors / Content switch and one search box. Meilisearch results come straight from the browser with the search key; SearchWP results come from its engine of the same name (`characters`, `shows`, `actors`; content has no engine of its own, so it uses `default` via `Index::searchwp_engine()`), cut to the index's displayed fields, through `GET /wp-json/lwtv/v1/meili-compare/searchwp?q=&type=`. Both columns show rank order and timing; show rows add the qualifier, years, Worth It, score and the first few characters; actor rows add years, a queer marker and the first few characters; content rows add the type, year and first few tags. The SearchWP column for content is thin: `default` returns its top 20 across every post type, and only the posts and pages among them are shown. It is a throwaway page for deciding whether to continue.

## Search modal

The header search button opens a Meilisearch modal instead of SearchWP's when `LWTV_MEILI_MODAL` says so. It searches all four indexes in one request and shows the results under four tabs with counts: Shows, Characters, Actors and Posts (the content index).

**Switch.** `Build\Modal_Switch::enabled( LWTV_MEILI_MODAL, configured, can edit_posts )`: `'editors'` gives it to users who can edit posts, `'everyone'` to all. A typo, `true` or anything else is off. It's also off when the search key equals the indexing key (`Config::search_key_is_public()`), so a wp-config slip can't print the indexing key into every page. Logged-in users skip the page cache, so `'editors'` can't leak into cached pages; `'everyone'` prints the same markup for everyone. `Search_Modal` is the WordPress glue, exposed as the template tags `lwtv_plugin()->meili_modal_enabled()` and `meili_modal_config_json()`. Both exist even when the module isn't configured; they report off.

**Config.** `Build\Modal_Config::build()` shapes the JSON the browser reads: `url`, `key` (the search key; the indexing key is never passed in), `searchPage`, `limit` 10, `debounce` 150, `timeout` 2000 (real JSON numbers, via `wp_json_encode`, never `wp_localize_script`), `tabs` in the fixed order shows, characters, actors, content (`type`, `uid`, `label`, singular and plural count patterns), and flat `strings`. It is printed as `<script type="application/json" id="lwtv-meili-config">` with `JSON_HEX_TAG | JSON_HEX_AMP`, so no string can close the script element. The `lwtv_meili_modal_config` filter can change it; it exists for debugging the fallback (a bad `url` or uid). Never add the indexing key.

**Markup.** `template-parts/header/searchbox-meili.php`. `navbar.php` uses it instead of `searchbox-searchwp.php` for both triggers (the compact one beside the hamburger and the bar-mode one) and, after `</nav>`, renders the dialog part once: a native `<dialog id="lwtv-meili-dialog">` with the input (inside a `<form>` to `/?s=`, so Enter goes to the results page), a `role="tablist"` of `role="tab"` buttons, one `role="tabpanel"` per tab, a polite `#lwtv-meili-status`, plus, outside the dialog, `#lwtv-meili-announce` (for the fallback message, heard after the dialog closes), one `<template id="lwtv-meili-icon-{type|page}">` per row icon, the config, and SearchWP's trigger inside `<span hidden data-lwtv-meili-fallback>` so its modal stays queued. Outside a `display: none` wrapper on purpose: the compact trigger's wrapper is hidden at xl.

**Preconnect.** While the modal is on for the request, `Search_Modal::resource_hints()` (on `wp_resource_hints`) adds `<link rel="preconnect" href="{origin}" crossorigin="anonymous">`, so DNS, TLS and the CORS preflight's connection are warm before the first search starts its 2-second clock. `crossorigin` matters: the search is a CORS fetch, which uses the anonymous connection pool. The origin comes from `Build\Modal_Config::origin()` (scheme, host and port; '' for anything but http(s)).

**Script.** `inc/js/meili-search.js`, a script module enqueued only when the modal is on. After a 150 ms pause it sends one `POST /multi-search` (not federated) with `limit: 10`, `matchingStrategy: 'frequency'`, `showRankingScore: true` and only the fields each row draws. Each search gets a sequence number; a newer search aborts the older request and stale responses are dropped. Until the visitor picks a tab (click or arrow keys), each response selects the tab whose top hit has the highest `_rankingScore` (ties in tab order, empty tabs never). That changes `aria-selected` and the visible panel, never focus. Index text reaches the page only through `textContent` and `setAttribute`. Shows add "Also known as" with `altNamesMatching()`, a port of `Alt_Names::matching()` with `remove_accents`: `fold()` strips combining marks and maps the letters that don't decompose (ø, ł, æ, œ, ß, đ, þ…) the way `remove_accents()` does. Languages aren't in the index, so names only. While focus is in the tabs or results, a response doesn't auto-select a tab, and a focused row keeps focus (same position) through the redraw. Keys pressed during IME composition are left to the IME. The pure helpers (`fold`, `altNamesMatching`, `strongestTab`, `statusText`) are exported, so they can be checked from the console: `await import( '/wp-content/themes/lwtv-underscores/inc/js/meili-search.js' )`. The module only starts when `#lwtv-meili-config` is on the page.

**Fallback.** A network error, any non-2xx (a missing index fails the whole multi-search with 400) or no answer in 2 seconds closes the modal and clicks the hidden SearchWP trigger, then puts the query in SearchWP's input and fires `input` and `keyup` so its live search runs. From then on, for that page view, the search button opens SearchWP. The console gets `LWTV search: falling back to SearchWP <status>`, never the key. With no SearchWP modal on the page, the modal says search is unavailable and links to `/?s=<query>`. An inline ES5 guard (`#lwtv-meili-guard`) sends the button to SearchWP (or the search page) until the module sets `<html data-lwtv-meili-ready>`, so it never does nothing: not when the module doesn't load or parse, not without `dialog.showModal()`, not when the config is unreadable or `start()` fails. Modal searches don't reach SearchWP's search log; that's intentional.

## Search results page

When the modal's switch is on for the request (`Search_Modal::enabled()`, the same decision), the full results page (`search.php`) comes from Meilisearch too. Meilisearch decides the order and WordPress renders the rows: only IDs come back from the index, so nothing in the index changed for it.

**Hook order.** `Search_Page::maybe_search()` runs on `pre_get_posts` at -10, before SearchWP's `Native::init` (0) and FacetWP's request handler (999). It only acts on the front-end main search query with a non-empty `s`: not admin, feeds, admin-ajax (SearchWP's live search) or REST, and never on a secondary `WP_Query`. It reads `s` the way core's own search does: `wp_unslash()` (the query var still carries WordPress's slashes) and, for a pretty `/search/<term>/` URL, `urldecode()`. `type` goes through `Build\Search_Params::type()` (a known tab or null) and `paged` through `Search_Params::paged()`: the raw `?paged=` first, because WP_Query has already `absint()`ed its copy (-5 would become 5), capped at `Search_Params::MAX_PAGE` (1000) so the offset stays an int.

**The request.** One server-side `POST /multi-search` with the **search key** (`Client::multi_search()`; the indexing key has no `search` action), 2-second timeout. `Build\Search_Request::body()` builds one query per index in tab order, each `matchingStrategy: 'frequency'`, `showRankingScore: true` and `attributesToRetrieve: [ 'id' ]`. The selected tab gets `limit` 20 at `offset ( paged - 1 ) × 20`; the others `limit` 1, enough for their counts. With no `type`, every index gets a first page, so one request both picks the tab and fills it, and `paged` is ignored. The term is data in the JSON body, never in a filter string. `Build\Search_Results::parse()` reads counts (`estimatedTotalHits`), the tab (the given one, or the modal's strongest-match rule), the IDs in order, and the page numbers; a response of the wrong shape throws. When `paged` is past the end, it asks for one retry at the last page's offset (`Search_Request::retry_body()`, `Search_Results::retry_ids()`).

**Loading the posts.** One `get_posts()` with the five post types, `post_status` publish, `post__in` the IDs and `orderby` `post__in`. An ID WordPress doesn't return (a stale index) is skipped; the page doesn't re-query to fill the gap. Each row also gets its index document, built fresh by that tab's `Index::documents()`, so the detail line uses the same values as the modal.

**The short-circuit.** Only once Meilisearch has answered, `hand_over()` adds three filters, each matching that one `WP_Query` by identity: `searchwp\native\short_circuit` (SearchWP's native search skips it), `facetwp_is_main_query` → false (FacetWP's SearchWP integration would otherwise run its own `SWP_Query` for the main search; the results page has no facets), and `posts_pre_query` at 1000, which hands the main query the loaded posts and sets `found_posts` and `max_num_pages`, so core's `LIKE` search doesn't run either. It also sets the query's `paged` to the clamped page (page 1 when no posts loaded), so an empty tab at `&paged=3`, or a later page whose hits are all stale, isn't a 404. Checked live: with the switch on, `did_action( 'searchwp\query\before' )` is 0 and no SQL contains the term.

**Template tag.** `lwtv_plugin()->meili_search_results()` returns null unless this request was served from Meilisearch; otherwise `q`, `type`, `counts`, `page`, `pages` and `rows` (`post`, `type`, `document`). `search.php` branches on it and renders `template-parts/search/meili-tabs.php` (a `<nav aria-label>` list of links, `aria-current="page"` on the current tab), `meili-row.php`, `meili-pagination.php` (`paginate_links()` in a `<nav aria-label>`, keeping `s` and `type`) and `meili-empty.php` (the empty-tab message with links to tabs that have hits, or `content/none` when nothing matches anywhere). Rows use `get_the_post_thumbnail()` at `character-img` (people) or `postloop-img` (shows, posts) with `alt=""` (the row forces it past the site's `auto_alt_fix`), and `Alt_Names::matching()` with language labels for "Also known as". Styles: `scss/addons/_meili-search-page.scss`, dark overrides in `_colors-dark.scss`. `searchform.php` drops its SearchWP scope radios while the switch is on.

**Debugging hooks.** `lwtv_meili_search_request` (the body, before sending; a bad uid exercises the fallback) and `lwtv_meili_search_ids` (the chosen tab's IDs, before loading; a fake ID shows the stale-hit path).

**Fallback.** A `WP_Error` (transport, non-2xx, timeout, or a 2xx without `results`) or a response `parse()` rejects is logged to the `meilisearch` debug topic, and nothing is hooked: SearchWP runs the main query exactly as it does with the switch off, and `search.php` renders its card grid. With the switch off or the module unconfigured, the page is byte-for-byte what it was. Search logging is dropped while the switch is on; SearchWP's own log is already off.

**Accepted gaps.** SearchWP Redirects and Custom Results Order only run inside SearchWP's query, so they don't apply while the switch is on. That's why it stays at `'editors'` until the retiring-SearchWP runbook's steps for both are done.

## FacetWP keyword facets

`FacetWP_Engine` adds three engines to FacetWP's search-facet dropdown: `meili_shows`, `meili_characters` and `meili_actors` ("Meilisearch – Shows" and so on), through `facetwp_facet_search_engines`. It answers any search facet set to one of them through `facetwp_facet_filter_posts`, at priority 20, after FacetWP's own SearchWP integration, which ignores anything that isn't `swp_*`. Design: `docs/superpowers/specs/2026-11-26-meilisearch-facetwp-engine-design.md`.

- **The request.** One `/multi-search` query to that type's index, with the search key, a 2-second timeout, `matchingStrategy: 'all'` (every word must match, so the box narrows the list), `limit: 1000`, and only `id` retrieved. Built and read by `Build\Facet_Search`.
- **The result.** The hit IDs, in Meilisearch's order, limited to `FWP()->unfiltered_post_ids`, so the archive's other facets keep narrowing. With "order by relevance" on, FacetWP keeps that order. An empty keyword returns `'continue'`.
- **The switch.** `LWTV_MEILI_MODAL`, through `Search_Modal::enabled()`. A request it doesn't cover gets the same-type SearchWP engine, run the way FacetWP's SearchWP integration runs it.
- **The fallback.** Any failure (transport, non-2xx, timeout, malformed response) is logged to `meilisearch` and handed to that same SearchWP engine. Without SearchWP, FacetWP's own WordPress search runs.
- Registered only when the module is configured and FacetWP is active. Nothing changes until a facet's engine is switched in FacetWP's settings, which live in the database, not the repo.

## Deferred

Language labels on the modal's "Also known as", FacetWP, synonyms ("faq" doesn't find the FAQ page, whose title is spelled out), an admin settings screen.
