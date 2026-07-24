# Audit Tracking Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add run-to-run diffing, acknowledge/ignore, and a triage summary to `wp lwtv audit`, so it becomes a tracker rather than a stateless snapshot.

**Architecture:** A new WP-CLI-free class `LWTV\Debugger\Audit` (in `php/debugger/`) owns all baseline/diff/ignore logic against options and character meta. `cli-audit.php` becomes a thin consumer: it builds finding arrays, calls `Audit::finalize()`, renders, and gains `ignore` / `ignores` / `reset` subcommands. A future wp-admin surface reuses the same class.

**Tech Stack:** PHP 8.1+, WordPress 6.5+, WP-CLI, WordPress Options API + post meta. Lint via `composer lint` (phpcs, WordPress-Extra).

## Global Constraints

- PHP 8.1+ minimum; WordPress 6.5+ minimum.
- Namespace `LWTV\`; class files named `class-*.php`; one class per file.
- Meta keys: characters `lezchars_`, shows `lezshows_`. Options `lwtv_`.
- All user-facing strings i18n-ready with the `'lwtv'` text domain.
- `composer lint` must pass (0 errors) after every task.
- **No PHPUnit harness exists.** Verification is manual on `lwtv.local` via
  `wp eval` / `wp lwtv audit` with the expected output shown per step.
- **Do not run `git commit` unless the user explicitly asks.** Commit steps
  below are checkpoints: stage the files, show the message, and wait for the
  user's go-ahead before committing.
- Design reference: `docs/superpowers/specs/2026-07-24-audit-tracker-design.md`.

---

## File Structure

- **Create** `plugins/lwtv-plugin/php/debugger/class-audit.php` — `LWTV\Debugger\Audit`: vocabulary, ignore, baseline/diff, `finalize()`.
- **Modify** `plugins/lwtv-plugin/php/wp-cli/cli-audit.php` — consume `Audit`, add identity keys to findings, scope derivation, diffed output + summary, `ignore` / `ignores` / `reset` subcommands, `--show-resolved`.

---

## Task 1: `Audit` class skeleton — vocabulary + finding key

**Files:**
- Create: `plugins/lwtv-plugin/php/debugger/class-audit.php`

**Interfaces:**
- Produces: `class LWTV\Debugger\Audit` with `const ISSUE_TYPES`, `const IGNORE_META`, `const BASELINE_PREFIX`, `const BASELINE_INDEX`, and `public function finding_key( array $finding ): string`.

- [ ] **Step 1: Create the class file with constants and `finding_key()`**

```php
<?php
/**
 * Audit tracking: baselines, diffing, and acknowledgements for `wp lwtv audit`.
 *
 * WP-CLI-free so a future wp-admin surface can reuse it. Consumed today only
 * by LWTV\WP_CLI\Audit (php/wp-cli/cli-audit.php).
 *
 * @package LWTV
 */

namespace LWTV\Debugger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Audit.
 */
class Audit {

	/**
	 * Character meta key holding acknowledgement (ignore) keys.
	 */
	const IGNORE_META = 'lezchars_audit_ignore';

	/**
	 * Option name prefix for per-scope baselines.
	 */
	const BASELINE_PREFIX = 'lwtv_audit_baseline_';

	/**
	 * Option holding the baseline index: scope => array( last_run, count ).
	 */
	const BASELINE_INDEX = 'lwtv_audit_baselines';

	/**
	 * Issue-type vocabulary, keyed by issue_type. 'level' is 'show' or
	 * 'character'; only character-level types can be acknowledged.
	 *
	 * @var array<string, array{level:string, label:string}>
	 */
	const ISSUE_TYPES = array(
		'no-match'     => array(
			'level' => 'show',
			'label' => 'No TVMaze match',
		),
		'ended'        => array(
			'level' => 'show',
			'label' => 'Show ended',
		),
		'tbd'          => array(
			'level' => 'show',
			'label' => 'Status in limbo',
		),
		'missing-year' => array(
			'level' => 'character',
			'label' => 'Missing year',
		),
		'verify-year'  => array(
			'level' => 'character',
			'label' => 'Verify year',
		),
	);

	/**
	 * Stable identity string for a finding, unique within a scope.
	 *
	 * @param array $finding Finding array.
	 * @return string
	 */
	public function finding_key( array $finding ): string {
		return implode(
			':',
			array(
				(int) ( $finding['show_id'] ?? 0 ),
				(int) ( $finding['char_id'] ?? 0 ),
				(string) ( $finding['issue_type'] ?? '' ),
				(int) ( $finding['year'] ?? 0 ),
			)
		);
	}

