# Meilisearch Connection Settings Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let Search → Settings → General set the Meilisearch URL and index prefix, with defaults worked out per environment, so a new environment needs only the two key lines in `wp-config.php`.

**Architecture:** A new pure class `Build\Connection` holds the defaults, the validation and the define → saved → default resolution. The two values live in the existing `lwtv_meili_settings` option as overrides only, where `''` means "use the default". `Config::load()` replaces `Config::from_constants()`, so every consumer (modal, results page, sync, FacetWP, WP-CLI) resolves the values the same way. The REST save also runs a health check against the newly resolved URL, and the React General tab gains a top "Connection Settings" row.

**Tech Stack:** PHP 8.1+ (WordPress plugin, `LWTV\` PSR-4), PHPUnit pure tests (no WP bootstrap), React through `@wordpress/scripts` (the `@lwtv/meili-admin` workspace).

**Spec:** `docs/superpowers/specs/2026-11-27-meilisearch-connection-settings-design.md`

## Global Constraints

- **The keys stay in `wp-config.php`.** `LWTV_MEILI_INDEX_KEY` and `LWTV_MEILI_SEARCH_KEY` are never stored, never sent in a REST response and never logged or printed, including in test output.
- **Where values come from:** a define wins, then a saved value, then the default.
- **Defaults:** the URL is `https://search.` + the `home_url()` host, lowercased, with a leading `www.` removed and no port. The prefix is `wp_get_environment_type()`, unmapped, so `development` stays `development`.
- **Only overrides are stored.** Saving `''`, or a value equal to the default, stores `''`.
- **`LWTV_MEILI_INDEX_PREFIX` defined as `''`** means no prefix, so index names have none.
- **URL rules:** the scheme is `http` or `https`, there must be a host, and there's no query or fragment. A path is allowed. Trim spaces and drop any trailing `/`.
- **Prefix rules:** spaces are trimmed and letters lowercased first, then a trailing `_` is dropped. What's left must match `^[a-z0-9_-]{1,40}$`.
- **No connection change while any index has a running job:** the response is 409, "Wait for the running job to finish." The switch and the timeout still save on their own.
- **An unreachable URL still saves.** The response carries the health result, and the page shows it as a warning.
- **Section order on General:** Connection Settings, then Who gets Meilisearch search, then Fallback. Use the existing `lwtv-settings__row` / `lwtv-settings__field` markup.
- **Text domain:** `lwtv-underscores`. Pure `build/` messages stay English-only, as in `Search_Settings`.
- **No commit steps:** Mika commits the whole diff at the end, so leave everything uncommitted.
- **Tools:** run `nvm use` (Node 24) before JS work, and never edit `admin/build/`. Run `composer analyse` as well as phpcs.

## Review Focus

1. **A copied database:** with nothing saved on the copy, staging resolves its own defaults (`staging`, `search.<staging host>`). Test `test_resolve_uses_defaults_when_nothing_saved` (Task 1) plus the live check in Task 4.
2. **The person types the default by hand:** it's stored as `''` and doesn't freeze this environment's value into the database. Test `test_override_equal_to_default_is_stored_empty` (Task 1).
3. **Odd input:** a URL with spaces or a trailing slash is cleaned, not refused, and so is a mixed-case prefix ("Staging"). Tests `test_normalizes_url_and_prefix` (Task 1).
4. **A garbage stored option**, such as an `url` that is an array: `read()` treats it as unsaved, nothing fatals, and the default applies. Test `test_read_drops_bad_connection_fields` (Task 2).
5. **A home URL with a port** (`https://lwtv.local:8443`): the default is still `https://search.lwtv.local`. Test `test_default_url_drops_www_and_port` (Task 1).

---

### Task 1: `Build\Connection`, the pure resolver and validation

**Files:**
- Create: `plugins/lwtv-plugin/php/meilisearch/build/class-connection.php` (namespace `LWTV\Meilisearch\Build`, with the `ABSPATH` guard like its siblings)
- Modify: `tests/bootstrap.php`, adding a `require_once` beside `build/class-search-settings.php` (line ~203)
- Test: `tests/unit/Meilisearch/ConnectionTest.php`

**Interfaces:**
- Produces:
  - `Connection::default_url( string $home_url ): string`, which returns `''` when there's no host.
  - `Connection::default_prefix( string $environment ): string`
  - `Connection::normalize_url( mixed $value ): ?string`: the cleaned URL, `''` for empty input, or `null` when invalid.
  - `Connection::normalize_prefix( mixed $value ): ?string`: the same, for the prefix.
  - `Connection::validate( array $input, array $defaults ): array{values: array<string,string>, errors: array<string,string>}`. It reads only the keys `url` and `prefix`. `$defaults` is `array{url: string, prefix: string}`, and a valid value equal to its default becomes `''`.
  - `Connection::resolve( array $constants, array $saved, array $defaults ): array{url: array{value: string, source: string, default: string}, prefix: array{...same}}`. `$constants` has the keys `url` and `prefix`, each `?string` (`null` = not defined). `$saved` holds `url` and `prefix` strings. `source` is `'constant'`, `'saved'` or `'default'`.

- [ ] **Step 1: Write the failing tests** in `ConnectionTest`:
  - `test_default_url_drops_www_and_port`: `default_url('https://www.LezWatchTV.com/')` is `'https://search.lezwatchtv.com'`, `default_url('https://lwtv.local:8443')` is `'https://search.lwtv.local'`, and `default_url('')` is `''`.
  - `test_default_prefix_is_environment_type`: `'local'` gives `'local'`, and `'development'` gives `'development'`.
  - `test_normalizes_url_and_prefix`:
    - `normalize_url(' https://search.lwtv.local/ ')` is `'https://search.lwtv.local'`.
    - `normalize_url('https://x.test/meili')` keeps the path.
    - `normalize_prefix(' Staging_ ')` is `'staging'`.
    - `normalize_url('')` and `normalize_prefix('')` are both `''`.
  - `test_refuses_bad_urls`: each of `'ftp://x.test'`, `'search.lwtv.local'`, `'https://'`, `'https://x.test/?a=1'`, `'https://x.test/#f'` and `array()` gives `null`.
  - `test_refuses_bad_prefixes`: each of `'has space'`, `'dot.ted'`, `str_repeat('a', 41)`, `'_'` and `5` gives `null`. Pass the value in the assert message with `var_export`; tests are exempt from that phpcs rule.
  - `test_override_equal_to_default_is_stored_empty`: `validate(['url' => 'https://search.lwtv.local/', 'prefix' => 'LOCAL'], ['url' => 'https://search.lwtv.local', 'prefix' => 'local'])` gives `values` of `['url' => '', 'prefix' => '']` and no errors.
  - `test_validate_reports_each_bad_field`: a bad URL and a bad prefix give errors under the keys `url` and `prefix`, and `values` is empty.
  - `test_resolve_uses_defaults_when_nothing_saved`: both `value`s equal the defaults and both `source`s are `'default'`.
  - `test_resolve_saved_beats_default_and_constant_beats_saved`.
  - `test_resolve_empty_prefix_constant_means_no_prefix`: with constants `prefix => ''`, the prefix is `['value' => '', 'source' => 'constant', ...]`.
  - `test_resolve_cleans_constant_values`: a constant URL with a trailing `/` is trimmed. An invalid constant is still used as given (trimmed), because a define is the admin's explicit choice and `Config` reports any failure.
- [ ] **Step 2: Run** `vendor/bin/phpunit --filter ConnectionTest`. Expected: it errors with "Class … Connection not found".
- [ ] **Step 3: Implement `Connection`.** Use `parse_url` (not `wp_parse_url`, because the class is pure) and a regex for the prefix. For the prefix, lowercase first, then `rtrim( '_' )`, then check the pattern.
- [ ] **Step 4: Run** `vendor/bin/phpunit --filter ConnectionTest`. Expected: PASS.
- [ ] **Step 5: Leave uncommitted.**

### Task 2: Store the overrides, and resolve them in `Config::load()`

**Files:**
- Modify: `plugins/lwtv-plugin/php/meilisearch/build/class-search-settings.php`. `defaults()` and `read()` gain `url` and `prefix`, each `''` by default. `read()` keeps a stored value only when `Connection::normalize_url`/`normalize_prefix` returns a non-null string. Update the array shapes in the docblocks.
- Modify: `plugins/lwtv-plugin/php/meilisearch/class-config.php`.
  - `REQUIRED` becomes `array( 'LWTV_MEILI_INDEX_KEY', 'LWTV_MEILI_SEARCH_KEY' )`.
  - Add `const URL_CONSTANT = 'LWTV_MEILI_URL'`.
  - `missing()` still lists `LWTV_MEILI_URL` first, but only if the resolved URL is `''`, which with a default can only happen on a broken `home_url()`.
  - Replace `from_constants()` with `public static function load(): Config`. It reads the constants (`null` when undefined), `Search_Settings::read( get_option( Search_Settings_Store::OPTION ) )`, and the defaults from `Connection::default_url( home_url() )` and `Connection::default_prefix( wp_get_environment_type() )`. It then builds with the resolved URL and prefix.
  - Add `public static function connection(): array`, which returns the same `Connection::resolve()` result `load()` used, for the REST layer.
  - Update the file header, which says "read from wp-config.php constants".
- Modify the callers of `from_constants()`: `_components/class-meilisearch.php:48` and `:113`, and `wp-cli/cli-meili.php:442`. Change the CLI message at `:445` from "Define in wp-config.php: " to "Missing in wp-config.php: ".
- Test: `tests/unit/Meilisearch/SearchSettingsTest.php` and `tests/unit/Meilisearch/ConfigTest.php`.

**Interfaces:**
- Consumes: `Connection::normalize_url`, `normalize_prefix` and `resolve` (Task 1).
- Produces:
  - `Search_Settings::read()` / `defaults()`: `array{version: int, switch: string, timeout_ms: int, url: string, prefix: string}`.
  - `Config::load(): Config`
  - `Config::connection(): array` (the `Connection::resolve()` shape).

- [ ] **Step 1: Write the failing tests.**
  - In `SearchSettingsTest`, `test_read_drops_bad_connection_fields`:
    - `read(['url' => array(), 'prefix' => 'bad prefix'])` gives `url` `''` and `prefix` `''`.
    - `read(['url' => 'https://x.test/', 'prefix' => 'qa'])` keeps `'https://x.test'` and `'qa'`.
    - The defaults include `'url' => ''` and `'prefix' => ''`.
  - In `ConfigTest`, update `test_configured_when_all_three_present` and `test_missing_names_constants_not_values` for the two-key `REQUIRED`. Add `test_missing_url_only_when_empty`: `new Config('', 'k', 's')` gives `missing()` of `['LWTV_MEILI_URL']`.
- [ ] **Step 2: Run** `vendor/bin/phpunit --filter 'SearchSettingsTest|ConfigTest'`. Expected: the new assertions FAIL.
- [ ] **Step 3: Implement** the changes listed under Files. `load()` and `connection()` touch WordPress, so they aren't unit-tested; Task 4 checks them live.
- [ ] **Step 4: Run** `vendor/bin/phpunit`. Expected: the whole suite passes. Also run `grep -rn from_constants plugins/`, which should return nothing.
- [ ] **Step 5: Run** `composer analyse`. Expected: no new errors. If PHPStan flags the changed array shapes, fix the docblocks rather than the baseline.
- [ ] **Step 6: Leave uncommitted.**

### Task 3: REST, saving the connection, the job lock and the health result

**Files:**
- Modify: `plugins/lwtv-plugin/php/meilisearch/class-search-settings-store.php`.
  - `save()` also runs `Connection::validate( $input, $defaults )`, with `$defaults` from `Config::connection()`'s `default` values, and merges both sets of errors and values.
  - `save()` refuses with status 409 when the input has `url` and `LWTV_MEILI_URL` is defined, or has `prefix` and `LWTV_MEILI_INDEX_PREFIX` is defined. The messages are "The URL is set in wp-config.php (LWTV_MEILI_URL). Remove that line to manage it here." and the same for the prefix.
  - Add `public function connection_changes( mixed $input ): bool`. It's true when the validated `url` or `prefix` differs from what's stored.
- Modify: `plugins/lwtv-plugin/php/meilisearch/class-search-settings-rest.php`.
  - In `save()`, before storing: if `connection_changes()` is true and `$this->jobs->running( $type )` is non-null for any `$this->indexes->all()` type, return `WP_Error( 'lwtv_meili_settings_busy', __( 'Wait for the running job to finish before changing the connection.', 'lwtv-underscores' ), status 409 )`.
  - After a save that changed the connection: `$health = ( new Client( Config::load() ) )->health();` then add `'health' => array( 'ok' => bool, 'message' => string )` to the response. The `message` is `WP_Error::get_error_message()` only, never a key.
  - Generalise the 409 mapping in `save()` (it currently reads only `errors['switch']`) to use the first error message.
  - `shape()` gains `'connection' => Config::connection()`.
  - `status()` gains `'host_source'` and `'prefix_source'` from `Config::connection()`.
- Test: no new unit test. This is WordPress glue, verified live in Task 4. The pure parts are covered in Tasks 1 and 2.

**Interfaces:**
- Consumes: `Config::connection()` and `Config::load()` (Task 2), `Connection::validate()` (Task 1), and the existing `Index_Jobs::running( string $type ): ?array`.
- Produces, in the JSON the app reads:
  - `GET`/`POST` `lwtv/v1/meili-settings`: `connection.url` and `connection.prefix`, each `{ value, source, default }`. `settings` adds `url` and `prefix`, the saved overrides. `POST` adds `health { ok, message }` when the connection changed.
  - `GET` `/status`: adds `host_source` and `prefix_source`.

- [ ] **Step 1: Implement** the changes listed under Files.
- [ ] **Step 2: Run** `vendor/bin/phpcs plugins/lwtv-plugin/php/meilisearch/` and `composer analyse`. Expected: both clean.
- [ ] **Step 3: Live-check the REST layer** on lwtv.local as an admin, in the browser console with `wp.apiFetch`:
  - `GET` shows `connection.url.source === 'constant'` while lwtv.local has the define.
  - A `POST` of `{url:'https://x.test'}` returns 409 with the wp-config message.
  - A `POST` of `{prefix:'bad prefix'}` returns 400 with `errors.prefix`, when the prefix isn't defined.
  - No response contains either key. Check with `JSON.stringify(r).includes(…)` against a string you know isn't a key; never print the keys.
- [ ] **Step 4: Leave uncommitted.**

### Task 4: The page, docs and the full gate

**Files:**
- Modify: `plugins/lwtv-plugin/php/meilisearch/admin/src/settings/General.js`.
  - Add a first `lwtv-settings__row` with the `<h2>` "Connection Settings" and two `TextControl`s:
    - "Meilisearch URL" (`type="url"`)
    - "Index prefix"
  - Each field has a `placeholder` set to its `connection.*.default`, and a `value` set to `settings.url`/`settings.prefix`.
  - Each field is `disabled` when its `source === 'constant'`. A locked field shows the define's value, with the help text "Set in wp-config.php (LWTV_MEILI_URL). Remove that line to use the default or a value saved here." (or `LWTV_MEILI_INDEX_PREFIX`).
  - Help text when the field isn't locked: "Leave empty to use the default." Show field errors from `errors.url` and `errors.prefix` the way `timeout_ms` errors are shown.
  - `save()` sends `url` and `prefix` only when they aren't locked and differ from `settings`.
  - When `next.health` is present and `! next.health.ok`, show a warning notice: "Saved, but the search server at that URL didn't answer (%s). Check the Status tab." When it's ok: "Saved. The search server answered." Update the file header comment.
- Modify: `plugins/lwtv-plugin/php/meilisearch/admin/src/settings/StatusTab.js`. After `status.host` and the prefix, add " (default)", " (saved)" or " (wp-config.php)" from `host_source`/`prefix_source`, as translatable strings.
- Modify: `docs/architecture/meilisearch.md`, in the Settings → General paragraph and wherever it says the URL is required or read only from constants. Modify `docs/runbooks/meilisearch.md`, in its new-environment setup steps: only the two keys are needed, the defaults are as above, and to override, use Settings → General or a define.
- Test: the JS lint and build. Pure logic is already covered.

- [ ] **Step 1: Implement** the changes listed under Files, then run `nvm use && npm run fix:js`.
- [ ] **Step 2: Run** `npm run lint:js`, `npm run lint:css` and `npm run build:workspace`. Expected: all pass, and the build compiles.
- [ ] **Step 3: Live-check on lwtv.local with the defines in place:**
  - General shows Connection Settings first, both fields greyed out with the define's value.
  - The modal search still returns results.
  - Status shows "(wp-config.php)" for both.
- [ ] **Step 4: Live-check the defaults.**
  - Run `wp eval 'echo \LWTV\Meilisearch\Build\Connection::default_url( home_url() ), " ", \LWTV\Meilisearch\Build\Connection::default_prefix( wp_get_environment_type() );'`, using the local wp-cli setup (mysqli socket override). Expected: `https://search.lwtv.local local`.
  - The save path for unlocked fields needs lwtv.local's `LWTV_MEILI_URL` and `LWTV_MEILI_INDEX_PREFIX` defines commented out. `wp-config.php` is Mika's, so **ask Mika to do it, and to restore it afterwards**. With the defines out:
    - The placeholders show `https://search.lwtv.local` and `local`.
    - Saving `https://nope.invalid` saves, with the warning notice.
    - Saving `bad prefix` is refused.
    - Clearing both fields goes back to "(default)" on the Status tab.
  - If Mika declines, record that these were covered by the Task 1 tests and the Task 3 REST checks only.
- [ ] **Step 5: Check the running-job lock.** Start a Reindex on one index from the Status tab, then try saving a prefix change. Expected: the "Wait for the running job…" error, while a timeout change saves.
- [ ] **Step 6: Run the full gate:** `vendor/bin/phpunit`, `composer lint`, `composer analyse`, `npm run lint` and `npm run docs:structure:check`. Expected: all pass, except the known stray `class-search-page.php.orig`, which Mika owns; report it, don't delete it.
- [ ] **Step 7: Leave uncommitted.**
