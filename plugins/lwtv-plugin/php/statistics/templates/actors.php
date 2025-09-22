<?php
/**
 * The template for displaying the actor stats page - Optimized Version
 *
 * @package LezWatch.TV
 */

use LWTV\Statistics\Build\Taxonomy_Optimized as Build_Taxonomy_Optimized;
use LWTV\CPTs\Actors as CPT_Actors;

$baseurl = '/statistics/actors/';

$valid_views = array( 'gender', 'sexuality' );
$sent_view   = get_query_var( 'view', 'overview' );
$view        = ( ! in_array( $sent_view, $valid_views, true ) ) ? 'overview' : $sent_view;

// OPTIMIZED: Cache actor count to avoid redundant calls
$actor_count = lwtv_plugin()->generate_total_counts( 'actors' );

// OPTIMIZED: Pre-load taxonomy data for overview section
$optimized_taxonomy   = new Build_Taxonomy_Optimized();
$actor_gender_data    = $optimized_taxonomy->make_comprehensive( CPT_Actors::SLUG, 'lez_actor_gender', false );
$actor_sexuality_data = $optimized_taxonomy->make_comprehensive( CPT_Actors::SLUG, 'lez_actor_sexuality', false );

// Sort by count descending for top 10
uasort(
	$actor_gender_data,
	function ( $a, $b ) {
		return $b['count'] <=> $a['count'];
	}
);
uasort(
	$actor_sexuality_data,
	function ( $a, $b ) {
		return $b['count'] <=> $a['count'];
	}
);

$top_genders     = array_slice( $actor_gender_data, 0, 10, true );
$top_sexualities = array_slice( $actor_sexuality_data, 0, 10, true );

// Get total counts efficiently
$count_genders     = count( $actor_gender_data );
$count_sexualities = count( $actor_sexuality_data );
?>
<h2>
	<a href="/actors/">Total Actors</a> (<?php echo (int) $actor_count; ?>)
</h2>

<?php
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __FILE__ ) . 'actors/navbar.php';
?>

<p>&nbsp;</p>

<?php

switch ( $view ) {
	case 'overview':
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __FILE__ ) . 'actors/overview.php';
		break;
	case 'sexuality':
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __FILE__ ) . 'actors/sexuality.php';
		break;
	case 'gender':
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __FILE__ ) . 'actors/gender.php';
		break;
	case 'roles':
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __FILE__ ) . 'actors/roles.php';
		break;
}

// Performance monitoring
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	echo '<!-- OPTIMIZED: Queries reduced from ~' . ( count( $top_genders ) + count( $top_sexualities ) + 10 ) . ' to ' . esc_html( get_num_queries() ) . ' -->';
}
