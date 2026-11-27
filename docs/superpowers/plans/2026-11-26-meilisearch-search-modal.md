# Meilisearch Search Modal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Behind a switch, replace the header search modal with a Meilisearch-powered modal that has four tabs with counts (Shows, Characters, Actors, Posts), meets WCAG 2.1 AA, and falls back to the SearchWP modal whenever Meilisearch fails.

**Architecture:** The plugin decides whether the modal is on for this request and prints a JSON config. Only the search key goes to the browser. The decision and the config shape are pure `build/` classes, unit-tested. A theme template renders the trigger button and a native `<dialog>`. A plain-JS script module sends one `/multi-search` request per pause in typing and draws the results with `textContent`. The SearchWP modal stays queued but hidden, as the fallback.

**Tech Stack:** PHP 8.1+ / WordPress 6.5+, PHPUnit (pure tests only, no WP bootstrap), vanilla ES module JS (no jQuery, no npm deps), Dart Sass via the theme build, Meilisearch v1.57 `/multi-search`.

**Spec:** `docs/superpowers/specs/2026-11-26-meilisearch-search-modal-design.md`

## Global Constraints

- **No commits** without Mika's explicit approval in that turn. Leave the work uncommitted in the main checkout (`/Users/ipstenu/Development/lezwatch/lwtv-underscores`, `release/8.0.0`). A hook blocks Edit/Write there, so either work in a worktree and copy across, or write with scripts.
- **The indexing key never reaches the browser, a log or the repo.** Only `Config::search_key()` goes into the page.
- **Don't change SearchWP or its add-ons.** `searchwp-modal-form/lwtv.php` and `searchwp-live-ajax-search/live-search-results.php` stay as they are.
- **Switch:** the `LWTV_MEILI_MODAL` constant. Unset or `false` means off, `'editors'` means users with `edit_posts`, `'everyone'` means everyone. It's off unless `Config::is_configured()`.
- **Tab order** is `shows`, `characters`, `actors`, `content`. Tab labels are "Shows", "Characters", "Actors", "Posts".
- **Each query** uses `limit: 10`, `matchingStrategy: 'frequency'` and `showRankingScore: true`.
- **Timing:** debounce about 150 ms, and fall back after 2000 ms.
- **Tab size:** at least 44×44 CSS px on mobile, labels never truncated. If the tabs don't fit, the row scrolls horizontally.
- **Escaping:** index text is inserted only with `textContent` or `setAttribute`, never `innerHTML`.
- **i18n:** strings use `__()` / `_n()` with the `'lwtv-underscores'` text domain. Font sizes are in `rem`. Colors use the `colors.$lwtv-*` variables, and dark-mode overrides go in `scss/partials/_colors-dark.scss` under `#masthead`.
- **Numbers in the config** go out as JSON numbers (`wp_json_encode`), never through `wp_localize_script`.
- **Out of scope:** `search.php`, `searchform.php`, search logging (dropped on purpose), synonyms, and new index fields.

## Review Focus

1. **Responses arriving out of order** while someone types fast: only the latest query's response may draw. Pinned in Task 4 (`seq` guard) and in the Task 6 live check "type fast, then delete back".
2. **HTML inside an index field** (a post titled `<img src=x onerror=alert(1)>`) must render as literal text. Pinned in the Task 6 live check, using a draft post's title in a `wp eval` document sent to `local_content`, then removed.
3. **One missing index on an environment.** Meilisearch fails the whole `/multi-search` when any query's index is missing, so that has to trigger the fallback, not an empty modal. Pinned in the Task 6 live check with a bad uid in the config filter.
4. **Accents and case in "Also known as"** ("las chicas del cable", "LAS CHICAS", "chicás") must match the PHP `Alt_Names::matching()` results. Pinned in the Task 4 console check against the `Alt_Names` PHPUnit cases.
5. **Opening from the mobile drawer.** The drawer is a `<dialog>` in the top layer. Opening the modal from the compact trigger must show above it, and Escape must return focus to that trigger. Pinned in the Task 6 375 px check.

---

