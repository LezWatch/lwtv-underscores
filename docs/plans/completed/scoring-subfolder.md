# Scoring Subfolder Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move the seven pure "scoring maths" classes in `cpts/shows/` into a new `cpts/shows/scoring/` subfolder under the `LWTV\CPTs\Shows\Scoring` namespace, and update every consumer accordingly. Zero behavior change — this is a namespace/path move only.

**Architecture:** `Longevity`, `Character_Score`, `Show_Rating`, `Show_Tropes`, `Trigger_Warning`, `Trope_Categories`, and `Airdates` all move together into `cpts/shows/scoring/` and share the new `LWTV\CPTs\Shows\Scoring` namespace — the hand-rolled autoloader (`_helpers/class-autoload.php`) already maps nested namespaces to matching subdirectories generically, so no autoloader change is needed. `Calculations` stays at `cpts/shows/` — it is the CPT-level orchestrator (meta reads/writes, the WP-glue boundary) that calls into the maths, not itself part of it, and every other file already in `cpts/shows/` (`Custom_Columns`, `Host_Name`, `Shows_Like_This`, `Ways_To_Watch`, the `Watch_*` family) is confirmed to have zero reference to any of the seven moving classes.

**Tech Stack:** PHP 8.1+, the project's hand-rolled PSR-4-ish autoloader, PHPUnit 11.

**Spec:** None — this plan is its own spec, grounded directly in the current source tree (verified below) rather than a separate design doc.

## Global Constraints

