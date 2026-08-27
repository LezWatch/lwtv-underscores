<?php
/**
 * On Air Debugger
 *
 * Checks all shows that are listed as "on air" and checks if they are actually on air.
 *
 * @since 6.6.0
 */

namespace LWTV\Debugger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Shows as CPT_Shows;
use LWTV\CPTs\Shows\Airdates;
use LWTV\Debugger\Build\On_Air_Rules;
use LWTV\Debugger\Collect\On_Air_Collector;
use LWTV\Debugger\Format\Rows;
use LWTV\Queeries\Post_Type;

class OnAir {

	/**
	 * Transient holding the results of find_on_air_problems().
	 */
	const TRANSIENT_PROBLEMS = 'lwtv_debug_on_air_problems';

	/**
	 * Find shows that are not on air
	 *
	 * @return array $items - array of show IDs that are not on air
	 */
	public function find_on_air_problems( $items = array() ): array {
		$shows = array();

		// A recheck only revisits the posts already flagged, so it is tagged
		// against the baseline rather than diffed against it. See tag_only().
		$is_recheck = ! empty( $items );

		// Are we a full scan or a recheck?
		if ( ! empty( $items ) ) {
			// Check only the shows from items!
			foreach ( $items as $show_item ) {
				if ( get_post_status( $show_item['id'] ) !== 'draft' ) {
					// If it's NOT a draft, we'll recheck.
					$shows[] = $show_item['id'];
				}
			}
		} else {
			$shows = ( new Post_Type() )->get_ids( CPT_Shows::SLUG );
		}

		// If somehow shows is totally empty...
		if ( empty( $shows ) ) {
			return array();
		}

		// Make sure we don't have dupes.
		$shows = array_unique( $shows );

		/*
		 * Collect, then evaluate. Build\On_Air_Rules holds the comparison and is
		 * pure -- it is handed the year rather than asking the clock, which is why
		 * it can be tested.
		 */
		$collector = new On_Air_Collector();
		$findings  = array();

		foreach ( array_chunk( $shows, On_Air_Collector::BATCH ) as $batch ) {
			foreach ( $collector->collect( $batch ) as $show ) {
				$findings = array_merge( $findings, On_Air_Rules::evaluate( $show ) );
			}
		}

		$diff  = $is_recheck
			? Baseline_Store::tag_only( 'onair_problems', $findings )
			: Baseline_Store::apply( 'onair_problems', $findings );
		$items = Rows::from_findings( $diff['findings'] );

		// Save Transient
		lwtv_plugin()->set_transient( self::TRANSIENT_PROBLEMS, $items, WEEK_IN_SECONDS );

		// Update Options
		Status::record( 'onair_problems', 'On Air Checker', count( $items ), $diff['summary'] );

		return $items;
	}

	/**
	 * Check if a show is on air
	 *
	 * @param int $show_id The ID of the show to check
	 * @return string 'yes' when currently airing, otherwise 'no'.
	 */
	public function check_if_on_air( $show_id ) {
		// The reading is here; the deciding is in Build\On_Air_Rules, so the repair
		// below and the scan above cannot drift apart on what "on air" means.
		return On_Air_Rules::should_be_on_air( Airdates::get( (int) $show_id ), (int) gmdate( 'Y' ) );
	}

	/**
	 * Fix the on air status of a show
	 *
	 * @param int $show_id The ID of the show to fix
	 * @return bool True if the on air status was fixed, false otherwise
	 */
	public function fix_on_air_status( $show_id ): bool {
		$airdates = Airdates::get( (int) $show_id );

		if ( '' === $airdates['start'] || '' === $airdates['finish'] ) {
			// No airdates, can't fix, assume not on air.
			update_post_meta( $show_id, 'lezshows_on_air', 'no' );
			return false;
		}

		update_post_meta( $show_id, 'lezshows_on_air', $this->check_if_on_air( $show_id ) );
		return true;
	}
}
