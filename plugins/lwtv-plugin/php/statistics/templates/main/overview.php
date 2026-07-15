<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Overview metric cards — redesigned.
 *
 * @package LezWatch.TV
 *
 * @var int   $stats_shows
 * @var int   $stats_characters
 * @var int   $stats_actors
 * @var int   $stats_dead
 * @var array $stats_series      Keyed shows|characters|actors|dead => growth series.
 * @var int   $stats_dead_ratio  "1 in N".
 */

if ( ! function_exists( 'lwtv_stats_sparkline_points' ) ) {
	/**
	 * Convert a cumulative series into SVG polyline points within a viewBox.
	 *
	 * @param array $series Cumulative series [ ['year'=>int,'count'=>int], … ].
	 * @param int   $w      viewBox width.
	 * @param int   $h      viewBox height.
	 * @return string Space-separated "x,y" pairs, or '' if fewer than 2 points.
	 */
	function lwtv_stats_sparkline_points( array $series, int $w = 120, int $h = 26 ): string {
		$counts = array_column( $series, 'count' );
		$n      = count( $counts );
		if ( $n < 2 ) {
			return '';
		}
		$max   = max( $counts );
		$min   = min( $counts );
		$range = ( $max - $min ) ?: 1;
		$pts   = array();
		foreach ( array_values( $counts ) as $i => $c ) {
			$x     = round( ( $i / ( $n - 1 ) ) * $w, 2 );
			$y     = round( $h - ( ( $c - $min ) / $range ) * $h, 2 );
			$pts[] = $x . ',' . $y;
		}
		return implode( ' ', $pts );
	}
}

$stats_cards = array(
	array(
		'type'    => 'shows',
		'class'   => 'shows',
		'label'   => __( 'Shows', 'lwtv' ),
		'count'   => $stats_shows,
		'caption' => __( 'TV series & films', 'lwtv' ),
		'svg'     => 'tv.svg',
		'icon'    => 'svg-television',
	),
	array(
		'type'    => 'characters',
		'class'   => 'characters',
		'label'   => __( 'Characters', 'lwtv' ),
		'count'   => $stats_characters,
		'caption' => __( 'Queer characters tracked', 'lwtv' ),
		'svg'     => 'user.svg',
		'icon'    => 'svg-user',
	),
	array(
		'type'    => 'actors',
		'class'   => 'actors',
		'label'   => __( 'Actors', 'lwtv' ),
		'count'   => $stats_actors,
		'caption' => __( 'Who played them', 'lwtv' ),
		'svg'     => 'film-strip.svg',
		'icon'    => 'svg-film',
	),
	array(
		'type'    => 'dead',
		'class'   => 'dead-characters',
		'label'   => __( 'Dead', 'lwtv' ),
		'count'   => $stats_dead,
		/* translators: %d is the "1 in N" ratio of dead characters. */
		'caption' => ( $stats_dead_ratio > 0 ) ? sprintf( __( '1 in %d characters', 'lwtv' ), $stats_dead_ratio ) : __( 'Characters lost', 'lwtv' ),
		'svg'     => 'skull.svg',
		'icon'    => 'svg-skull',
	),
);
?>

<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'The Database, Live', 'lwtv' ); ?></p>

<div class="lwtv-metric-grid">
	<?php
	foreach ( $stats_cards as $stats_card ) {
		$stats_points = lwtv_stats_sparkline_points( $stats_series[ $stats_card['type'] ] ?? array() );
		?>
		<div class="lwtv-metric-card bg-light card-header <?php echo esc_attr( $stats_card['class'] ); ?>">
			<div class="lwtv-metric-top">
				<span class="lwtv-stats-eyebrow"><?php echo esc_html( $stats_card['label'] ); ?></span>
				<span class="lwtv-metric-icon">
					<?php echo lwtv_plugin()->get_symbolicon( svg: $stats_card['svg'], icon: $stats_card['icon'], max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
			</div>
			<span class="lwtv-metric-number" data-count-to="<?php echo (int) $stats_card['count']; ?>"><?php echo esc_html( number_format_i18n( $stats_card['count'] ) ); ?></span>
			<?php if ( '' !== $stats_points ) : ?>
				<svg class="lwtv-sparkline" viewBox="0 0 120 26" preserveAspectRatio="none" role="img" aria-hidden="true">
					<polyline points="<?php echo esc_attr( $stats_points ); ?>" fill="none" stroke="currentColor" stroke-width="1.5" />
				</svg>
			<?php endif; ?>
			<span class="lwtv-metric-caption"><?php echo esc_html( $stats_card['caption'] ); ?></span>
		</div>
		<?php
	}
	?>
</div>