- **Behavior-preserving only.** No class body, method, or constant changes — every edit in this plan is either a `namespace` declaration, a `use` statement, a `require_once` path, or a file move.
- One class per `class-*.php` file; namespace mirrors directory path under `LWTV\` (unchanged convention, just one directory level deeper for the seven moving classes).
- **No commits during execution.** Leave all changes uncommitted for the user to review as one diff and commit themselves.

## Verified scope

Grepped directly against the current tree — this is not an estimate:

- **7 files move**, namespace `LWTV\CPTs\Shows;` → `LWTV\CPTs\Shows\Scoring;`: `class-character-score.php`, `class-longevity.php`, `class-show-rating.php`, `class-show-tropes.php`, `class-trigger-warning.php`, `class-trope-categories.php`, `class-airdates.php`.
- **1 file needs new `use` statements added** (it references three of the moving classes *bare*, since it currently shares their namespace): `cpts/shows/class-calculations.php` — needs `Character_Score`, `Show_Rating`, `Show_Tropes`. It does **not** reference `Longevity`, `Trigger_Warning`, `Trope_Categories`, or `Airdates` directly (confirmed by grep), so no `use` lines for those.
- **16 files need an existing `use` line updated** (confirmed by grep — files that only import `Calculations`, which is not moving, are excluded; several sources cited `Calculations` as well as a moving class, and only the moving-class line needs to change):
  `debugger/build/class-on-air-rules.php`, `debugger/build/class-show-rules.php`, `debugger/class-onair.php`, `debugger/collect/class-on-air-collector.php`, `debugger/collect/class-show-collector.php`, `rest-api/class-what-happened-json.php`, `statistics/build/class-trope-category-coverage.php`, `theme/class-content-warning.php`, `wp-cli/cli-calc.php`, `wp-cli/cli-score-preview.php`, `wp-cli/cli-tvmaze.php`, `tests/unit/CPTs/AirdatesTest.php`, `tests/unit/CPTs/CharacterScoreTest.php`, `tests/unit/CPTs/ShowLongevityTest.php`, `tests/unit/CPTs/ShowRatingTest.php`, `tests/unit/CPTs/ShowTropesTest.php`, `tests/unit/CPTs/TriggerWarningTest.php`.
- **1 file needs 7 `require_once` paths updated**: `tests/bootstrap.php`.
- **10 other `cpts/shows/` files confirmed to need zero changes** (grepped for bare references to all seven moving class names — none found): `class-custom-columns.php`, `class-host-name.php`, `class-shows-like-this.php`, `class-ways-to-watch.php`, and the six `class-watch-*.php` files.

Total: 24 files touched, one mechanical change each.

## File Structure

- Move: `plugins/lwtv-plugin/php/cpts/shows/class-character-score.php` → `plugins/lwtv-plugin/php/cpts/shows/scoring/class-character-score.php`
- Move: `plugins/lwtv-plugin/php/cpts/shows/class-longevity.php` → `plugins/lwtv-plugin/php/cpts/shows/scoring/class-longevity.php`
- Move: `plugins/lwtv-plugin/php/cpts/shows/class-show-rating.php` → `plugins/lwtv-plugin/php/cpts/shows/scoring/class-show-rating.php`
- Move: `plugins/lwtv-plugin/php/cpts/shows/class-show-tropes.php` → `plugins/lwtv-plugin/php/cpts/shows/scoring/class-show-tropes.php`
- Move: `plugins/lwtv-plugin/php/cpts/shows/class-trigger-warning.php` → `plugins/lwtv-plugin/php/cpts/shows/scoring/class-trigger-warning.php`
- Move: `plugins/lwtv-plugin/php/cpts/shows/class-trope-categories.php` → `plugins/lwtv-plugin/php/cpts/shows/scoring/class-trope-categories.php`
- Move: `plugins/lwtv-plugin/php/cpts/shows/class-airdates.php` → `plugins/lwtv-plugin/php/cpts/shows/scoring/class-airdates.php`
- Modify: `plugins/lwtv-plugin/php/cpts/shows/class-calculations.php` (add 3 `use` lines)
- Modify: 16 consumer files listed above (one `use` line each, two in three of them)
- Modify: `tests/bootstrap.php` (7 `require_once` paths)

---

### Task 1: Move the scoring classes into `cpts/shows/scoring/` and repoint every consumer

This is one atomic task, not several — the seven files, `Calculations`, the 16 consumers, and the test bootstrap must all land together. Moving the files alone (without updating consumers in the same step) leaves the suite red and every admin/CLI/REST path that touches a show fatally broken, so there is no safe intermediate checkpoint to split this at.

**Files:** see File Structure above.

**Interfaces:** None — no method signature, constant, or return type changes anywhere in this task. Every class keeps its exact name; only its namespace (and therefore its `use` statements elsewhere) changes.

- [ ] **Step 1: Move the seven files and update their namespace declaration**

```bash
mkdir -p plugins/lwtv-plugin/php/cpts/shows/scoring
git mv plugins/lwtv-plugin/php/cpts/shows/class-character-score.php plugins/lwtv-plugin/php/cpts/shows/scoring/class-character-score.php
git mv plugins/lwtv-plugin/php/cpts/shows/class-longevity.php plugins/lwtv-plugin/php/cpts/shows/scoring/class-longevity.php
git mv plugins/lwtv-plugin/php/cpts/shows/class-show-rating.php plugins/lwtv-plugin/php/cpts/shows/scoring/class-show-rating.php
git mv plugins/lwtv-plugin/php/cpts/shows/class-show-tropes.php plugins/lwtv-plugin/php/cpts/shows/scoring/class-show-tropes.php
git mv plugins/lwtv-plugin/php/cpts/shows/class-trigger-warning.php plugins/lwtv-plugin/php/cpts/shows/scoring/class-trigger-warning.php
git mv plugins/lwtv-plugin/php/cpts/shows/class-trope-categories.php plugins/lwtv-plugin/php/cpts/shows/scoring/class-trope-categories.php
git mv plugins/lwtv-plugin/php/cpts/shows/class-airdates.php plugins/lwtv-plugin/php/cpts/shows/scoring/class-airdates.php
```

In each of the seven moved files, change the namespace declaration:

```php
namespace LWTV\CPTs\Shows;
```
to:
```php
namespace LWTV\CPTs\Shows\Scoring;
```

This is the ONLY content change inside these seven files — every one of them was confirmed by grep to have no bare reference to a sibling class outside this group of seven (their internal cross-references, e.g. `Character_Score` calling `Longevity::`, or `Show_Rating` calling `Trigger_Warning::normalize()`, all stay valid because every class they reference bare is moving into the same new namespace alongside them). `Character_Score`'s existing `use LWTV\Queeries\Is_Actor_Queer;` and `use LWTV\Queeries\Is_Actor_Trans;` lines are absolute paths outside `CPTs\Shows` entirely and need no change.

- [ ] **Step 2: Add the three new `use` statements to `class-calculations.php`**

`class-calculations.php` stays in `LWTV\CPTs\Shows` and currently references `Character_Score`, `Show_Rating`, and `Show_Tropes` bare (no `use`, because they used to share its namespace). Add these three lines to its existing `use` block:

```php
use LWTV\_Components\Grading;
use LWTV\CPTs\Shows as CPT_Shows;
use LWTV\CPTs\Shows\Scoring\Character_Score;
use LWTV\CPTs\Shows\Scoring\Show_Rating;
use LWTV\CPTs\Shows\Scoring\Show_Tropes;
use LWTV\Theme\Show_Characters;
```

(The exact ordering doesn't matter; keep the file's existing `use LWTV\_Components\Grading;`, `use LWTV\CPTs\Shows as CPT_Shows;`, and `use LWTV\Theme\Show_Characters;` lines exactly as they are — only add the three new lines.) Do **not** add `use` lines for `Longevity`, `Trigger_Warning`, `Trope_Categories`, or `Airdates` here — `class-calculations.php` does not reference any of those four directly (confirmed by grep); adding unused imports would be dead code this plan doesn't call for.

- [ ] **Step 3: Update the 16 consumer files' `use` statements**

Each change below is a single old-line → new-line replacement (three files have two lines each). The class name is unchanged — only its namespace gains `\Scoring`.

`plugins/lwtv-plugin/php/debugger/build/class-on-air-rules.php`:
```php
use LWTV\CPTs\Shows\Airdates;
```
→
```php
use LWTV\CPTs\Shows\Scoring\Airdates;
```

`plugins/lwtv-plugin/php/debugger/build/class-show-rules.php`:
```php
use LWTV\CPTs\Shows\Airdates;
```
→
```php
use LWTV\CPTs\Shows\Scoring\Airdates;
```

`plugins/lwtv-plugin/php/debugger/class-onair.php`:
```php
use LWTV\CPTs\Shows\Airdates;
```
→
```php
use LWTV\CPTs\Shows\Scoring\Airdates;
```

`plugins/lwtv-plugin/php/debugger/collect/class-on-air-collector.php`:
```php
use LWTV\CPTs\Shows\Airdates;
```
→
```php
use LWTV\CPTs\Shows\Scoring\Airdates;
```

`plugins/lwtv-plugin/php/debugger/collect/class-show-collector.php`:
```php
use LWTV\CPTs\Shows\Airdates;
```
→
```php
use LWTV\CPTs\Shows\Scoring\Airdates;
```

`plugins/lwtv-plugin/php/rest-api/class-what-happened-json.php`:
```php
use LWTV\CPTs\Shows\Airdates;
```
→
```php
use LWTV\CPTs\Shows\Scoring\Airdates;
```

`plugins/lwtv-plugin/php/statistics/build/class-trope-category-coverage.php`:
```php
use LWTV\CPTs\Shows\Trope_Categories;
```
→
```php
use LWTV\CPTs\Shows\Scoring\Trope_Categories;
```

`plugins/lwtv-plugin/php/theme/class-content-warning.php`:
```php
use LWTV\CPTs\Shows\Trigger_Warning;
```
→
```php
use LWTV\CPTs\Shows\Scoring\Trigger_Warning;
```

`plugins/lwtv-plugin/php/wp-cli/cli-calc.php` — this file also has `use LWTV\CPTs\Shows\Calculations as Shows_Calculations;` immediately above; leave that line untouched and change only:
```php
use LWTV\CPTs\Shows\Character_Score;
```
→
```php
use LWTV\CPTs\Shows\Scoring\Character_Score;
```

`plugins/lwtv-plugin/php/wp-cli/cli-score-preview.php` — this file also has `use LWTV\CPTs\Shows\Calculations as Shows_Calculations;` immediately above; leave that line untouched and change:
```php
use LWTV\CPTs\Shows\Character_Score;
use LWTV\CPTs\Shows\Longevity;
```
→
```php
use LWTV\CPTs\Shows\Scoring\Character_Score;
use LWTV\CPTs\Shows\Scoring\Longevity;
```

`plugins/lwtv-plugin/php/wp-cli/cli-tvmaze.php`:
```php
use LWTV\CPTs\Shows\Airdates;
use LWTV\CPTs\Shows\Longevity;
```
→
```php
use LWTV\CPTs\Shows\Scoring\Airdates;
use LWTV\CPTs\Shows\Scoring\Longevity;
```

`tests/unit/CPTs/AirdatesTest.php`:
```php
use LWTV\CPTs\Shows\Airdates;
```
→
```php
use LWTV\CPTs\Shows\Scoring\Airdates;
```

`tests/unit/CPTs/CharacterScoreTest.php`:
```php
use LWTV\CPTs\Shows\Character_Score;
use LWTV\CPTs\Shows\Longevity;
```
→
```php
use LWTV\CPTs\Shows\Scoring\Character_Score;
use LWTV\CPTs\Shows\Scoring\Longevity;
```

`tests/unit/CPTs/ShowLongevityTest.php`:
```php
use LWTV\CPTs\Shows\Longevity;
```
→
```php
use LWTV\CPTs\Shows\Scoring\Longevity;
```

`tests/unit/CPTs/ShowRatingTest.php`:
```php
use LWTV\CPTs\Shows\Show_Rating;
```
→
```php
use LWTV\CPTs\Shows\Scoring\Show_Rating;
```

`tests/unit/CPTs/ShowTropesTest.php`:
```php
use LWTV\CPTs\Shows\Show_Tropes;
```
→
```php
use LWTV\CPTs\Shows\Scoring\Show_Tropes;
```

`tests/unit/CPTs/TriggerWarningTest.php`:
```php
use LWTV\CPTs\Shows\Trigger_Warning;
```
→
```php
use LWTV\CPTs\Shows\Scoring\Trigger_Warning;
```

- [ ] **Step 4: Update `tests/bootstrap.php`'s seven `require_once` paths**

Lines 56-62 currently read:

```php
require_once __DIR__ . '/../plugins/lwtv-plugin/php/cpts/shows/class-trope-categories.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/cpts/shows/class-trigger-warning.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/cpts/shows/class-show-rating.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/cpts/shows/class-show-tropes.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/cpts/shows/class-airdates.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/cpts/shows/class-longevity.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/cpts/shows/class-character-score.php';
```

Replace with:

```php
require_once __DIR__ . '/../plugins/lwtv-plugin/php/cpts/shows/scoring/class-trope-categories.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/cpts/shows/scoring/class-trigger-warning.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/cpts/shows/scoring/class-show-rating.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/cpts/shows/scoring/class-show-tropes.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/cpts/shows/scoring/class-airdates.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/cpts/shows/scoring/class-longevity.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/cpts/shows/scoring/class-character-score.php';
```

(Only the directory segment changes, from `cpts/shows/` to `cpts/shows/scoring/`, for exactly these seven lines. Every other `require_once` line in this file — `class-host-name.php`, `class-watch-*.php`, etc. — stays in `cpts/shows/` and must NOT be touched.)

- [ ] **Step 5: Run the full pure-unit suite**

Run: `vendor/bin/phpunit`
Expected: PASS, same test/assertion counts as before this task (840 tests, 2121 assertions) — this task changes no logic, so the count must not move.

- [ ] **Step 6: Run phpcs across everything touched**

Run: `composer lint` (or `vendor/bin/phpcs` against the 24 touched files plus the new `cpts/shows/scoring/` directory)
Expected: no new errors. `WordPress.Files.FileName.InvalidClassFileName`/`NotHyphenatedLowercase` stay excluded per `phpcs.xml.dist`, so the moved `class-*.php` names in the new subdirectory are still fine.

- [ ] **Step 7: Self-check for any remaining stale reference**

Run:
```bash
grep -rn "LWTV\\\\CPTs\\\\Shows\\\\Character_Score\|LWTV\\\\CPTs\\\\Shows\\\\Longevity\|LWTV\\\\CPTs\\\\Shows\\\\Show_Rating\|LWTV\\\\CPTs\\\\Shows\\\\Show_Tropes\|LWTV\\\\CPTs\\\\Shows\\\\Trigger_Warning\|LWTV\\\\CPTs\\\\Shows\\\\Trope_Categories\|LWTV\\\\CPTs\\\\Shows\\\\Airdates\b" plugins/lwtv-plugin/php tests --include='*.php'
```
Expected: no output. Any match here (other than inside the moved files' own new `LWTV\CPTs\Shows\Scoring\...` namespace, which this pattern does not match since it looks for the OLD unqualified form immediately followed by the class name) means a consumer was missed and must be fixed before this task is done — this grep is exactly the plan's own "16 consumer files" search from the Verified Scope section, so a clean run here is direct confirmation the migration list was complete.

- [ ] **Step 8: Verify against the running site, if available**

None of these classes changed behavior, so this is a light confidence check rather than a required gate (unlike the fortify-weak-points plan's scoring-value checks). If `lwtv.local` is reachable, load a show page (exercises `Content_Warning` → `Trigger_Warning`), run `wp lwtv calc <post_id> --force` (exercises `Calculations` → `Show_Rating`/`Show_Tropes`/`Character_Score`), and run `wp lwtv debug watchurls` or view the debugger admin screen once (exercises the `Airdates`-consuming debugger rules) to confirm nothing fatals. If the site isn't running, skip this step and note it as deferred — the same posture as the fortify-weak-points plan's live-verification steps.

## What this plan deliberately does not do

- It does not touch the seven-file "watch provider" cluster (`class-watch-*.php`, `class-host-name.php`, `class-ways-to-watch.php`) even though it's arguably just as strong a candidate for its own subfolder — that's a separate conversation the user hasn't asked for yet.
- It does not move `Calculations` itself. It is the CPT-level orchestrator that calls into the scoring maths, not part of the maths — the architectural line this plan draws is exactly the caller/callee boundary that already exists in the code today.
- It does not rename any class or change any method signature, constant, or return type. This is a pure namespace/path move.
