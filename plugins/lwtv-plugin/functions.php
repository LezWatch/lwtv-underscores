<?php
/**
 * Plugin Name: Core LezWatch.TV Plugin
 * Description: All the base code for LezWatch.TV - If this isn't active, the site dies. An ugly death.
 * Version: 6.1.0
 *
 * @package LWTV
 */

/**
 * Copyright 2014-24 LezWatch.TV (webmaster@lezwatchtv.com)
 *
 * This file is part of the core LWTV plugin, a plugin for WordPress.
 *
 * Core LezWatch.TV Plugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3.
 *
 * Core LezWatch.TV Plugin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this installation as LICENSE.
 *
 * If not, see <https://www.gnu.org/licenses/gpl-3.0.html>.
 */

// If the old plugin is active, deactivate it.

use LWTV\_Helpers\Autoload;
use LWTV\Plugin;

require_once __DIR__ . '/php/_helpers/class-autoload.php';

// Plugin Version.
define( 'LWTV_PLUGIN_VERSION', '6.1.0' );

/**
 * Autoloader serves for `LWTV` namespace and autoload all files under the php directory.
 *
 * To add a new component, see the file /php/class-plugin.php
 */
$autoload = new Autoload();
$autoload->add( 'LWTV', sprintf( '%s/php', __DIR__ ) );

/**
 * Retrieves an instance of the Plugin.
 *
 * @return Plugin
 */
function lwtv_plugin() {
	static $plugin = null;

	if ( ! $plugin ) {
		$plugin = new Plugin();
		$plugin->init();
	}

	return $plugin;
}

lwtv_plugin();
