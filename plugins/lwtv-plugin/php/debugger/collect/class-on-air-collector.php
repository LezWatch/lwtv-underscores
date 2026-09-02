<?php
/**
 * Fetches what the on-air rules need.
 *
 * @package LWTV
 */

namespace LWTV\Debugger\Collect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Shows\Airdates;
use LWTV\Debugger\Build\On_Air_Rules;

class On_Air_Collector {

	/**
	 * How many shows to gather per pass.
	 */
	const BATCH = 200;

	/**
	 * Collect one batch of shows.
	 *
	 * @param  array<int> $show_ids Show post IDs.
	 * @return array<int, array<string, mixed>>
	 */
	public function collect( array $show_ids ): array {
		$show_ids = array_values( array_unique( array_map( 'intval', $show_ids ) ) );

		if ( empty( $show_ids ) ) {
			return array();
		}

		update_postmeta_cache( $show_ids );

		$year      = (int) gmdate( 'Y' );
		$collected = array();

		foreach ( $show_ids as $show_id ) {
			$collected[] = array(
				'post_id'  => $show_id,
				'on_air'   => (string) get_post_meta( $show_id, On_Air_Rules::META_ON_AIR, true ),
				'airdates' => Airdates::get( $show_id ),
				'year'     => $year,
			);
		}

		return $collected;
	}
}