	/**
	 * Character-level issue types that can be acknowledged.
	 *
	 * @return array<string>
	 */
	public function character_issue_types(): array {
		$types = array();
		foreach ( self::ISSUE_TYPES as $type => $meta ) {
			if ( 'character' === $meta['level'] ) {
				$types[] = $type;
			}
		}
		return $types;
	}
}
```

- [ ] **Step 2: Lint**

Run: `composer lint -- plugins/lwtv-plugin/php/debugger/class-audit.php`
Expected: `0 ERRORS`.

- [ ] **Step 3: Verify autoload + key format on lwtv.local**

Run:
```bash
wp eval '$a = new \LWTV\Debugger\Audit(); echo $a->finding_key( array( "show_id" => 123, "char_id" => 456, "issue_type" => "missing-year", "year" => 2026 ) ), "\n"; print_r( $a->character_issue_types() );'
```
Expected: prints `123:456:missing-year:2026` then an array `( [0] => missing-year [1] => verify-year )`.

- [ ] **Step 4: Commit checkpoint (wait for user go-ahead)**

```bash
git add plugins/lwtv-plugin/php/debugger/class-audit.php
git commit -m "feat: add Audit class skeleton with issue-type vocabulary

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 2: Ignore methods

**Files:**
- Modify: `plugins/lwtv-plugin/php/debugger/class-audit.php`

**Interfaces:**
- Consumes: `Audit` from Task 1.
- Produces: `is_ignored( int, int, string ): bool`, `add_ignore( int, int, string ): bool`, `remove_ignore( int, int, string ): bool`, `get_ignores( int ): array`.

- [ ] **Step 1: Add ignore methods after `character_issue_types()`**

```php
	/**
	 * Ignore key for a show + issue type (year-independent, per spec).
	 *
	 * @param int    $show_id    Show post ID.
	 * @param string $issue_type Issue type.
	 * @return string
	 */
	private function ignore_key( int $show_id, string $issue_type ): string {
		return $show_id . ':' . $issue_type;
	}

	/**
	 * Acknowledgement keys stored on a character.
	 *
	 * @param int $char_id Character post ID.
	 * @return array<string>
	 */
	public function get_ignores( int $char_id ): array {
		$ignores = get_post_meta( $char_id, self::IGNORE_META, true );
		return is_array( $ignores )
			? array_values( array_unique( array_map( 'strval', $ignores ) ) )
			: array();
	}

	/**
	 * Is a character+show+issue acknowledged?
	 *
	 * @param int    $char_id    Character post ID.
	 * @param int    $show_id    Show post ID.
	 * @param string $issue_type Issue type.
	 * @return bool
	 */
	public function is_ignored( int $char_id, int $show_id, string $issue_type ): bool {
		if ( ! $char_id ) {
			return false;
		}
		return in_array( $this->ignore_key( $show_id, $issue_type ), $this->get_ignores( $char_id ), true );
	}

	/**
	 * Acknowledge a character+show+issue.
	 *
	 * @param int    $char_id    Character post ID.
	 * @param int    $show_id    Show post ID.
	 * @param string $issue_type Issue type.
	 * @return bool True if now acknowledged.
	 */
	public function add_ignore( int $char_id, int $show_id, string $issue_type ): bool {
		$ignores = $this->get_ignores( $char_id );
		$key     = $this->ignore_key( $show_id, $issue_type );

		if ( in_array( $key, $ignores, true ) ) {
			return true;
		}

		$ignores[] = $key;
		update_post_meta( $char_id, self::IGNORE_META, $ignores );
		return true;
	}

	/**
	 * Remove an acknowledgement.
	 *
	 * @param int    $char_id    Character post ID.
	 * @param int    $show_id    Show post ID.
	 * @param string $issue_type Issue type.
	 * @return bool True if not acknowledged after the call.
	 */
	public function remove_ignore( int $char_id, int $show_id, string $issue_type ): bool {
		$ignores = $this->get_ignores( $char_id );
		$key     = $this->ignore_key( $show_id, $issue_type );

		$filtered = array_values(
			array_filter(
				$ignores,
				static fn( $existing ) => $existing !== $key
			)
		);

		if ( $filtered === $ignores ) {
			return true; // Nothing to remove.
		}

		if ( empty( $filtered ) ) {
			delete_post_meta( $char_id, self::IGNORE_META );
		} else {
			update_post_meta( $char_id, self::IGNORE_META, $filtered );
		}
		return true;
	}
```

- [ ] **Step 2: Lint**

Run: `composer lint -- plugins/lwtv-plugin/php/debugger/class-audit.php`
Expected: `0 ERRORS`.

- [ ] **Step 3: Verify against a real character on lwtv.local**

Pick any published character ID (replace `<CID>` and use a real show ID `<SID>`):
```bash
wp eval '$a = new \LWTV\Debugger\Audit(); $a->add_ignore( <CID>, <SID>, "missing-year" ); var_dump( $a->is_ignored( <CID>, <SID>, "missing-year" ) ); print_r( $a->get_ignores( <CID> ) ); $a->remove_ignore( <CID>, <SID>, "missing-year" ); var_dump( $a->is_ignored( <CID>, <SID>, "missing-year" ) );'
```
Expected: `bool(true)`, an array containing `<SID>:missing-year`, then `bool(false)`.

- [ ] **Step 4: Commit checkpoint (wait for user go-ahead)**

