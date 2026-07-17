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

// Year series (overview + years) — sparse [ ['death_year','death_count'], … ] ascending.
$dead_years_series = null;
if ( in_array( $view, array( 'overview', 'years' ), true ) ) {
	$dead_years_average = lwtv_plugin()->generate_dead_statistics( 'characters', 'years', 'average' );
	$dead_years_series  = lwtv_plugin()->generate_dead_statistics( 'characters', 'years', 'array' );
}

// Full record (list) — date-keyed groups, newest first.
$dead_records = null;
if ( 'list' === $view ) {
	$deadchars_with_stats = lwtv_plugin()->generate_dead_statistics( 'characters', 'all', 'time' );
	$dead_records         = lwtv_plugin()->generate_dead_statistics( 'characters', 'all', 'array' );
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

?>
<div class="lwtv-stats-overview">
	<?php
	$baseurl      = '/statistics/death/';
	$death_subnav = array_merge( array( 'overview' => 1 ), array_fill_keys( $valid_views, 1 ) );
	echo '<nav class="lwtv-stats-subnav" aria-label="' . esc_attr__( 'Death statistics views', 'lwtv' ) . '">';
	foreach ( array_keys( $death_subnav ) as $death_v ) {
		$death_is  = ( $view === $death_v );
		$death_url = ( 'overview' === $death_v ) ? $baseurl : $baseurl . $death_v . '/';
		printf(
			'<a class="lwtv-stats-subnav-item%1$s" href="%2$s"%3$s>%4$s</a>',
			$death_is ? ' is-active' : '',
			esc_url( $death_url ),
			$death_is ? ' aria-current="page"' : '',
			esc_html( ucwords( str_replace( '-', ' ', $death_v ) ) )
		);
	}
	echo '</nav>';

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
	?>
</div><!-- .lwtv-stats-overview -->
<?php
