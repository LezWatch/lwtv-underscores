# FacetWP Indexing

What LWTV changes about FacetWP's index, and how the index is kept current when data changes outside a normal save.

Code: `Plugins\FacetWP\Indexing` ([class-indexing.php](../../plugins/lwtv-plugin/php/plugins/facetwp/class-indexing.php)) and `Schedulers\Facet_Reindex_Task`.

## Index rows

`Indexing::facetwp_index_row()` (on `facetwp_index_row`) rewrites rows per post type before FacetWP stores them. It splits arrays into one row per value and turns stored values into readable labels (for example `'1'` → "Is Queer", `'yes'` → "Yes").

Each FacetWP index row stores a **display value**. For the character facets that value is **another post's title**, resolved when the *character* is indexed:

- `facetwp_index_row_characters_actors()` uses `get_the_title()` of each linked actor.
- `facetwp_index_row_characters_shows()` uses `get_the_title()` of each linked show.

## Why related titles go stale

Renaming an actor or a show changes nothing about the characters that reference it, so FacetWP never re-indexes them. Every character row keeps quoting the old name. Nothing breaks visibly until someone searches the facet for the new name and gets nothing.

The normal save path does not cover this. `Calculation_Task` re-indexes only the post it just calculated, because calculations update meta (scores, counts, on-air) outside `save_post`, where FacetWP would not notice.

## Rename fan-out

1. `Indexing::reindex_characters_on_rename()` runs on `post_updated`. It returns early unless:
   - the post was not an `auto-draft`,
   - the post type is in `Indexing::TITLE_SOURCES` (actors, shows),
   - the title changed, and
   - the new title is not empty (an empty title is a save in progress, not a new name).
2. It calls `lwtv_plugin()->schedule_task( 'facet_reindex', $post_id )`. The work is deferred because one rename can touch every character on a long-running show, and none of it needs to happen before the redirect.
3. `Facet_Reindex_Task::process_facet_reindex_task()` (hook `lwtv_facet_reindex_task`) finds the characters through the character shadow taxonomy (`CPTs\Characters::SHADOW_TAXONOMY`). Characters own the relationship, and their shadow terms are what get attached to actors and shows, so the same lookup works in both directions. It then calls `FWP()->indexer->index()` for each character.

If FacetWP is not active the task logs to the `facetwp` topic and does nothing. See [scheduling.md](scheduling.md#schedule_task) for the Action Scheduler requirement.

## Full re-index

The Sunday debug cron runs `FWP()->indexer->index()` for the whole site (see [cron-schedule.md](../operations/cron-schedule.md#debug-checks-by-day)), which also repairs anything the incremental paths missed.

## CLI output

The WP-CLI command classes add a `facetwp_is_main_query` filter returning `false` in their constructors (`cli-scheduler.php` and `cli-sweep.php` hook `fwp_is_main_query` instead, which is not a FacetWP filter name), so FacetWP does not inject `<!--fwp-loop-->` into command output.
