<?php
/**
 * Statistics For Gutenberg - server side rendering.
 *
 * In a perfect world this would be 100% gutenized. It's not.
*/

namespace LWTV\Statistics;

use LWTV\CPTs\Actors as CPT_Actors;
use LWTV\CPTs\Characters as CPT_Characters;
use LWTV\CPTs\Shows as CPT_Shows;

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

	/*
	 * Display statistics for actors/characters etc.
	 *
	 * Usage:
	 *  [ministats posttype=[actor|show|character]]
	 *
	 * @since 1.0
	 */
	public function mini_stats( $atts ) {
		$attributes = shortcode_atts(
			array(
				'posttype' => 'none',
			),
			$atts
		);

		$valid_postypes = array( CPT_Actors::SLUG, CPT_Characters::SLUG, CPT_Shows::SLUG );
		$this_posttype  = ( ! in_array( sanitize_text_field( $attributes['posttype'] ), $valid_postypes, true ) ) ? 'none' : sanitize_text_field( $attributes['posttype'] );

		$output = self::get_include_contents( __DIR__ . '/templates/' . $this_posttype . '.php' );
		return '<div class="lwtv-stats ' . $this_posttype . ' ">' . $output . '</div>';
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
