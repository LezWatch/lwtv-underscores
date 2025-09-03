<?php

/**
 * AIOSEO Plugin
 *
 * Custom code for AIOSEO
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
