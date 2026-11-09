# Plan: Retire the shadow taxonomy

**Status:** phases 1 to 4 implemented (2026-11-09), not yet verified on a live site. The read source defaults to `shadow`; nothing changes until you run `wp lwtv links source table`. Phases 5 and 6 are not started.

## What we actually have

The bundled library at `plugins/shadow-taxonomy/` is not `mrbobbybryant/shadow-taxonomy`. It is
`spock/shadow-taxonomies` 0.3.3, a fork by Utkarsh Patel. It is vendored into the repo rather than
installed through Composer, and phpcs and PHPStan skip it. The conclusion is the same: nobody
maintains it, and we use very little of it.

**What we use from the library:**

| Function | Used for |
|---|---|
| `create_relationship()` | Creates a character's term on `wp_insert_post` and deletes it on `before_delete_post` |
| `get_associated_term()` | Character post → its term (via the `shadow_shadow_tax_characters_term_id` postmeta) |
| `get_associated_post_id()` | Term → character post (via the `shadow_shadow_tax_characters_post_id` termmeta) |
| `get_the_posts()` | Show/actor → their character posts |

The library's `wp shadow sync` CLI is loaded as well. Our own command is `wp lwtv shadow`.

**What the taxonomy is for:** `shadow_tax_characters` is registered on shows, actors *and*
characters. It is a **reverse index only**. The real relationship data lives on the character, in
ACF fields:

- `lezchars_show_group`: a repeater whose `show` sub-field is a `post_object`
- `lezchars_actor`: a `relationship` field

A character "owns" the links. The term exists so a show or actor can ask "which characters point at
me?" with a `tax_query` instead of a `meta_query` against a repeater.

**FacetWP does not use it.** No facet sources `tax/shadow_tax_characters`. Both character facets read
`lezchars_show_group` through ACF directly (`plugins/facetwp/class-indexing.php`). The only thing
FacetWP-related that touches the taxonomy is the reindex task, which uses it to find a show's or
actor's characters.

### Consumers (all in `plugins/lwtv-plugin/php/`)

**Writers** (keep terms in sync with the ACF fields):

1. `cpts/characters/class-calculations.php`: `sync_shows()` and `sync_actors()`, run by the
   `calculation` task on every character save. Includes the transient-based retry/failure logic and
   the `lwtv_shadow_tax_sync_failed` action.
2. `theme/class-show-characters.php`: `clean_character_array()` **adds and removes terms while a
   show is being read**.
3. `theme/class-actor-characters.php`: `build_character_info()` **removes terms while an actor is
   being read**.
4. `wp-cli/cli-shadow.php`: `wp lwtv shadow shows|actors` does a full resync.

**Readers:**

5. `theme/class-show-characters.php`: `make()` gets a show's character list (this feeds the
   **show score**: character score and alive ratio).
6. `theme/class-actor-characters.php`: `generate_list()` gets an actor's character list (this feeds
   `lezactors_char_list` and the counts).
7. `theme/class-character-relationships.php`: finds characters that reference this character
   (details below).
8. `plugins/class-cache.php`: cache URLs and object-cache invalidation for related characters.
9. `schedulers/class-facet-reindex-task.php`: finds a show's or actor's characters to reindex.
10. `queeries/class-shadow-taxonomy.php`: `get_shows_for_character()` and
    `get_actors_for_character()` are used by #1. The six `*_cached` and `*_batch` methods have **no
    callers**.

**Ops scaffolding:** the daily drift check (an Action Scheduler action), the admin notice, the
debugger's actor problem flag, the `shadow-taxonomy` debug-log category, the
`lezchars_relationship_chart => shadow_tax_characters` entry in `SELECT2_TAXONOMIES` (nothing reads
that constant any more), and the phpcs/PHPStan excludes and PHPStan baseline entries.

### Things this review turned up

- **Character ↔ character relationships are frozen legacy data.** `Character_Relationships::make()`
  combines (a) the `lezchars_relationship_chart` postmeta and (b) characters that have *this*
  character's term attached. Nothing in the ACF field groups defines `lezchars_relationship_chart`,
  and no current code attaches shadow terms to character posts. Both sources are leftovers from the
  CMB2 era that nobody can edit any more. **We need to decide what happens to this before we drop
  the terms.**
