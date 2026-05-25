<?php
/**
 * Calculate grades for LWTV
 */

namespace LWTV\Grading;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\_Components\Grading as Grading_Component;
use LWTV\CPTs\Shows\Calculations as Shows_Calculations;

class LWTV {

	/**
	 * Get all the data for LWTV
	 *
	 * @param  int $show_id
	 * @return array
	 */
	public function get_all_data( int $show_id ): array {
		$score = $this->get_score( $show_id );

		return array(
			'image' => LWTV_PLUGIN_URL . '/assets/images/scores/lwtv.png',
			'name'  => 'LezWatchTV',
			'score' => $score,
			'color' => ( new Grading_Component() )->color( $score ),
			'bg'    => '#d1548e',
			'url'   => site_url( '/about/scoring-queer-shows/' ),
		);
	}

	/**
	 * Get the LWTV score
	 *
	 * @param  int   $show_id
	 * @return float
	 */
	public function get_score( int $show_id ): float {
		return ( get_post_meta( $show_id, 'lezshows_the_score', true ) && is_numeric( (int) get_post_meta( $show_id, 'lezshows_the_score', true ) ) ) ? round( min( (int) get_post_meta( $show_id, 'lezshows_the_score', true ), 100 ) ) : '0.00';
	}

	/**
	 * Update the LWTV scores
	 *
	 * Calls \LWTV\CPTs\Shows\Calculations::do_the_math()
	 *
	 * @param  int  $show_id
	 * @return void
	 */
	public function update_scores( int $show_id ): void {
		( new Shows_Calculations() )->do_the_math( $show_id );
	}
}
