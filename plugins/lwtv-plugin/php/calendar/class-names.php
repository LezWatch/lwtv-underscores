<?php
/**
 * Name: Calendar Names
 * Description: Sometimes we have weird names for the calendar.
 */

namespace LWTV\Calendar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\_Helpers\{ Calendar_Object_Pool, Calendar_Meta_Batcher };

class Names {

	/**
	 * Check Show Name
	 *
	 * Since TV Maze sometimes uses different names than we do, we have to make
	 * a related array that can handle two names.
	 *
	 * Names can be customized in the admin area using the TVMaze CPT.
	 *
	 * @param  string $showname Display Name of the show
	 * @param  string $source   lwtv or tvmaze
	 * @return string           The display name
	 */
	public function make( $name, $source, $output ) {

		// Set Defaults:
		$check_name = array(
			'id'   => 0,
			'name' => $name,
		);

		// Check TV Maze
		$check_name = $this->tvmaze( $name );

		// If the ID is 0, try looking for a local show that matches.
		if ( 0 === $check_name['id'] ) {
			$check_name = $this->local( $name );
		}

		// Output depends on source calling.
		switch ( $source ) {
			case 'lwtv':
				// Return only the name
				return ( 'name' === $output ) ? $check_name['name'] : $check_name['id'];
			case 'tvmaze':
				if ( 0 === $check_name['id'] ) {
					return ( 'name' === $output ) ? $check_name['name'] : $check_name['id'];
				} else {
					return ( 'name' === $output ) ? '<a href="' . get_permalink( $check_name['id'] ) . '">' . $check_name['name'] . '</a>' : $check_name['id'];
				}
		}
	}

	/**
	 * Check TV Maze for a stored name.
	 *
	 * @param  string $name
	 * @return array
	 */
	private function tvmaze( string $name ): array {
		// Set base name and ID.
		$show_name = $name;
		$show_id   = 0;

		$sanitized_slug = sanitize_title( $name );
		$tvmaze_obj     = get_page_by_path( $sanitized_slug, OBJECT, 'post_type_tvmaze' );

		// If there is a TV Maze entry already, we use it.
		if ( isset( $tvmaze_obj->ID ) && 0 !== $tvmaze_obj->ID && 'publish' === get_post_status( $tvmaze_obj->ID ) ) {
			$post_id = $tvmaze_obj->ID;
			$show    = get_post_meta( $post_id, 'leztvmaze_our_show', true );

			if ( isset( $show[0] ) && 0 !== $show[0] ) {
				$show_id   = $show[0];
				$show_name = get_the_title( $show_id );
			}
		}

		return array(
			'id'   => $show_id,
			'name' => $show_name,
		);
	}

	/**
	 * Check local shows for the matching name.
	 *
	 * @param  string $name
	 * @return array
	 */
	private function local( string $name ): array {
		$show_name = $name;
		$show_id   = 0;

		// Find the show based on the LezWatch name
		$show_page_obj = get_page_by_path( sanitize_title( $name ), OBJECT, 'post_type_shows' );

		// If there is a local show with a full match, we can use it.
		if ( isset( $show_page_obj->ID ) && 0 !== $show_page_obj->ID && 'publish' === get_post_status( $show_page_obj->ID ) ) {
			$show_id   = $show_page_obj->ID;
			$show_name = get_the_title( $show_id );
		}

		return array(
			'id'   => $show_id,
			'name' => $show_name,
		);
	}
}
