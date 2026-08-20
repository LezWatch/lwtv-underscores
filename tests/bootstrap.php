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
require_once __DIR__ . '/../plugins/lwtv-plugin/php/statistics/build/class-format-decade-buckets.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/statistics/build/class-genre-decade-buckets.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/statistics/build/class-character-identity-decade-buckets.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/cpts/shows/class-trope-categories.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/cpts/shows/class-airdates.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/statistics/build/class-trope-category-coverage.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/statistics/build/class-term-count-distribution.php'; // to_cells() lives here too.
require_once __DIR__ . '/../plugins/lwtv-plugin/php/statistics/build/class-score-distribution.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/statistics/build/class-series-trend.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/statistics/build/class-star-podium.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/statistics/build/class-role-podium.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/statistics/build/class-trigger-levels.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/statistics/build/class-worth-it-grid.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/statistics/build/class-we-love-compare.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/this-year/format/class-new-shows-formatter.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/this-year/format/class-canceled-shows-formatter.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/this-year/format/class-dead-characters-formatter.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/_components/interface-component.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/_components/interface-templater.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/_components/class-transients.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/calendar/build/class-agenda.php';
