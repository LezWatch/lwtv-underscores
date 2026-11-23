<?php
/**
 * Dashboard Data Health report.
 *
 * Turns each validation check's counts into what the "LezWatch.TV Tools"
 * dashboard widget shows: rows grouped by severity and (for admins) the
 * checks that have stopped running. Background jobs are in Site Pulse.
 *
 * Pure. Validation::health_checks() gathers the inputs and the widget renders
 * the result. See docs/architecture/validation-screen.md#dashboard-widget.
 *
 * @package LWTV
 */

namespace LWTV\Admin_Menu\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Health_Report {

	/**
	 * Severity tiers, in display order.
	 */
	const TIERS = array( 'fix_now', 'attention', 'backlog' );

	/**
	 * Where a check with no (or an unknown) severity lands, so a newly added
	 * check is shown rather than silently dropped.
	 */
	const DEFAULT_TIER = 'attention';

	/**
	 * Each check runs once a week on its own weekday, so a check that has not
	 * run for longer than this has missed its slot.
	 */
	const STALE_AFTER = 8 * DAY_IN_SECONDS;

	/**
	 * Build the report.
	 *
	 * @param  array<string, array<string, mixed>>         $checks   Per-check inputs keyed by tab slug:
	 *                                                               name, severity, link_tab, count, cached,
	 *                                                               stored, new, resolved, last, and optional
	 *                                                               cron (false = not in the weekly rotation).
	 * @param  int                                          $now     Current Unix time.
	 * @param  bool                                         $is_admin Whether the viewer sees stale-check warnings.
	 * @return array{tiers: array<string, list<array{key:string, name:string, count:int, new:int, resolved:int, link_tab:string, as_of:bool, last:int}>>, stale: list<array{name:string,last:int}>, last_checked: int, all_clear: bool}
	 */
	public static function build( array $checks, int $now, bool $is_admin ): array {
		$tiers        = array_fill_keys( self::TIERS, array() );
		$stale        = array();
		$last_checked = 0;

		foreach ( $checks as $key => $check ) {
			$cached = (bool) ( $check['cached'] ?? false );
			$count  = $cached ? (int) ( $check['count'] ?? 0 ) : (int) ( $check['stored'] ?? 0 );
			$last   = (int) ( $check['last'] ?? 0 );
			$name   = (string) ( $check['name'] ?? $key );

			$last_checked = max( $last_checked, $last );

			// Checks outside the weekly rotation (`cron` false) run on demand, so
			// an old or missing `last` is normal for them.
			$on_cron = (bool) ( $check['cron'] ?? true );

			if ( $on_cron && ( 0 === $last || ( $now - $last ) > self::STALE_AFTER ) ) {
				$stale[] = array(
					'name' => $name,
					'last' => $last,
				);
			}

			if ( $count <= 0 ) {
				continue;
			}

			$tier = (string) ( $check['severity'] ?? '' );
			if ( ! in_array( $tier, self::TIERS, true ) ) {
				$tier = self::DEFAULT_TIER;
			}

			$tiers[ $tier ][] = array(
				'key'      => (string) $key,
				'name'     => $name,
				'count'    => $count,
				'new'      => (int) ( $check['new'] ?? 0 ),
				'resolved' => (int) ( $check['resolved'] ?? 0 ),
				'link_tab' => (string) ( $check['link_tab'] ?? $key ),
				'as_of'    => ! $cached,
				'last'     => $last,
			);
		}

		foreach ( $tiers as $tier => $rows ) {
			usort(
				$rows,
				static fn ( array $a, array $b ) => ( $b['count'] <=> $a['count'] ) ?: strcmp( $a['name'], $b['name'] )
			);
			$tiers[ $tier ] = $rows;
		}

		$tiers = array_filter( $tiers );

		return array(
			'tiers'        => $tiers,
			'stale'        => $is_admin ? $stale : array(),
			'last_checked' => $last_checked,
			'all_clear'    => empty( $tiers ),
		);
	}

	/**
	 * One-line summary shown in place of a list of several stale checks.
	 *
	 * @param  int $count Number of stale checks.
	 * @return string
	 */
	public static function stale_summary( int $count ): string {
		return sprintf(
			/* translators: %s: number of checks. */
			_n( "%s check hasn't run in over 8 days.", "%s checks haven't run in over 8 days.", $count, 'lwtv-underscores' ),
			number_format_i18n( $count )
		);
	}

	/**
	 * Trend text for one row: "+5 new, 3 fixed", or '' when nothing moved.
	 *
	 * @param  int $added    Findings new since the last full run.
	 * @param  int $resolved Findings fixed since the last full run.
	 * @return string
	 */
	public static function trend( int $added, int $resolved ): string {
		$parts = array();

		if ( $added > 0 ) {
			/* translators: %s: number of new problems. */
			$parts[] = sprintf( _n( '+%s new', '+%s new', $added, 'lwtv-underscores' ), number_format_i18n( $added ) );
		}

		if ( $resolved > 0 ) {
			/* translators: %s: number of fixed problems. */
			$parts[] = sprintf( _n( '%s fixed', '%s fixed', $resolved, 'lwtv-underscores' ), number_format_i18n( $resolved ) );
		}

		return implode( ', ', $parts );
	}
}
