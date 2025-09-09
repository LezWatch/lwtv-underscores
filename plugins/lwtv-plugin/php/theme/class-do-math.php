<?php

namespace LWTV\Theme;

use LWTV\CPTs\Actors\Calculations as Actors_Calculations;
use LWTV\CPTs\Shows\Calculations as Shows_Calculations;
use LWTV\CPTs\Characters\Calculations as Characters_Calculations;

class Do_Math {
	/**
	 * Do the Math for a specific show/char/actor
	 *
	 * @param string  $post_id  Post ID
	 *
	 * @return void
	 */
	public function make( $post_id ): void {
		$post_type = get_post_type( $post_id );

		switch ( $post_type ) {
			case 'post_type_shows':
				( new Shows_Calculations() )->do_the_math( $post_id );
				break;
			case 'post_type_characters':
				( new Characters_Calculations() )->do_the_math( $post_id );
				break;
			case 'post_type_actors':
				( new Actors_Calculations() )->do_the_math( $post_id );
				break;
			default:
				break;
		}
	}
}
