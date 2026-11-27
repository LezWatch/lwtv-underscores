# Meilisearch Shows Index Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `shows` Meilisearch index next to `characters`, generalize the module to several indexes, and let Search Compare compare shows against SearchWP.

**Architecture:** Approach A from the spec: an abstract `Index` (WP glue) with `Characters_Index` and `Shows_Index`, an `Indexes` registry, and pure decisions in `build/` (title split, years, show document, sync routes, index state, CLI type option). `Sync` routes each WordPress event to per-index queued tasks; each index answers "which of my documents does this post change affect?"

**Tech Stack:** PHP 8.1+, WordPress, ACF, Action Scheduler (via `lwtv_plugin()->schedule_task()`), Meilisearch v1.57 HTTP API through `LWTV\Meilisearch\Client`, PHPUnit (pure tests, no WP bootstrap), plain JS for Search Compare.

**Spec:** `docs/superpowers/specs/2026-11-25-meilisearch-shows-design.md` (and, for everything it inherits, `docs/superpowers/specs/2026-11-24-meilisearch-characters-design.md`).

## Global Constraints

- PHP 8.1+ syntax; WordPress-Extra phpcs (`composer lint`) and PHPStan (`TMPDIR=<scratchpad>/phpstan-tmp composer analyse`) clean.
- No new Composer runtime dependencies. HTTP only through `Client`.
- Never put a key in code, logs, error messages or commits. Errors show HTTP status + Meilisearch `code`/`message` only.
- A failed or slow search call must never break or slow a save: hooks only queue; tasks catch `\Throwable` and log to the `meilisearch` debug channel.
- Index uid always from `Config::index( $type )` (`{PREFIX}_{type}`), never a literal.
- Pure logic lives in `plugins/lwtv-plugin/php/meilisearch/build/`, test first; each new build class is `require_once`d in `tests/bootstrap.php` after the existing Meilisearch lines.
- Request shapes checked against the Meilisearch v1.57 docs before use.
- SearchWP, its engines and FacetWP's `search_shows` / `search_characters` / `search_actors` facets are untouched.
- User-facing strings use text domain `'lwtv-underscores'`.
- Sync never creates an index; only `wp lwtv meili settings` / `reindex --fresh` do.
- First write to `local_shows` needs Mika's go-ahead in chat. Nothing beyond lwtv.local this round.
- **No commits.** Mika commits the whole diff herself at the end. Where a task would commit, stop at "tests, lint and analyse pass".

## Review Focus

- **A title whose brackets aren't a trailing qualifier** (`Foo (Bar) Baz`, `Foo (Bar (Baz))`, `(Untitled)`) keeps the whole title as `name` and an empty `qualifier`: pinned in Task 1.
- **A show with nothing attached** (no characters, no airdates, no worth-it, no score, no terms) still builds a valid document with empty values, never a PHP warning: pinned in Task 2.
- **The search server down vs. an index missing**: only `index_not_found` means "missing"; any other error is "unknown", isn't cached, and the task writes nothing: pinned in Task 3.
- **A queued task for a post that has since been deleted** (post type now `false`) deletes that ID's document from the index instead of erroring: pinned in Task 4 (`Index::run()`), verified live in Task 9.
- **A typo in `--type`** (`--type=show`) stops with an error naming the valid types, never falls back to characters: pinned in Task 7.

---

### Task 1: Show title split and years

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/build/class-show-title.php`
- Test: `tests/unit/Meilisearch/ShowTitleTest.php`
- Modify: `tests/bootstrap.php` (require the new class)

**Interfaces:**
- Produces: `LWTV\Meilisearch\Build\Show_Title::split( string $title ): array{name:string, qualifier:string}` and `Show_Title::years( string $start, string $finish ): string`.

- [ ] **Step 1: Write the failing tests** (`ShowTitleTest`, data providers welcome)

```php
// split() decodes entities itself; callers pass the raw post title.
$this->assertSame( array( 'name' => 'Charmed', 'qualifier' => '2018' ), Show_Title::split( 'Charmed (2018)' ) );
$this->assertSame( array( 'name' => 'Skins', 'qualifier' => 'US' ), Show_Title::split( 'Skins (US)' ) );
$this->assertSame( array( 'name' => 'Skins', 'qualifier' => '' ), Show_Title::split( 'Skins' ) );
$this->assertSame( array( 'name' => 'Foo (Bar) Baz', 'qualifier' => '' ), Show_Title::split( 'Foo (Bar) Baz' ) );
$this->assertSame( array( 'name' => 'Foo (Bar (Baz))', 'qualifier' => '' ), Show_Title::split( 'Foo (Bar (Baz))' ) );
$this->assertSame( array( 'name' => '(Untitled)', 'qualifier' => '' ), Show_Title::split( '(Untitled)' ) );
$this->assertSame( array( 'name' => 'Foo', 'qualifier' => '' ), Show_Title::split( 'Foo ()' ) );
$this->assertSame( array( 'name' => 'Mapp & Lucia', 'qualifier' => '2014' ), Show_Title::split( 'Mapp &amp; Lucia (2014)' ) );
$this->assertSame( array( 'name' => 'Terra e Paixão', 'qualifier' => '' ), Show_Title::split( '  Terra e Paixão ' ) );

