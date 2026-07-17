<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The template for displaying station statistics -- Optimized Version
 *
 * @package LezWatch.TV
 */

use LWTV\Statistics\Build\Taxonomy_Optimized as Build_Taxonomy_Optimized;

// Stations
$sent_station  = get_query_var( 'station', '' );
$valid_station = term_exists( $sent_station, 'lez_stations' );
$station       = ( '' === $sent_station || ! is_array( $valid_station ) ) ? 'all' : sanitize_title( $sent_station );

// Views
$valid_views = array(
	'sexuality' => 'characters',
	'gender'    => 'characters',
	'tropes'    => 'shows',
	// removed because there's not enough data yet.
	// 'intersections' => 'shows',
	'formats'   => 'shows',
	'on-air'    => 'shows',
);
$sent_view   = get_query_var( 'view', 'overview' );
$view        = ( ! array_key_exists( $sent_view, $valid_views ) ) ? 'overview' : $sent_view;

// OPTIMIZED: Get all station data in a single query instead of N+1 queries
$optimized_taxonomy = new Build_Taxonomy_Optimized();
$all_stations_data  = $optimized_taxonomy->make_comprehensive( 'post_type_shows', 'lez_stations', true );

// OPTIMIZED: Pre-load all character counts in a single query to eliminate N+1 pattern
$character_counts = $optimized_taxonomy->get_bulk_character_counts( 'lez_stations', array_keys( $all_stations_data ) );
$show_counts      = $optimized_taxonomy->get_bulk_show_counts( 'lez_stations', array_keys( $all_stations_data ) );

// Get total counts efficiently
$count           = count( $all_stations_data );
$shows_count     = lwtv_plugin()->generate_station_statistics( 'all', 'all', 'count' );
$all_shows_count = lwtv_plugin()->generate_total_counts( 'shows' );

?>
<div class="lwtv-stats-overview">
	<div class="lwtv-nations-picker">
		<form method="get" id="go" class="lwtv-nations-pickerform">
			<label for="station" class="lwtv-stats-eyebrow"><?php esc_html_e( 'Station', 'lwtv' ); ?></label>
			<select name="station" id="station" class="form-select lwtv-nations-select" onchange="this.form.submit()">
				<option value="all"><?php esc_html_e( 'All Stations', 'lwtv' ); ?></option>
				<?php
				foreach ( $all_stations_data as $lwtv_n_slug => $lwtv_n_data ) {
					printf(
						'<option value="%1$s"%2$s>%3$s</option>',
						esc_attr( $lwtv_n_slug ),
						selected( $station, $lwtv_n_slug, false ),
						esc_html( $lwtv_n_data['name'] )
					);
				}
				?>
			</select>
			<noscript><button type="submit" id="submit" class="btn btn-outline-primary btn-sm"><?php esc_html_e( 'Go', 'lwtv' ); ?></button></noscript>
			<?php if ( 'all' !== $station ) : ?>
				<a class="lwtv-nations-reset" href="/statistics/stations/"><?php esc_html_e( 'Reset to all stations', 'lwtv' ); ?></a>
			<?php endif; ?>
		</form>
	</div>

	<?php
	// Station sub-nav (single station only). The primary tab bar is in the page shell.
	if ( 'all' !== $station ) {
		$lwtv_sub_base  = '/statistics/stations/';
		$lwtv_sub_query = array( 'station' => $station );
		$lwtv_subnav    = array_merge( array( 'overview' => 'shows' ), $valid_views );
		echo '<nav class="lwtv-stats-subnav" aria-label="' . esc_attr__( 'Station statistics views', 'lwtv' ) . '">';
		foreach ( $lwtv_subnav as $lwtv_v => $lwtv_pt ) {
			$lwtv_is  = ( $view === $lwtv_v );
			$lwtv_url = ( 'overview' === $lwtv_v ) ? add_query_arg( $lwtv_sub_query, $lwtv_sub_base ) : add_query_arg( $lwtv_sub_query, $lwtv_sub_base . $lwtv_v . '/' );
			printf(
				'<a class="lwtv-stats-subnav-item%1$s" href="%2$s"%3$s>%4$s</a>',
				$lwtv_is ? ' is-active' : '',
				esc_url( $lwtv_url ),
				$lwtv_is ? ' aria-current="page"' : '',
				esc_html( ucwords( str_replace( '-', ' ', $lwtv_v ) ) )
			);
		}
		echo '</nav>';
	}
	?>
<?php
	$col_class = ( 'all' !== $station && 'overview' !== $view && 'on-air' !== $view ) ? 'col-sm-6' : 'col';
	$cpts_type = ( 'overview' === $view ) ? 'shows' : $valid_views[ $view ];
?>

<div class="container">
	<div class="row">
		<?php
		// Remember: station [substation] [view]
		$view    = ( 'overview' === $view && 'all' !== $station ) ? 'all' : $view;
		$view    = ( 'overview' === $view ) ? '_all' : '_' . $view;
		$station = ( 'overview' === $station ) ? '_all' : '_' . $station;

		if ( '_all' === $station ) {
			include plugin_dir_path( __FILE__ ) . 'stations/all.php';
		} else {
			include plugin_dir_path( __FILE__ ) . 'stations/single.php';
		}
		?>

	</div>
</div>
</div><!-- .lwtv-stats-overview -->
