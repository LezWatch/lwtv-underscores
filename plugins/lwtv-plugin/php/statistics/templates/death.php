<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The template for displaying the death stats page - Optimized Version
 *
 * @package LezWatch.TV
 */

$baseurl = '/statistics/death/';

$valid_views = array( 'characters', 'shows', 'stations', 'nations', 'years', 'list' );
$sent_view   = get_query_var( 'view', 'overview' );
$view        = ( ! in_array( $sent_view, $valid_views, true ) ) ? 'overview' : $sent_view;

// OPTIMIZED: Build only the datasets the requested view actually consumes.
$deadchars_with_stats = null;
$dead_years_average   = null;

// The full time-summary list is only used by the list view.
if ( 'list' === $view ) {
	$deadchars_with_stats = lwtv_plugin()->generate_dead_statistics( 'characters', 'all', 'time' );
}

// The dead-years average is shown on the overview and years views.
if ( in_array( $view, array( 'overview', 'years' ), true ) ) {
	$dead_years_average = lwtv_plugin()->generate_dead_statistics( 'characters', 'years', 'average' );
}

// Dead/total counts and their percentages are only shown on the overview.
if ( 'overview' === $view ) {
	$deadchars        = lwtv_plugin()->generate_total_dead( 'characters' );
	$allchars         = lwtv_plugin()->generate_total_counts( 'characters' );
	$deadshows        = lwtv_plugin()->generate_total_dead( 'shows' );
	$allshows         = lwtv_plugin()->generate_total_counts( 'shows' );
	$deadchar_percent = $allchars ? round( ( $deadchars / $allchars ) * 100, 2 ) : 0;
	$deadshow_percent = $allshows ? round( ( $deadshows / $allshows ) * 100, 2 ) : 0;
}

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __FILE__ ) . 'death/navbar.php';

switch ( $view ) {
	case 'overview':
		include plugin_dir_path( __FILE__ ) . 'death/overview.php';
		break;
	case 'characters':
		include plugin_dir_path( __FILE__ ) . 'death/characters.php';
		break;
	case 'shows':
		include plugin_dir_path( __FILE__ ) . 'death/shows.php';
		break;
	case 'stations':
		include plugin_dir_path( __FILE__ ) . 'death/stations.php';
		break;
	case 'nations':
		include plugin_dir_path( __FILE__ ) . 'death/nations.php';
		break;
	case 'years':
		include plugin_dir_path( __FILE__ ) . 'death/years.php';
		break;
	case 'list':
		include plugin_dir_path( __FILE__ ) . 'death/list.php';
		break;
}

// Performance monitoring
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	echo '<!-- OPTIMIZED: Death statistics optimized with efficient queries -->';
}
