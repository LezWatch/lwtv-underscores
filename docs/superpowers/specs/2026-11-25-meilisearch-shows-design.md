# Meilisearch shows index: design

Issue: [LezWatch/lwtv-underscores#749](https://github.com/LezWatch/lwtv-underscores/issues/749). Follows the characters prototype: `docs/superpowers/specs/2026-11-24-meilisearch-characters-design.md` (read it first; everything it says about keys, constants, the client, queued sync and failure handling still holds).

## Intent

Mika's 20-query comparison of characters strongly favoured Meilisearch over SearchWP, so shows are next. This round builds a `shows` index next to `characters`, so the same side-by-side comparison can be run against SearchWP's `shows` engine. Nothing on the live site changes.

It is also the round where the module stops being characters-only. The end state is four indexes (characters, shows, actors, content), so the generalization is built to take the next two without copying code.

## How people search for shows (Mika, 2026-11-25)

- About 80% of searches are the show's name.
- The rest disambiguate same-name shows with a hint: "Charmed from the late 2000s with the lesbian sister" means *Charmed (2018)*. People misremember years ("late 2000s" for a 2018 show), so a character's name ("charmed mel") is a more reliable hint than a year.
- Actor names should find shows too ("the show with Kate McKinnon").

On lwtv.local (2026-11-25): 2,266 published shows, no duplicate titles, 26 base titles shared by two or three shows (titles disambiguate with a trailing parenthetical: `Charmed (2018)`, `Skins (US)`, `Queer as Folk (UK)`). 61 shows have alternate names; SearchWP's `shows` engine searches title, content, slug and excerpt with equal weight and doesn't index the alternate-name repeater (it can still match one the write-up mentions).

## Goals

- A `shows` index whose settings are versioned in `config/shows.json` and applied by WP-CLI.
- A pure, tested `Build\Show_Document`.
- Shows sync on save, rename, unpublish, trash and delete, and when a linked character or actor changes in a way the show's document quotes.
- `wp lwtv meili` commands work per index; the characters index behaves exactly as today.
- Search Compare can compare shows against SearchWP's `shows` engine.
- Architecture and runbook docs updated.

## Non-goals

- The actors and content indexes (the design must make them easy; building them is a later round).
- Front-end search, the modal, FacetWP, synonyms, filters in the UI.
- Any change to SearchWP, its engines, or FacetWP's `search_shows` / `search_characters` / `search_actors` facets (which use SearchWP engines `swp_shows` etc.).
- Searching the show write-up (`post_content`), excerpt, tropes or genres.

## Anti-goals

Same as characters: no change to SearchWP or the live search UI; no save slowed or broken by the search server; no key in code, logs or commits; nothing unpublished and no private actor's name reachable through the public search key.

## Constraints

As for characters: PHP 8.1+, WordPress-Extra phpcs and PHPStan clean (`TMPDIR=<scratch> composer analyse`), no new Composer runtime dependencies, HTTP only through `Client`, request shapes checked against the Meilisearch v1.57 docs, pure logic in `build/` written test-first (`vendor/bin/phpunit`), index uid from `Config::index( 'shows' )`. The first write to any index needs Mika's go-ahead in chat (lwtv.local `local_*` follows the pattern from the characters work; production needs its own). Never commit without Mika's approval in that turn.

Production keys: the runbook mints production keys with `indexes: ["characters", …]`. If production's key was minted with `["characters"]` only, it must be re-minted to include `shows` before production can be indexed. lwtv.local's `local_*` key already covers `local_shows`.

## Approaches (generalizing from one index to several)

| Approach | Trade-off | Outcome |
| --- | --- | --- |
| **A. One class per index behind a shared base** | Each index says its post type, settings file, document builder and which of its documents a post change affects. Batching, add/delete, pruning and the CLI stay shared. Actors and content are one class each later. Moves the characters code but not its behaviour. | **Chosen by Mika.** |
| B. Copy the characters code for shows | Fastest now, touches characters least. Four copies of batching, pruning and sync by the end state. | Rejected. |
| C. One generic indexer driven by a config table | Least code, but per-type differences (character fan-out, actor privacy, show↔character links) become special cases inside the table. | Rejected. |

Mika asked how A holds up for actors. The answer that settled it: every index answers one question, "this post changed: which of my documents does that affect?" `Sync` asks every index and queues one task per index with affected IDs. The full matrix once actors exist:

| Change | characters index | shows index | actors index (later) |
| --- | --- | --- | --- |
| Character saved / renamed / in or out of publish / deleted | that character | its shows | its actors |
| Show saved / renamed / in or out of publish / deleted | its characters | that show | its actors (via its characters) |
| Actor saved / renamed / in or out of publish | their characters | their shows (via their characters) | that actor |

This round implements the first two columns. Every cell is a lookup on `lwtv_char_links` (`Store::characters_for_object()`, `Store::objects_for_character()`).

## Design

### The show document

One per **published** show:

```json
{
  "id": 32399,
  "name": "Charmed",
  "qualifier": "2018",
  "alt_names": [],
  "characters": ["Abigael Jameson-Caine", "Mel Vera", "…"],
  "actors": ["Melonie Diaz", "…"],
  "years": "2018 2022",
  "worth_it": "Yes",
  "worth_it_rank": 3,
  "score": 64.45,
  "stations": ["The CW"],
  "nations": ["USA"],
  "url": "https://lezwatchtv.com/show/charmed-2018/",
  "thumb": "…"
}
```

Rules:

- `name` / `qualifier`: the title, entity-decoded, split on **one trailing parenthetical**: `Charmed (2018)` → `Charmed` + `2018`; `Skins` → `Skins` + `""`. Only a final `( … )` at the very end counts; a title with nothing after the name keeps an empty qualifier. *(Why: Meilisearch's `exactness` rule prefers a whole-attribute match, so with full titles "charmed" ranks plain *Charmed* above *Charmed (2018)* before any tie-breaker runs. Splitting lets same-name shows tie on the name, so Worth It and score decide, while "charmed 2018" and "skins us" still match the qualifier.)*
- `alt_names`: `lezshows_alt_show_name` values from the `lezshows_show_names` repeater, trimmed, entity-decoded, de-duplicated, empty ones dropped. The language code (`type`) is not stored.
- `characters`: titles of **published** characters linked to the show (`Store::characters_for_object( $id, 'show' )`), decoded, de-duplicated, in that query's order (title).
- `actors`: titles of actors linked to those characters that are **published and not `hide_all`** (the same visibility test the character document uses), decoded, de-duplicated.
- `years`: start and finish years, read through `Shows\Airdates::get()` (so shows still on the legacy airdates format are covered), as one space-separated string (`"2018 2022"`; `"2024"` when finish is `current` or empty; `""` when there's no start; a single year when start equals finish). Searchable so "charmed 2018" works; no attempt to match decades.
- `worth_it` / `worth_it_rank`: `lezshows_worthit_rating`. Rank Yes 3, Meh 2, TBD 1, No 0, anything else 0. *(Builder's call: TBD is "not rated yet", so it sits above an editorial No and below Meh. Mika may overrule.)*
- `score`: `lezshows_the_score` (clamped 0–100) as a float; 0 when missing.
- `stations` (`lez_stations`) and `nations` (`lez_country`): term names, stored for later filtering and for display on Search Compare. Not searchable: "The CW" would otherwise match hundreds of shows.
- `url`: permalink. `thumb`: featured image at `thumbnail`, or `""`.

`Build\Show_Document::build()` is pure: the glue passes the title, raw repeater rows, start/finish values, worth-it value, score, character titles, actor titles (already visibility-filtered, or with a visible flag the builder applies), station and nation names, url and thumb. Title splitting (`Build\Show_Title` or a static method) and the years string are separate pure functions with their own tests.

Unit tests (written first): title split (no parenthetical, trailing year, trailing country, parenthetical mid-title, nested or unbalanced brackets, entities), alt-name normalizing (false/null rows, empty names, duplicates, entity decoding), years (start only, `current`, equal start/finish, missing start), worth-it ranks including TBD and unknown, de-duplication of characters and actors, hidden actors left out.

### Index settings (`config/shows.json`)

- Primary key `id`.
- **Searchable, in order:** `name`, `qualifier`, `alt_names`, `characters`, `actors`, `years`.
- **Ranking rules:** the v1.57 defaults in the same order `characters.json` uses, then `worth_it_rank:desc`, then `score:desc`.
- **Filterable:** `id` (needed by `reindex` pruning), `worth_it`, `stations`, `nations`.
- **Stop words:** `from` and `on` (as for characters), plus `with` ("charmed with mel"). *(Builder's call: `the` stays a normal word because so many titles start with it.)*
- Displayed: all.

Expected results, used as the live check list:

| Query | Expected first |
| --- | --- |
| skins | Skins (UK original, Worth It Yes) |
| charmed | Charmed (2018) |
| night court | Night Court (2023) |
| shameless | Shameless (UK) |
| ghosts | Ghosts (UK) |
| mistresses | Mistresses (2008) |
| gossip girl | Gossip Girl (2021) |
| charmed mel | Charmed (2018) |
| skins us | Skins (US) |
| land of desire | Terra e Paixão (alternate name) |

Mika confirmed the plain-name rows (2026-11-25).

### When an index isn't set up (Mika, 2026-11-25)

An index that doesn't exist on this environment is **never created by sync**. Meilisearch creates an index on the first document add, so without a guard the first editor save on production would build `shows` with default settings before anyone approved it.

- **Sync skips it.** Before writing, a queued task checks the index exists (`Client::index_stats()`), cached per uid in a transient: `exists` for an hour, `missing` for five minutes. On `missing` the task logs one line to `meilisearch` and returns. The check runs in the queued task, never during a save. Only `wp lwtv meili settings` / `reindex --fresh` create an index; running `wp lwtv meili settings --type=shows` is what switches shows on for an environment.
- **Admins are told.** An admin notice for `manage_options` users names each configured index type whose index is missing, and the command that sets it up. It reads the same cached state; when the cache is empty it does one check per uid with the short timeout, at most once per expiry, so admin pages aren't slowed. *(Builder's call: shown on the Dashboard and LezWatch.TV admin screens, not every screen, so editors working on posts aren't nagged; not dismissible, because the fix is one command.)*
- **Search Compare** shows "Index not set up on this environment" in the Meilisearch column instead of a raw error.
- **Fallback to regular WordPress search** applies to the front-end search, which doesn't use Meilisearch yet. It is a requirement for the front-end round: when the search server is unreachable or the index is missing, the front end falls back to WordPress's own search. Recorded under Deferred; nothing to build now.

### Expected results: who decides

Mika (2026-11-25): the builder uses their own judgement if a row in the expected-results table fails live, and reports each decision and why so it can be adjusted. Levers, in the order to try them: TBD's rank, the searchable attribute order, stop words, then adding a tie-breaker field (for example start year). Every change, and every row that still fails, goes in the note to Mika.

### Query options

Search Compare sends `matchingStrategy: "frequency"` for shows as for characters.

### Generalization (approach A)

Suggested shape; names are the builder's:

- **`Index` (abstract base, WP glue):** `type()` (`'characters'`, `'shows'`), `post_type()`, `uid()` via `Config::index( type )`, `settings_path()`, `documents( int[] $ids ): array[]` (abstract; published posts of its type only), `affected_by( string $post_type, int $post_id ): int[]` (abstract), plus shared `document()`, `sync( $id )`, `sync_many( $ids, $chunk )`. The existing `lookup()` (title + visibility, with the actor `hide_all` check) moves here as a shared helper.
- **`Characters_Index`:** today's `Indexer` logic, unchanged in behaviour. `TYPE = 'characters'`.
- **`Shows_Index`:** gathers show inputs, primes caches per batch (shows, their characters, those characters' actors, term caches for stations/nations), calls `Show_Document`.
- **`Indexes` (registry):** builds every index from one `Client` + `Config`; `get( type )`, `all()`, `types()`. The only place that lists index types.
- `Indexer` as a name goes away; anything referencing `Indexer::TYPE` is updated.

`affected_by()` for this round:

| Index | Character changed | Show changed | Actor changed |
| --- | --- | --- | --- |
| Characters | itself | `characters_for_object( show, 'show' )` | `characters_for_object( actor, 'actor' )` |
| Shows | `objects_for_character( char, 'show' )` | itself | shows of `characters_for_object( actor, 'actor' )` |

Note `objects_for_character()` and `characters_for_object()` return **published** posts only. That's correct for fan-out targets (an unpublished show's task deletes its own document via "itself", not via a lookup).

### Sync

Hooks stay where they are (the existing `acf/save_post` priority 30, `transition_post_status` crossing publish, `before_delete_post`, `post_updated` rename via `Change_Filter`). For each event, `Sync` decides which indexes to ask (see the list below: a routine show save asks only the shows index), and queues **one task per index**, keyed by the changed post: `lwtv_meili_{type}_task` (`lwtv_meili_characters_task`, `lwtv_meili_shows_task`) with the post ID as the only arg, because `schedule_task()` takes a single int. *(Builder's call.)* Keying by the changed post, not the affected list, means the task re-reads links when it runs and the queue stays one pending task per post per index. The task runs `affected_by()` and `sync_many()`s the result; the "itself" case reconciles (add if published, delete otherwise).

Changes in what triggers a re-sync:

- **Show saved** (`acf/save_post`): now queues the show's own document. Routine show edits change `worth_it`, score, alt names, years, stations, so every show save re-syncs the show itself (one document, cheap). It does **not** fan out to characters on a routine save; character documents only quote the show's title and visibility, which `post_updated`/`transition_post_status` already catch.
- **Score recalculation** writes `lezshows_the_score` outside an ACF save (Action Scheduler calculation task, `wp lwtv calc`). Queue the show's own task right after `update_post_meta( $post_id, 'lezshows_the_score', … )` in `Calculations::do_the_math()` (via an action fired there, or `updated_post_meta`/`added_post_meta` filtered to that key). *(Builder's call.)* `wp lwtv calc --all` then queues ~2,266 unique tasks; acceptable since each is one document, but note it in the runbook.
- **Character saved:** its shows re-sync too, because the show document lists character and actor names.
- **Character removed from a show** (a show-group row deleted): after the save, `objects_for_character()` no longer returns that show, so the old show would keep the character's name. `Sync` captures the character's show IDs **before** ACF and the link table write (`acf/save_post` priority 5, request-scoped), and queues those shows as well. Same need for actors removed from a character (affects shows' `actors`).
- **Character deleted:** the link table deletes its rows on `before_delete_post` at priority 10, so Meilisearch's handler runs at priority **5**, reads the character's shows immediately and queues each show's own task (not the character's fan-out).
- **Actor saved / renamed / made private:** queues both indexes (characters and shows via characters).

Known gap, kept from characters: force-deleting a published show or actor drops its link rows before any fan-out runs, so dependants keep the old name until the next `reindex`.

Old task hooks: `lwtv_meili_character_task` and `lwtv_meili_fanout_task` are **removed**, not kept (Mika, 2026-11-25). The characters prototype that queued them never left `release/8.0.0`, so no deployed environment has them queued. On lwtv.local, check Action Scheduler for pending ones before switching the site to this code; any left would be marked failed, and `wp lwtv meili reindex` repairs the affected documents.

### WP-CLI

- `status`, `settings`, `reindex` take `[--type=<type>]`, one of `characters|shows|all`, **default `characters`** so today's commands do the same thing. Each command prints `Index: <uid> on <host>` per index before touching it.
- `reindex --type=shows` loops published shows in batches exactly like characters, then prunes with `Prune_Filter` (`id NOT IN [...]`).
- `sync <id>`: re-syncs everything that post affects, in every index (same path as the queued task, run inline), and says which documents were added or removed per index.

### Search Compare

- A Characters / Shows switch (radio or select) above the search box; the choice picks the Meilisearch index uid and the SearchWP engine (`characters` or `shows`) sent to the REST route.
- The REST route `GET /lwtv/v1/meili-compare/searchwp?q=&type=` accepts `type` from an allow-list (the registry's types); unknown types are rejected. It builds SearchWP hits through the matching index's `documents()`.
- The localized data carries `{ type: { index, engine } }` for each type.
- Show rows display name + qualifier, years, Worth It, score and the first few characters; character rows stay as they are.
- `matchingStrategy: "frequency"` for both.

### Docs

Update `docs/architecture/meilisearch.md` (shows document, settings, the affected-by matrix, sync table, commands with `--type`), `docs/runbooks/meilisearch.md` (first run for shows, `--type`, production key needing `shows`, the `wp lwtv calc --all` queue note) and `docs/plugin-structure.txt`.

## Scope of this round

lwtv.local only (`local_shows`). Staging and production indexing, the production key re-mint, and a production comparison are a later step with their own go-ahead.

## Deferred

Actors and content indexes; front-end search and modal, including **falling back to regular WordPress search when the search server or an index is unavailable**; staging/production indexing; filters in the UI.

## Done when

- `wp lwtv meili reindex --type=shows --fresh` builds `local_shows` on lwtv.local with versioned settings, and `status --type=shows` reports settings match.
- `wp lwtv meili reindex` (no flag) still builds and prunes `local_characters` exactly as before.
- On lwtv.local, verified live: a show save, rename, unpublish, trash and delete; a character added to and removed from a show; a character rename; an actor made private. Each changes the right documents in both indexes.
- The expected-results table above checked on Search Compare (Shows), next to SearchWP's `shows` engine.
- On an environment without `local_shows`, saves don't create it, and admins see the notice.
- Unit tests pass; `composer lint` and `composer analyse` clean.
- Mika gets a note: what was built, what was verified live, what wasn't.

## Left to the builder

- Class and method names; whether `Show_Title` is its own class.
- The exact hook for score recalculation.
- Batch sizes and cache priming details.
- Whether the old task hooks route to the new handler or simply run the old logic for one release.