- **Reading a page writes to the database.** Show and actor pages add and remove term relationships
  as they render (#2 and #3). This is the "keep it in lockstep" pattern working against us: every
  page view that hits a cold cache can write.
- **`do_the_math()` assumes the term exists.** `get_associated_term()` returns `false` when a
  character has no term, and `sync_shows()`/`sync_actors()` then read `->term_id` off `false`. On PHP
  8 that is a warning and a silent no-op. The character's links never reach the index.
- **The fallback paths are broken.** `get_characters_from_post_meta()` runs a `LIKE` against
  `lezchars_show_group`. Because that is an ACF repeater, the parent meta key only stores the **row
  count**, not show IDs. The actor fallback's `LIKE '%123%'` against a serialized array also matches
  1234. These only fire if the taxonomy doesn't exist, but they are not a safety net.
- **`lezshows_char_list` and `lezactors_char_list` already exist** as denormalized reverse lists,
  written during show and actor calculations. They are derived *from* the shadow taxonomy read, so
  they inherit its drift. After this migration they should be derived from the new index instead.
- **`Character_Relationships::make()` was already fatal.** It called `Taxonomy_Optimized::make()`,
  which does not exist, so the legacy character links never rendered. It now works through the
  repository.

## Recommendation: a junction table, behind a repository class

Of the three options in the proposal:

- **A: Junction table. Recommended.** A character owns its links, so syncing is just "delete this
  character's rows, insert the current ones". That is idempotent, has a single owner, and leaves
  nothing to drift or orphan. The reverse lookup becomes one indexed `SELECT`.
- **B: ACF bidirectional.** This doesn't fit the main relationship. `lezchars_show_group.show` is a
  sub-field inside a repeater. As far as I know, ACF's bidirectional setting only works on top-level
  fields (check against the ACF version we run). It would also replace term rows with large
  serialized arrays on each show (The L Word has 60+ characters), updated read-modify-write, so two
  character saves at once can lose a link. It could work for `lezchars_actor` alone, but then we'd be
  running two mechanisms.
- **C: Swap in another shadow-term library.** This keeps the doubled term footprint, the sync hooks
  and the drift problem, and only changes who maintains them. I couldn't confirm the plugin the
  proposal names, so I wouldn't plan around it.

### Table

```sql
CREATE TABLE {$wpdb->prefix}lwtv_char_links (
  character_id BIGINT UNSIGNED NOT NULL,
  object_id    BIGINT UNSIGNED NOT NULL,
  object_type  VARCHAR(20)     NOT NULL,  -- 'show' | 'actor' | 'character' (legacy, see decision)
  PRIMARY KEY  (character_id, object_type, object_id),
  KEY reverse  (object_id, object_type)
);
```

Rough size: about 7,400 characters × (shows + actors) ≈ 20–25k narrow rows. It is created with
`dbDelta()` behind a schema-version option, the same way other installs handle custom tables.

The table stores links only. Post status is checked when reading (`JOIN wp_posts … post_status =
'publish'`), so trashing or drafting a character drops it everywhere without any extra write.

### Legacy character links

Both legacy sources become rows where `character_id` is the character that does the referencing:

| Source | Means | Row |
|---|---|---|
| `lezchars_relationship_chart` on X lists Y | X references Y | `(character_id=X, object_id=Y, 'character')` |
| X's shadow term is attached to character post Y | Y references X | `(character_id=Y, object_id=X, 'character')` |

`characters_referencing( X )` used to return the union of both directions, so it must keep doing
that: `object_id = X` **or** `character_id = X`, with `object_type = 'character'` in both cases,
deduplicated, published only.

**Load-bearing rule:** `sync_character()` deletes and rewrites **only** the `show` and `actor` rows.
The `character` rows don't come from ACF, so a normal save that wiped them would lose them for good.
The only thing that writes them is the one-off `wp lwtv links rebuild --legacy-characters` import.
That import must run in Phase 3, **before** Phase 6 purges the terms the second source comes from.

> **Warning:** run `wp lwtv links rebuild --legacy-characters` before any phase-6 shadow purge. Half of
> the legacy character links exist only as shadow terms, and the purge destroys them for good.

`before_delete_post` still deletes them when either character is deleted.

Phase 0 counts both sources so the import can be checked. Phase 3's `diff` compares
`Character_Relationships::make()` output for every character before and after.

## Phases

Each phase ships on its own and can be rolled back without the next one.

### Phase 0: Measure (read-only)

On production and on lwtv.local, record: the term count compared with character counts by status;
`term_relationships` rows by object post type; orphan terms (termmeta pointing at a missing post);
character-on-character rows; and how many characters have `lezchars_relationship_chart` set. This
gives a baseline for the diff in Phase 3 and tells us how big the legacy relationship data is.

### Phase 1: Put one seam in front of every consumer (no behavior change)

Add `LWTV\Relationships\Character_Links` (name TBD), with:

- `characters_for_show( int ): int[]`
- `characters_for_actor( int ): int[]`
- `shows_for_character( int ): int[]`
- `actors_for_character( int ): int[]`
- `characters_referencing( int ): int[]`

Implement it on top of the shadow taxonomy first. Then route readers #5–#9 through it. After this
phase, the library is only called from inside one class.

At the same time, **remove the writes from read paths** (#2 and #3). The save-time sync in #1 is the
source of truth, so page renders should only read.

Delete the dead `*_cached`/`*_batch` queery methods.

### Phase 2: Build and dual-write the table

- Add a pure transform class (under the `build/` convention, written test-first) that turns
  `get_field( 'lezchars_show_group' )` and `get_field( 'lezchars_actor' )` output into link rows.
  This is where the pre-CMB2 `[ 'show' => [ id ] ]` unwrapping lives, so it gets unit tests.
- Add a writer, `sync_character( $id )`: in a transaction, delete this character's rows, then
  insert the new ones. Hook it to `acf/save_post` at priority 20 (after ACF has written) and to the
  existing `calculation` task. Add `before_delete_post` to delete rows on either side of a link.
- Keep the shadow-term writes running for now.

### Phase 3: Backfill and prove parity

- Add `wp lwtv links rebuild [--id=<id>]` to fill the table from ACF for every character.
- Add `wp lwtv links diff` to compare, for each show and actor, the table, the shadow terms and the
  ACF source, and print any mismatches. Expect some, because the shadow index has known drift (see
  above). Every mismatch should come out in the table's favor, meaning the table agrees with ACF.

### Phase 4: Flip reads

- Back the repository with the table. Feed `lezshows_char_list` and `lezactors_char_list` from it.
- **Score gate:** run `wp lwtv score-preview` before and after the flip. Every score change has to
  trace back to a shadow-drift mismatch from the Phase 3 diff. Only then run `wp lwtv calc --all`.
- Check against the running site: a show page, an actor page (count and dead list), the FacetWP
  reindex after an actor save, cache purge URLs, and the character relationship block.

### Phase 5: Remove the library and its scaffolding

Remove: the `create_relationship()` call and `require` in `_components/class-plugins.php`; the
`plugins/shadow-taxonomy/` directory; `cli-shadow.php`; `queeries/class-shadow-taxonomy.php`; the
retry transients and the `lwtv_shadow_tax_sync_failed` action and debugger flag; the drift check
(unschedule `lwtv_shadow_tax_drift_check` with `as_unschedule_all_actions`); the admin notice; the
stale `SELECT2_TAXONOMIES` entry; the phpcs/PHPStan excludes; and the baseline entries (run
`composer analyse`). Retire the `shadow-taxonomy` log category, or rename it to `char-links`, and
update `LogRulesTest`. Update `docs/plugin-structure.txt` and the architecture docs.

Keep `register_taxonomy( 'shadow_tax_characters' )` registered for one more release, so the old
data can still be inspected and the change can be rolled back.

### Phase 6: Clean up the data (after a release cycle)

Add `wp lwtv links purge-shadow --dry-run`. It deletes the taxonomy's `term_relationships`,
`term_taxonomy`, `terms` and termmeta, plus the `shadow_shadow_tax_characters_term_id` postmeta, in
batches. Take a database backup first, since this can't be undone. After that, unregister the
taxonomy.

## Open decisions

1. **Character ↔ character relationships: DECIDED (2026-11-09): migrate them into the table**
   as `object_type = 'character'`. They stay read-only legacy data. See "Legacy character links"
   below.
2. **Table compared with meta: DECIDED (2026-11-09): use the custom table.**
   `lezshows_char_list` and `lezactors_char_list` stay as caches derived from it.

## Production cut-over runbook

Phases 1 to 4 ship together, with reads still on `shadow`. Phases 5 and 6 (removing the library and
cleaning up its data) come in later releases. Do these in order:

1. Back up the database.
2. Deploy. The link table installs itself on the first request, through `Store::maybe_install` on
   `init`.
3. `wp lwtv links rebuild`
4. `wp lwtv links rebuild --legacy-characters`
5. `wp lwtv links diff --type=show`, then `--type=actor`, then `--type=character`. Save all three
   reports.
6. Snapshot scores and counts (`lezshows_the_score`, `lezshows_char_count`, `lezshows_dead_count`,
   `lezactors_char_count`, `lezactors_dead_count`) to `before.tsv`. Use the query in
   `docs/plans/shadow-taxonomy-live-checks.md`, Task 6. It uses `$(wp db prefix)` rather than a
   hardcoded `wp_`, and joins `posts` so revisions (which copy the ACF meta) are excluded.
7. `wp lwtv links source table` (refuses while the table is empty; `--force` overrides)
8. `wp lwtv calc --all`
9. Take the same snapshot to `after.tsv` and diff it. Every changed post ID must be in the step 5
   reports with `acf=yes`. Anything else is a bug.
10. Spot-check pages: a show, an actor, a character with legacy relationships, an actor save (the
    FacetWP log line `Re-indexed N character(s)`), and trashing then untrashing a character.

**Rollback:** `wp lwtv links source shadow`, then `wp lwtv calc --all`. Shadow terms are still being
written during phases 1 to 4, so a rollback is lossless.
