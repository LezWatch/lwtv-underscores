# Testing

How the PHPUnit suite is set up, what belongs in it, and the rules for the bootstrap's WordPress shims. This expands on the Testing section of `.claude/CLAUDE.md`.

## Running the suite

```bash
composer install                     # PHPUnit comes from require-dev
vendor/bin/phpunit                   # whole unit suite (or: npm test)
vendor/bin/phpunit --filter Trends   # one test class or method
```

- Config: `phpunit.xml.dist`. It defines one suite, `unit`, over `tests/unit/`, and bootstraps `tests/bootstrap.php`.
- The PHPUnit version is pinned in `composer.json` (`require-dev`).
- Tests are grouped by area: `tests/unit/Statistics/`, `This_Year/`, `CPTs/`, `Debugger/`, `Helpers/`, `Calendar/`, `Components/`, `Wikidata/`, `Admin_Menu/`.

## What gets unit-tested

Only **pure transforms**: code that takes arrays and scalars and returns arrays and scalars, with no database, options, meta, globals, HTTP or output. In the view modules these are the `build/` classes (see [docs/statistics/pages.md](statistics/pages.md#build-layer)). Rule, scoring and helper classes are tested the same way when they're pure.

Code that reads WordPress state (queries, `get_post_meta()`, `get_field()`, transients, permalinks) is not unit-tested. Keep it at the edges, behind a seam that hands the pure class its input, and check it against the running site.

New `build/` logic is written test-first.

## The bootstrap

`tests/bootstrap.php` doesn't load WordPress. It:

1. Defines `ABSPATH`, so class files guarded by `if ( ! defined( 'ABSPATH' ) ) exit;` load.
2. Defines the time constants some class constants are built from (`DAY_IN_SECONDS`, for example `Findings_Store::TTL`).
3. Loads Composer's autoloader.
4. Defines a few shims for WordPress functions (below).
5. `require`s each class under test directly. When you add a test for a new class, add its `require_once` here.

Requiring a file only declares the class. A file can be required for its pure methods even when it has others that touch state (for example `Findings_Store`, `Debugger`, `Admin_Notice`, `Rows`), as long as the tests only call the pure methods. The bootstrap comments name which methods those are.

### Shim policy

The bootstrap shims a few WordPress functions that are themselves pure: `wp_parse_url()`, `__()`, `_n()`, `number_format_i18n()`. **These shims are not a WordPress bootstrap and must not turn into one.** To add a shim, the function must be:

- deterministic,
- free of side effects,
- free of globals, options and the database.

In other words, a shim must never let untestable code pass as testable. Anything that reads state belongs behind a seam.

Where a WordPress function fails this test, make the behaviour injectable instead. `remove_accents()` depends on `get_locale()`, so `Name_Key` takes the accent-folding function as a parameter and the tests pass in their own deterministic fold.
