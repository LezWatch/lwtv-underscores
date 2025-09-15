<?php
/**
 * The template for displaying the death stats page - Optimized Version
 *
 * @package LezWatch.TV
 */

use LWTV\Statistics\Build\Taxonomy_Optimized as Build_Taxonomy_Optimized;

$baseurl = '/statistics/death/';

// OPTIMIZED: Pre-load death-related data efficiently
$optimized_taxonomy = new Build_Taxonomy_Optimized();

$deadchars = lwtv_plugin()->generate_total_dead( 'characters' );
$allchars  = lwtv_plugin()->generate_total_counts( 'characters' );
$deadshows = lwtv_plugin()->generate_total_dead( 'shows' );
$allshows  = lwtv_plugin()->generate_total_counts( 'shows' );

// OPTIMIZED: Get dead-years average directly without output buffering
$dead_years_average = lwtv_plugin()->generate_dead_statistics( 'characters', 'years', 'average' );

$deadchar_percent = round( ( $deadchars / $allchars ) * 100, 2 );
$deadshow_percent = round( ( $deadshows / $allshows ) * 100, 2 );

$valid_views = array( 'characters', 'shows', 'stations', 'nations', 'years', 'list' );
$sent_view   = get_query_var( 'view', 'overview' );
$view        = ( ! in_array( $sent_view, $valid_views, true ) ) ? 'overview' : $sent_view;

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