```bash
git add plugins/lwtv-plugin/php/debugger/class-audit.php
git commit -m "feat: add acknowledge/ignore methods to Audit

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 3: Baseline load / save / reset + index

**Files:**
- Modify: `plugins/lwtv-plugin/php/debugger/class-audit.php`

**Interfaces:**
- Consumes: `finding_key()` from Task 1.
- Produces: `load_baseline( string ): array`, `save_baseline( string, array ): void`, `list_scopes(): array`, `reset_baseline( string = '' ): void`.

- [ ] **Step 1: Add baseline methods after the ignore methods**

```php
	/**
	 * Load a scope's baseline: finding_key => finding array.
	 *
	 * @param string $scope Scope string.
	 * @return array
	 */
	public function load_baseline( string $scope ): array {
		$baseline = get_option( self::BASELINE_PREFIX . $scope );
		return is_array( $baseline ) ? $baseline : array();
	}

	/**
	 * Persist a scope's baseline (non-autoloaded) and update the index.
	 *
	 * @param string $scope    Scope string.
	 * @param array  $findings Raw findings (pre ignore-filter).
	 * @return void
	 */
	public function save_baseline( string $scope, array $findings ): void {
		$stored = array();
		foreach ( $findings as $finding ) {
			$stored[ $this->finding_key( $finding ) ] = $finding;
		}
		update_option( self::BASELINE_PREFIX . $scope, $stored, false );

		$index           = $this->list_scopes();
		$index[ $scope ] = array(
			'last_run' => time(),
			'count'    => count( $stored ),
		);
		update_option( self::BASELINE_INDEX, $index, false );
	}

	/**
	 * The baseline index: scope => array( last_run, count ).
	 *
	 * @return array
	 */
	public function list_scopes(): array {
		$index = get_option( self::BASELINE_INDEX );
		return is_array( $index ) ? $index : array();
	}

	/**
	 * Clear one scope's baseline, or all when $scope is empty.
	 *
	 * @param string $scope Scope string, or '' for all.
	 * @return void
	 */
	public function reset_baseline( string $scope = '' ): void {
		$index = $this->list_scopes();

		if ( '' === $scope ) {
			foreach ( array_keys( $index ) as $known ) {
				delete_option( self::BASELINE_PREFIX . $known );
			}
			delete_option( self::BASELINE_INDEX );
			return;
		}

		delete_option( self::BASELINE_PREFIX . $scope );
		unset( $index[ $scope ] );
		update_option( self::BASELINE_INDEX, $index, false );
	}
```

- [ ] **Step 2: Lint**

Run: `composer lint -- plugins/lwtv-plugin/php/debugger/class-audit.php`
Expected: `0 ERRORS`.

- [ ] **Step 3: Verify round-trip + autoload=no on lwtv.local**

```bash
wp eval '$a = new \LWTV\Debugger\Audit(); $f = array( "show_id" => 1, "char_id" => 0, "issue_type" => "ended", "year" => 0, "action" => "x" ); $a->save_baseline( "test_scope", array( $f ) ); print_r( $a->load_baseline( "test_scope" ) ); print_r( $a->list_scopes() );'
wp db query "SELECT option_name, autoload FROM $(wp config get table_prefix)options WHERE option_name LIKE 'lwtv_audit_%'"
wp eval '$a = new \LWTV\Debugger\Audit(); $a->reset_baseline( "test_scope" ); var_dump( $a->load_baseline( "test_scope" ) );'
```
Expected: first command prints the stored finding keyed `1:0:ended:0` and an index entry for `test_scope`; the `autoload` column reads `no` (or `off`) for every `lwtv_audit_%` row; the last command prints `array(0) {}`.

- [ ] **Step 4: Commit checkpoint (wait for user go-ahead)**

```bash
git add plugins/lwtv-plugin/php/debugger/class-audit.php
git commit -m "feat: add per-scope baseline storage to Audit

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 4: `diff()` + `finalize()`

**Files:**
- Modify: `plugins/lwtv-plugin/php/debugger/class-audit.php`

**Interfaces:**
- Consumes: `load_baseline()`, `save_baseline()`, `finding_key()`, `is_ignored()`, `list_scopes()`, `ISSUE_TYPES`.
- Produces: `finalize( string $scope, array $findings ): array` returning `array{ rows: array, resolved: array, summary: array }`. `summary` keys: `scope, had_baseline, previous_run, total, new, open, resolved, ignored, by_issue`.

- [ ] **Step 1: Add `diff()` and `finalize()` after the baseline methods**

