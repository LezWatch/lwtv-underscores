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
}