// years()
$this->assertSame( '2018 2022', Show_Title::years( '2018', '2022' ) );
$this->assertSame( '2024', Show_Title::years( '2024', 'current' ) );
$this->assertSame( '2024', Show_Title::years( '2024', '' ) );
$this->assertSame( '2016', Show_Title::years( '2016', '2016' ) );
$this->assertSame( '', Show_Title::years( '', '2020' ) );
$this->assertSame( '1998 2006', Show_Title::years( '1998-09-01', '2006' ) ); // first 4-digit year wins
```

- [ ] **Step 2: Run** `vendor/bin/phpunit --filter ShowTitle` — expect FAIL (class not found).
- [ ] **Step 3: Implement `Show_Title`.** `split()`: decode with `html_entity_decode( …, ENT_QUOTES | ENT_HTML5, 'UTF-8' )`, trim, then match one trailing group with `/^(.*\S)\s*\(([^()]*)\)$/u`; split only when the prefix itself has balanced brackets (so `Foo (Bar (Baz))` doesn't split); trim the qualifier. `years()`: first `\d{4}` from each value; `''` without a start year; finish dropped when it has no year (covers `current`) or equals start.
- [ ] **Step 4: Run** `vendor/bin/phpunit --filter ShowTitle` — expect PASS.

### Task 2: Show document builder

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/build/class-show-document.php`
- Test: `tests/unit/Meilisearch/ShowDocumentTest.php`
- Modify: `tests/bootstrap.php`

**Interfaces:**
- Consumes: `Show_Title::split()`, `Show_Title::years()` (Task 1).
- Produces:
  - `Show_Document::WORTH_IT_RANKS = array( 'Yes' => 3, 'Meh' => 2, 'TBD' => 1, 'No' => 0 )`
  - `Show_Document::alt_names( mixed $rows ): string[]` (rows from the `lezshows_show_names` repeater; sub-field `lezshows_alt_show_name`; accepts raw-mode rows keyed by field key too — reuse the key-or-name normalizing approach `Show_Group::normalize()` uses, keys `field_lwtv_lezshows_alt_show_name` / `lezshows_alt_show_name`)
  - `Show_Document::build( int $id, string $title, mixed $alt_rows, array $airdates, mixed $worth_it, mixed $score, string[] $characters, array $actors, string[] $stations, string[] $nations, string $url, string $thumb ): array` where `$airdates` is `array{start:string, finish:string}` (from `Shows\Airdates::get()`) and `$actors` is `array<int, array{title:string, visible:bool}>` (the existing lookup shape). Returns keys in this order: `id, name, qualifier, alt_names, characters, actors, years, worth_it, worth_it_rank, score, stations, nations, url, thumb`.

- [ ] **Step 1: Write the failing tests**

