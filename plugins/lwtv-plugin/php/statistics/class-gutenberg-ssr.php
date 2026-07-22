<?php
/**
 * Statistics For Gutenberg - server side rendering.
 *
 * In a perfect world this would be 100% gutenized. It's not.
*/

namespace LWTV\Statistics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Gutenberg_SSR {

	/*
	 * Display statistics
	 *
	 * Usage:
	 *  [statistics page=[main|death]]
	 *
	 * @since 1.0
	 */
	public function statistics( $atts ) {
		$attributes = shortcode_atts(
			array(
				'page' => 'main',
			),
			$atts
		);

		$valid_pages = array( 'main', 'actors', 'characters', 'death', 'formats', 'nations', 'shows', 'stations' );
		$the_page    = ( ! in_array( sanitize_text_field( $attributes['page'] ), $valid_pages, true ) ) ? 'main' : sanitize_text_field( $attributes['page'] );

		$output = self::get_include_contents( __DIR__ . '/templates/' . $the_page . '.php' );

		return '<div class="lwtv-stats">' . $output . '</div>';
	}

	public function get_include_contents( $filename ) {
		if ( is_file( $filename ) ) {
			ob_start();
			include $filename;
			return ob_get_clean();
		}
		return false;
	}
}
