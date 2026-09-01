<?php
/**
 * The two halves every scan shares: working out what to look at, and what to do
 * with what it found.
 *
 * Ten copies of the "full scan or recheck" preamble and eleven of the
 * "diff, render, save, record" epilogue — the last two items of
 * DEBUGGER-REVIEW.md 7. Neither is interesting, both are exactly the sort of
 * thing that drifts: the findings key and the status key have to agree, and
 * whether a run is a recheck decides whether the baseline may be written at all.
 * Getting that wrong is silent.
 *
 * Not pure — it reads post status and queries for IDs — so it lives here rather
 * than in build/.
 *
 * @package LWTV
 */

namespace LWTV\Debugger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Debugger\Format\Rows;
use LWTV\Queeries\Post_Type;

class Scan {

	/**
	 * Write a check's findings. The only place that does.
	 *
	 * The expiry is a property of each *write*, not of the key, so the lifetime of
	 * a report belongs to whichever code path happened to touch it last, and four
	 * paths do: a scan, a per-finding repair, a hook-appended finding, and the
	 * Watch Providers worklist.
	 *
	 * When those disagreed, repairing a finding silently changed when the report
	 * would disappear -- earlier if you fixed something soon after a scan, later
	 * if you fixed it a week on. Nobody would find that by reading one file.
	 *
	 * Hence one door. The TTL is not a number callers pass, it is a fact about
	 * findings, and it lives with the storage in Findings_Store.
	 *
	 * Findings are an option, not a transient, and Findings_Store's docblock has
	 * the whole reason why. The short version: WP-CLI and web requests on
	 * production do not share an object cache tier, so a cron-written transient
	 * was invisible to wp-admin.
	 *
	 * @param  string $key   Findings key.
	 * @param  array  $items Rows to store.
	 * @return array         The rows, so callers can `return Scan::store( … )`.
	 */
	public static function store( string $key, array $items ): array {
		return Findings_Store::save( $key, $items );
	}

	/**
	 * Which posts this run should look at.
	 *
	 * Two modes, and the distinction matters beyond performance: a recheck only
	 * revisits what was already flagged, which is why the epilogue must not let
	 * it overwrite the baseline.
	 *
	 * Drafts are dropped on the recheck path. A draft is not published, so it
	 * cannot be a real finding any more -- and leaving it in would keep it in the
	 * report forever, since a draft's problems never get fixed.
	 *
	 * @param  array    $items Findings from a previous run, or empty for a full scan.
	 * @param  callable $all   Returns every ID to check, for a full scan.
	 * @return array<int>
	 */
	public static function targets( array $items, callable $all ): array {
		if ( empty( $items ) ) {
			$ids = (array) $all();
		} else {
			$ids = array();

			foreach ( $items as $item ) {
				if ( empty( $item['id'] ) ) {
					continue;
				}

				if ( 'draft' !== get_post_status( $item['id'] ) ) {
					$ids[] = $item['id'];
				}
			}
		}

		return array_values( array_unique( array_map( 'intval', $ids ) ) );
	}

	/**
	 * Targets for a whole post type.
	 *
	 * The common case: eight of the ten call sites want every published post of
	 * one type.
	 *
	 * @param  array  $items     Findings from a previous run, or empty.
	 * @param  string $post_type Post type slug.
	 * @return array<int>
	 */
	public static function post_ids( array $items, string $post_type ): array {
		return self::targets(
			$items,
			static fn () => ( new Post_Type() )->get_ids( $post_type )
		);
	}

	/**
	 * Diff a run against its baseline, render it, save it, record it.
	 *
	 * `$is_recheck` decides whether the baseline may be written. A recheck
	 * visited a subset, so diffing it would report everything it did not look at
	 * as resolved *and* store the subset as the whole truth, making the next full
	 * scan call everything new. That is the failure this centralises.
	 *
	 * @param  array         $check    array( scope, findings, label ). `scope` keys
	 *                                 both the baseline and the status entry, so
	 *                                 they cannot drift apart.
	 * @param  array         $findings Typed findings from this run.
	 * @param  bool          $is_recheck Whether this run visited only flagged posts.
	 * @param  callable|null $to_rows  How findings become display rows. Defaults to
	 *                                 Rows::from_findings(); the term-shaped and
	 *                                 duplicate checks pass their own.
	 * @return array<int, array<string, mixed>> The rows, as the scanners return them.
	 */
	public static function finish( array $check, array $findings, bool $is_recheck, ?callable $to_rows = null ): array {
		$scope = $check['scope'];

		$diff = $is_recheck
			? Baseline_Store::tag_only( $scope, $findings )
			: Baseline_Store::apply( $scope, $findings );

		$to_rows = $to_rows ?? static fn ( array $tagged ) => Rows::from_findings( $tagged );
		$items   = (array) $to_rows( $diff['findings'] );

		self::store( $check['findings'], $items );

		Status::record( $scope, $check['label'], count( $items ), $diff['summary'] );

		return $items;
	}
}
