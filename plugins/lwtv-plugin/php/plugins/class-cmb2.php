<?php
/*
 * Library: CMB2 Add Ons
 * Description: Addons for CMB2 that make life worth living
 * Version: 2.0.3
 */

namespace LWTV\Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMB2 {
	/**
	 * Constructor
	 *
	 * As CMB2 is being phased out, this exists only to make sure it's DISabled.
	 */
	public function __construct() {
		if ( class_exists( 'ACF' ) && is_plugin_active( 'cmb2/init.php' ) ) {
			deactivate_plugins( 'cmb2/init.php' );
		}
	}
}