```php
$doc = Show_Document::build( 32399, 'Charmed (2018)', array( array( 'lezshows_alt_show_name' => 'Charmed Reboot', 'type' => 'en' ) ),
	array( 'start' => '2018', 'finish' => '2022' ), 'Yes', '64.45', array( 'Mel Vera', 'Maggie Vera', 'Mel Vera' ),
	array( 7 => array( 'title' => 'Melonie Diaz', 'visible' => true ), 8 => array( 'title' => 'Private Person', 'visible' => false ) ),
	array( 'The CW' ), array( 'USA' ), '/show/charmed-2018/', '' );
$this->assertSame( 'Charmed', $doc['name'] );
$this->assertSame( '2018', $doc['qualifier'] );
$this->assertSame( array( 'Charmed Reboot' ), $doc['alt_names'] );
$this->assertSame( array( 'Mel Vera', 'Maggie Vera' ), $doc['characters'] );   // de-duplicated, order kept
$this->assertSame( array( 'Melonie Diaz' ), $doc['actors'] );                   // hidden actor left out
$this->assertSame( '2018 2022', $doc['years'] );
$this->assertSame( 3, $doc['worth_it_rank'] );
$this->assertSame( 64.45, $doc['score'] );
$this->assertSame( array( 'id', 'name', 'qualifier', 'alt_names', 'characters', 'actors', 'years', 'worth_it', 'worth_it_rank', 'score', 'stations', 'nations', 'url', 'thumb' ), array_keys( $doc ) );

// Worth It ranks: 'Meh' => 2, 'TBD' => 1, 'No' => 0, null => 0 with worth_it '', 'Bogus' => 0 with worth_it ''.
// Empty show: build( 5, 'Foo', false, array( 'start' => '', 'finish' => '' ), null, null, array(), array(), array(), array(), '/foo/', '' )
//   → alt_names [], characters [], actors [], years '', worth_it '', worth_it_rank 0, score 0.0, stations [], nations [].
// Score clamps: '120' → 100.0, '-5' → 0.0, 'abc' → 0.0.
// alt_names(): false/null/'' → []; rows with empty or whitespace names dropped; '&amp;' decoded; duplicates dropped;
//   a row keyed by field key ( 'field_lwtv_lezshows_alt_show_name' => 'X' ) is read too.
// Character and actor titles entity-decoded ('Jo &amp; Co' → 'Jo & Co').
```

- [ ] **Step 2: Run** `vendor/bin/phpunit --filter ShowDocument` — expect FAIL.
- [ ] **Step 3: Implement `Show_Document`.** Pure; decoding matches `Character_Document::decode()` (extract a shared private helper only if it stays pure and tested). Score is cast to float and clamped 0–100 (the stored meta is already clamped; clamping again is cheap insurance for the ranking rule). Unknown worth-it values store `''` and rank 0.
- [ ] **Step 4: Run** `vendor/bin/phpunit --filter ShowDocument` — expect PASS.

### Task 3: Index state and sync routes (pure decisions)

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/build/class-index-state.php`
- Create: `plugins/lwtv-plugin/php/meilisearch/build/class-sync-routes.php`
- Test: `tests/unit/Meilisearch/IndexStateTest.php`, `tests/unit/Meilisearch/SyncRoutesTest.php`
- Modify: `tests/bootstrap.php`

**Interfaces:**
- Produces:
  - `Index_State::EXISTS = 'exists'`, `MISSING = 'missing'`, `UNKNOWN = 'unknown'`; `Index_State::EXISTS_TTL = 3600`, `MISSING_TTL = 300`.
  - `Index_State::classify( ?string $error_message ): string` — `null` → exists; message containing `index_not_found` → missing; anything else → unknown.
  - `Index_State::ttl( string $state ): int` — exists 3600, missing 300, unknown 0 (0 = don't cache).
  - `Sync_Routes::EVENTS = array( 'acf_save', 'changed', 'delete', 'score' )` (`changed` = rename while published or crossing publish).
  - `Sync_Routes::for_event( string $event, string $post_type ): string[]` — index types to queue, in registry order.

- [ ] **Step 1: Write the failing tests**

```php
// IndexStateTest
$this->assertSame( Index_State::EXISTS, Index_State::classify( null ) );
$this->assertSame( Index_State::MISSING, Index_State::classify( 'HTTP 404: index_not_found: Index `local_shows` not found.' ) );
$this->assertSame( Index_State::UNKNOWN, Index_State::classify( 'cURL error 7: Failed to connect' ) );
$this->assertSame( Index_State::UNKNOWN, Index_State::classify( 'HTTP 403: invalid_api_key' ) );
$this->assertSame( 0, Index_State::ttl( Index_State::UNKNOWN ) );

