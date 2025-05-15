<?php

namespace LWTV\Statistics\Build;

use LWTV\Queeries\Post_Type;

class Scores {

	/*
	 * Statistics Scores
	 *
	 * @return array
	 */
	public function make( $post_type ) {

		$transient = 'scores_' . $post_type;
		$array     = lwtv_plugin()->get_transient( $transient );

		if ( false === $array ) {

			$the_queery = ( new Post_Type() )->make( $post_type );
			$array      = array();

			if ( is_object( $the_queery ) && $the_queery->have_posts() ) {
				$scores_shows = wp_list_pluck( $the_queery->posts, 'ID' );
			}

			if ( is_array( $scores_shows ) ) {
				foreach ( $scores_shows as $show_id ) {
					$array[ $show_id ] = array(
						'id'    => $show_id,
						'count' => get_post_meta( $show_id, 'lezshows_the_score', true ),
						'url'   => get_the_permalink( $show_id ),
					);
				}
			}

			// save array as transient for a reason.
			if ( ! empty( $array ) ) {
				lwtv_plugin()->set_transient( $transient, $array, DAY_IN_SECONDS );
			}
		}

		return $array;
	}
}
