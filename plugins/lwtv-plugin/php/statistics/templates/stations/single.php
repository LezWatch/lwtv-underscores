<?php
/**
 * The template for displaying a single station statistics - Optimized Version
 *
 * @package LezWatch.TV
 *
 * Required variables:
 * @var array $all_stations_data - Station data array
 * @var array $character_counts - Character counts array
 * @var int   $shows_count - Total shows count for this station
 * @var int   $all_shows_count - Total shows count for all shows
 * @var string $station - Station slug
 * @var string $view - View
 * @var string $format - Format
 */

// There is a specific Station!
$this_station  = $all_stations_data[ ltrim( $station, '_' ) ];
$format        = 'piechart';
$bar_direction = 'horizontal';
$station_slug  = ltrim( $station, '_' );
$onair         = $show_counts[ $station_slug ]['onair'] ?? 0;
$allshows      = $show_counts[ $station_slug ]['total'] ?? 0;
$showscore     = $show_counts[ $station_slug ]['score'] ?? 0;
$onairscore    = $show_counts[ $station_slug ]['onairscore'] ?? 0;

// Initialize custom_data array
$custom_data = array();

// If the view is all, we need to display the barchart.
if ( '_all' === $view ) {
	echo wp_kses_post( '<p>Currently, ' . $onair . ' out of a total of ' . $allshows . ' shows are on air.</p><p>The average score for all shows in this station is ' . $showscore );

	if ( 0 !== $onair ) {
		echo wp_kses_post( ', and ' . $onairscore . ' for shows currently on air' );
	}

	echo wp_kses_post( ' (out of a possible 100).</p>' );

	$format = 'barchart';
	// Pass the counts as custom data for the barchart
	$custom_data = array(
		'total'      => $allshows,
		'characters' => $character_counts[ $station_slug ]['total'] ?? 0,
		'dead'       => $character_counts[ $station_slug ]['dead'] ?? 0,
	);
} elseif ( '_on-air' === $view ) {
	$format = 'trendline';
	echo wp_kses_post( '<h4>Shows On-Air Per Year</h4>' );
}

// Pass custom data if it exists
if ( ! empty( $custom_data ) ) {
	?>
	<div class="col-sm-12">
		<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo lwtv_plugin()->generate_station_statistics( $station, $view, $format, $custom_data, $bar_direction );
		?>
	</div>
	<?php
} else {
	$col_class = '_on-air' === $view ? 'col-sm-12' : 'col-sm-6';
	?>
	<div class="<?php echo esc_attr( $col_class ); ?>">
		<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo lwtv_plugin()->generate_station_statistics( $station, $view, $format );
		?>
	</div>
	<?php
	// We don't show the percentage for on-air because it's already shown in the trendline
	if ( '_on-air' !== $view ) {
		?>
		<div class="col-sm-6">
			<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo lwtv_plugin()->generate_station_statistics( $station, $view, 'percentage' );
			?>
		</div>
		<?php
	}
}