// SyncRoutesTest (post types as their slugs)
$c = 'post_type_characters'; $s = 'post_type_shows'; $a = 'post_type_actors';
$this->assertSame( array( 'characters', 'shows' ), Sync_Routes::for_event( 'acf_save', $c ) );
$this->assertSame( array( 'shows' ), Sync_Routes::for_event( 'acf_save', $s ) );          // routine show save: no character fan-out
$this->assertSame( array( 'characters', 'shows' ), Sync_Routes::for_event( 'acf_save', $a ) );
foreach ( array( $c, $s, $a ) as $type ) {
	$this->assertSame( array( 'characters', 'shows' ), Sync_Routes::for_event( 'changed', $type ) );
}
$this->assertSame( array( 'characters' ), Sync_Routes::for_event( 'delete', $c ) );      // its shows are queued separately, before links go
$this->assertSame( array( 'shows' ), Sync_Routes::for_event( 'delete', $s ) );
$this->assertSame( array(), Sync_Routes::for_event( 'delete', $a ) );                    // known gap, as today
$this->assertSame( array( 'shows' ), Sync_Routes::for_event( 'score', $s ) );
$this->assertSame( array(), Sync_Routes::for_event( 'score', $c ) );
$this->assertSame( array(), Sync_Routes::for_event( 'acf_save', 'post' ) );
$this->assertSame( array(), Sync_Routes::for_event( 'bogus', $c ) );
```

- [ ] **Step 2: Run** `vendor/bin/phpunit --filter 'IndexState|SyncRoutes'` — expect FAIL.
- [ ] **Step 3: Implement both classes.** `Sync_Routes` holds the matrix as a constant array keyed by event then post-type slug, using string literals (the build layer doesn't load `LWTV\CPTs\*`; the test pins the literals against `Characters::SLUG` etc. by value).
- [ ] **Step 4: Run** the same filter — expect PASS.

### Task 4: Generalize the indexer (no behaviour change for characters)

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/abstract-index.php` (`abstract class Index`)
- Create: `plugins/lwtv-plugin/php/meilisearch/class-characters-index.php`
- Create: `plugins/lwtv-plugin/php/meilisearch/class-indexes.php`
- Delete: `plugins/lwtv-plugin/php/meilisearch/class-indexer.php` (logic moves; update every `Indexer` reference: `class-meilisearch.php`, `class-sync.php`, `class-compare-page.php`, `cli-meili.php`)
- Modify: `plugins/lwtv-plugin/php/meilisearch/build/class-index-settings.php` only if `path()`'s default argument needs to go (keep the default; callers pass the type).

**Interfaces:**
- Consumes: `Index_State` (Task 3), existing `Client`, `Config`, `Show_Group`, `Character_Document`, `Store`.
- Produces (`LWTV\Meilisearch\Index`):
  - `__construct( Client $client, Config $config )`
  - `abstract public function type(): string;` `abstract public function post_type(): string;`
  - `abstract public function documents( array $ids ): array;` — documents for published posts of its type only.
  - `abstract public function affected_by( string $post_type, int $post_id ): array;` — IDs of this index's documents a change to that post affects; `$post_id` itself when `$post_type === $this->post_type()`.
  - `public function uid(): string` (`Config::index( type() )`), `settings_path(): string` (`Index_Settings::path( type() )`), `label(): string` (translated, e.g. "Characters", "Shows").
  - `public function document( int $id ): ?array`, `sync( int $id ): bool`, `sync_many( array $ids, int $chunk = 100 ): int` — moved from `Indexer`, log lines name `$this->uid()`.
  - `public function state( bool $check = true ): string` — cached `Index_State` in transient `lwtv_meili_state_{uid}`; on a cache miss and `$check`, calls `Client::index_stats()` and caches by `Index_State::ttl()`; `$check = false` returns `UNKNOWN` on a miss without HTTP.
  - `public function forget_state(): void` — deletes the transient (called by CLI after creating/deleting an index).
  - `public function run( int $post_id ): void` — the task body: if `state() !== EXISTS`, log once and return; if `get_post_type( $post_id )` is false, `delete_document( uid, $post_id )`; else `sync_many( affected_by( post type, id ) )`, where an ID equal to `$post_id` of this index's own type reconciles (deleted when not published — `documents()` skips it, so `sync_many` must delete IDs of its own type that produced no document). Catches `\Throwable`, logs.
  - `protected function lookup( array $expected ): array` — moved unchanged from `Indexer` (id => expected post type; `hide_all` check).
- Produces (`Characters_Index`): `TYPE = 'characters'`; `documents()` = today's `Indexer::documents()`; `affected_by()` per the spec's table (character → itself; show → `Store::characters_for_object( id, 'show' )`; actor → `Store::characters_for_object( id, 'actor' )`; other → `array()`).
- Produces (`Indexes`): `__construct( Client $client, Config $config )`; `get( string $type ): ?Index`; `all(): array<string, Index>` keyed by type in order characters, shows; `types(): string[]`. Task 5 adds `Shows_Index` to it.

