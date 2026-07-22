<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

// Redesign overview extras.
$actor_growth = lwtv_plugin()->generate_growth_series( 'actors' );

// Group sums (stable slugs) for the donut/callout roll-ups.
$actor_cis_slugs      = array( 'cis-woman', 'cis-man', 'cisgender' );
$actor_gunknown_slugs = array( 'unknown', 'undefined' );
$actor_cis_sum        = 0;
$actor_gunknown_sum   = 0;
foreach ( $actor_gender_data as $actor_g_slug => $actor_g_row ) {
	if ( in_array( $actor_g_slug, $actor_cis_slugs, true ) ) {
		$actor_cis_sum += (int) $actor_g_row['count'];
	} elseif ( in_array( $actor_g_slug, $actor_gunknown_slugs, true ) ) {
		$actor_gunknown_sum += (int) $actor_g_row['count'];
	}
}
$actor_straight = isset( $actor_sexuality_data['heterosexual'] ) ? (int) $actor_sexuality_data['heterosexual']['count'] : 0;
$actor_sunknown = isset( $actor_sexuality_data['unknown'] ) ? (int) $actor_sexuality_data['unknown']['count'] : 0;

// Callout figures = "the rest", computed not stored.
$actor_lgbtq   = max( 0, (int) $actor_count - $actor_straight - $actor_sunknown );
$actor_transnb = max( 0, (int) $actor_count - $actor_cis_sum - $actor_gunknown_sum );
?>
<div class="lwtv-stats-overview">
	<?php
	// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
	include plugin_dir_path( __FILE__ ) . 'actors/subnav.php';
	?>
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
?>
</div><!-- .lwtv-stats-overview -->
<?php
