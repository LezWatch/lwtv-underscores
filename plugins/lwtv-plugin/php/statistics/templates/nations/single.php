<?php
/**
 * The template for displaying a single nation statistics - Optimized Version
 *
 * @package LezWatch.TV
 *
 * Required variables:
 * @var array $all_nations_data - Nation data array
 * @var array $character_counts - Character counts array
 * @var int   $shows_count - Total shows count for this nation
 * @var int   $all_shows_count - Total shows count for all shows
 * @var string $nation - Nation slug
 * @var string $view - View
 * @var string $format - Format
 */

// There is a specific Nation!
$this_nation   = $all_nations_data[ ltrim( $nation, '_' ) ];
$format        = 'piechart';
$bar_direction = 'horizontal';
$nation_slug   = ltrim( $nation, '_' );
$onair         = $show_counts[ $nation_slug ]['onair'] ?? 0;
$allshows      = $show_counts[ $nation_slug ]['total'] ?? 0;
$showscore     = $show_counts[ $nation_slug ]['score'] ?? 0;
$onairscore    = $show_counts[ $nation_slug ]['onairscore'] ?? 0;

// Initialize custom_data array
$custom_data = array();

// If the view is all, we need to display the barchart.
if ( '_all' === $view ) {
	echo wp_kses_post( '<p>Currently, ' . $onair . ' out of a total of ' . $allshows . ' shows are on air.</p><p>The average score for all shows in this nation is ' . $showscore );

	if ( 0 !== $onair ) {
		echo wp_kses_post( ', and ' . $onairscore . ' for shows currently on air' );
	}

	echo wp_kses_post( ' (out of a possible 100).</p>' );

	$format = 'barchart';
	// Pass the counts as custom data for the barchart
	$custom_data = array(
		'total'      => $allshows,
		'characters' => $character_counts[ $nation_slug ]['total'] ?? 0,
		'dead'       => $character_counts[ $nation_slug ]['dead'] ?? 0,
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
			echo lwtv_plugin()->generate_nation_statistics( $nation, $view, $format, $custom_data, $bar_direction );
		?>
	</div>
	<?php
} else {
	$col_class = '_on-air' === $view ? 'col-sm-12' : 'col-sm-6';
	?>
	<div class="<?php echo esc_attr( $col_class ); ?>">
		<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo lwtv_plugin()->generate_nation_statistics( $nation, $view, $format );
		?>
	</div>
	<?php
	// We don't show the percentage for on-air because it's already shown in the trendline
	if ( '_on-air' !== $view ) {
		?>
		<div class="col-sm-6">
			<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo lwtv_plugin()->generate_nation_statistics( $nation, $view, 'percentage' );
			?>
		</div>
		<?php
	}
}
