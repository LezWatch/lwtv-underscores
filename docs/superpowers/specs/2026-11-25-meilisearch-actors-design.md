# Meilisearch actors index: design

Issue: [LezWatch/lwtv-underscores#749](https://github.com/LezWatch/lwtv-underscores/issues/749). Third index, after characters and shows. Read `2026-11-24-meilisearch-characters-design.md` (keys, constants, client, queued sync, failure handling, `matchingStrategy: "frequency"`) and `2026-11-25-meilisearch-shows-design.md` (the `Index` base class, `Indexes` registry, `Sync_Routes`, the rule that sync never creates an index). Everything they say still holds unless this document says otherwise.

## Intent

Mika's comparisons favour Meilisearch over SearchWP for characters and shows, so actors are next. This round builds an `actors` index next to the other two and adds it to Search Compare against SearchWP's `actors` engine. Nothing on the live search changes.

It also replaces a workaround. Actors' other names (dead names, married names, stage names) live today in the post excerpt, labelled "Additional Notes" in the editor, because that's what Jetpack Search could read. This round gives them a proper field and moves them there.

## How people search for actors (Mika, 2026-11-25)

- By name, current or former. **Former names must be searchable**: someone searching "Ellen Page" means Elliot Page. Married names too (Courteney Cox → Courteney Cox Arquette).
- Same-name actors are told apart by when they were born: the Jorja Fox born in the 1960s vs one born in the 2000s.
- When names tie, **queer actors rank first**.

On lwtv.local (2026-11-25): 6,109 published actors, no two with the same title today (so years and the tie-breaker matter for future duplicates and for partial matches, such as a first name alone). Every actor has a bio (median 262 characters, max 9,650). 189 published actors have an excerpt: 139 are a single name, about 50 a comma list, and about a dozen are prose, quotes or a URL rather than names. Privacy: 2 `hide_all` actors (both private), 4 `hide_dob`, 0 `hide_socials`. Birth dates are stored mostly as `Y-m-d`, some as `Y-n-j` (`1975-1-25`); ACF can also write `Ymd`.

SearchWP's `actors` engine searches title, content, slug and excerpt with equal weight.

## Goals

- A new ACF repeater, **Other names**, on actors, replacing the "Additional Notes" (`excerpt`) field.
- A manual WP-CLI migration that moves name-list excerpts into it and clears those excerpts.
- An `actors` index (`config/actors.json`, applied by WP-CLI) built from a pure, tested `Build\Actor_Document`.
- Actor documents sync on save, rename, privacy change, unpublish, trash and delete, and when a linked character or show changes in a way the document quotes.
- `wp lwtv meili … --type=actors` works; `all` includes actors.
- Search Compare compares actors against SearchWP's `actors` engine.
- Architecture, runbook and structure docs updated.

## Non-goals

- The content index, front-end search and modal, FacetWP, synonyms.
- Showing other names anywhere on the site.
- Pronoun, gender or sexuality fields in the document (they aren't needed to find someone; add them when a filter wants them).
- Changing SearchWP in code. Its engine settings live in the database and are Mika's to change (see Rollout).

## Anti-goals

- A `hide_all` actor reachable through the public search key in any form.
- Other names readable through the public search key, or printed by our own templates. In Meilisearch they may only be *matched*.
- A birth year in the index for a `hide_dob` actor.
- Live SearchWP search losing dead-name matches because the migration ran before SearchWP was told about the new field.
- Anything from characters/shows: a save slowed or broken by the search server, a key in code, logs or commits, sync creating an index.

## Constraints

As for shows: PHP 8.1+, WordPress-Extra phpcs and PHPStan clean (`TMPDIR=<scratch> composer analyse`), no new Composer runtime dependencies, HTTP only through `Client`, request shapes checked against Meilisearch v1.57, pure logic in `build/` written test-first, index uid from `Config::index( 'actors' )`, `id` filterable so a plain `reindex` prunes. Ask Mika before the first write to any index, and before the migration writes to any database (lwtv.local included). Never commit without her approval in that turn.

The actor field group keeps `show_in_rest: 0`, and the new field is not registered with `show_in_rest` in `CPTs\Post_Meta` (see the privacy note at the top of that class).

Keys: lwtv.local's index key is scoped to `local_*`, so `local_actors` needs nothing new. A production key minted without `actors` must be re-minted before production can be indexed.

## Approaches

**The index.** One sensible way: an `Actors_Index` class on the shared `Index` base, registered in `Indexes`, as the shows round set up. Copying code or a config-table indexer were rejected then and nothing has changed.

**The migration** (Mika's pick, 2026-11-25):

| Approach | Trade-off | Outcome |
| --- | --- | --- |
| **A. Manual WP-CLI command, dry run first** | Each environment chooses when to run it, after SearchWP knows about the new field. Prose excerpts get a human. One more step to remember per environment. | **Chosen.** |
| B. Automatic upgrade routine on deploy | Nothing to remember. Runs before SearchWP is updated (live dead-name search breaks until it is) and moves prose excerpts blind. | Rejected. |

**Keeping former names safe while searchable** (Mika, 2026-11-25): a plain list, never shown. A kind per name (dead, married, stage) was considered so married names could be displayed; rejected, because one list that is never displayed can't leak the wrong kind.

## Design

### Other names field

- Repeater `lezactors_other_names` (label "Other Names"), one text sub-field `lezactors_other_name` (label "Name"). Same shape as shows' `lezshows_show_names` without the language column. Field keys `field_lwtv_lezactors_other_names` / `field_lwtv_lezactors_other_name`.
- Instructions: names this actor may be searched by: a former name (including a dead name), a married name, a stage name. Never shown on the site; used only to match searches.
- Placed where "Additional Notes" is now. The `excerpt` field is removed from `group_lwtv_actors_details.json`.
- Not shown on the actor page, the search results page or any template. Not in REST.
- `class-acf.php`'s excerpt bridge (`save_excerpt_to_post`) narrows to shows only. `load_excerpt_from_post` hooks a field name only shows still use, so it can stay.

### Migration: `wp lwtv migrate othernames [--dry-run] [--yes]`

A new type on the existing `wp lwtv migrate` command, alongside `bootstrap6`.

- Candidates: actors of any status with a non-empty `post_excerpt`.
- A pure `Build\Other_Names::from_excerpt( string ): ?array` decides. It splits on newlines and commas, trims, entity-decodes, drops empties and de-duplicates. It returns `null` ("needs a human") when the text doesn't look like a list of names: a piece longer than five words, a URL, a quote mark, a colon, or sentence punctuation (a full stop followed by a space and a capital). *(Builder's call on the exact tests; the dry run shows every decision, so a wrong call is visible before anything is written.)*
- Report: one row per candidate: ID, current title, status, the decision (`move` / `review` / `skip`), and for `move` the number of names. Names and excerpt text are not printed by default, so the output is safe to paste into an issue. *(Builder's call.)*
- `--show-review`: prints the full excerpt of every `review` and `skip` actor, so the operator can deal with them by hand (Mika's pick, 2026-11-25: editors can't see the excerpt once the field is gone, so the terminal is the way in).
- `--clear=<ids>`: empties `post_excerpt` and the `excerpt` meta for those actor IDs once they've been dealt with. Refuses IDs that aren't actors. Honors `--dry-run`.
- `skip`: the actor already has Other names rows. Nothing is merged; the row is reported for a human.
- Real run: confirm (unless `--yes`), then for each `move`: write the rows with `update_field( 'field_lwtv_lezactors_other_names', … )`, then clear `post_excerpt` directly (`$wpdb->update` + `clean_post_cache`, as the bridge does, so save hooks don't fire) and delete the `excerpt` / `_excerpt` meta. The meta matters: `load_excerpt_from_post` would otherwise reload the value. `review` and `skip` actors are left untouched.
- Idempotent: a second run finds no `move` rows.
- Afterwards it tells the operator to run `wp lwtv meili reindex --type=actors` (direct writes don't fire sync) and to rebuild SearchWP's index.

### The actor document

One per **published** actor that isn't `hide_all`:

```json
{
  "id": 14089,
  "name": "Jorja Fox",
  "other_names": [],
  "characters": ["Sara Sidle", "…"],
  "shows": ["CSI: Crime Scene Investigation", "…"],
  "years": "1968",
  "bio": "Plain text of the bio…",
  "queer": 1,
  "character_count": 4,
  "url": "https://lezwatchtv.com/actor/jorja-fox/",
  "thumb": "…"
}
```

Rules:

- `name`: title, entity-decoded, trimmed.
- `other_names`: the repeater's values, decoded, trimmed, de-duplicated, empties dropped (raw rows by field key or by name, like `Show_Document::alt_names()`).
- `characters`: titles of **published** characters linked to the actor (`Store::characters_for_object( $id, 'actor' )`), decoded, de-duplicated.
- `shows`: titles of **published** shows those characters are on (`Store::objects_for_character( $character, 'show' )`), decoded, de-duplicated.
- `years`: birth year and death year, space-separated, read from `lezactors_birth` / `lezactors_death`. The year is the leading four digits of `Y-m-d`, `Y-n-j` or `Ymd`; anything else gives no year. **No birth year when `hide_dob` is set.** The death year stays, since `hide_dob` doesn't cover it. `""` when neither is known. Years only, never full dates.
- `bio`: `post_content` with blocks' markup, shortcodes and tags stripped, entities decoded, whitespace collapsed. The glue strips (`strip_shortcodes()`, `wp_strip_all_tags()`); the builder decodes and collapses.
- `queer`: `1` when `lezactors_queer` is truthy, else `0` (the flag `Actors\Calculations::do_the_math()` stores, including the manual override).
- `character_count`: `lezactors_char_count` as an int, `0` when missing.
- `url`: permalink. `thumb`: featured image at `thumbnail`, or `""`.
- Socials, IMDb/Wikidata IDs, notes and every privacy field are never in the document, so `hide_socials` needs no rule.

`Build\Actor_Document::build()` is pure: the glue passes the title, raw repeater rows, character titles, show titles, birth and death values, the privacy array, the bio text, the queer flag, the count, url and thumb.

Tests first: other-names normalizing (non-array, rows by key and by name, empty, duplicates, entities); years (`Y-m-d`, `Y-n-j`, `Ymd`, garbage, birth only, death only, both, `hide_dob` drops birth but keeps death, privacy not an array); bio whitespace and entities; `queer` from `"1"`, `""`, `true`, `false`; de-duplication of characters and shows. `Other_Names::from_excerpt()`: a single name, a comma list, newlines, a married name with a hyphen, a URL, a quote, prose, a colon, empty.

### Index settings (`config/actors.json`)

- **Searchable, in order:** `name`, `other_names`, `characters`, `shows`, `years`, `bio`. A name match beats a bio mention.
- **Displayed:** every field except `other_names`. Meilisearch leaves non-displayed fields out of hits, `_formatted` and `_matchesPosition`, and the search key can't fetch documents, so other names can be matched but never read.
- **Ranking rules:** the v1.57 defaults in the order the other indexes use, then `queer:desc`, then `character_count:desc` (Mika, 2026-11-25).
- **Filterable:** `id`, `queer`.
- **Stop words:** `from`, `on`, `in` ("the actor from Skins", "jorja fox in csi"). *(Builder's call.)*

Search Compare sends `matchingStrategy: "frequency"`, as the other indexes do.

### `Actors_Index`

- `type()` `actors`, `post_type()` `Actors::SLUG`, label "Actors".
- `documents()`: prime post and meta caches for the actors, then their characters, then those characters' shows. Skip anything that isn't a published actor, or is `hide_all` (the same test `Index::lookup()` uses, so a lagging status can't leak).
- `affected_by()`:

| Changed | Actor documents affected |
| --- | --- |
| an actor | itself |
| a character | `objects_for_character( character, 'actor' )` |
| a show | the actors of `characters_for_object( show, 'show' )`, de-duplicated |

### Sync

`Sync_Routes` gains `actors`. Changes to the routes:

| Event | Characters | Shows | Actors (new) |
| --- | --- | --- | --- |
| `acf_save` character | characters, shows | | **+ actors** |
| `acf_save` actor | characters, shows | | **+ actors** |
| `acf_save` show | shows | | (unchanged: actor documents quote only a show's title and visibility, which `changed` covers) |
| `changed` (rename, in or out of publish) any of the three | characters, shows | | **+ actors** |
| `delete` actor | none today | | **actors** |
| `calculated` actor (new event) | | | **actors** |

- **Old links.** `before_acf_save` already records a character's shows before the save. It records the character's actors too, and `on_acf_save` queues the actors index for each, so an actor the character was unlinked from drops its name.
- **Character delete.** `on_delete` (priority 5, before the link rows go at 10) queues the actors index for each of the character's actors, as it does for shows.
- **Actor calculation.** `Actors\Calculations::do_the_math()` fires a new `lwtv_actor_calculated` action after writing `lezactors_queer` and the counts. `Sync` maps it to the `calculated` event, so the tie-break values are fresh. This runs after a character save too, because character calculations recalculate their actors.
- **Privacy.** `hide_all` is ACF meta, so an actor save covers it, and the post going private is a `changed` transition.
- Task hook `lwtv_meili_actors_task`, registered from the registry like the others.
- Known gap, unchanged: force-deleting a published show drops its link rows before fan-out, so actors keep its title until the next `reindex`.

### WP-CLI

`--type` accepts `actors`; `all` includes it. `Type_Option`'s default stays `characters`. `reindex --type=actors` batches and prunes like the others. `sync <id>` already covers every index.

### Search Compare

An Actors option in the switch, SearchWP engine `actors`, `matchingStrategy: "frequency"`. Actor rows show name, years, a queer marker and the first few characters. `compare.js` highlights `name`, `characters`, `shows` (never `other_names`). The SearchWP column builds its rows through `Actors_Index::documents()`, so it also never prints other names.

### Rollout, per environment

1. Deploy.
2. In SearchWP's `actors` engine, add the custom field `lezactors_other_names_*_lezactors_other_name` (Mika, in SearchWP's settings).
3. `wp lwtv migrate othernames --dry-run`, review the `review` rows, then run it.
4. Rebuild SearchWP's index, so it picks up the moved names.
5. `wp lwtv meili settings --type=actors`, then `wp lwtv meili reindex --type=actors`.

**SearchWP may show a matched other name.** With highlighting on (it is on lwtv.local), SearchWP builds a result's excerpt from the matching custom field when the term isn't in the excerpt or bio, so a search for a former name can print that name under the result. Mika, 2026-11-25: accepted, these names aren't secret; no guard is added.

Old names stay in actor revisions (`post_excerpt` and ACF meta are copied onto revisions). Only editors see revisions, and they see the Other names field anyway, so revisions aren't scrubbed. *(Builder's call.)*

Until step 3 the old excerpts keep working in SearchWP. They also still print on the search results page (`template-parts/content/search.php` and SearchWP's live results call `the_excerpt()`), which is a reason not to wait long.

## Verification on lwtv.local

- `reindex --type=actors` builds `local_actors` (about 6,107 documents); `status --type=actors` reports settings match; the 2 `hide_all` actors are absent.
- A search for a moved other name, with the search key, finds the actor, and the raw response contains no `other_names` field, no highlighted former name and no `_matchesPosition` for it.
- A `hide_dob` actor's document has no birth year.
- Throwaway-post sync test: actor create, save, rename, `hide_all` on and off, `hide_dob` on, unpublish and republish, trash and restore, force-delete; a character linked and unlinked, renamed, trashed, deleted; a show renamed and unpublished. Each changes the right actor documents.
- Migration dry run and real run on lwtv.local (after Mika's go-ahead), then a second dry run reporting nothing to move.
- Search Compare (Actors) next to SearchWP for a short list of queries Mika picks; the builder suggests some (an other name, a first name alone, an actor by character, a misspelling).
- Unit tests, `composer lint`, `composer analyse`, eslint and `npm run docs:structure:check` clean.

## Editorial follow-ups (not code)

- 3 of the 4 `hide_dob` actors mention "born" or a year in their bio. The bio is public on their page, so this is for editors to check.
- About a dozen excerpts are prose, not names; `--show-review` prints them and `--clear` empties them once handled.

## Deferred

The content index; front-end search with its WordPress-search fallback; synonyms; pronoun/gender filters; staging and production indexing.

## Left to the builder

- Class and method names; whether `Other_Names` lives under `meilisearch/build/` or `cpts/actors/build/` (it's an actor-data rule, used by the migration).
- The exact list-vs-prose tests in `Other_Names::from_excerpt()`.
- Cache priming and batch sizes.
- Search Compare row layout.