### Task 1: Modal switch decision (pure)

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/build/class-modal-switch.php`
- Modify: `tests/bootstrap.php` (add `require_once` after the `class-content-document.php` line)
- Test: `tests/unit/Meilisearch/ModalSwitchTest.php`

**Interfaces:**
- Produces: `LWTV\Meilisearch\Build\Modal_Switch::mode( mixed $setting ): string` returns `'off'`, `'editors'` or `'everyone'`. `Modal_Switch::enabled( mixed $setting, bool $configured, bool $can_edit ): bool`.

- [ ] **Step 1: Write the failing test**

```php
namespace LWTV\Tests\Meilisearch;

use PHPUnit\Framework\TestCase;
use LWTV\Meilisearch\Build\Modal_Switch;

class ModalSwitchTest extends TestCase {
	public function test_mode_normalizes(): void {
		$this->assertSame( 'off', Modal_Switch::mode( null ) );
		$this->assertSame( 'off', Modal_Switch::mode( false ) );
		$this->assertSame( 'off', Modal_Switch::mode( '' ) );
		$this->assertSame( 'off', Modal_Switch::mode( 'Editor' ) );   // typo is off, not on
		$this->assertSame( 'off', Modal_Switch::mode( true ) );       // ambiguous is off
		$this->assertSame( 'editors', Modal_Switch::mode( 'editors' ) );
		$this->assertSame( 'editors', Modal_Switch::mode( ' EDITORS ' ) );
		$this->assertSame( 'everyone', Modal_Switch::mode( 'everyone' ) );
	}

	public function test_unconfigured_is_always_off(): void {
		$this->assertFalse( Modal_Switch::enabled( 'everyone', false, true ) );
	}

	public function test_editors_needs_edit(): void {
		$this->assertTrue( Modal_Switch::enabled( 'editors', true, true ) );
		$this->assertFalse( Modal_Switch::enabled( 'editors', true, false ) );
	}

	public function test_everyone_ignores_edit(): void {
		$this->assertTrue( Modal_Switch::enabled( 'everyone', true, false ) );
	}

