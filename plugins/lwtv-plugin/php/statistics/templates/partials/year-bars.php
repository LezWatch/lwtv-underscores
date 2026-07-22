<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Reusable vertical bar-per-year chart. Static heights; the peak bar is
 * highlighted. Renders whatever $yearbars provides.
 *
 * @package LezWatch.TV
 *
 * @var array $yearbars {
 *   @type array  $rows        Dense [ ['year'=>int,'count'=>int], … ] ascending.
 *   @type int    $peak_year
 *   @type int    $peak_count
 *   @type string $average     Optional per-year average (numeric string).
 *   @type string $eyebrow
 *   @type string $headline
 *   @type string $description
 *   @type array  $callouts    Optional [ ['label','text','svg','icon'], … ] boxes
 *                              rendered above the chart (reuses trendline callout markup).
 *   @type string $hover_sub   Optional printf format for the corner readout sub-line on
 *                              hover; %s is the year. Defaults to the year alone ('%s').
 * }
 */

$yb_rows      = $yearbars['rows'] ?? array();
$yb_peak      = max( 1, (int) ( $yearbars['peak_count'] ?? 0 ) );
$yb_pyear     = (int) ( $yearbars['peak_year'] ?? 0 );
$yb_first     = ! empty( $yb_rows ) ? (int) $yb_rows[0]['year'] : 0;
$yb_last      = ! empty( $yb_rows ) ? (int) $yb_rows[ count( $yb_rows ) - 1 ]['year'] : 0;
$yb_hover_sub = $yearbars['hover_sub'] ?? '%s';
?>
<?php if ( ! empty( $yearbars['callouts'] ) && is_array( $yearbars['callouts'] ) ) : ?>
	<div class="lwtv-trend-callouts">
		<?php foreach ( $yearbars['callouts'] as $yb_callout ) : ?>
			<div class="lwtv-trend-callout">
				<div class="lwtv-trend-callout-body">
					<span class="lwtv-stats-eyebrow"><?php echo esc_html( $yb_callout['label'] ?? '' ); ?></span>
					<p class="lwtv-trend-callout-text"><?php echo esc_html( $yb_callout['text'] ?? '' ); ?></p>
				</div>
				<?php if ( ! empty( $yb_callout['svg'] ) ) : ?>
					<span class="lwtv-trend-callout-icon">
						<?php echo lwtv_plugin()->get_symbolicon( svg: $yb_callout['svg'], icon: $yb_callout['icon'] ?? '', max_size: '24' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</span>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
<section class="lwtv-yearbars-card bg-light">
	<div class="lwtv-yearbars-head">
		<div>
			<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php echo esc_html( $yearbars['eyebrow'] ?? '' ); ?></p>
			<h2 class="lwtv-yearbars-headline"><?php echo esc_html( $yearbars['headline'] ?? '' ); ?></h2>
			<?php if ( ! empty( $yearbars['description'] ) ) : ?>
				<p class="lwtv-yearbars-desc"><?php echo esc_html( $yearbars['description'] ); ?></p>
			<?php endif; ?>
		</div>
		<?php if ( isset( $yearbars['stat_num'] ) ) : ?>
			<div class="lwtv-yearbars-avg">
				<span class="lwtv-yearbars-avg-num" data-count-to="<?php echo (int) $yearbars['stat_num']; ?>"><?php echo esc_html( number_format_i18n( (int) $yearbars['stat_num'] ) ); ?></span>
				<span class="lwtv-yearbars-avg-sub"><?php echo esc_html( $yearbars['stat_sub'] ?? '' ); ?></span>
			</div>
		<?php elseif ( isset( $yearbars['average'] ) && '' !== $yearbars['average'] ) : ?>
			<div class="lwtv-yearbars-avg">
				<span class="lwtv-yearbars-avg-num" data-count-to="<?php echo (int) round( (float) $yearbars['average'] ); ?>"><?php echo esc_html( number_format_i18n( (int) round( (float) $yearbars['average'] ) ) ); ?></span>
				<span class="lwtv-yearbars-avg-sub"><?php esc_html_e( 'per year on average', 'lwtv' ); ?></span>
			</div>
		<?php endif; ?>
	</div>
	<div class="lwtv-yearbars" role="img" aria-label="<?php echo esc_attr( $yearbars['eyebrow'] ?? '' ); ?>" data-hover-sub="<?php echo esc_attr( $yb_hover_sub ); ?>">
		<?php
		foreach ( $yb_rows as $yb ) {
			$yb_year   = (int) $yb['year'];
			$yb_count  = (int) $yb['count'];
			$yb_height = round( ( $yb_count / $yb_peak ) * 100, 1 );
			// Peak bar = deepest crimson; others ramp rose->crimson by height (6 buckets).
			if ( $yb_year === $yb_pyear ) {
				$yb_class = ' lwtv-yearbar--peak';
			} else {
				$yb_bucket = min( 5, (int) floor( ( $yb_count / $yb_peak ) * 6 ) );
				$yb_class  = ' lwtv-yearbar--r' . $yb_bucket;
			}
			// data-year/data-count feed the corner readout on hover (see
			// statistics-overview.js). The on-bar value label is kept only for the
			// peak — every other year's number lives in the readout, off to the side.
			echo '<span class="lwtv-yearbar' . esc_attr( $yb_class ) . '" style="height:' . esc_attr( (string) max( 2, $yb_height ) ) . '%" title="' . esc_attr( $yb_year . ' — ' . number_format_i18n( $yb_count ) ) . '" data-year="' . esc_attr( (string) $yb_year ) . '" data-count="' . esc_attr( (string) $yb_count ) . '">';
			if ( $yb_year === $yb_pyear ) {
				echo '<span class="lwtv-yearbar-val">' . esc_html( number_format_i18n( $yb_count ) ) . '</span>';
			}
			echo '</span>';
		}
		?>
	</div>
	<?php if ( $yb_first && $yb_last ) : ?>
		<div class="lwtv-yearbars-axis">
			<span><?php echo esc_html( (string) $yb_first ); ?></span>
			<?php if ( $yb_pyear && $yb_pyear !== $yb_first && $yb_pyear !== $yb_last ) : ?>
				<span class="lwtv-yearbars-axis-peak"><?php echo esc_html( (string) $yb_pyear ); ?></span>
			<?php endif; ?>
			<span><?php echo esc_html( (string) $yb_last ); ?></span>
		</div>
	<?php endif; ?>
</section>
