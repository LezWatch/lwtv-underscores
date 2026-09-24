# Duplicate detection

How we catch one actor or show entered twice: comparable name keys, the after-the-fact duplicate scan, the unique-IMDb rule on save, and the editor's duplicate warning.

## Overview

| Layer | Where | Blocks? |
|---|---|---|
| Name keys | `plugins/lwtv-plugin/php/_helpers/class-name-key.php` (`LWTV\_Helpers\Name_Key`, pure) | n/a, produces keys only |
| Editor warning | `blocks/src/actor-dupe-check/` + `rest-api/class-actor-name-check.php` | Strict match pauses publishing until acknowledged; loose match only warns |
| Unique IMDb ID | `ACF::validate_unique_imdb()` in `plugins/class-acf.php` | Yes, refuses the save |
| Duplicate scan | `Debugger\Dupes::find_duplicates()`, `Debugger\Collect\Duplicate_Collector`, `Debugger\Build\Duplicate_Rules` (`wp lwtv debug dupes`, `wp lwtv dupes`) | No, reports |

Nothing name-based is ever a verdict. Two different people really do share names. So name matches produce candidates, a human decides, and the decision is stored in `lezactors_dupe_override` ([Acknowledging a pair](#acknowledging-a-pair)). The only hard stop is an IMDb ID collision, because an IMDb ID is an identity claim, not a resemblance.

## Name keys

Editors add actors by typing a name, and the same person turns up spelled more than one way: "Bae Doona" and "Doona Bae", "Moennig, Katherine", "Zoë" and "Zoe", "Doo-na" and "Doona". A check keyed on the title as written catches none of these. That's how one actor ends up in the database twice, under a second slug.

`Name_Key` takes a name and returns comparable keys. It runs no queries and reads no meta.

### Two key families

| Method | Key | Tier | Catches |
|---|---|---|---|
| `Name_Key::variants()` | Every token, sorted. Returns 1–2 keys. | `full`, strict: every token must be on both sides | Surname-first order ("Bae Doona" / "Doona Bae"), comma forms, accents, punctuation |
| `Name_Key::ends()` | First and last token only, sorted, generational suffixes dropped. At most 1 key. | `ends`, loose | A dropped middle name ("Sarah Michelle Gellar" / "Sarah Gellar"), which `variants()` can't catch |

`Name_Key::compare()` is a convenience for one pair: `'full'`, `'ends'` or `''`. Code that compares many actors should key each one once and compare the keys.

A loose match is a weaker signal, so it gets a lower confidence tier and never a hard block.

### Normalisation (both families)

`Name_Key::base()`:

- drops a trailing parenthetical, e.g. "(actress)" or a second spelling added to tell two people apart;
- folds accents;
- removes apostrophes, straight, curly and modifier-letter, so "O'Donnell" is one token. Apostrophes join rather than split, because nobody writes "O Donnell" on purpose.

`tokens()` splits on anything that isn't `\p{L}` or `\p{N}`. So a name in its own script (e.g. 배두나) still produces tokens instead of disappearing. An empty key would collide with every other unkeyable name.

### Hyphens

Hyphens are why `variants()` returns a list. The convention really is ambiguous. A Korean given name joins ("Doo-na" is "Doona"), while a Western double-barrelled name splits ("Mary-Louise" is "Mary Louise"). Guessing one way misses real duplicates the other way, so `variants()` emits both readings (joined across every dash shape, and split) and callers match on any of them.

`ends()` always treats a hyphen as a word boundary, because it wants the outer edges of the name. "Ji-won Kim" gives `ji kim`, not `jiwon kim`.

### Generational suffixes

`Name_Key::SUFFIXES` (`jr`, `sr`, `ii`, `iii`, `iv`) are dropped by `ends()` only, from the end, and never the last remaining token. `variants()` keeps them, so Robert Downey Sr. and Jr. stay distinct at the strict tier. At the loose tier, `ends()` pairs them. That's the cost of catching a genuinely dropped suffix, and it's the kind of known pair an editor acknowledges once.

### Accent folding is injectable

`variants()`, `ends()` and `compare()` take an optional `$fold` callable. It defaults to WordPress's `remove_accents()`. That function branches on `get_locale()` for German and Danish, which puts it outside what `tests/bootstrap.php` will shim. So `tests/unit/Helpers/NameKeyTest.php` injects a small fixed map instead. See [testing.md](../testing.md).

### Known limits (deliberate)

- Romanisation systems aren't reconciled: "Zhang Ziyi" won't match "Chang Tzu-i".
- Native script won't match its romanisation.
- A changed name is a different name.
- Two people who share a name look exactly like a duplicate ("Li Wei" / "Wei Li" is `full`). This is why nothing name-based may hard-block a save.

The tests assert these misses, so nobody later reads them as bugs.

### Storage

`CPTs\Actors::save_name_keys()` writes the keys on save:

- `Actors::NAME_KEY_META` (`lezactors_name_key`): one row per `variants()` reading, read with `$single = false`;
- `Actors::NAME_ENDS_META` (`lezactors_name_key_ends`): a single row.

It reads the raw `post_title`, not `get_the_title()`, because wptexturize would hand back curly apostrophes and en dashes the editor never typed. It writes nothing when the keys haven't changed. `wp lwtv namekeys backfill` (`wp-cli/cli-namekeys.php`) keys actors that existed before the keys did. It is safe to re-run. It covers every status except trash, auto-draft and inherit, private included: `Actors\Privacy` makes a post private on request, and accidentally duplicating a deliberately hidden actor is the worst case.

## Duplicate scan

`Debugger\Dupes::find_duplicates()` merges two candidate sources. `Duplicate_Rules::evaluate()` then judges each pair.

### Slug scan

`Duplicate_Collector::candidate_ids()`: published shows and actors whose slug ends in `-<number>`, each paired with the post at the base slug. This only finds a duplicate whose title was typed identically the second time, because only an identical title collides in `wp_unique_post_slug()` and gets the `-2`. Some titles really are numbers (90210). A pairing that resolves back to the same post is discarded.

### Name-key pairing (actors)

`Duplicate_Collector::name_key_pairs()` groups actors on shared `lezactors_name_key` / `lezactors_name_key_ends` values. This finds one person entered under two different spellings, with two unsuffixed slugs, which the slug scan never sees.

- Grouping happens in PHP, not with an SQL self-join. `postmeta` has no index on `meta_value`, so a self-join across every actor scans the table against itself. Pulling the rows the `meta_key` index already narrows and grouping them is linear.
- Groups are keyed by `meta_key` as well as value, so a strict key never groups with a loose key that happens to read the same.
- Both families count. Loose-tier noise is fine here, because `Duplicate_Rules` still needs a matching IMDb ID.
- Trashed, auto-draft and inherit posts are excluded. Name keys survive trashing.

A pair found by both sources (a suffixed slug whose names also key alike) is evaluated once. `find_duplicates()` de-duplicates on `post_id:original_id`. On a recheck, both sources are narrowed to the flagged IDs. A name-key finding has no suffix to rediscover, so collecting it by ID alone would find no original and wrongly clear it as fixed.

### Lowest ID is the original

In a group, the lowest post ID is the original and each newer post is flagged against it. That matches the slug scan, where the `-2` copy came second. `Duplicate_Collector::name_key_pairs_for()` answers the same question for a single actor (used by `Dupes::compare_duplicates()`). It has to use the group's minimum ID, not the lower of each pair. For a key shared by 5, 9 and 12, the full scan yields 9→5 and 12→5, never 12→9. It returns nothing when the post is the group's original. The same convention decides which side of an IMDb collision may still save ([Unique IMDb IDs](#unique-imdb-ids)).

### What counts as a duplicate

`Duplicate_Rules::evaluate()` reports a pair only when both posts carry the same, non-empty IMDb ID and the pair hasn't been acknowledged. Two posts both missing an IMDb ID is not evidence of anything.

## Acknowledging a pair

`Duplicate_Rules::is_acknowledged()` reads two shapes:

- **Actors:** `lezactors_dupe_override` is an ACF relationship ("Not a duplicate of"), stored as an array of actor IDs. It is set as bidirectional in the ACF JSON, so listing a pair on one actor records it on both. It has to be per pair. Saying this Sarah Jones isn't that Sarah Jones must not also silence a third Sarah Jones added next year. Names collide far more often than slugs, so a blanket exemption on a common name would hide real duplicates for good. `Duplicate_Collector::collect_one()` deliberately doesn't cast it to a string.
- **Shows:** `lezshows_dupe_override` is a post-wide flag meaning "not a duplicate of anything".

## Unique IMDb IDs

`ACF::validate_unique_imdb()` runs on `acf/validate_value` for the fields in `ACF::UNIQUE_IMDB_FIELDS` (`lezactors_imdb`, `lezshows_imdb`). It refuses an ID that another post of the same type already holds. Values are compared after `Imdb_Canonical::normalise()`, so a pasted URL and a bare ID compare equal. Malformed values are left to the IMDb debugger check. `Queeries\Get_Post_By_Imdb::make()` returns the lowest-numbered other holder (`ORDER BY p.ID ASC`).

Rules:

- An emptied field always passes. There is always a way out.
- No other holder, or the only holder is this post: pass.
- Another post holds it: refuse, unless this post is the **older** of the two and its stored value is already this ID. That lets someone editing the original of a legacy pair still save their work, and asks the newer post to fix or clear its ID, which is the right fix anyway. `wp lwtv dupes` reports existing pairs.

### Block-editor ordering

"Unchanged" can't mean "the value is already in the database". When you publish in the block editor, ACF meta is written before ACF validation runs. A brand-new post's colliding ID is therefore already stored when the validator sees it. An "already stored, so allow" rule would pass exactly the case this check exists to refuse, on that save and every save after. So the collision lookup runs first, and an unchanged value survives only for the lower post ID.

`ACF::editing_post_id()` finds the post being edited. In the block editor, validation runs in ACF's own AJAX request, where `get_the_ID()` returns nothing. So it tries `acf_get_form_data( 'post_id' )`, then `$_POST['_acf_post_id']`, `post_id` and `post_ID`, then `get_the_ID()`. Non-numeric answers like `options` or `term_12` are skipped. A revision or autosave resolves to its parent. If nothing resolves, the check still runs. Refusing a new actor that pastes a published actor's ID is the whole point, and a possible false collision against itself on an unresolved existing post is the smaller risk.

## Editor warning

### REST route

`GET /lwtv/v1/actors/name-check?name=<name>&exclude=<post id>` (`Rest_API\Actor_Name_Check`). It calls `Queeries\Get_Actors_By_Name::make()`, which matches the typed name's `variants()` / `ends()` keys against stored keys and returns candidates with `id`, `title`, `slug`, `status` and `tier`, strict first, capped at `MAX_RESULTS`. That lookup is deliberately uncached. The usual case is an actor added minutes ago, and a transient would answer with a snapshot from before they existed. The route removes candidates already listed in the edited actor's `lezactors_dupe_override` and adds `edit_url`.

**The route is private**, unlike the other routes in `rest-api/`. They serve data the site already publishes. This one returns draft and private actor names, and `Actors\Privacy` makes a post private precisely because the person asked not to be listed. `can_edit_actors()` reads the capability from the post type object. The actors CPT registers `capability_type => array( 'actor', 'actors' )` with `map_meta_cap`, so the capability is `edit_actors`, not `edit_posts`.

### Why the title field

The editorial order is show, then actors, then characters. So an actor is almost always created from a blank Add Actor screen, not found by search. The title field is the only place to catch a duplicate before a second post exists.

### Block behaviour

`plugins/lwtv-plugin/php/blocks/src/actor-dupe-check/` registers a plugin (`registerPlugin`) through `block.json`'s `editorScript`. That key is load-bearing: without it, the script is never built or enqueued. `js/render.js`:

- checks only a name the editor has changed from the saved title, at least `MIN_LENGTH` (3) characters, debounced `DEBOUNCE_MS` (500 ms), aborting the previous request. Reopening an actor and changing nothing says nothing;
- **strict (`full`) match:** `lockPostSaving( 'lwtv-actor-dupe-check' )` pauses publishing until the editor clicks "These are different people". **Loose (`ends`) match:** a warning only. Never the other way round. When this was sized, loose matches were mostly different people, and strict ones were rare;
- a new set of matches resets the acknowledgement;
- the unlock lives in a `core/notices` warning as well as the pre-publish panel. The panel only renders when WordPress's pre-publish checks are on, and a locked Publish button with no visible way to clear it would look like a broken editor;
- fails open: an error clears the matches. A check we couldn't run must never be the reason someone can't publish. The lock is released in effect cleanup, so no code path can leave Publish dead;
- the in-editor acknowledgement lasts for the session only. The hint points editors to "Not a duplicate of" (under Administrative) to record it permanently.

### apiFetch and the REST nonce

Both `actor-dupe-check/js/render.js` and `wikidata-actor/js/render.js` import `@wordpress/api-fetch`. The build externalises that import to `wp.apiFetch`. So it is the editor's own configured instance and sends the REST nonce, and `wp-api-fetch` shows up in the generated asset file. Without the nonce, WordPress treats the request as logged out. The name check returns 401. The WikiData panel falls back to the public view: stored comparison only, published non-private actors only.

## Related

- [imdb.md](../integrations/imdb.md): stale IMDb IDs, which make the same show look like two different IDs.
- [actor-identity.md](actor-identity.md): the other use of IMDb IDs as identity.