```php
	/**
	 * Tag findings against a scope's baseline.
	 *
	 * @param string $scope    Scope string.
	 * @param array  $findings Current findings.
	 * @return array{tagged: array, resolved: array}
	 */
	public function diff( string $scope, array $findings ): array {
		$baseline     = $this->load_baseline( $scope );
		$current_keys = array();
		$tagged       = array();

		foreach ( $findings as $finding ) {
			$key                  = $this->finding_key( $finding );
			$current_keys[ $key ] = true;
			$finding['status']    = isset( $baseline[ $key ] ) ? 'open' : 'new';
			$tagged[]             = $finding;
		}

		$resolved = array();
		foreach ( $baseline as $key => $old_finding ) {
			if ( ! isset( $current_keys[ $key ] ) ) {
				$old_finding['status'] = 'resolved';
				$resolved[]            = $old_finding;
			}
		}

		return array(
			'tagged'   => $tagged,
			'resolved' => $resolved,
		);
	}

	/**
	 * Diff, persist, and partition a run's findings for rendering.
	 *
	 * Ignore is applied here as a DISPLAY filter only; the raw finding set is
	 * what gets saved as the baseline, so toggling an ignore never corrupts
	 * new/open/resolved detection.
	 *
	 * @param string $scope    Scope string.
	 * @param array  $findings Raw findings for this run.
	 * @return array{rows: array, resolved: array, summary: array}
	 */
	public function finalize( string $scope, array $findings ): array {
		foreach ( $findings as $i => $finding ) {
			$findings[ $i ]['scope'] = $scope;
		}

		// Capture the PREVIOUS run time before save_baseline overwrites it.
		$index        = $this->list_scopes();
		$had_baseline = isset( $index[ $scope ] );
		$previous_run = $index[ $scope ]['last_run'] ?? 0;

		$diffed = $this->diff( $scope, $findings );
		$this->save_baseline( $scope, $findings );

		$rows       = array();
		$by_issue   = array();
		$new_ct     = 0;
		$open_ct    = 0;
		$ignored_ct = 0;

		foreach ( $diffed['tagged'] as $finding ) {
			$char_id    = (int) ( $finding['char_id'] ?? 0 );
			$show_id    = (int) ( $finding['show_id'] ?? 0 );
			$issue_type = (string) ( $finding['issue_type'] ?? '' );

			if ( $char_id && $this->is_ignored( $char_id, $show_id, $issue_type ) ) {
				++$ignored_ct;
				continue;
			}

			$rows[]                  = $finding;
			$by_issue[ $issue_type ] = ( $by_issue[ $issue_type ] ?? 0 ) + 1;

			if ( 'new' === $finding['status'] ) {
				++$new_ct;
			} else {
				++$open_ct;
			}
		}

		return array(
			'rows'     => $rows,
			'resolved' => $diffed['resolved'],
			'summary'  => array(
				'scope'        => $scope,
				'had_baseline' => $had_baseline,
				'previous_run' => $previous_run,
				'total'        => count( $rows ),
				'new'          => $new_ct,
				'open'         => $open_ct,
				'resolved'     => count( $diffed['resolved'] ),
				'ignored'      => $ignored_ct,
				'by_issue'     => $by_issue,
			),
		);
	}
```

- [ ] **Step 2: Lint**

Run: `composer lint -- plugins/lwtv-plugin/php/debugger/class-audit.php`
Expected: `0 ERRORS`.

- [ ] **Step 3: Verify diff lifecycle on lwtv.local**

```bash
wp eval '$a = new \LWTV\Debugger\Audit(); $a->reset_baseline( "t" ); $f1 = array( "show_id" => 1, "char_id" => 0, "issue_type" => "ended", "year" => 0 ); $f2 = array( "show_id" => 2, "char_id" => 0, "issue_type" => "tbd", "year" => 0 ); $r = $a->finalize( "t", array( $f1, $f2 ) ); echo "run1: "; print_r( $r["summary"] );'
wp eval '$a = new \LWTV\Debugger\Audit(); $f1 = array( "show_id" => 1, "char_id" => 0, "issue_type" => "ended", "year" => 0 ); $r = $a->finalize( "t", array( $f1 ) ); echo "run2: "; print_r( $r["summary"] ); echo "resolved: "; print_r( $r["resolved"] );'
wp eval '$a = new \LWTV\Debugger\Audit(); $a->reset_baseline( "t" );'
```
Expected: run1 summary has `new => 2, open => 0, resolved => 0, had_baseline => (false/empty)`; run2 summary has `new => 0, open => 1, resolved => 1, had_baseline => 1`, and `resolved` lists the `tbd` finding for show 2.

- [ ] **Step 4: Commit checkpoint (wait for user go-ahead)**

```bash
git add plugins/lwtv-plugin/php/debugger/class-audit.php
git commit -m "feat: add diff and finalize orchestration to Audit

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 5: Wire `cli-audit.php` — identity findings, scope, diffed output + summary

**Files:**
- Modify: `plugins/lwtv-plugin/php/wp-cli/cli-audit.php`

**Interfaces:**
- Consumes: `LWTV\Debugger\Audit::finalize()`, `Audit::ISSUE_TYPES`.
- Produces: findings now carry `scope, show_id, char_id, issue_type, year`; `--show-resolved` flag; summary emitted to STDERR.

- [ ] **Step 1: Add the `use` statement**

At the top with the other `use` lines (after `use LWTV\CPTs\Shows as CPT_Shows;`):
```php
use LWTV\CPTs\Characters as CPT_Characters;
use LWTV\Debugger\Audit;
```

- [ ] **Step 2: Add `--show-resolved` to the docblock OPTIONS**

Insert before the `[--format=<format>]` block in the `__invoke` docblock:
```php
	 * [--show-resolved]
	 * : Also list items resolved since the last run of this scope (catalog/deep only).
	 *
