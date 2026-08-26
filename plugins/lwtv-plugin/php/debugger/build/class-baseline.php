<?php
/**
 * Tells a new problem from one that has been sitting there for months.
 *
 * A raw count cannot be acted on: "41 shows need attention" is the same number
 * whether nothing changed or eleven things broke last night. Diffing this run
 * against the last one turns that into "3 new, 38 open, 5 resolved", which can.
 *
 * PURE. Array in, array out. Storage lives in Debugger\Baseline_Store, and the
 * separation is the point: the diff is the part worth testing, and it is
 * testable with no WordPress and no options table.
 *
 * Identity is `post_id:issue_type` and deliberately excludes the message. A
 * show renamed, or a per-post message reworded, must not make an old problem
 * look new -- the problem is "this post has this kind of problem", and that is
 * what gets tracked.
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
		$key = (int) ( $finding['post_id'] ?? 0 ) . ':' . (string) ( $finding['issue_type'] ?? '' );

		/*
		 * An optional third part, for the one case where object + type is not
		 * unique: a watch provider term can have several broken URLs, and each is
		 * its own problem. Without this they collapse into one, and fixing one of
		 * three would read as resolving all of them.
		 */
		$identity = (string) ( $finding['identity'] ?? '' );

		return ( '' === $identity ) ? $key : $key . ':' . $identity;
	}

	/**
	 * Reduce a run's findings to the set worth storing as a baseline.
	 *
	 * Only identity is kept. Messages, context and fixability all change for
	 * reasons that have nothing to do with whether a problem is still there, and
	 * storing them would bloat the option for no gain.
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
	 * Separate from diff() because a *partial* run -- the admin "Recheck"
	 * button, which only re-scans the posts already flagged -- can honestly say
	 * whether each finding it looked at is new, but cannot say anything about
	 * what it did not look at. Calling diff() there would report every finding
	 * on every unvisited post as resolved.
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
	 * A first run -- no baseline at all -- reports everything as `open` rather
	 * than `new`. Calling a decade of accumulated problems "new" on the day the
	 * feature ships would be false, and would train everyone to ignore the
	 * number permanently.
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
	 * `new` wins: a row with one new problem and four old ones is a row
	 * something just happened to.
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

		if ( ! empty( $summary['first_run'] ) ) {
			return sprintf(
				'%d outstanding (first run — everything counts as open until there is something to compare against).',
				$total
			);
		}

		$parts = array(
			sprintf( '%d new', (int) ( $summary['new'] ?? 0 ) ),
			sprintf( '%d open', (int) ( $summary['open'] ?? 0 ) ),
		);

		if ( ! empty( $summary['resolved'] ) ) {
			$parts[] = sprintf( '%d resolved since the last run', (int) $summary['resolved'] );
		}

		return implode( ', ', $parts ) . '.';
	}
}
