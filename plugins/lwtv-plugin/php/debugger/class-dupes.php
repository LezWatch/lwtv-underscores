<?php
/*
 * Find all Duplicates.
 *
 * Orchestration only. The rules are pure and live in Build\Duplicate_Rules --
 * which is where they belong, since every duplicate-detection bug this check has
 * had was a comparison rather than a query (see DEBUGGER-REVIEW.md 1.9b). The
 * database reads are in Collect\Duplicate_Collector.
 */

namespace LWTV\Debugger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Debugger\Build\Duplicate_Rules;
use LWTV\Debugger\Collect\Duplicate_Collector;
use LWTV\Debugger\Format\Rows;

class Dupes {

	/**
	 * Transient holding the results of find_duplicates().
	 */
	const TRANSIENT_DUPES = 'lwtv_debug_duplicates';

	/**
	 * Find Duplicates
	 *
	 * Find all posts whose slug ends in a number, and work out which of them are
	 * really duplicates of the post without it.
	 *
	 * @param array $items - array of Posts
	 */
	public function find_duplicates( $items = array() ): array {
		$collector = new Duplicate_Collector();

		// A recheck only revisits what was already flagged, so it is tagged
		// against the baseline rather than diffed against it. See tag_only().
		$is_recheck = ! empty( $items ) && is_array( $items );

		if ( $is_recheck ) {
			$items_to_check = wp_list_pluck( $items, 'id' );
		} else {
			$items_to_check = $collector->candidate_ids();
		}

		$findings = array();

		foreach ( $collector->collect( $items_to_check ) as $candidate ) {
			$findings = array_merge( $findings, Duplicate_Rules::evaluate( $candidate ) );
		}

		return Scan::finish(
			array(
				'scope'     => 'duplicates',
				'transient' => self::TRANSIENT_DUPES,
				'label'     => 'Duplicate Actors/Shows',
			),
			$findings,
			$is_recheck,
			// `name` is not in the standard row shape: cli-dupes.php names it as
			// an output column, and a post ID alone tells you nothing there.
			static function ( array $tagged ) {
				$rows = Rows::from_findings( $tagged );

				foreach ( $rows as $index => $row ) {
					$rows[ $index ]['name'] = get_the_title( (int) $row['id'] );
				}

				return $rows;
			}
		);
	}

	/**
	 * Get Duplicates
	 *
	 * Kept as a thin pass-through: `wp lwtv dupes` and anything else outside this
	 * class calls it, and the query itself now lives with the other reads.
	 *
	 * @return array<int>
	 */
	public function get_dupes() {
		return ( new Duplicate_Collector() )->candidate_ids();
	}

	/**
	 * Compare Duplicates
	 *
	 * Kept for callers outside this class. The verdict is the rules' to make; all
	 * this does is collect one candidate and translate a finding back into the
	 * string-or-false this used to return.
	 *
	 * @param  int $post_id - Post ID to check
	 * @return bool|string
	 */
	public function compare_duplicates( $post_id ) {
		$candidate = ( new Duplicate_Collector() )->collect_one( (int) $post_id );
		$findings  = Duplicate_Rules::evaluate( $candidate );

		if ( empty( $findings ) ) {
			return false;
		}

		return (string) $findings[0]['message'];
	}
}