	public function test_off_is_off(): void {
		$this->assertFalse( Modal_Switch::enabled( null, true, true ) );
	}
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit --filter ModalSwitch`
Expected: FAIL / Error: class `Modal_Switch` not found.

- [ ] **Step 3: Implement `Modal_Switch`**

Follow `class-type-option.php`: an `ABSPATH` guard, a header docblock ("Name: Modal Switch"), and static methods. `mode()` trims, lowercases, and accepts only the two strings. Anything else is `'off'`.

- [ ] **Step 4: Run tests**

Run: `vendor/bin/phpunit --filter ModalSwitch`, then `vendor/bin/phpunit`
Expected: PASS, and the full suite is green.

---

### Task 2: Browser config shape (pure)

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/build/class-modal-config.php`
- Modify: `tests/bootstrap.php`
- Test: `tests/unit/Meilisearch/ModalConfigTest.php`

**Interfaces:**
- Produces: `LWTV\Meilisearch\Build\Modal_Config::TABS` (const, `[ 'shows', 'characters', 'actors', 'content' ]`). `Modal_Config::build( string $url, string $search_key, array $uids, string $search_page, array $strings ): array`. `$uids` maps type to uid. The result is:
  ```
  { url, key, searchPage, limit: 10, debounce: 150, timeout: 2000,
    tabs: [ { type, uid, label, one, many }, … in TABS order ],
    strings: { … } }
  ```
  `$strings` holds `labels[type]`, `one[type]` and `many[type]` (printf patterns with `%d`), plus flat keys `dialogLabel`, `inputLabel`, `close`, `seeAll`, `noResults` (`%s`), `showing` (`%s`), `unavailable`, `fallback`, `aka` (`%s`). Flat keys are copied to `strings`. Per-type values are folded into each tab.

- [ ] **Step 1: Write the failing test**

Write these tests, each with exact assertions:
- `test_numbers_are_ints`: `assertSame( 10, $c['limit'] )`, `assertSame( 150, $c['debounce'] )`, `assertSame( 2000, $c['timeout'] )`, and `assertSame( 10, json_decode( json_encode( $c ), true )['limit'] )`.
- `test_tabs_in_fixed_order`: `$uids` is passed in the order content, shows, actors, characters. Then `array_column( $c['tabs'], 'type' ) === Modal_Config::TABS`, and `$c['tabs'][0]['uid'] === 'staging_shows'`.
- `test_tab_without_uid_is_dropped`: with no `actors` uid, there are 3 tabs.
- `test_top_level_keys_are_fixed`: `assertSame( [ 'url', 'key', 'searchPage', 'limit', 'debounce', 'timeout', 'tabs', 'strings' ], array_keys( $c ) )`. `build()` never receives the indexing key, so this pins that nothing else can slip into the blob.
- `test_url_trailing_slash_trimmed`: `'https://s.example/'` becomes `'https://s.example'`.

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit --filter ModalConfig`
Expected: Error: class `Modal_Config` not found.

- [ ] **Step 3: Implement `Modal_Config::build()`**

- [ ] **Step 4: Run tests**

Run: `vendor/bin/phpunit`
Expected: the full suite is green.

---

### Task 3: WordPress glue: Search_Modal and template tags

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/class-search-modal.php`
- Modify: `plugins/lwtv-plugin/php/_components/class-meilisearch.php` (implement `Templater`)

**Interfaces:**
- Consumes: `Modal_Switch::enabled()`, `Modal_Config::build()`, `Config::from_constants()`, `Config::index( $type )`, `Config::url()`, `Config::search_key()`.
- Produces: the template tags `lwtv_plugin()->meili_modal_enabled(): bool` and `lwtv_plugin()->meili_modal_config_json(): string`. The second returns `wp_json_encode` output, or `''` when the modal is off. It also produces the filter `lwtv_meili_modal_config` (array → array), which exists so the Task 6 fallback checks can inject a bad uid. It's documented as a debugging hook.

- [ ] **Step 1: Implement `Search_Modal`**
  - `enabled(): bool` reads `defined( 'LWTV_MEILI_MODAL' ) ? LWTV_MEILI_MODAL : null`, `$config->is_configured()` and `current_user_can( 'edit_posts' )`.
  - `config_json(): string` builds the uids from `Modal_Config::TABS` through `Config::index()`. `searchPage` is `home_url( '/' )`. The strings come from `__()`, and the singular and plural forms for each type are hard-coded per string pair, wrapped in `_n_noop()` so translators see them.
  - The `_Components\Meilisearch` component implements `Templater`, and its `get_template_tags()` returns both tags. Its `init()` path is unchanged. It builds `Search_Modal` from `Config::from_constants()`, whether or not the module is configured, so both tags always exist and the theme never hits the `__call` exception.

- [ ] **Step 2: Verify on lwtv.local**

Run, with no `LWTV_MEILI_MODAL` in `wp-config.php` (the wp-cli wrapper and flags are in the background):
`wp eval 'var_dump( lwtv_plugin()->meili_modal_enabled(), lwtv_plugin()->meili_modal_config_json() );'`
Expected: `bool(false)` and `string(0) ""`.

Then run `wp eval 'define("LWTV_MEILI_MODAL","everyone"); echo lwtv_plugin()->meili_modal_config_json();'`
Expected: JSON with `"limit":10` (unquoted), four tabs `local_shows`…`local_content`, and a `key` that is the search key. Check it differs from `LWTV_MEILI_INDEX_KEY` with `wp eval 'echo (int)( LWTV_MEILI_SEARCH_KEY !== LWTV_MEILI_INDEX_KEY );'` → `1`. **Don't print either key.**

- [ ] **Step 3: Lint**

Run: `composer lint` and `TMPDIR=<scratchpad>/phpstan-tmp composer analyse`
Expected: clean. If PHPStan flags the new magic tags, regenerate the baseline only if that's how the repo handles `TOOL_TABS`-style additions. Otherwise fix the code.

---

### Task 4: Front-end script module

**Files:**
- Create: `inc/js/meili-search.js`

**Interfaces:**
- Consumes: the config JSON from Task 3, read from `<script type="application/json" id="lwtv-meili-config">` (rendered in Task 5). Uses DOM ids and classes from Task 5: `#lwtv-meili-dialog`, `#lwtv-meili-input`, `#lwtv-meili-tablist`, `#lwtv-meili-panel-{type}`, `#lwtv-meili-status`, the `[data-lwtv-meili-open]` triggers, and `[data-lwtv-meili-fallback]` (the hidden SearchWP trigger).
- Produces these pure exported helpers, documented with JSDoc:
  - `fold( s: string ): string` — lowercase, then NFD and strip `\p{Diacritic}`.
  - `altNamesMatching( names: string[], term: string ): string[]` — every whitespace-split word of the folded term must be a substring of the folded name. De-duplicated by folded name, original order. Empty term → `[]`.
  - `strongestTab( results: Array<{type, hits, total}>, order: string[] ): string|null` — the highest `hits[0]._rankingScore` among tabs with `total > 0`, ties broken by `order`. `null` if nothing matched.
  - `statusText( results, selectedType, strings, query ): string`

Behavior to implement (from the spec, "Decided behavior"):
1. **Opening.** A click on any `[data-lwtv-meili-open]` element calls `dialog.showModal()` and focuses the input. Remember which trigger was clicked. On the `close` event, focus that trigger again. A native `<dialog>` gives Escape, the focus trap and an inert background, and it opens above the drawer.
2. **Input.** On `input`, debounce by `cfg.debounce`. An empty or whitespace query clears the panels, sets every count to 0, clears the status, and sends nothing.
3. **Request.** `fetch( cfg.url + '/multi-search' )` with a `POST`, `Authorization: Bearer cfg.key`, and a body of `{ queries: tabs.map( t => ( { indexUid: t.uid, q, limit: cfg.limit, matchingStrategy: 'frequency', showRankingScore: true, attributesToRetrieve: FIELDS[ t.type ] } ) ) }`. Use an `AbortController` timeout of `cfg.timeout`. Give each request an incrementing `seq`, and drop any response whose `seq` isn't the latest.
   - `FIELDS`:
     - shows: `id, name, qualifier, years, characters, alt_names, url, thumb`
     - characters: `id, name, shows, actors, url, thumb`
     - actors: `id, name, characters, character_count, url, thumb`
     - content: `id, type, title, date, url, thumb`
4. **Tabs.** Use the WAI-ARIA tabs pattern with roving `tabindex`. Left and Right move between tabs, Home and End jump to the ends, and every move both selects and focuses. Each tab's accessible name is `label + ', ' + sprintf( one|many, total )`.
   - Until the user picks a tab (`userPicked` is reset each time the modal opens), each response sets the selected tab to `strongestTab()`. The selection changes `aria-selected`, `tabindex` and the panel's `hidden`, **never focus**.
5. **Rows.** Each panel is a `<ul>` of `<li><a href=url>` with `<img alt="" loading="lazy">` (only if `thumb`), a title element and a detail element. Each row has a type icon with screen-reader text. Clone the inline SVG icons from `<template>` elements that Task 5 renders. The detail line per type:
   - Shows: `years · N characters`. Add an "Also known as" line when `altNamesMatching()` returns names.
   - Characters: up to 2 `shows`, joined by ", ", then ` · actors[0]`.
   - Actors: up to 2 `characters`, then ` · ` and the "N characters" plural.
   - Posts: "News" or "Page", ` · `, then the month and year of `new Date( date * 1000 )` via `toLocaleDateString( document.documentElement.lang || undefined, { month: 'short', year: 'numeric' } )`.
   - Each panel ends with "See all results", linking to `cfg.searchPage + '?s=' + encodeURIComponent( q )`.
   - With 0 hits, the panel shows `noResults`.
6. **Results navigation.** Down from the input focuses the first link in the selected panel. Up and Down move between links, Up from the first link returns to the input, and Enter follows the link (native).
7. **Status.** After each drawn response, set `#lwtv-meili-status` (`aria-live="polite"`) to `statusText()` once.
8. **Fallback.** On a fetch error, a non-2xx response or a timeout:
   - `console.warn( 'LWTV search: falling back to SearchWP', status )`, with no key in the message.
   - Set `fellBack = true`.
   - If `[data-lwtv-meili-fallback]` exists: close the dialog, click that trigger, then after the SearchWP modal has opened (poll for its input, for up to 1 s), set its `input[data-swplive]` value to `q` and dispatch `input`. Announce `strings.fallback`.
   - If it doesn't exist: show `strings.unavailable` with a link to the search page.
   - Once `fellBack` is set, `[data-lwtv-meili-open]` clicks go to the fallback trigger.
9. Nothing animates under `prefers-reduced-motion` (CSS handles that in Task 5).

- [ ] **Step 1: Write the pure helpers first, then check them in the browser console**

After the module loads on lwtv.local (Task 5), import it in the console with `const m = await import( '/wp-content/themes/lwtv-underscores/inc/js/meili-search.js?' + Date.now() )` and check:
- `m.altNamesMatching( [ 'Las chicas del cable', 'Cable Girls' ], 'chicas cable' )` → `[ 'Las chicas del cable' ]`
- `m.altNamesMatching( [ 'Chicás' ], 'CHICAS' )` → `[ 'Chicás' ]`
- `m.altNamesMatching( [ 'X' ], '  ' )` → `[]`
- `m.strongestTab( [ { type: 'shows', total: 1, hits: [ { _rankingScore: 0.9 } ] }, { type: 'characters', total: 14, hits: [ { _rankingScore: 0.9 } ] } ], [ 'shows', 'characters' ] )` → `'shows'` (a tie goes by order)
- `m.strongestTab( [ { type: 'shows', total: 0, hits: [] } ], [ 'shows' ] )` → `null`

Then mirror every case in `tests/unit/Plugins/AltNamesTest.php` and confirm the same names come back.

The module exports the four helpers, so they can be imported for these checks. It only starts itself when `#lwtv-meili-config` exists, so importing it on its own has no side effects.

- [ ] **Step 2: Implement the behavior above**

- [ ] **Step 3: Lint**

Run: `npm run fix:js`, then `npm run lint:js`
Expected: clean.

---

### Task 5: Theme template, enqueue and styles

**Files:**
- Create: `template-parts/header/searchbox-meili.php`
- Create: `scss/addons/_meili-search.scss`
- Modify: `template-parts/header/navbar.php` (both trigger spots: line ~31 compact, line ~70 drawer)
- Modify: `functions.php` (`lwtv_theme_scripts()`)
- Modify: `style.scss` (add `@include meta.load-css("scss/addons/meili-search");` next to `searchwp`)
- Modify: `scss/partials/_colors-dark.scss`

**Interfaces:**
- Consumes: the template tags from Task 3, and the DOM contract from Task 4.
- Produces: the DOM contract listed in Task 4.

- [ ] **Step 1: Template**

`searchbox-meili.php` renders the trigger every time it's included: a `<button type="button" class="nav-link" data-lwtv-meili-open>` with the search symbolicon and the screen-reader text "Search the Site". The rest renders **once per page**, guarded by a static flag or a `did_action`-style global:
- the `<dialog id="lwtv-meili-dialog" aria-labelledby=…>`, containing:
  - a visible label for the input
  - the input (`type="search"`, `autocomplete="off"`)
  - a close `<button>`
  - `<div role="tablist" id="lwtv-meili-tablist">`, holding a `<button role="tab" id="lwtv-meili-tab-{type}" aria-controls="lwtv-meili-panel-{type}">` per config tab, with the label and a count span
  - one `<div role="tabpanel" id="lwtv-meili-panel-{type}" aria-labelledby="lwtv-meili-tab-{type}" tabindex="0" hidden>` per tab
  - `<div id="lwtv-meili-status" class="screen-reader-text" aria-live="polite" role="status">`
- `<template id="lwtv-meili-icon-{type}">` for each type, holding the same symbolicons as `live-search-results.php`: tv.svg for shows, rubber-stamp.svg for characters, award-academy.svg for actors, newspaper.svg for posts.
- `<script type="application/json" id="lwtv-meili-config">`, printing `meili_modal_config_json()`. Escape it with `wp_json_encode` flags `JSON_HEX_TAG | JSON_HEX_AMP` in Task 3, so a `</script>` in a string can't break out.
- If `function_exists( 'searchwp_modal_form_trigger' )`, a `<span hidden>`. It includes `searchbox-searchwp.php` and marks its anchor with `data-lwtv-meili-fallback`, either by wrapping it and having the JS select `span[hidden] a[data-searchwp-modal-trigger]`, or by passing an arg through `get_template_part`. That queues the SearchWP modal exactly as today.

In `navbar.php`, both spots become: if `lwtv_plugin()->meili_modal_enabled()`, use `searchbox-meili`; else the existing branch, unchanged.

- [ ] **Step 2: Enqueue**

In `lwtv_theme_scripts()`: `if ( lwtv_plugin()->meili_modal_enabled() ) { wp_enqueue_script_module( 'lwtv-meili-search', get_template_directory_uri() . '/inc/js/meili-search.js', array(), LWTV_THEME_VERSION['lwtv-underscores'] ); }`

- [ ] **Step 3: Styles**

The dialog is full-screen below `sm` and centered with a max width above it.
- Tabs: `display: flex`, each tab `flex: 1 0 auto`, `min-height: 2.75rem` (44 px), `min-width: 2.75rem`, label and count stacked. `overflow-x: auto` on the tablist, and no truncation.
- Selected tab: underline (`border-bottom`) plus `font-weight: 600`, not color alone.
- `:focus-visible` outlines on tabs, links and the input.
- `@media (prefers-reduced-motion: reduce)` removes transitions.
- Lock background scrolling while the dialog is open: `html:has(#lwtv-meili-dialog[open]) { overflow: hidden; }`.
- Colors use only `colors.$lwtv-*`. Add the dark overrides to `_colors-dark.scss` inside `mixins.color-mode(dark)` and under `#masthead` (the dialog markup lives in the header).

Build: `nvm use && npm run buildquick`
Expected: `style.min.css` contains `lwtv-meili`.

- [ ] **Step 4: Lint**

Run: `npm run lint:css`, `composer lint`
Expected: clean.

---

### Task 6: Live verification, docs

**Files:**
- Modify: `docs/architecture/meilisearch.md` (a "Search modal" section: switch, config, DOM, fallback, the `lwtv_meili_modal_config` filter)
- Modify: `docs/runbooks/meilisearch.md` (a "Search modal" section: how to turn it on, how to tell which modal you're seeing, what fallback looks like, how to turn it off)
- Regenerate: `npm run docs:structure`

- [ ] **Step 1: Turn it on locally**

Add `define( 'LWTV_MEILI_MODAL', 'editors' );` to lwtv.local's `wp-config.php`. Tell Mika before doing it, and remove it afterwards unless Mika says to keep it.

- [ ] **Step 2: Browser checks on lwtv.local (built-in browser), logged in**

Each line below is pass/fail:
- "supergirl" opens on Shows, "alex danvers" on Characters, and a known post title on Posts. The counts match Search Compare for the same query.
- After clicking Characters, typing more keeps Characters selected. Close and reopen, and auto-selection is back.
- Type fast, then delete back to a short query: the final panel matches the final query (Review Focus 1).
- **Keyboard only:** Tab to the trigger, Enter, type, Down into the results, Up back to the input, Shift+Tab to the tabs, then Left, Right, Home and End, and finally Escape. Focus returns to the trigger, and nothing moves focus by itself.
- `read_page` shows `tablist`, tabs named with their counts, and one status text per search.
- **375 px** (`resize_window` preset mobile, then reload): every tab is at least 44 px tall (`getBoundingClientRect`), no label is ellipsized, and `document.documentElement.scrollWidth <= innerWidth`. Open from the compact trigger with the drawer open, then Escape (Review Focus 5).
- **Dark mode:** check the contrast of tab text, the selected underline, row text and the focus ring against their backgrounds (AA, 4.5:1 for text).
- **HTML as text** (Review Focus 2): push a throwaway document `{ id: 999999999, type: 'post', title: '<img src=x onerror=alert(1)>', … }` to `local_content` with `wp eval`, using the indexing key from the constant and never printing it. Search for it, confirm it shows as literal text, then delete document 999999999.
- **Fallback, bad URL:** use `add_filter( 'lwtv_meili_modal_config', … )` in a throwaway mu-plugin to set `url` to `https://127.0.0.1:9`. Search, and the SearchWP modal opens with the query.
- **Fallback, missing index** (Review Focus 3): set one tab's uid to `local_nope`. Same result.
- Remove the mu-plugin.
- **Logged out:** the SearchWP modal, byte-for-byte the same trigger markup as before.

- [ ] **Step 3: The full suite and linters**

Run: `vendor/bin/phpunit && composer lint && TMPDIR=<scratchpad>/phpstan-tmp composer analyse && npm run lint:js && npm run lint:css && npm run docs:structure:check`
Expected: everything green.

- [ ] **Step 4: Hand over**

Leave everything uncommitted in the main checkout. Report which checks passed, with evidence, plus any rulings made along the way.
