<?php

namespace LWTV\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Relationships\Character_Links;

class Character_Relationships {
	/**
	 * Generate Relationship data.
	 *
	 * Characters this character references (its legacy
	 * lezchars_relationship_chart) plus characters that reference it, from
	 * Character_Links::characters_referencing().
	 *
	 * @access public
	 *
	 * @param  int|string $char_id   - Character ID
	 * @return array      $all_chars - Array of Character IDs
	 */
	public function make( $char_id ) {
		$all_chars = ( new Character_Links() )->characters_referencing( (int) $char_id );

		// Only published character posts (exclude draft, pending, private, etc.).
		$all_chars = array_values(
			array_filter(
				$all_chars,
				static function ( $id ) {
					return $id > 0 && 'publish' === get_post_status( $id );
				}
			)
		);

		// Output.
		return $all_chars;
	}
}
