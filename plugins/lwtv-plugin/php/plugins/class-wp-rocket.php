<?php
/**
 * WP Rocket Helpers
 *
 * exclude_src - https://github.com/wp-media/wp-rocket-helpers/tree/master/ImageDimensions/exclude-src-from-image-dimensions/
 */

namespace LWTV\Plugins;

class WP_Rocket {

	public function __construct() {
		add_filter( 'rocket_specify_dimension_images', array( $this, 'exclude_src' ) );
	}

	/**
	 * Exclude specified image sources from Image Dimensions.
	 *
	 * https://github.com/wp-media/wp-rocket-helpers/tree/master/ImageDimensions/exclude-src-from-image-dimensions/
	 *
	 * @author Ahmed Saed (WP Rocket Support Team)
	 * Copyright SAS WP MEDIA 2018
	 * License:     GNU General Public License v2 or later
	 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
	 *
	 * @param  array  $images Image tags found on the current page.
	 * @return array  Image tags after removing the exclusions.
	 */
	public function exclude_src( array $images ) {

		// Exclude SVGs from Image Dimensions
		$excluded_src = array( '.svg' );

		$excluded_src = array_map(
			function ( $src ) {
				return preg_quote( $src, '#' );
			},
			$excluded_src
		);

		$filtered_images = array_filter(
			$images,
			function ( $img ) use ( $excluded_src ) {
				return ! preg_match( '#' . implode( '|', $excluded_src ) . '#', $img );
			}
		);

		return $filtered_images;
	}
}
