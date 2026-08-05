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
require_once __DIR__ . '/../plugins/lwtv-plugin/php/this-year/build/class-characters-on-air.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/this-year/build/class-dead-characters.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/this-year/build/class-shared-builder.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/this-year/build/class-shows-block.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/statistics/build/class-overview-factsheet.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/statistics/build/class-intersection-pairs.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/statistics/build/class-score-distribution.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/statistics/build/class-series-trend.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/statistics/build/class-star-podium.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/statistics/build/class-trigger-levels.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/this-year/format/class-new-shows-formatter.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/this-year/format/class-canceled-shows-formatter.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/this-year/format/class-dead-characters-formatter.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/_components/interface-component.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/_components/interface-templater.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/_components/class-transients.php';
