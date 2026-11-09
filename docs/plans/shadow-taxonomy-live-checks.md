# Shadow taxonomy removal: live checks

These checks need a running site. Run them on lwtv.local once its theme symlink points at this worktree, or once the branch is merged. Each task adds its own section.

## Task 2: Store

```bash
wp eval 'LWTV\Relationships\Store::maybe_install();'
wp db query "SHOW CREATE TABLE wp_lwtv_char_links"
wp option get lwtv_char_links_db_version
```

Expected:

- `SHOW CREATE TABLE` lists `character_id`, `object_id` (both `bigint unsigned NOT NULL`) and `object_type varchar(20) NOT NULL`, with `PRIMARY KEY (character_id, object_type, object_id)` and `KEY reverse (object_id, object_type)`.
- `wp option get lwtv_char_links_db_version` prints `1`.

## Task 3: repository seam

With `lwtv_char_links_source` set to `shadow` or unset (both mean the source is `shadow`), this task must not change any show's character list, any count or any score. `wp` below means the socket-override invocation from the local wp-cli notes:

```bash
wp() { php -d error_reporting=0 -d mysqli.default_socket="/Users/ipstenu/Library/Application Support/Local/run/aCt09KKZS/mysql/mysqld.sock" "$(which wp)" --path="/Users/ipstenu/Websites/Local/lwtv-new/app/public" "$@"; }
SCRATCH=/tmp/lwtv-seam   # or any scratch folder
mkdir -p "$SCRATCH"
PFX=$(wp db prefix)   # the snapshot queries use it; never hardcode wp_
wp option get lwtv_char_links_source   # expected: "shadow", or "Could not get 'lwtv_char_links_source' option" if the table hasn't been installed yet (unset also means shadow)
```

### Step 0: baseline (main checkout, before re-pointing the symlink)

The old code writes term relationships while it reads (`clean_character_array()` and `build_character_info()`). The first run can therefore change the terms that the next run reads. **Run the baseline twice and keep the second run** as `seam-before.txt`. The two runs should match. If they don't, the first run repaired drift, and the second run is the stable state to compare against.

The script picks three shows (The L Word, the published show with the fewest characters, and the one with the most dead characters) and three actors (the most characters, exactly one character, and the most dead characters). It prints the IDs it chose. Pin them with `SHOWS`/`ACTORS` so the after-run looks at the same posts.

```bash
cat > "$SCRATCH/seam.php" <<'PHP'
<?php
$pick = function ( $type, $key, $order, $value = null ) {
	$args = array(
		'post_type'      => $type,
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => $key,
		'orderby'        => array( 'meta_value_num' => $order, 'ID' => 'ASC' ),
		'no_found_rows'  => true,
	);
	if ( null !== $value ) {
		$args['meta_value'] = $value;
	}
	$ids = get_posts( $args );
	return (int) ( $ids[0] ?? 0 );
};

$env_shows  = getenv( 'SHOWS' );
$env_actors = getenv( 'ACTORS' );

if ( $env_shows ) {
	$shows = array_map( 'intval', explode( ',', $env_shows ) );
} else {
	$lword = get_page_by_path( 'the-l-word', OBJECT, 'post_type_shows' );
	$shows = array(
		$lword ? (int) $lword->ID : 0,
		$pick( 'post_type_shows', 'lezshows_char_count', 'ASC', 1 ),
		$pick( 'post_type_shows', 'lezshows_dead_count', 'DESC' ),
	);
}

if ( $env_actors ) {
	$actors = array_map( 'intval', explode( ',', $env_actors ) );
} else {
	$actors = array(
		$pick( 'post_type_actors', 'lezactors_char_count', 'DESC' ),
		$pick( 'post_type_actors', 'lezactors_char_count', 'ASC', 1 ),
		$pick( 'post_type_actors', 'lezactors_dead_count', 'DESC' ),
	);
}

echo 'SHOWS=' . implode( ',', $shows ) . ' ACTORS=' . implode( ',', $actors ) . "\n";

foreach ( $shows as $id ) {
	$list = lwtv_plugin()->get_characters_list( $id, 'query' );
	echo "show {$id} " . get_the_title( $id ) . "\n";
	echo '  query: ' . implode( ',', (array) $list ) . "\n";
	echo '  count: ' . lwtv_plugin()->get_characters_list( $id, 'count' ) . "\n";
	echo '  dead:  ' . lwtv_plugin()->get_characters_list( $id, 'dead' ) . "\n";
	echo '  trans: ' . lwtv_plugin()->get_characters_list( $id, 'trans' ) . "\n";
	echo '  score: ' . get_post_meta( $id, 'lezshows_the_score', true ) . "\n";
}

foreach ( $actors as $id ) {
	echo "actor {$id} " . get_the_title( $id ) . ': ' . count( lwtv_plugin()->get_actor_characters( $id ) ) . "\n";
}
PHP

wp eval-file "$SCRATCH/seam.php" > "$SCRATCH/seam-before-run1.txt"
wp eval-file "$SCRATCH/seam.php" > "$SCRATCH/seam-before.txt"
diff "$SCRATCH/seam-before-run1.txt" "$SCRATCH/seam-before.txt" && echo "baseline stable"
head -1 "$SCRATCH/seam-before.txt"   # note the SHOWS=/ACTORS= line
```

