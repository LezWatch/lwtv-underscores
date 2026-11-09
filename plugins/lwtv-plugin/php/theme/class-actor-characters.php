<?php

namespace LWTV\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Relationships\Character_Links;

/**
 * Generate character data for actors.
 */
class Actor_Characters {
	/**
	 * Generate Actor Data
	 *
	 * @access public
	 *
	 * @param  string $actor_id
	 * @param  string $format
	 *
	 * @return mixed
	 */
	public function make( $actor_id, $format ) {

		// Early Bail
		$valid_data = array( 'all', 'dead', 'count' );
		if ( ! in_array( $format, $valid_data, true ) ) {
			return;
		}

		$array  = array( $actor_id, $format );
		$output = self::$format( ...$array );

		return $output;
	}

	/**
	 * Generate list of characters
	 *
	 * @param string  $actor  ID Actor ID
	 * @param string  $format Type of Output
	 *
	 * @return int[] Character IDs (any post status; callers filter).
	 */
	public function generate_list( $actor_id ) {
		return ( new Character_Links() )->characters_for_actor( (int) $actor_id );
	}

	/**
	 * Generate count of characters
	 *
	 * @param string  $actor  ID Actor ID
	 * @param string  $format Type of Output
	 *
	 * @return array  number of characters for that actor.
	 */
	public function count( $actor_id, $format = 'count' ) {
		$format = $format;

		$characters = $this->generate_list( $actor_id );

		if ( ! is_array( $characters ) ) {
			return 0;
		}

		$published = array_filter(
			array_map( 'intval', $characters ),
			static function ( $id ) {
				return $id > 0 && 'publish' === get_post_status( $id );
			}
		);

		return count( $published );
	}

	/**
	 * Generate list of characters
	 *
	 * @param string  $actor  ID Actor ID
	 * @param string  $format Type of Output
	 *
	 * @return array  All the characters by ID.
	 */
	public function all( $actor_id, $format ) {
		$format = $format;

		$characters = $this->generate_list( $actor_id );
		$build_data = $this->build_character_info( $characters, $actor_id );

		return $build_data;
	}

	/**
	 * Build minimal character info
	 *
	 * Thanks to the L Word having over 60 characters (I don't know
	 * why I'm surprised by this...) having the full content loop was
	 * causing server overload. This way we build the smaller loop.
	 *
	 * @param array $character_array
	 * @param int   $actor_id
	 *
	 * @return array
	 */
	public function build_character_info( array $character_array, int $actor_id ) {
		$characters = array();
		$dead       = array();
		// Rebuild the character array in format:
		foreach ( $character_array as $char_id ) {
			$actors_array = get_field( 'lezchars_actor', $char_id ) ?: array();

			if ( 'publish' === get_post_status( $char_id ) && isset( $actors_array ) && ! empty( $actors_array ) ) {
				foreach ( $actors_array as $char_actor ) {
					if ( (int) $char_actor === (int) $actor_id ) {
						$characters[ $char_id ] = array(
							'id'    => $char_id,
							'title' => get_the_title( $char_id ),
							'url'   => get_the_permalink( $char_id ),
							'shows' => get_field( 'lezchars_show_group', $char_id ),
						);

						if ( has_term( 'dead', 'lez_cliches', $char_id ) ) {
							$dead[ $char_id ] = array(
								'id'    => $char_id,
								'title' => get_the_title( $char_id ),
								'url'   => get_the_permalink( $char_id ),
							);
						}
					}
				}
			}
		}

		update_post_meta( $actor_id, 'lezactors_char_count', count( $characters ) );
		update_post_meta( $actor_id, 'lezactors_char_list', $characters );
		update_post_meta( $actor_id, 'lezactors_dead_count', count( $dead ) );
		update_post_meta( $actor_id, 'lezactors_dead_list', $dead );

		return $characters;
	}

	/**
	 * Generate list of dead characters
	 *
	 * @param string  $actor  ID Actor ID
	 * @param string  $format Type of Output
	 *
	 * @return array  All the characters by ID.
	 */
	public function dead( $actor_id, $format ) {
		$format = $format;
		$dead   = array();

		// Get array of characters (by ID)
		$character_array = $this->generate_list( $actor_id );

		if ( is_array( $character_array ) ) {
			foreach ( $character_array as $char_id ) {
				$actors = get_field( 'lezchars_actor', $char_id ) ?: array();
				if ( ! empty( $actors ) ) {
					foreach ( $actors as $actor ) {
						// We have to check because due to so many characters, we have some actor mis-matches.
						if ( ( (int) $actor === (int) $actor_id ) && 'publish' === get_post_status( $char_id ) && has_term( 'dead', 'lez_cliches', $char_id ) ) {
							$dead[ $char_id ] = array(
								'id'    => $char_id,
								'title' => get_the_title( $char_id ),
								'url'   => get_the_permalink( $char_id ),
							);
						}
					}
				}
			}
		}

		update_post_meta( $actor_id, 'lezactors_dead_count', count( $dead ) );

		return $dead;
	}
}