```

- [ ] **Step 3: Replace `build_row()` to carry identity keys**

Replace the entire `build_row()` method with:
```php
	/**
	 * Build one finding row (identity keys + display keys). The 'scope' key is
	 * stamped later by Audit::finalize().
	 *
	 * @param int    $show_id    Show post ID.
	 * @param string $status     TVMaze status.
	 * @param string $ended      TVMaze ended date or year.
	 * @param string $character  Character name (empty for show-level rows).
	 * @param string $actor      Actor name.
	 * @param string $role       Character role type.
	 * @param string $action     Action needed.
	 * @param string $issue_type Issue type (see Audit::ISSUE_TYPES).
	 * @param int    $char_id    Character post ID (0 for show-level).
	 * @param int    $year       Year the finding concerns (0 for show-level).
	 * @return array
	 */
	private function build_row( int $show_id, string $status, string $ended, string $character, string $actor, string $role, string $action, string $issue_type, int $char_id = 0, int $year = 0 ): array {
		$ended_year = ! empty( $ended ) ? substr( $ended, 0, 4 ) : '';

		return array(
			'scope'         => '',
			'show_id'       => $show_id,
			'char_id'       => $char_id,
			'issue_type'    => $issue_type,
			'year'          => $year,
			'show'          => html_entity_decode( get_the_title( $show_id ), ENT_QUOTES, 'UTF-8' ),
			'tvmaze_status' => $status,
			'tvmaze_ended'  => $ended_year,
			'character'     => html_entity_decode( $character, ENT_QUOTES, 'UTF-8' ),
			'actor'         => html_entity_decode( $actor, ENT_QUOTES, 'UTF-8' ),
			'role'          => $role,
			'action'        => $action,
		);
	}
```

- [ ] **Step 4: Add `issue_type` (and char identity) to every `build_row()` call**

In `audit_catalog()`:
- No-match row → append `, 'no-match'`:
  ```php
  $results[] = $this->build_row( $show_id, 'No Match', '', '', '', '', 'Add IMDb/TVMaze ID or audit manually', 'no-match' );
  ```
- Ended row → append `, 'ended'`:
  ```php
  $results[] = $this->build_row( $show_id, $status, $ended_year, '', '', '', 'Set end year (TVMaze: ended ' . ( $ended_year ?: 'date unknown' ) . ')', 'ended' );
  ```
- TBD row → append `, 'tbd'`:
  ```php
  $results[] = $this->build_row( $show_id, $status, '', '', '', '', 'Review: show in limbo on TVMaze', 'tbd' );
  ```

In `audit_characters()`, the missing-year row becomes:
```php
					$rows[] = $this->build_row(
						$show_id,
						$status,
						'',
						get_the_title( $char_id ),
						$this->get_actor_name( $char_id ),
						$type,
						'Add ' . $current_year . ' to Years Appears',
						'missing-year',
						$char_id,
						$current_year
					);
```

In `audit_single()`, the none-path status rows:
```php
			if ( 'Ended' === $status ) {
				$results[] = $this->build_row( $show_id, $status, $ended_year, '', '', '', 'Set end year (TVMaze: ended ' . ( $ended_year ?: 'date unknown' ) . ')', 'ended' );
			} elseif ( 'To Be Determined' === $status ) {
				$results[] = $this->build_row( $show_id, $status, '', '', '', '', 'Review: show in limbo on TVMaze', 'tbd' );
			}
```
And the two character rows in the year loop:
```php
					if ( $found && ! $has ) {
						$results[] = $this->build_row( $show_id, $status, '', get_the_title( $char_id ), $this->get_actor_name( $char_id ), $type, 'TVMaze shows ' . $year . ' -- add?', 'missing-year', $char_id, $year );
					} elseif ( ! $found && $has ) {
						$results[] = $this->build_row( $show_id, $status, '', get_the_title( $char_id ), $this->get_actor_name( $char_id ), $type, 'Verify ' . $year . ' -- no TVMaze appearance found', 'verify-year', $char_id, $year );
					}
```

- [ ] **Step 5: Add scope helpers and replace `output_results()`**

Add two scope helpers (near `parse_letter()`):
```php
	/**
	 * Scope-name token for a raw --letter flag (a-z / num / intl / '').
	 *
	 * Uses the flag token, never the raw marker (# / -), so option names stay
	 * storage-safe.
	 *
	 * @param string $raw Raw --letter flag value.
	 * @return string
	 */
	private function letter_token( string $raw ): string {
		$raw = strtolower( trim( $raw ) );
		if ( '' === $raw ) {
			return '';
		}
		if ( in_array( $raw, array( 'num', '#', '0-9' ), true ) ) {
			return 'num';
		}
		if ( in_array( $raw, array( 'intl', 'other', '-' ), true ) ) {
			return 'intl';
		}
		return $raw;
	}

	/**
	 * Catalog scope string: catalog[_<letter>]_<roles>.
	 *
	 * @param string $letter_token Letter token (may be '').
	 * @param string $roles_flag   Normalized --roles flag.
	 * @return string
	 */
	private function catalog_scope( string $letter_token, string $roles_flag ): string {
		$parts = array( 'catalog' );
		if ( '' !== $letter_token ) {
			$parts[] = $letter_token;
		}
		$parts[] = $roles_flag;
		return implode( '_', $parts );
	}

	/**
	 * Deep-audit scope string: show_<id>_<rolesig>[_all].
	 *
	 * @param int   $show_id        Show post ID.
	 * @param array $roles_to_audit Roles being audited.
	 * @param bool  $do_all         Whether --all is set.
	 * @return string
	 */
	private function show_scope( int $show_id, array $roles_to_audit, bool $do_all ): string {
		$roles = $roles_to_audit;
		sort( $roles );
		$scope = 'show_' . $show_id . '_' . ( ! empty( $roles ) ? implode( '-', $roles ) : 'none' );
		if ( $do_all ) {
			$scope .= '_all';
		}
		return $scope;
	}
