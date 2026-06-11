<?php
/*
 * Library: CMB2
 * Description: A force disable of CMB2 since we're moving to ACF and want to make sure there are no conflicts.
 */

namespace LWTV\Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMB2 {

	/**
	 * As CMB2 is being phased out, this exists only to make sure it's DISabled.
	 */
	public function __construct() {
		if ( class_exists( 'ACF' ) && is_plugin_active( 'cmb2/init.php' ) ) {
			deactivate_plugins( 'cmb2/init.php' );
		}
	}
}
