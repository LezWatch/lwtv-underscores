<?php
/**
 * Where a check's previous run is kept, so this run can be compared to it.
 *
 * Storage only. The diff itself is pure and lives in Debugger\Build\Baseline.
 *
 * One non-autoloaded option per check, plus a small index of which checks have
 * a baseline and when they last ran. That mirrors Audit's storage shape without
 * sharing its keys: Audit's identity string is `show_id:char_id:issue_type:year`
 * and its baselines are already populated under it, so reusing that namespace
 * would have meant either rewriting its identity function -- resetting every
 * audit scope on deploy -- or two incompatible key formats in one option space.
 *
 * The payload is identity only (see Baseline::snapshot()), so this is smaller
 * than the findings each check already stores for ten days.
 *
 * @package LWTV
 */

namespace LWTV\Debugger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Debugger\Build\Baseline;

class Baseline_Store {

	/**
	 * Option name prefix for per-check baselines.
	 */
	const PREFIX = 'lwtv_debug_baseline_';

	/**
	 * Option holding the index: scope => array( last_run, count ).
	 */
	const INDEX = 'lwtv_debug_baselines';

	/**
	 * Read one check's stored snapshot.
	 *
	 * @param  string $scope Check scope, e.g. 'show_problems'.
	 * @return array
	 */
	public static function load( string $scope ): array {
		$baseline = get_option( self::PREFIX . $scope );

		return is_array( $baseline ) ? $baseline : array();
	}

	/**
	 * Has this check ever stored a baseline?
	 *
	 * Asked before saving, because "no baseline" and "an empty baseline" mean
	 * very different things: the first is a first run, the second is a check
	 * that was clean last time. Conflating them would report every finding as
	 * new the first time the feature ships.
	 *
	 * @param  string $scope Check scope.
	 * @return bool
	 */
	public static function exists( string $scope ): bool {
		return isset( self::index()[ $scope ] );
	}

	/**
	 * Store this run as the baseline for next time.
	 *
	 * Saves the raw finding set, not the displayed one. Once acknowledgements
	 * exist they must filter display only -- an ignored finding that stayed out
	 * of the baseline would come back as `new` the moment it was un-ignored.
	 *
	 * @param  string $scope    Check scope.
	 * @param  array  $findings Findings from this run.
	 * @return void
	 */
	public static function save( string $scope, array $findings ): void {
		$snapshot = Baseline::snapshot( $findings );

		update_option( self::PREFIX . $scope, $snapshot, false );

		$index           = self::index();
		$index[ $scope ] = array(
			'last_run' => time(),
			'count'    => count( $snapshot ),
		);

		update_option( self::INDEX, $index, false );
	}

	/**
	 * Diff a full run against the stored baseline, then store the run.
	 *
	 * Full runs only. Handing this a partial run would record the subset as the
	 * whole truth, and the next full scan would then report every post the
	 * partial run skipped as brand new.
	 *
	 * @param  string $scope    Check scope.
	 * @param  array  $findings Findings from a run that visited every post.
	 * @return array{findings: array, resolved: array, summary: array}
	 */
	public static function apply( string $scope, array $findings ): array {
		$result = Baseline::diff( $findings, self::load( $scope ), ! self::exists( $scope ) );

		self::save( $scope, $findings );

		return $result;
	}

	/**
	 * Stamp a partial run's findings without touching the baseline.
	 *
	 * For the admin "Recheck", which re-scans only the posts already flagged. It
	 * can say whether what it looked at is new; it cannot say what was resolved,
	 * and it must not overwrite the baseline. The summary comes back empty
	 * because a partial run has no honest new/open/resolved breakdown to report.
	 *
	 * @param  string $scope    Check scope.
	 * @param  array  $findings Findings from the partial run.
	 * @return array{findings: array, resolved: array, summary: array}
	 */
	public static function tag_only( string $scope, array $findings ): array {
		return array(
			'findings' => Baseline::tag( $findings, self::load( $scope ), ! self::exists( $scope ) ),
			'resolved' => array(),
			'summary'  => array(),
		);
	}

	/**
	 * When did this check last store a baseline?
	 *
	 * @param  string $scope Check scope.
	 * @return int Unix timestamp, or 0.
	 */
	public static function last_run( string $scope ): int {
		return (int) ( self::index()[ $scope ]['last_run'] ?? 0 );
	}

	/**
	 * Every check with a baseline.
	 *
	 * @return array<string, array<string, int>>
	 */
	public static function index(): array {
		$index = get_option( self::INDEX );

		return is_array( $index ) ? $index : array();
	}

	/**
	 * Forget one check's baseline, or all of them.
	 *
	 * After a reset the next run is a first run again: everything reads as open
	 * rather than as a wall of new problems.
	 *
	 * @param  string $scope Check scope, or '' for all.
	 * @return void
	 */
	public static function reset( string $scope = '' ): void {
		$index = self::index();

		if ( '' === $scope ) {
			foreach ( array_keys( $index ) as $known ) {
				delete_option( self::PREFIX . $known );
			}

			delete_option( self::INDEX );
			return;
		}

		delete_option( self::PREFIX . $scope );
		unset( $index[ $scope ] );

		update_option( self::INDEX, $index, false );
	}
}
