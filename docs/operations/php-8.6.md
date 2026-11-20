# PHP 8.6

PHP 8.6.0 ships on 2026-11-19. This page records what the 8.6 checks found in this repo, what was fixed or deferred, and what still has to be checked on staging before the site moves (issue #746).

## How it's checked

The non-blocking `php-next` job in `ci.yml` runs on PHP 8.6 (a PHP-8.6 branch nightly until 8.6.0 is tagged). See [GitHub Workflows](../github/workflows.md#ci-ciyml) for what it runs. To run the same checks locally, use the `php:8.6-rc-cli` Docker image with `mbstring` and `intl` added. Without `intl`, two `NameMatchTest` tests fail, because `Name_Match` only folds accents when `Normalizer` exists.

The first triage pass ran on 8.6.0RC7 on 2026-11-16, against the [PHP-8.6 `UPGRADING` notes](https://github.com/php/php-src/blob/PHP-8.6/UPGRADING):

| Check | Result |
| --- | --- |
| `php -l`, all 616 tracked PHP files | Clean. No parse errors or compile-time deprecations. |
| PHPUnit, `--fail-on-deprecation` | Clean (1324 tests) after the CSV fix below. |
| PHPStan, `phpVersion: 80600` | No errors beyond the existing baseline. |
| `composer check-platform-reqs --lock` (theme and plugin) | Every locked package accepts 8.6. |
| PHPCS running on 8.6 | No deprecations from the tooling itself. |

## Findings

### Fixed

- [x] **`fputcsv()` without `$escape`** (`statistics/format/class-csv.php`). This was deprecated in PHP 8.4, so it already fires on the site's PHP 8.5, every time someone downloads stats as CSV. It now passes `$escape = ''`, which turns off PHP's proprietary backslash escaping, so the output is plain RFC 4180. As a result, a backslash before a quote in a cell is now quoted correctly. `CsvFormatTest` covers it. The API usage reader (`str_getcsv()` in `class-api-usage-csv.php`) already passed `''`.

### Checked, no change needed

From the 8.6 deprecation list:

- [x] Returning a value from `__construct()` / `__destruct()`. Every `return` inside a constructor is in a closure (WP-CLI `facetwp_is_main_query` filters, `rest_prepare_taxonomy`), which is allowed. `php -l` would have reported this, since it's a compile-time deprecation.
- [x] `return` inside `finally`. The only `finally` block (`gravity-forms/class-triage-runner.php`) just deletes the lock option.
- [x] `is_a()` / `is_subclass_of()` with a string when `$allow_string` is false. The only call (`_helpers/class-utils.php`) passes an object with `$allow_string = true`.
- [x] Not used anywhere: `is_double()`, `is_long()`, `is_integer()`, `doubleval()`, `strcoll()`, `metaphone()`, `spl_object_hash()`, `spl_classes()`, `SORT_LOCALE_STRING`, `mb_ereg*` (Mbregex), `mysqli_get_charset()`, `stmt_init()`, the `ArrayIterator` and `SplFileObject` CSV methods, objects passed to `array_walk()` or `http_build_query()`, a third argument to `define()`, and `namespace` / `let` / `is` / `_` / `readonly` as identifiers.

From the backward-incompatible changes:

- [x] `openssl_sign()` gained an optional `$salt_length` argument. This doesn't affect the Gravity Forms service-account JWT, and `ServiceAccountJwtTest` passes on 8.6.
- [x] `file_put_contents()` / `fclose()` now return false when a flush fails. `debugger/class-log.php` already checks the result. `_components/class-calendar.php` ignores it, as before.
- [x] Not used: `preg_grep()`, `CURLOPT_READFUNCTION`, `unpack()`, `setlocale()`, `SplFileObject`, GMP, Intl date parsing, sessions.

### Bundled third-party code

- [x] **ICal** (`calendar/ICal/`, vendored `johngrogg/ics-parser`). Already on the latest upstream release (v3.6.0), and it parses cleanly. PHPCompatibility 10 flags `ini_set( 'auto_detect_line_endings' )`, but that line sits behind `PHP_VERSION_ID < 80100`, so it never runs. No patch. Runtime is still unverified (see staging below).
- [x] **shadow-taxonomy** (`plugins/shadow-taxonomy/`). Parses cleanly. Issue #745 is removing it; if it's still here when the site moves, cover it in the staging pass.

### Deferred

- [ ] **The PHPCS compatibility gate checks nothing for PHP 8.** `phpcs.xml.dist` asks PHPCompatibilityWP to check `testVersion 8.1-`, but the installed PHPCompatibility is 9.3.5 (2019), which has no sniffs past PHP 7.4. Version 10.0 adds them, but it's still an alpha (10.0.0-alpha2), and PHPCompatibilityWP 2.1.x requires `^9`. A one-off scan with 10.0.0-alpha2 found only the two items above. **Next step:** when PHPCompatibility 10.0 and PHPCompatibilityWP 3.0 are stable, update both and re-run `composer lint`. Upstream's 8.6 deprecation sniffs are still on a work-in-progress branch.

## Staging checklist

Static checks and unit tests don't reach the WordPress glue: queries, ACF, templates, and other plugins. Do this on staging before switching production to 8.6.

1. [ ] Check whether DreamPress offers PHP 8.6 for staging. If not, note it on #746, check again once the host adds it, and stop here.
2. [ ] Switch staging to 8.6. Make sure `E_DEPRECATED` gets logged: `WP_DEBUG` and `WP_DEBUG_LOG` on, `WP_DEBUG_DISPLAY` off.
3. [ ] Browse these pages, then read `debug.log`:
   - [ ] home, a show, a character, an actor
   - [ ] every `/statistics/` section, plus one **CSV download** (`?download=csv`), to confirm the `fputcsv()` fix
   - [ ] `/this-year/`
   - [ ] search and FacetWP filters
   - [ ] the calendar (ICal)
   - [ ] the REST endpoints
   - [ ] the wp-admin edit screens for shows, characters and actors, including a save, so the score calculation runs
4. [ ] Run the CLI and cron paths, then read the log again:
   - [ ] `wp lwtv generate cron daily`
   - [ ] `wp lwtv calc --all --post-type=shows --yes`
   - [ ] `wp lwtv score-preview`
   - [ ] `wp lwtv triage` (the Gravity Forms runner: the Sheets JWT and Gemini HTTP calls)
5. [ ] Session defaults changed in 8.6: `session.use_strict_mode`, `cookie_httponly` and `cookie_samesite=Lax` are now on by default. LWTV code doesn't use PHP sessions, but other plugins might. Check that logins and Gravity Forms submissions still work.
6. [ ] Sort the log entries into three groups: ours (fix them), bundled code (patch it or wait for upstream), and other plugins or `vendor/` (report them upstream, never patch them).

## When it's clean

- [ ] Make `php-next` blocking: drop `continue-on-error`, and add it to the `production` ruleset's required checks if wanted.
- [ ] When the site moves to 8.6, set `phpVersion: 80600` in `.github/phpstan/phpstan.neon.dist`, delete `.github/phpstan/phpstan-php86.neon` and the `analyse:php86` composer script, move the `lint-test` and `security` jobs to `'8.6'`, consider raising `config.platform.php` and `require.php` in both `composer.json` files, and update the PHP version in `.claude/CLAUDE.md`. Then point `php-next` at the release after that.
