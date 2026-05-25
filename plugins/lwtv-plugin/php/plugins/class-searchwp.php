<?php
/*
 * SearchWP
 *
 * @package lwtv-plugin
 */
namespace LWTV\Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class SearchWP {
	/**
	 * Constructor
	 */
	public function __construct() {
		if ( ! class_exists( 'SearchWP' ) ) {
			return;
		}

		// If we're on a dev site, we want to index the alternate content.
		if ( defined( 'LWTV_DEV' ) && LWTV_DEV ) {
			add_filter( 'searchwp\indexer\alternate', '__return_true' );
		}
	}
}