```

Replace `output_results()` with a diffed renderer + summary:
```php
	/**
	 * Diff the run against its baseline, render rows, and emit the summary.
	 *
	 * @param string $scope         Scope string.
	 * @param array  $results       Raw finding rows.
	 * @param bool   $show_resolved Whether to also list resolved items.
	 */
	private function output_results( string $scope, array $results, bool $show_resolved ): void {
		$audit     = new Audit();
		$finalized = $audit->finalize( $scope, $results );

		$rows = $finalized['rows'];
		if ( $show_resolved ) {
			$rows = array_merge( $rows, $finalized['resolved'] );
		}

		if ( ! empty( $rows ) ) {
			$fields = array( 'status', 'show', 'tvmaze_status', 'tvmaze_ended', 'character', 'actor', 'role', 'action' );
			\WP_CLI\Utils\format_items( $this->format, $rows, $fields );
		}

		// Summary goes through WP_CLI::success -> STDERR, so it never corrupts
		// a redirected CSV/JSON stream on STDOUT.
		\WP_CLI::success( $this->summary_line( $finalized['summary'] ) );
	}

	/**
	 * Human-readable one-line summary from a finalize() summary block.
	 *
	 * @param array $summary Summary block.
	 * @return string
	 */
	private function summary_line( array $summary ): string {
		if ( 0 === $summary['total'] && 0 === $summary['resolved'] && 0 === $summary['ignored'] ) {
			return __( 'Audit complete. Nothing needs attention!', 'lwtv' );
		}

		$parts   = array();
		$parts[] = sprintf(
			/* translators: %d: number of items needing attention. */
			_n( '%d item needs attention', '%d items need attention', $summary['total'], 'lwtv' ),
			$summary['total']
		);

		if ( ! empty( $summary['by_issue'] ) ) {
			$bits = array();
			foreach ( $summary['by_issue'] as $type => $count ) {
				$label  = Audit::ISSUE_TYPES[ $type ]['label'] ?? $type;
				$bits[] = $count . ' ' . $label;
			}
			$parts[] = '(' . implode( ', ', $bits ) . ')';
		}

		if ( $summary['had_baseline'] ) {
			$since   = $summary['previous_run']
				? wp_date( get_option( 'date_format' ), $summary['previous_run'] )
				: __( 'last run', 'lwtv' );
			$parts[] = sprintf(
				/* translators: 1: new count, 2: still-open count, 3: resolved count, 4: date of last run. */
				__( '%1$d new / %2$d still open / %3$d resolved since %4$s', 'lwtv' ),
				$summary['new'],
				$summary['open'],
				$summary['resolved'],
				$since
			);
		} else {
			$parts[] = __( 'first run for this scope -- all items are new', 'lwtv' );
		}

		if ( $summary['ignored'] ) {
			$parts[] = sprintf(
				/* translators: %d: number of acknowledged (hidden) items. */
				_n( '%d acknowledged (hidden)', '%d acknowledged (hidden)', $summary['ignored'], 'lwtv' ),
				$summary['ignored']
			);
		}

		return __( 'Audit complete.', 'lwtv' ) . ' ' . implode( '. ', $parts ) . '.';
	}
```

- [ ] **Step 6: Update the two `output_results()` call sites + read `--show-resolved`**

In `audit_catalog()`: capture the raw letter flag before `parse_letter()` consumes it, and replace the closing call.
Near the top of `audit_catalog()` where `$letter` is set, add:
```php
		$letter_raw    = \WP_CLI\Utils\get_flag_value( $assoc_args, 'letter', '' );
		$show_resolved = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'show-resolved', false );
		$letter        = $this->parse_letter( $letter_raw );
```
(Delete the old line that computed `$letter` from an inline `get_flag_value`.)
Replace the final `$this->output_results( $results, $is_table );` with:
```php
		$scope = $this->catalog_scope( $this->letter_token( $letter_raw ), $roles_flag );
		$this->output_results( $scope, $results, $show_resolved );
