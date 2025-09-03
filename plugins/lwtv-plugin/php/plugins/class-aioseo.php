<?php

/**
 * AIOSEO Plugin
 *
 * Adds extra replacements for AIOSEO
 * Enables sitemap caching
 *
 * @package LezWatch.TV Plugin
 *
 */

namespace LWTV\Plugins;

class AIOSEO {

	public function __construct() {
		if ( ! function_exists( 'aioseo' ) ) {
			return;
		}
	}
}
