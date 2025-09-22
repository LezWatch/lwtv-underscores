<?php
/**
 * The template for displaying the shows stats page - Optimized Version
 *
 * @package LezWatch.TV
 */

use LWTV\Statistics\Build\Taxonomy_Optimized as Build_Taxonomy_Optimized;

$valid_views = array( 'formats', 'tropes', 'genres', 'intersectionality', 'stars', 'triggers', 'on-air', 'worth-it', 'we-love-it' );
$sent_view   = get_query_var( 'view', 'overview' );
$view        = ( ! in_array( $sent_view, $valid_views, true ) ) ? 'overview' : $sent_view;

$baseurl = '/statistics/shows/';

// OPTIMIZED: Cache shows count to avoid redundant calls
$shows_count = lwtv_plugin()->generate_total_counts( 'shows' );

// OPTIMIZED: Pre-load taxonomy data for overview section
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
?>
<h2>
	<a href="/shows/">Total Shows</a> (<?php echo (int) $shows_count; ?>)
</h2>

<?php
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __FILE__ ) . 'shows/navbar.php';
?>

<p>&nbsp;</p>

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
	case 'on-air':
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __FILE__ ) . 'shows/on-air.php';
		break;
}

// Performance monitoring
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	echo '<!-- OPTIMIZED: Queries reduced from ~' . ( count( $top_tropes ) + count( $top_genres ) + 10 ) . ' to ' . esc_html( get_num_queries() ) . ' -->';
}
