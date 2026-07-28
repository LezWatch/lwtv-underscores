<?php
/**
 * PHPUnit bootstrap for pure (non-WordPress) unit tests.
 *
 * These tests cover logic that has no WordPress runtime dependency. We define
 * ABSPATH so class files guarded by the standard `if ( ! defined( 'ABSPATH' ) )`
 * check load correctly, then require the classes under test directly — no WP
 * bootstrap is loaded or needed.
 *
 * @package lwtv-underscores
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/../plugins/lwtv-plugin/php/this-year/build/class-trends.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/this-year/build/class-deaths-strip.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/this-year/build/class-longest-running.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/this-year/build/class-breakdowns.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/this-year/build/class-standouts.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/this-year/build/class-character-facts.php';
