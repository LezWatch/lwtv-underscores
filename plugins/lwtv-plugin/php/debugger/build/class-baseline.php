<?php
/**
 * Tells a new problem from one that has been sitting there for months.
 *
 * @package LWTV
 */

namespace LWTV\Debugger\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Baseline {

	/**
	 * A problem seen for the first time this run.
	 */
	const NEW_ISSUE = 'new';

	/**
	 * A problem that was already in the last run's baseline.
	 */
	const OPEN = 'open';

	/**
	 * A problem in the baseline that this run did not find.
	 */
	const RESOLVED = 'resolved';

	/**
	 * Stable identity for one finding within a check.
	 *
	 * @param  array $finding Finding from Findings::make().
	 * @return string
	 */
	public static function key( array $finding ): string {
		$key      = (int) ( $finding['post_id'] ?? 0 ) . ':' . (string) ( $finding['issue_type'] ?? '' );
		$identity = (string) ( $finding['identity'] ?? '' );

		return ( '' === $identity ) ? $key : $key . ':' . $identity;
	}

	/**
	 * Reduce a run's findings to the set worth storing as a baseline.
	 *
	 * @param  array $findings List of findings.
	 * @return array<string, array<string, mixed>> Keyed by finding key.
	 */
	public static function snapshot( array $findings ): array {
		$snapshot = array();

		foreach ( $findings as $finding ) {
			$post_id = (int) ( $finding['post_id'] ?? 0 );

			if ( ! $post_id ) {
				continue;
			}

			$entry = array(
				'post_id'    => $post_id,
				'issue_type' => (string) ( $finding['issue_type'] ?? '' ),
			);

			if ( isset( $finding['identity'] ) ) {
				$entry['identity'] = (string) $finding['identity'];
			}

			$snapshot[ self::key( $finding ) ] = $entry;
		}

		return $snapshot;
	}

	/**
	 * Stamp each finding new or open against a baseline.
	 *
	 * @param  array $findings  List of findings.
	 * @param  array $baseline  Stored snapshot.
	 * @param  bool  $first_run True when no baseline has ever been stored.
	 * @return array Findings with a `status` key.
	 */
	public static function tag( array $findings, array $baseline, bool $first_run = false ): array {
		$tagged = array();

		foreach ( $findings as $finding ) {
			if ( ! (int) ( $finding['post_id'] ?? 0 ) ) {
				continue;
			}

			$is_new = ! $first_run && ! isset( $baseline[ self::key( $finding ) ] );

			$finding['status'] = $is_new ? self::NEW_ISSUE : self::OPEN;

			$tagged[] = $finding;
		}

		return $tagged;
	}

	/**
	 * Diff this run against a stored baseline.
	 *
	 * @param  array $findings List of findings from this run.
	 * @param  array $baseline Stored snapshot from the previous run.
	 * @param  bool  $first_run True when no baseline has ever been stored.
	 * @return array{findings: array, resolved: array, summary: array}
	 */
	public static function diff( array $findings, array $baseline, bool $first_run = false ): array {
		$tagged  = self::tag( $findings, $baseline, $first_run );
		$seen    = array();
		$new_ct  = 0;
		$open_ct = 0;

		foreach ( $tagged as $finding ) {
			$seen[ self::key( $finding ) ] = true;

			if ( self::NEW_ISSUE === $finding['status'] ) {
				++$new_ct;
			} else {
				++$open_ct;
			}
		}

		$resolved = array();

		foreach ( $baseline as $key => $old ) {
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$old['status'] = self::RESOLVED;
			$resolved[]    = $old;
		}

		return array(
			'findings' => $tagged,
			'resolved' => $resolved,
			'summary'  => array(
				'total'     => count( $tagged ),
				'new'       => $new_ct,
				'open'      => $open_ct,
				'resolved'  => count( $resolved ),
				'first_run' => $first_run,
				'by_issue'  => Findings::count_by_issue( $tagged ),
			),
		);
	}

	/**
	 * The worst status on a row, for a per-post badge.
	 *
	 * @param  array $statuses Statuses of the issues on one row.
	 * @return string
	 */
	public static function row_status( array $statuses ): string {
		return in_array( self::NEW_ISSUE, $statuses, true ) ? self::NEW_ISSUE : self::OPEN;
	}

	/**
	 * One-line summary, for CLI output and admin copy.
	 *
	 * @param  array $summary Summary from diff().
	 * @return string
	 */
	public static function describe_summary( array $summary ): string {
		$total = (int) ( $summary['total'] ?? 0 );

		if ( ! $total && ! (int) ( $summary['resolved'] ?? 0 ) ) {
			return 'Nothing outstanding.';
		}

		$noun = ( 1 === $total ) ? 'problem' : 'problems';

		if ( ! empty( $summary['first_run'] ) ) {
			return sprintf(
				'%d %s outstanding (first run — everything counts as open until there is something to compare against).',
				$total,
				$noun
			);
		}

		$parts = array(
			sprintf( '%d new', (int) ( $summary['new'] ?? 0 ) ),
			sprintf( '%d open', (int) ( $summary['open'] ?? 0 ) ),
		);

		if ( ! empty( $summary['resolved'] ) ) {
			$parts[] = sprintf( '%d resolved since the last run', (int) $summary['resolved'] );
		}

		return 'Problems: ' . implode( ', ', $parts ) . '.';
	}
}