(`wp eval-file` is `wp eval` with the code in a file, so the quoting survives.)

### Step 5: after (symlink pointing at this worktree)

```bash
# Use the IDs from the first line of seam-before.txt.
export SHOWS=... ACTORS=...
wp eval-file "$SCRATCH/seam.php" > "$SCRATCH/seam-after.txt"
diff "$SCRATCH/seam-before.txt" "$SCRATCH/seam-after.txt" && echo "seam: no change"
```

Expected: no diff. The show `query` lists must match in order as well as content. Both versions read the show's shadow terms with `wp_get_object_terms()` and its default `orderby => name`.

### Page loads write nothing

```bash
wp db query "SELECT COUNT(*) FROM wp_term_relationships" --skip-column-names > "$SCRATCH/tr-before.txt"

for id in ${SHOWS//,/ } ${ACTORS//,/ }; do
	url=$(wp eval "echo get_permalink( $id );")
	printf '%s %s\n' "$(curl -sk -o /dev/null -w '%{http_code}' "$url?nocache=$(date +%s)")" "$url"
done

wp db query "SELECT COUNT(*) FROM wp_term_relationships" --skip-column-names > "$SCRATCH/tr-after.txt"
diff "$SCRATCH/tr-before.txt" "$SCRATCH/tr-after.txt" && echo "reads wrote nothing"
tail -50 /Users/ipstenu/Websites/Local/lwtv-new/app/public/wp-content/debug.log
```

Expected: every URL returns `200`, the two counts are equal, and `debug.log` has no new PHP warnings or notices from `relationships/`, `theme/class-show-characters.php` or `theme/class-actor-characters.php`. On the old code, a count change here would have come from `clean_character_array()`. On this branch it must not move.

### Smoke checks for the other routed readers

```bash
# Legacy character links. The old make() called an undefined method, so there is no baseline: it only has to return an array.
wp eval '$ids = get_posts( array( "post_type" => "post_type_characters", "posts_per_page" => 1, "fields" => "ids", "meta_key" => "lezchars_relationship_chart" ) ); var_dump( lwtv_plugin()->get_chars_relationships( (int) ( $ids[0] ?? 0 ) ) );'

# Character save path: sync still attaches terms and recalculates.
CHAR=$(wp eval 'echo (int) get_posts( array( "post_type" => "post_type_characters", "posts_per_page" => 1, "fields" => "ids" ) )[0];')
wp eval "( new LWTV\CPTs\Characters\Calculations() )->do_the_math( $CHAR );"
wp eval "var_dump( ( new LWTV\Relationships\Character_Links() )->shows_for_character( $CHAR ), ( new LWTV\Relationships\Character_Links() )->actors_for_character( $CHAR ) );"
```

Expected: the show and actor IDs match the character's `lezchars_show_group` and `lezchars_actor`. `sync_shows()` now really removes stale show terms: the old loop tested `is_object()` on the array that `get_shows_for_character()` returned, so it never ran. A drop in `term_relationships` after a **character save** is therefore expected. A drop after a **page load** is not.

## Task 4: Sync

Pick a character with two shows and one actor (`CHAR`). Pick any other post ID as `OTHER`. `wp` is the socket-override invocation.

```bash
CHAR=<id>; OTHER=<id>
wp eval "LWTV\Relationships\Sync::character( $CHAR );"
wp db query "SELECT * FROM wp_lwtv_char_links WHERE character_id=$CHAR"
# Run it again: still 3 rows, no duplicates.
wp eval "LWTV\Relationships\Sync::character( $CHAR );"
wp db query "SELECT COUNT(*) FROM wp_lwtv_char_links WHERE character_id=$CHAR" --skip-column-names
```

Expected: first query lists 3 rows (two `show`, one `actor`); the count after the second run is `3`.

Legacy rows survive an empty save:

```bash
wp db query "INSERT INTO wp_lwtv_char_links (character_id, object_id, object_type) VALUES ($CHAR, $OTHER, 'character')"
# Note the character's show group and actors first, then clear both in wp-admin and click Update.
wp db query "SELECT * FROM wp_lwtv_char_links WHERE character_id=$CHAR"
```

Expected: exactly one row, `($CHAR, $OTHER, 'character')`. Then restore the character's shows and actors in wp-admin, save, and delete the fake row:

```bash
wp db query "DELETE FROM wp_lwtv_char_links WHERE character_id=$CHAR AND object_type='character'"
wp db query "SELECT COUNT(*) FROM wp_lwtv_char_links WHERE character_id=$CHAR" --skip-column-names
```

Expected: `3` again. Deleting a character, show or actor (`wp post delete <id> --force`) must also remove its rows on either side.

