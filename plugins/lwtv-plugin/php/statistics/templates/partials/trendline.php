<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Reusable area trendline (SVG). Line + area render immediately; the
 * current-year headline figure counts up.
 *
 * @package LezWatch.TV
 *
 * @var array $trend {
 *   @type array  $points   Ordered [ ['year'=>int,'count'=>int], … ].
 *   @type string $eyebrow  Section eyebrow.
 *   @type string $headline Headline sentence.
 *   @type string $description Supporting sentence.
 *   @type int    $current  Current-year figure (counts up).
 *   @type int    $current_year Label year for the current figure.
 * }
 */

$trend_points = $trend['points'] ?? array();
$trend_w      = 800;
$trend_h      = 240; // baseline y for area.
$trend_pad    = 8;
$trend_counts = array_map( fn( $p ) => (int) $p['count'], $trend_points );
$trend_n      = count( $trend_counts );
$trend_max    = $trend_n ? max( $trend_counts ) : 0;
$trend_peak_i = 0;
foreach ( $trend_counts as $trend_i => $trend_c ) {
	if ( $trend_c === $trend_max ) {
		$trend_peak_i = $trend_i;
		break;
	}
}

$trend_xy = array();
foreach ( $trend_counts as $trend_i => $trend_c ) {
	$trend_x    = ( $trend_n > 1 ) ? round( ( $trend_i / ( $trend_n - 1 ) ) * $trend_w, 2 ) : 0;
	$trend_y    = ( $trend_max > 0 ) ? round( $trend_h - ( $trend_c / $trend_max ) * ( $trend_h - $trend_pad ), 2 ) : $trend_h;
	$trend_xy[] = array( $trend_x, $trend_y );
}
$trend_line = implode( ' ', array_map( fn( $p ) => $p[0] . ',' . $p[1], $trend_xy ) );
$trend_area = $trend_n ? ( '0,' . $trend_h . ' ' . $trend_line . ' ' . $trend_w . ',' . $trend_h ) : '';
$trend_peak = $trend_xy[ $trend_peak_i ] ?? array( 0, $trend_h );

// First / last year for the x-axis labels below the graph.
$trend_start_year = $trend_n ? (int) ( $trend_points[0]['year'] ?? 0 ) : 0;
$trend_end_year   = $trend_n ? (int) ( $trend_points[ $trend_n - 1 ]['year'] ?? 0 ) : 0;
?>
<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php echo esc_html( $trend['eyebrow'] ?? '' ); ?></p>

<section class="lwtv-trend-card bg-light">
	<div class="lwtv-trend-head">
		<div>
			<h2 class="lwtv-trend-headline"><?php echo esc_html( $trend['headline'] ?? '' ); ?></h2>
			<?php if ( ! empty( $trend['description'] ) ) : ?>
				<p class="lwtv-trend-desc"><?php echo esc_html( $trend['description'] ); ?></p>
			<?php endif; ?>
		</div>
		<div class="lwtv-trend-current">
			<span class="lwtv-trend-current-num" data-count-to="<?php echo (int) ( $trend['current'] ?? 0 ); ?>"><?php echo esc_html( number_format_i18n( (int) ( $trend['current'] ?? 0 ) ) ); ?></span>
			<span class="lwtv-trend-current-sub">
				<?php
				printf(
					/* translators: %d: year. */
					esc_html__( 'on air in %d', 'lwtv' ),
					(int) ( $trend['current_year'] ?? 0 )
				);

				if ( gmdate( 'Y' ) !== (int) ( $trend['current_year'] ) ) {
					echo '<br />';
					esc_html_e( ' (the last year a show aired)', 'lwtv' );
				}
				?>
			</span>
		</div>
	</div>

	<?php if ( '' !== $trend_area ) : ?>
		<svg class="lwtv-trend-svg" viewBox="0 0 <?php echo (int) $trend_w; ?> 280" preserveAspectRatio="none" role="img" aria-label="<?php echo esc_attr( $trend['eyebrow'] ?? '' ); ?>">
			<polygon class="lwtv-trend-area" points="<?php echo esc_attr( $trend_area ); ?>" />
			<polyline class="lwtv-trend-line" points="<?php echo esc_attr( $trend_line ); ?>" fill="none" stroke-width="2.5" />
			<circle class="lwtv-trend-peak" cx="<?php echo esc_attr( (string) $trend_peak[0] ); ?>" cy="<?php echo esc_attr( (string) $trend_peak[1] ); ?>" r="4" />
		</svg>
		<?php if ( $trend_n > 1 ) : ?>
			<div class="lwtv-trend-axis">
				<span><?php echo esc_html( (string) $trend_start_year ); ?></span>
				<span><?php echo esc_html( (string) $trend_end_year ); ?></span>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</section>