```

In `audit_single()`: compute scope once (after `$do_all` and `$roles_to_audit` exist) and use it at both exits.
After `$do_all` is set, add:
```php
		$show_resolved = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'show-resolved', false );
		$scope         = $this->show_scope( $show_id, $roles_to_audit, $do_all );
```
Replace the none-path `$this->output_results( $results, $is_table ); return;` with:
```php
			$this->output_results( $scope, $results, $show_resolved );
			return;
```
Replace the final `$this->output_results( $results, $is_table );` with:
```php
		$this->output_results( $scope, $results, $show_resolved );
```

- [ ] **Step 7: Lint**

Run: `composer lint -- plugins/lwtv-plugin/php/wp-cli/cli-audit.php`
Expected: `0 ERRORS`.

- [ ] **Step 8: Verify diffed output end-to-end on lwtv.local**

```bash
wp lwtv audit reset --yes 2>/dev/null; true
wp lwtv audit shows --letter=a
wp lwtv audit shows --letter=a
wp lwtv audit shows --letter=a --format=csv > /tmp/audit-a.csv
head -3 /tmp/audit-a.csv
```
Expected: the first `--letter=a` summary says "first run … all items are new"; the second says "0 new / N still open / 0 resolved since <date>"; `/tmp/audit-a.csv` contains only CSV (a `status` column, no "Audit complete" text — that went to STDERR).

- [ ] **Step 9: Commit checkpoint (wait for user go-ahead)**

```bash
git add plugins/lwtv-plugin/php/wp-cli/cli-audit.php
git commit -m "feat: diff audit runs against a baseline and summarize

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 6: CLI subcommands — `ignore` / `ignores` / `reset`

**Files:**
- Modify: `plugins/lwtv-plugin/php/wp-cli/cli-audit.php`

**Interfaces:**
- Consumes: `Audit::add_ignore/remove_ignore/get_ignores/reset_baseline`, `Audit::character_issue_types()`, `CPT_Characters::SLUG`.
- Produces: three new subcommands routed through `__invoke`.

- [ ] **Step 1: Extend the `__invoke` docblock `<type>` options and add EXAMPLES**

Add to the `<type>` options list:
```php
	 * - ignore  (acknowledge one character+show+issue so it stops recurring)
	 * - ignores (list a character's acknowledged items)
	 * - reset   (clear a scope's baseline, or all baselines)
```
Add `[--show=<id>]`, `[--issue=<type>]`, `[--remove]` option blocks, and these EXAMPLES:
```php
	 *     # Acknowledge a missing-year flag so it stops recurring
	 *     wp lwtv audit ignore 456 --show=123 --issue=missing-year
	 *
	 *     # List a character's acknowledgements, then remove one
	 *     wp lwtv audit ignores 456
	 *     wp lwtv audit ignore 456 --show=123 --issue=missing-year --remove
	 *
	 *     # Start a scope fresh (next run shows everything as new)
	 *     wp lwtv audit reset catalog_a_regular
```

- [ ] **Step 2: Route the new subcommands in the `__invoke` switch**

Add cases before `default:`:
```php
				case 'ignore':
					$this->cmd_ignore( (int) ( $args[1] ?? 0 ), $assoc_args );
					break;
				case 'ignores':
					$this->cmd_ignores( (int) ( $args[1] ?? 0 ) );
					break;
				case 'reset':
					$this->cmd_reset( (string) ( $args[1] ?? '' ), $assoc_args );
					break;
```
And update the `default:` error message:
```php
				default:
					\WP_CLI::error( 'Invalid audit type. Use: shows, show <id>, ignore, ignores, reset.' );
```

- [ ] **Step 3: Add the three command methods (near the deep-audit section)**

