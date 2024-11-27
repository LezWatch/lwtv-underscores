<?php
/**
 * Calculate grades for LWTV
 */

namespace LWTV\Grading;

use LWTV\_Components\CPTs;
use LWTV\_Components\Grading as Grading_Component;

class TMDB {

	/**
	 * Get All TMDB Data
	 *
	 * @param  int   $show_id
	 * @return array
	 */
	public function get_all_data( int $show_id ): array {
		return array(
			'image' => LWTV_PLUGIN_URL . '/assets/images/scores/tmdb.png',
			'name'  => 'The Movie Database',
			'score' => $this->get_score( $show_id ),
			'color' => ( new Grading_Component() )->color( $this->get_score( $show_id ) ),
			'bg'    => '#0d253f',
			'url'   => $this->get_url( $show_id ),
		);
	}

	/**
	 * Get TMDB Score
	 *
	 * @param  int   $show_id
	 * @return float
	 */
	public function get_score( int $show_id ): float {
		$external = get_post_meta( $show_id, 'lezshows_3rd_scores', true );
		return ( isset( $external['tmdb']['score'] ) ) ? round( (int) $external['tmdb']['score'] ) : '0.00';
	}

	/**
	 * Update TMDB URL
	 *
	 * @param  int    $show_id
	 * @return string
	 */
	public function get_url( int $show_id ): string {
		$external = get_post_meta( $show_id, 'lezshows_3rd_scores', true );
		return ( isset( $external['tmdb']['url'] ) ) ? $external['tmdb']['url'] : 'https://themoviedb.org';
	}

	/**
	 * Update TMDB Scores
	 *
	 * @param  int  $show_id
	 * @return array
	 */
	public function update_scores( int $show_id ): array {
		$score = 'TBD';
		$url   = $this->get_url( $show_id );

		// Only call their service once a day.
		$transient = lwtv_plugin()->get_transient( 'lwtv_3rd_scores_tmdb_' . $show_id );
		if ( false === $transient ) {
			$tmdb_data = ( new CPTs() )->get_tmdb_info( $show_id );

			if ( $tmdb_data ) {
				$score = ( isset( $tmdb_data['tv_results'][0]['vote_average'] ) ) ? round( $tmdb_data['tv_results'][0]['vote_average'] * 10 ) : 'TBD';
				$url   = ( isset( $tmdb_data['tv_results'][0]['id'] ) ) ? 'https://themoviedb.org/tv/' . $tmdb_data['tv_results'][0]['id'] : '';
			}

			// Set transient and don't re-check until tomorrow.
			set_transient( 'lwtv_3rd_scores_tmdb_' . $show_id, $score, 24 * HOUR_IN_SECONDS );
		} else {
			$score = $transient;
		}

		return array(
			'score' => $score,
			'url'   => $url,
		);
	}
}