- [ ] **Step 1: Run the existing suite** `vendor/bin/phpunit` — expect PASS (baseline).
- [ ] **Step 2: Move the code** into `Index` / `Characters_Index` / `Indexes`; `Meilisearch` builds one `Indexes` and passes it to `Sync`, `Compare_Page` and (Task 8) `Admin_Notice`; expose `Meilisearch::indexes(): Indexes` in place of `indexer()`.
- [ ] **Step 3: Update `Sync` to the minimum that compiles** (characters index only, same hooks); full rework is Task 6.
- [ ] **Step 4: Run** `vendor/bin/phpunit`, `composer lint`, `TMPDIR=<scratchpad>/phpstan-tmp composer analyse` — expect all clean. If PHPStan's baseline names `Indexer`, regenerate only those entries.
- [ ] **Step 5: Live check (read-only)** on lwtv.local: `wp lwtv meili reindex --dry-run` prints the same count as before (7,357 on 2026-11-25) and the same sample shape.

### Task 5: Shows index and its settings file

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/class-shows-index.php`
- Create: `plugins/lwtv-plugin/php/meilisearch/config/shows.json`
- Modify: `plugins/lwtv-plugin/php/meilisearch/class-indexes.php` (register shows)
- Test: `tests/unit/Meilisearch/IndexSettingsTest.php` (add a shows case)

**Interfaces:**
- Consumes: `Index` (Task 4), `Show_Document` (Task 2), `Shows\Airdates::get()`, `Store`.
- Produces: `Shows_Index` with `TYPE = 'shows'`; `documents( int[] $ids )` builds `Show_Document`s for published shows, priming post/meta caches for the shows, their published characters (`Store::characters_for_object( id, 'show' )`), and those characters' actors (`get_field( 'lezchars_actor', $char, false )`, visibility via `lookup()`), and term caches for `lez_stations` / `lez_country`; `affected_by()`: show → itself; character → `Store::objects_for_character( id, 'show' )`; actor → union of `objects_for_character( c, 'show' )` over `characters_for_object( id, 'actor' )`; other → `array()`. Alt-name rows: `get_field( 'lezshows_show_names', $id, false )`. Worth It: `get_post_meta( $id, 'lezshows_worthit_rating', true )`. Score: `get_post_meta( $id, 'lezshows_the_score', true )`. Stations/nations: `wp_get_post_terms( $id, 'lez_stations'|'lez_country', array( 'fields' => 'names' ) )`, `array()` on `WP_Error`.

- [ ] **Step 1: Write the failing test** in `IndexSettingsTest`:

```php
$settings = Index_Settings::load( (string) file_get_contents( Index_Settings::path( 'shows' ) ) );
$this->assertSame( array( 'name', 'qualifier', 'alt_names', 'characters', 'actors', 'years' ), $settings['searchableAttributes'] );
$this->assertSame( array( 'words', 'typo', 'proximity', 'attributeRank', 'sort', 'wordPosition', 'exactness', 'worth_it_rank:desc', 'score:desc' ), $settings['rankingRules'] );
$this->assertContains( 'id', $settings['filterableAttributes'] );
$this->assertSame( array( 'from', 'on', 'with' ), $settings['stopWords'] );
```

- [ ] **Step 2: Run** `vendor/bin/phpunit --filter IndexSettings` — expect FAIL (file missing).
- [ ] **Step 3: Write `config/shows.json`** with exactly those values and `filterableAttributes: [ "id", "worth_it", "stations", "nations" ]`. Confirm against the v1.57 settings and ranking-rules docs that custom `field:desc` rules need no `sortableAttributes` entry (as for `role_rank`).
- [ ] **Step 4: Implement `Shows_Index`** and register it in `Indexes`.
- [ ] **Step 5: Run** `vendor/bin/phpunit`, `composer lint`, analyse — clean.
- [ ] **Step 6: Live check (read-only):** `wp lwtv meili reindex --type=shows --dry-run` (after Task 7; if doing Task 5 first, `wp eval` `Shows_Index::documents( [32399] )`) shows Charmed (2018) with `name` "Charmed", `qualifier` "2018", Mel Vera in `characters`, and a count of 2,266.

### Task 6: Sync rework

**Files:**
- Modify: `plugins/lwtv-plugin/php/meilisearch/class-sync.php`
- Modify: `plugins/lwtv-plugin/php/cpts/shows/class-calculations.php` (one action after the `lezshows_the_score` write)

**Interfaces:**
- Consumes: `Indexes`, `Index::run()`, `Sync_Routes::for_event()`, `Change_Filter`, `Store::objects_for_character()`.
- Produces: task hooks `lwtv_meili_{type}_task` (`lwtv_meili_characters_task`, `lwtv_meili_shows_task`) via `schedule_task( 'meili_' . $type, $post_id, 0, self::DELAY )`, each calling `Indexes::get( $type )->run( $post_id )`. Action `lwtv_show_score_updated` with `( int $post_id )`, fired in `Calculations::do_the_math()` right after `update_post_meta( $post_id, 'lezshows_the_score', $calculate )`.

- [ ] **Step 1: Route every event through `Sync_Routes`**: `acf/save_post` 30 → `acf_save`; `transition_post_status` crossing publish and `post_updated` rename → `changed`; `before_delete_post` → `delete`; `lwtv_show_score_updated` → `score`. Queue one task per returned type (skip revisions/autosaves/non-numeric ACF IDs as today).
- [ ] **Step 2: Character's old shows.** `acf/save_post` priority 5: for a character, remember `Store::objects_for_character( id, 'show' )` in a request-scoped array; at priority 30 queue `meili_shows` for each remembered show ID (uniqueness dedupes the ones still linked).
- [ ] **Step 3: Character delete.** Hook `before_delete_post` at priority **5** (the link table deletes rows at 10): queue `meili_characters` for the character and `meili_shows` for each `objects_for_character( id, 'show' )`.
- [ ] **Step 4: Legacy hooks** ~~stay registered one release~~ — removed after the final review (Mika, 2026-11-25): they never shipped beyond `release/8.0.0`.
- [ ] **Step 5: Run** `vendor/bin/phpunit`, `composer lint`, analyse — clean. (Hook wiring is verified live in Task 9.)

### Task 7: WP-CLI `--type`

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/build/class-type-option.php`
- Test: `tests/unit/Meilisearch/TypeOptionTest.php`
- Modify: `plugins/lwtv-plugin/php/wp-cli/cli-meili.php`, `tests/bootstrap.php`