```php
	/**
	 * Acknowledge (or un-acknowledge) a character+show+issue.
	 *
	 * @param int   $char_id    Character post ID.
	 * @param array $assoc_args Associative args.
	 */
	private function cmd_ignore( int $char_id, array $assoc_args ): void {
		if ( ! $char_id || CPT_Characters::SLUG !== get_post_type( $char_id ) ) {
			\WP_CLI::error( 'Please provide a valid character ID: wp lwtv audit ignore <char_id> --show=<id> --issue=<type>' );
		}

		$audit   = new Audit();
		$show_id = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'show', 0 );
		$issue   = strtolower( trim( (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'issue', '' ) ) );
		$remove  = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'remove', false );
		$valid   = $audit->character_issue_types();

		if ( ! $show_id ) {
			\WP_CLI::error( 'Please provide --show=<show_id>.' );
		}
		if ( ! in_array( $issue, $valid, true ) ) {
			\WP_CLI::error( 'Please provide --issue=<type>, one of: ' . implode( ', ', $valid ) . '.' );
		}

		if ( $remove ) {
			$audit->remove_ignore( $char_id, $show_id, $issue );
			\WP_CLI::success( sprintf( 'Removed acknowledgement: character %1$d, show %2$d, %3$s.', $char_id, $show_id, $issue ) );
			return;
		}

		$audit->add_ignore( $char_id, $show_id, $issue );
		\WP_CLI::success( sprintf( 'Acknowledged: character %1$d, show %2$d, %3$s. Hidden from future audits.', $char_id, $show_id, $issue ) );
	}

	/**
	 * List a character's acknowledgements.
	 *
	 * @param int $char_id Character post ID.
	 */
	private function cmd_ignores( int $char_id ): void {
		if ( ! $char_id || CPT_Characters::SLUG !== get_post_type( $char_id ) ) {
			\WP_CLI::error( 'Please provide a valid character ID: wp lwtv audit ignores <char_id>' );
		}

		$audit   = new Audit();
		$ignores = $audit->get_ignores( $char_id );

		if ( empty( $ignores ) ) {
			\WP_CLI::success( sprintf( 'Character %1$d (%2$s) has no acknowledged audit items.', $char_id, get_the_title( $char_id ) ) );
			return;
		}

		$rows = array();
		foreach ( $ignores as $key ) {
			list( $show_id, $issue ) = array_pad( explode( ':', $key, 2 ), 2, '' );
			$rows[]                  = array(
				'show_id' => (int) $show_id,
				'show'    => get_the_title( (int) $show_id ),
				'issue'   => $issue,
			);
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'show_id', 'show', 'issue' ) );
	}

	/**
	 * Clear a scope's baseline, or all baselines.
	 *
	 * @param string $scope      Scope string, or '' for all.
	 * @param array  $assoc_args Associative args (for --yes).
	 */
	private function cmd_reset( string $scope, array $assoc_args ): void {
		$audit = new Audit();

		if ( '' === $scope ) {
			\WP_CLI::confirm( 'Reset ALL audit baselines? Every scope will show all items as new next run.', $assoc_args );
			$audit->reset_baseline();
			\WP_CLI::success( 'All audit baselines cleared.' );
			return;
		}

		\WP_CLI::confirm( sprintf( 'Reset baseline for scope "%s"?', $scope ), $assoc_args );
		$audit->reset_baseline( $scope );
		\WP_CLI::success( sprintf( 'Baseline "%s" cleared.', $scope ) );
	}
```

- [ ] **Step 4: Lint**

Run: `composer lint -- plugins/lwtv-plugin/php/wp-cli/cli-audit.php`
Expected: `0 ERRORS`.

- [ ] **Step 5: Verify acknowledge lifecycle on lwtv.local**

Use a real character ID `<CID>` that the `--letter=a` audit currently flags with `missing-year`, and its show ID `<SID>`:
```bash
wp lwtv audit ignore <CID> --show=<SID> --issue=missing-year
wp lwtv audit ignores <CID>
wp lwtv audit shows --letter=a          # summary should report "1 acknowledged (hidden)" and omit that row
wp lwtv audit ignore <CID> --show=<SID> --issue=missing-year --remove
wp lwtv audit ignore <CID> --show=<SID> --issue=bogus     # expect an error listing valid types
wp lwtv audit reset catalog_a_regular   # prompts; confirm
```
Expected: `ignores` lists one row; the audit summary shows `1 acknowledged (hidden)` and the character row is absent; after `--remove` it returns; the `bogus` issue errors with the valid-types list; `reset` prompts and clears.

- [ ] **Step 6: Full lint gate**

Run: `composer lint`
Expected: `0 ERRORS` across the project.

- [ ] **Step 7: Commit checkpoint (wait for user go-ahead)**

```bash
git add plugins/lwtv-plugin/php/wp-cli/cli-audit.php
git commit -m "feat: add ignore/ignores/reset subcommands to wp lwtv audit

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Self-Review

**Spec coverage:**
- Reusable `LWTV\Debugger\Audit` class → Tasks 1–4. ✓
- Ignore (character meta, per char+issue) → Task 2 + Task 6 subcommand. ✓
- Baseline per-scope option, non-autoloaded, + index → Task 3. ✓
- Diff new/open/resolved + `finalize()` contract → Task 4. ✓
- Issue-type vocabulary → Task 1. ✓
- Finding array shape (identity + display) → Task 5 Step 3. ✓
- Scope string (location + roles + `--all`; token not marker) → Task 5 Step 5. ✓
- `status` column, resolved-out-of-CSV + `--show-resolved`, STDERR summary → Task 5. ✓
- Subcommands `ignore`/`ignores`/`reset` + validation + confirm → Task 6. ✓
- Approved judgment calls (arrays not value object; resolved opt-in; year-independent ignore) → reflected throughout. ✓
- Error handling (invalid char/issue non-zero; malformed baseline = empty) → Task 6 + `load_baseline()` guard. ✓
- Manual verification instead of PHPUnit → each task Step 3/5/8. ✓

**Type consistency:** `finalize()` returns `{rows, resolved, summary}` — consumed exactly so in Task 5 `output_results()`. Summary keys defined in Task 4 match those read in `summary_line()` (Task 5). `character_issue_types()` defined in Task 1, used in Task 6. `build_row()` new signature (Task 5 Step 3) matches all updated call sites (Step 4). ✓

**Placeholder scan:** No TBD/TODO; every code step shows full code; verification commands include expected output. `<CID>`/`<SID>`/`<id>` are operator-supplied runtime IDs, not plan placeholders. ✓
