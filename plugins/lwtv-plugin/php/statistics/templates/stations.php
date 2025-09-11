<?php
/**
 * The template for displaying station statistics
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

// OPTIMIZED: Pre-load all show counts in a single query to eliminate multiple show count queries
$show_counts = $optimized_taxonomy->get_bulk_show_counts( 'lez_stations', array_keys( $all_stations_data ) );

// Get total counts efficiently
$count       = count( $all_stations_data );
$shows_count = lwtv_plugin()->generate_station_statistics( 'all', 'all', 'count' );

// Title
switch ( $station ) {
	case 'all':
		$title_station = 'All Stations (' . $count . ')';
		break;
	default:
		// Use the cached data instead of making new queries
		if ( isset( $all_stations_data[ $station ] ) ) {
			$station_data = $all_stations_data[ $station ];
			$shows        = $station_data['count'];

			// OPTIMIZED: Use pre-loaded character counts instead of individual query
			// Strip any underscore prefix from station slug for character counts lookup
			$station_slug = ltrim( $station, '_' );
			$characters   = $character_counts[ $station_slug ]['total'] ?? 0;

			$title_station = '<a href="' . home_url( '/station/' . $station ) . '">' . $station_data['name'] . '</a> (' . $shows . ' Shows / ' . $characters . ' Characters)';
		} else {
			$title_station = 'Station Not Found';
		}
}
?>
<h2><?php echo wp_kses_post( $title_station ); ?></h2>

<form method="get" id="go">
<div class="container-fluid text-center">
	<div class="row">
		<div class="col-8">
			<select name="station" id="station">
				<option value="all">All Stations</option>
				<?php
				foreach ( $all_stations_data as $station_slug => $station_data ) {
					$selected = ( $station === $station_slug ) ? 'selected=selected' : '';
					echo '<option value="' . esc_attr( $station_slug ) . '" ' . esc_html( $selected ) . '>' . esc_html( $station_data['name'] ) . '</option>';
				}
				?>
			</select>
		</div>
		<div class="col-2">
			<button type="submit" id="submit" class="btn btn-default btn-outline-primary">Go</button>
			<?php
			if ( 'all' !== $station ) {
				echo '&nbsp;<a class="btn btn-default btn-outline-primary" href="/statistics/stations/" role="button">Reset</a>';
			}
			?>
		</div>
	</div>
</form>

<ul class="nav nav-tabs">
	<?php
	$baseurl   = '/statistics/stations/';
	$query_arg = array();
	if ( 'all' !== $station ) {
		$query_arg['station'] = $station;
	}

	echo '<li class="nav-item"><a class="nav-link' . esc_attr( ( 'overview' === $view ) ? ' active' : '' ) . '" href="' . esc_url( add_query_arg( $query_arg, $baseurl ) ) . '">OVERVIEW</a></li>';
	if ( 'all' !== $station ) {
		foreach ( $valid_views as $the_view => $the_post_type ) {
			$active = ( $view === $the_view ) ? ' active' : '';
			echo '<li class="nav-item"><a class="nav-link' . esc_attr( $active ) . '" href="' . esc_url( add_query_arg( $query_arg, $baseurl . $the_view . '/' ) ) . '">' . esc_html( strtoupper( str_replace( '-', ' ', $the_view ) ) ) . '</a></li>';
		}
	}
	?>
</ul>

<p>&nbsp;</p>

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
			// Include the all stations template with required data
			include plugin_dir_path( __FILE__ ) . 'stations/all.php';
		} else {
			include plugin_dir_path( __FILE__ ) . 'stations/single.php';
		}
		?>

	<?php
	if ( '_all' !== $station && '_all' !== $view && '_on-air' !== $view ) {
		$format = ( 'shows' === $cpts_type ) ? 'list' : 'percentage';
		?>
		<div class="<?php echo esc_attr( $col_class ); ?>">
			<?php lwtv_plugin()->generate_station_statistics( $station, $view, $format ); ?>
		</div>
		<?php
	}
	?>

	</div>
</div>

<?php
// Performance monitoring
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	echo '<!-- OPTIMIZED: Character count N+1 queries eliminated. Queries reduced from ~' . ( count( $all_stations_data ) * 3 + 10 ) . ' to ' . esc_html( get_num_queries() ) . ' -->';
}