## Task 5: wp lwtv links

Deferred: needs the running site. `wp` is the socket-override invocation.

```bash
wp lwtv links rebuild
wp lwtv links rebuild --legacy-characters
wp db query "SELECT object_type, COUNT(*) FROM wp_lwtv_char_links GROUP BY object_type"
# Run both rebuilds again, then the count query again.
wp lwtv links rebuild
wp lwtv links rebuild --legacy-characters
wp db query "SELECT object_type, COUNT(*) FROM wp_lwtv_char_links GROUP BY object_type"
wp lwtv links diff --type=show
wp lwtv links diff --type=actor
wp lwtv links diff --type=character
wp lwtv links source          # prints: shadow
```

Idempotent means the counts from the second GROUP BY are identical to the first, and the
`Table rows: N before, N after` line of the second rebuild reports the same N both times.

Save the three diff reports (counts and a sample of mismatches) as
`docs/plans/shadow-taxonomy-diff-local.md`. That is the Phase 0 baseline.

Watch memory during `wp lwtv links rebuild`: it flushes the runtime cache between batches of 200, so usage should stay flat. A steady climb means a leak.

## Task 6: flip to the table

Deferred: needs the running site. `wp` is the socket-override invocation from Task 3.

**Order matters.** Re-point the lwtv.local theme symlink at this worktree (or merge the branch), then run Tasks 2 to 6 in that order. Task 3's baseline has to be taken **before** the symlink change, because it must come from the old code. Task 5's `diff` reports have to exist before you start here: Step 2 checks against them.

Pick a scratch folder and keep it for the whole task:

```bash
SCRATCH=/tmp/lwtv-flip
mkdir -p "$SCRATCH"
PFX=$(wp db prefix)    # the snapshot queries below use it
wp lwtv links source   # expected: shadow
```

### Step 1: snapshot with source = shadow

```bash
wp db query "SELECT m.post_id, m.meta_key, m.meta_value FROM ${PFX}postmeta m JOIN ${PFX}posts p ON p.ID = m.post_id AND p.post_type IN ('post_type_shows','post_type_actors') WHERE m.meta_key IN ('lezshows_the_score','lezshows_char_count','lezshows_dead_count','lezactors_char_count','lezactors_dead_count') ORDER BY m.post_id, m.meta_key" > "$SCRATCH/before.tsv"
wc -l "$SCRATCH/before.tsv"
```

### Step 2: flip, recalculate, compare

```bash
wp lwtv links source table     # prints: table (refuses if the table is empty; run links rebuild first)
wp lwtv calc --all
wp db query "SELECT m.post_id, m.meta_key, m.meta_value FROM ${PFX}postmeta m JOIN ${PFX}posts p ON p.ID = m.post_id AND p.post_type IN ('post_type_shows','post_type_actors') WHERE m.meta_key IN ('lezshows_the_score','lezshows_char_count','lezshows_dead_count','lezactors_char_count','lezactors_dead_count') ORDER BY m.post_id, m.meta_key" > "$SCRATCH/after.tsv"
diff "$SCRATCH/before.tsv" "$SCRATCH/after.tsv"
```

The rule: every post ID that changed must appear in the Task 5 diff report (`docs/plans/shadow-taxonomy-diff-local.md`) with `acf=yes` (the `acf_agrees` column of `links diff`, one `<character id>:acf=yes` entry per differing character; the `character` report shows `n/a`, so it only tells you which posts to expect). That means the table fixed a shadow drift and did not introduce one. A changed post ID that is not in the report, or is there with `acf=no`, is a bug. Roll back and investigate:

```bash
wp lwtv links source shadow
wp lwtv calc --all
```

Shadow terms are still being written, so the rollback loses nothing. No diff at all is a valid result if there was no drift.

### Step 3: site checks with source = table

- **Three shows and three actors from the Task 3 baseline** (use the IDs on the first line of `seam-before.txt`): load each page. The character lists and counts should match the baseline, except for posts that appeared in the Step 2 diff. Re-running the Task 3 script with `SHOWS=... ACTORS=...` and diffing against `seam-before.txt` is the quickest way.
- **A character page with legacy relationships** (pick an ID from the `character` diff, or one that has `lezchars_relationship_chart` meta). The relationship block lists the same characters as before and the page loads without errors.
- **Actor save:** open an actor in wp-admin and click Update. Then:

  ```bash
  wp lwtv debug-log tail --topic=facetwp --lines=20
  ```

  Expect a line like `Re-indexed N character(s) for ID: <actor id>` with N matching the actor's character count.
- **Character order:** on the same three shows, the show page still lists characters A-Z within each role, matching the Task 3 baseline (the table read sorts by character title, like the shadow read did). The actor page's character list is A-Z too.
- **Trash a character:** trash one that is on a show page. Reload the show page: the character is gone, and no write happened (the `wp_lwtv_char_links` row count is unchanged, because reads filter on published status). Untrash it: it comes back on the show page without any resave.

Leave the local site on `table` when you are done.

