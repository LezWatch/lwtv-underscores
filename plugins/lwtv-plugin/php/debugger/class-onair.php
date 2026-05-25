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
use LWTV\Queeries\Post_Type;

class OnAir {

	/**
	 * Find shows that are not on air
	 *
	 * @return array $items - array of show IDs that are not on air
	 */
	public function find_on_air_problems( $items = array() ): array {
		$shows = array();

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
			$the_loop = ( new Post_Type() )->make( CPT_Shows::SLUG );

			if ( is_object( $the_loop ) && $the_loop->have_posts() ) {
				$shows = wp_list_pluck( $the_loop->posts, 'ID' );
			}
		}

		// If somehow shows is totally empty...
		if ( empty( $shows ) ) {
			return array();
		}

		// Make sure we don't have dupes.
		$shows = array_unique( $shows );

		// reset items since we recheck off $characters.
		$items = array();

		// Loop through the shows and check on-air meta versus the actual airdates
		foreach ( $shows as $show_id ) {
			$on_air_meta   = get_post_meta( $show_id, 'lezshows_on_air', true );
			$on_air_actual = $this->check_if_on_air( $show_id );

			if ( empty( $on_air_meta ) || empty( $on_air_actual ) ) {
				$items[] = array(
					'url'     => get_permalink( $show_id ),
					'id'      => $show_id,
					'problem' => 'Show has no on-air meta data and/or airdates.',
				);
				continue;
			}

			// If on-air meta doesn't match the actual on-air status, add to items.
			// Ignore case for the meta value.
			if ( strtolower( $on_air_meta ) !== strtolower( $on_air_actual ) ) {
				$items[] = array(
					'url'     => get_permalink( $show_id ),
					'id'      => $show_id,
					'problem' => 'On-air meta (' . $on_air_meta . ') does not match actual on-air status (' . $on_air_actual . ').',
				);
			}
		}

		// Save Transient
		lwtv_plugin()->set_transient( 'lwtv_debug_on_air_problems', $items, WEEK_IN_SECONDS );

		// Update Options
		$option                   = get_option( 'lwtv_debugger_status' );
		$option['onair_problems'] = array(
			'name'  => 'On Air Checker',
			'count' => ( ! empty( $items ) ) ? count( $items ) : 0,
			'last'  => time(),
		);
		return $items;
	}

	/**
	 * Check if a show is on air
	 *
	 * @param int $show_id The ID of the show to check
	 * @return bool True if the show is on air, false otherwise
	 */
	public function check_if_on_air( $show_id ) {
		$year     = gmdate( 'Y' );
		$airdates = get_post_meta( $show_id, 'lezshows_airdates', true );
		if ( ! is_array( $airdates ) || ! isset( $airdates['start'] ) || ! isset( $airdates['finish'] ) ) {
			return 'no';
		}

		$start  = (int) $airdates['start'];
		$finish = (int) $airdates['finish'];

		return ( $start <= $year && $finish >= $year ) ? 'yes' : 'no';
	}

	/**
	 * Fix the on air status of a show
	 *
	 * @param int $show_id The ID of the show to fix
	 * @return bool True if the on air status was fixed, false otherwise
	 */
	public function fix_on_air_status( $show_id ): bool {
		$airdates = get_post_meta( $show_id, 'lezshows_airdates', true );
		if ( ! is_array( $airdates ) || ! isset( $airdates['start'] ) || ! isset( $airdates['finish'] ) ) {
			// No airdates, can't fix, assume not on air.
			update_post_meta( $show_id, 'lezshows_on_air', 'no' );
			return false;
		}

		$start  = (int) $airdates['start'];
		$finish = (int) $airdates['finish'];
		$year   = gmdate( 'Y' );

		// If the start date is before the current year and the finish date
		// is after the current year, or the finish date is current, then the
		// show is on air.
		if ( $start <= $year && ( $finish >= $year || 'current' === strtolower( $finish ) ) ) {
			update_post_meta( $show_id, 'lezshows_on_air', 'yes' );
		} else {
			update_post_meta( $show_id, 'lezshows_on_air', 'no' );
		}

		return true;
	}
}
