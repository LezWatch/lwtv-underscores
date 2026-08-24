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

/*
 * Minimal shims for the handful of WordPress functions that are themselves pure.
 *
 * These are not a WordPress bootstrap and must not become one. The bar for
 * adding to this list is that the function is deterministic, has no side
 * effects, and touches no globals, options, or database -- in other words, that
 * shimming it does not let untestable code pretend to be testable. Anything that
 * reads state belongs behind a seam and gets verified against the running site
 * instead.
 */
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		return $text;
	}
}

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
require_once __DIR__ . '/../plugins/lwtv-plugin/php/cpts/shows/class-host-name.php';
require_once __DIR__ . '/../plugins/lwtv-plugin/php/cpts/shows/class-watch-url-health.php';
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
