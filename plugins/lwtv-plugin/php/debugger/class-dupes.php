<?php
/*
 * Find all Duplicates.
 *
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
	 * Findings from find_duplicates().
	 */
	const FINDINGS_DUPES = 'lwtv_debug_duplicates';

	/**
	 * Find Duplicates
	 *
	 * Two candidate sources, because a duplicate only inherits a `-2` slug when
	 * its title was typed identically the second time. Posts whose slug ends in a
	 * number are paired with the post without it; actors are additionally paired
	 * on a matching name key, which is what finds the same person entered under
	 * two different spellings. Either way a pair is only called a duplicate when
	 * both posts carry the same IMDb ID.
	 *
	 * @param array $items - array of Posts
	 */
	public function find_duplicates( $items = array() ): array {
		$collector = new Duplicate_Collector();

		// A recheck only revisits what was already flagged, so it is tagged
		// against the baseline rather than diffed against it. See tag_only().
		$is_recheck = ! empty( $items ) && is_array( $items );

		$slug_ids = $collector->candidate_ids();
		$pairs    = $collector->name_key_pairs();

		if ( $is_recheck ) {
			$wanted = array_map( 'intval', wp_list_pluck( $items, 'id' ) );

			// Narrow both sources rather than only the slug one. A finding that
			// came from a name-key pair has no suffix to rediscover, so collecting
			// it by ID alone would find no original and clear it as fixed.
			$slug_ids = $wanted;
			$pairs    = array_values(
				array_filter(
					$pairs,
					static function ( array $pair ) use ( $wanted ): bool {
						return in_array( (int) $pair['post_id'], $wanted, true );
					}
				)
			);
		}

		$candidates = array_merge(
			$collector->collect( $slug_ids ),
			$collector->collect_pairs( $pairs )
		);

		$findings = array();
		$seen     = array();

		foreach ( $candidates as $candidate ) {
			// One pair can arrive from both sources -- a suffixed slug whose names
			// also key alike, which is what the Desirée Rodriguez pair looks like --
			// and evaluating it twice would put the same row in the report twice.
			$pair_key = (int) ( $candidate['post_id'] ?? 0 ) . ':' . (int) ( $candidate['original']['id'] ?? 0 );

			if ( isset( $seen[ $pair_key ] ) ) {
				continue;
			}

			$seen[ $pair_key ] = true;

			$findings = array_merge( $findings, Duplicate_Rules::evaluate( $candidate ) );
		}

		return Scan::finish(
			array(
				'scope'    => 'duplicates',
				'findings' => self::FINDINGS_DUPES,
				'label'    => 'Duplicate Actors/Shows',
			),
			$findings,
			$is_recheck,
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
		$post_id   = (int) $post_id;
		$collector = new Duplicate_Collector();
		$findings  = Duplicate_Rules::evaluate( $collector->collect_one( $post_id ) );

		// Nothing from the slug. Try the name-key pairing, so this answers the
		// same question find_duplicates() does rather than a narrower one.
		// name_key_pairs_for() returns the same pairs the full scan would hand
		// back for this post, without reading every actor's keys to find them.
		if ( empty( $findings ) ) {
			foreach ( $collector->name_key_pairs_for( $post_id ) as $pair ) {
				$findings = Duplicate_Rules::evaluate(
					$collector->collect_one( $post_id, (int) $pair['original_id'] )
				);

				if ( ! empty( $findings ) ) {
					break;
				}
			}
		}

		if ( empty( $findings ) ) {
			return false;
		}

		return (string) $findings[0]['message'];
	}
}
