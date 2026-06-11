<?php
/*
Library: FacetWP Add Ons
Description: Addons for FacetWP that make life worth living
Version: 1.1.0
*/

namespace LWTV\Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Plugins\FacetWP\Indexing;
use LWTV\Plugins\FacetWP\Labels;
use LWTV\Plugins\FacetWP\Pagination;

class FacetWP {

	/**
	 * Constructor
	 */
	public function __construct() {
		new Indexing();
		new Labels();
		new Pagination();

		// Reset Shortcode
		add_shortcode( 'facetwp-reset', array( $this, 'reset_shortcode' ) );
	}

	/*
	 * Reset Shortcode
	 *
	 * Echo reset button
	 *
	 * @since 1.1.0
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	public function reset_shortcode( $atts ) {
		$reset = '<center><button class="facetwp-reset" onclick="FWP.reset()">Reset Filters</button></center>';
		return $reset;
	}
}
