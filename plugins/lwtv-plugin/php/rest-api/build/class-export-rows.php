<?php
/**
 * Name: Export Rows
 * Description: Formatting for the /export/full/characters/ rows.
 *
 * Pure: field values in, strings out. The title lookup is passed in, so this
 * is unit-tested without WordPress.
 *
 * @package LWTV
 */

namespace LWTV\Rest_API\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Export_Rows {

	/**
	 * A character's shows as one string.
	 *
	 * Each row becomes "Title - type (year;year)", joined with "; ". The ACF
	 * repeater can come back as false or '' when empty, and a row can be
	 * incomplete, so anything without a show is skipped rather than fatal.
	 *
	 * @param mixed    $shows_full Value of the lezchars_show_group field.
	 * @param callable $title_for  Maps a show ID to its title.
	 *
	 * @return string
	 */
	public static function show_list( $shows_full, callable $title_for ): string {
		if ( ! is_array( $shows_full ) ) {
			return '';
		}

		$shows = array();
		foreach ( $shows_full as $show ) {
			if ( ! is_array( $show ) || empty( $show['show'] ) ) {
				continue;
			}

			// The relationship field can hold an array of one ID.
			$show_id = is_array( $show['show'] ) ? reset( $show['show'] ) : $show['show'];
			$appears = $show['appears'] ?? '';
			$appears = is_array( $appears ) ? implode( ';', $appears ) : (string) $appears;
			$type    = (string) ( $show['type'] ?? '' );

			$shows[] = $title_for( $show_id ) . ' - ' . $type . ' (' . $appears . ')';
		}

		return implode( '; ', $shows );
	}
}