**Interfaces:**
- Consumes: `Indexes::types()`, `Index` methods.
- Produces: `Type_Option::parse( ?string $value, string[] $known, string $default = 'characters' ): string[]` — `null`/`''` → `[ $default ]`; `'all'` → `$known`; a known type → `[ $type ]`; anything else throws `\InvalidArgumentException` whose message lists `$known` and `all`.

- [ ] **Step 1: Write the failing tests**

```php
$known = array( 'characters', 'shows' );
$this->assertSame( array( 'characters' ), Type_Option::parse( null, $known ) );
$this->assertSame( array( 'shows' ), Type_Option::parse( 'shows', $known ) );
$this->assertSame( $known, Type_Option::parse( 'all', $known ) );
$this->expectException( \InvalidArgumentException::class );
$this->expectExceptionMessage( 'characters, shows, all' );
Type_Option::parse( 'show', $known );
```

- [ ] **Step 2: Run** `vendor/bin/phpunit --filter TypeOption` — expect FAIL.
- [ ] **Step 3: Implement `Type_Option`; then the CLI:** `status`, `settings`, `reindex` accept `[--type=<type>]` and loop the parsed indexes, printing `Index: <uid> on <host>` before each; `reindex` queries `$index->post_type()` instead of `Characters::SLUG`; `repo_settings()` reads `$index->settings_path()`; `apply_settings()` calls `$index->forget_state()` after create/delete. `sync <id>` runs `run( $id )` inline on every index whose `affected_by()` (or own type) applies, printing per index what it did. Docblocks list the new option.
- [ ] **Step 4: Run** `vendor/bin/phpunit`, `composer lint`, analyse — clean.
- [ ] **Step 5: Live check (read-only):** `wp lwtv meili status` (characters, unchanged output), `wp lwtv meili status --type=shows` (warns `index_not_found`), `wp lwtv meili status --type=show` (error listing types), `wp lwtv meili reindex --type=shows --dry-run` (count 2,266).

