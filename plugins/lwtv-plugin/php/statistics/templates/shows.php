<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The template for displaying the shows stats page - Optimized Version
 *
 * @package LezWatch.TV
 */

use LWTV\Statistics\Build\Taxonomy_Optimized as Build_Taxonomy_Optimized;

$valid_views = array( 'formats', 'tropes', 'genres', 'intersectionality', 'stars', 'scores', 'triggers', 'on-air', 'worth-it', 'we-love-it' );
$sent_view   = get_query_var( 'view', 'overview' );
$view        = ( ! in_array( $sent_view, $valid_views, true ) ) ? 'overview' : $sent_view;

$baseurl = '/statistics/shows/';

// OPTIMIZED: Cache shows count to avoid redundant calls
$shows_count = lwtv_plugin()->generate_total_counts( 'shows' );

// OPTIMIZED: Only the overview view consumes these aggregated datasets; the
// subpages build their own data, so skip this work (and its queries) for them.
if ( 'overview' === $view ) {
	$optimized_taxonomy = new Build_Taxonomy_Optimized();
	$tropes_data        = $optimized_taxonomy->make_comprehensive( 'post_type_shows', 'lez_tropes', false );
	$genres_data        = $optimized_taxonomy->make_comprehensive( 'post_type_shows', 'lez_genres', false );

	// Sort by count descending for top 10
	uasort(
		$tropes_data,
		function ( $a, $b ) {
			return $b['count'] <=> $a['count'];
		}
	);
	uasort(
		$genres_data,
		function ( $a, $b ) {
			return $b['count'] <=> $a['count'];
		}
	);

	$top_tropes = array_slice( $tropes_data, 0, 10, true );
	$top_genres = array_slice( $genres_data, 0, 10, true );

	// Get total counts efficiently
	$count_tropes = count( $tropes_data );
	$count_genres = count( $genres_data );

	// Trope Gap pull-stats: counts for the buried vs. happy-ending tropes.
	$trope_buried = isset( $tropes_data['dead-queers'] ) ? (int) $tropes_data['dead-queers']['count'] : 0;
	$trope_happy  = isset( $tropes_data['happy-ending'] ) ? (int) $tropes_data['happy-ending']['count'] : 0;
}
?>
<div class="lwtv-stats-overview">
	<?php
	// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
	include plugin_dir_path( __FILE__ ) . 'shows/subnav.php';
	?>
<?php

switch ( $view ) {
	case 'overview':
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __FILE__ ) . 'shows/overview.php';
		break;
	case 'tropes':
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __FILE__ ) . 'shows/tropes.php';
		break;
	case 'genres':
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __FILE__ ) . 'shows/genres.php';
		break;
	case 'intersectionality':
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __FILE__ ) . 'shows/intersectionality.php';
		break;
	case 'formats':
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __FILE__ ) . 'shows/formats.php';
		break;
	case 'triggers':
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __FILE__ ) . 'shows/triggers.php';
		break;
	case 'we-love-it':
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __FILE__ ) . 'shows/we-love-it.php';
		break;
	case 'worth-it':
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __FILE__ ) . 'shows/worth-it.php';
		break;
	case 'stars':
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __FILE__ ) . 'shows/stars.php';
		break;
	case 'scores':
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __FILE__ ) . 'shows/scores.php';
		break;
	case 'on-air':
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __FILE__ ) . 'shows/on-air.php';
		break;
}

// Performance monitoring
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	echo '<!-- OPTIMIZED: ' . esc_html( get_num_queries() ) . ' queries for view "' . esc_html( $view ) . '" -->';
}
?>
</div><!-- .lwtv-stats-overview -->
<?php