### Task 8: Admin notice and Search Compare for shows

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/class-admin-notice.php`
- Modify: `plugins/lwtv-plugin/php/meilisearch/class-meilisearch.php`, `class-compare-page.php`, `assets/compare.js`

**Interfaces:**
- Consumes: `Indexes`, `Index::state()`, `Index::label()`.
- Produces: `Admin_Notice::register()` hooking `admin_notices`; REST `GET /lwtv/v1/meili-compare/searchwp?q=&type=` where `type` is `enum` of `Indexes::types()`, default `characters`, SearchWP engine = the type name (`characters`, `shows`); localized `lwtvMeiliCompare.types = { characters: { index, label }, shows: { index, label } }` plus label `notSetUp`.

- [ ] **Step 1: Admin notice.** For `manage_options` users on the Dashboard (`dashboard`) and LezWatch.TV screens (screen id contains `lwtv`), one `notice-warning` listing each index whose `state()` is `missing`: "Meilisearch: the %1$s index (%2$s) isn't set up on this site. Run %3$s to create it." with `%3$s` = `<code>wp lwtv meili settings --type=shows</code>` (the type filled in). Escaped; no notice when every state is `exists` or `unknown`.
- [ ] **Step 2: Compare page.** A Characters / Shows radio group above the search box (default Characters, labelled for screen readers); the description line names the active index. Changing type re-runs the current query.
- [ ] **Step 3: compare.js.** Search `cfg.types[ type ].index`; send `type` to the REST route; on a Meilisearch 404 with `code === 'index_not_found'` show `cfg.labels.notSetUp`; render show rows as name + qualifier, years, "Worth It: …", score (rounded), first three characters; character rows unchanged. Keep `matchingStrategy: 'frequency'` and the overtaken-run guard. Run `npm run fix:js` and `npm run lint:js` (after `nvm use`).
- [ ] **Step 4: Run** `composer lint`, analyse, `npm run lint:js` — clean.

### Task 9: Live build and verification on lwtv.local

Nothing here writes until Mika says go in chat.

- [ ] **Step 1: Ask Mika** for the go-ahead to create and fill `local_shows` on lwtv.local.
- [ ] **Step 2: Missing-index behaviour first** (before creating it): save a show in wp-admin; confirm the queued `lwtv_meili_shows_task` logs "not set up" and `status --type=shows` still says `index_not_found`; confirm the admin notice shows on the Dashboard.
- [ ] **Step 3: Build:** `wp lwtv meili reindex --type=shows --fresh`, then `status --type=shows` → "Settings match the repo file", 2,266 documents. Record elapsed time. Confirm the notice is gone.
- [ ] **Step 4: Characters unchanged:** `wp lwtv meili reindex` → same count as before, "Removed 0 stale document(s)".
- [ ] **Step 5: Sync checks** (run the queue with `wp action-scheduler run --group=lwtv` or wait; check documents with a search-key query or `Client` via `wp eval`): show save (worth-it change moves rank), show rename, unpublish, trash, delete; character added to a show, removed from a show (old show loses the name), renamed; actor made private (name leaves both indexes); a score recalculation (`wp lwtv calc <show>`) updates `score`. Restore every test change afterwards.
- [ ] **Step 6: Expected-results table** from the spec on Search Compare → Shows, next to SearchWP. For any failing row, apply the spec's levers in order and record each change and why.
- [ ] **Step 7: Note for Mika** in the shared notes folder and the final report: what was built, what was verified live, what wasn't, every ranking decision.

### Task 10: Docs

**Files:**
- Modify: `docs/architecture/meilisearch.md`, `docs/runbooks/meilisearch.md`, `docs/plugin-structure.txt`

- [ ] **Step 1: Architecture:** the module now holds several indexes; the shows document and settings; the affected-by table; the sync table with the new task hooks; the missing-index guard and admin notice; `--type`; Search Compare's switch. Drop "Deferred: shows".
- [ ] **Step 2: Runbook:** first run for shows (`settings --type=shows` turns it on); `--type` on every command; production/staging keys must include `shows` before indexing; `wp lwtv calc --all` queues ~2,266 show tasks; new symptom rows (admin notice "isn't set up", `--type` typo).
- [ ] **Step 3: plugin-structure.txt:** new files, `class-indexer.php` gone.
- [ ] **Step 4: Final run:** `vendor/bin/phpunit`, `composer lint`, `TMPDIR=<scratchpad>/phpstan-tmp composer analyse`, `npm run lint:js` — all clean. `git status` lists only the intended files. No commit.
