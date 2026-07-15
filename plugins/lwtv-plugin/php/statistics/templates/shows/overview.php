<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows overview: metric cards + trope-gap pull-stats + top tropes/genres panels.
 *
 * @package LezWatch.TV
 *
 * @var int   $shows_count
 * @var int   $count_tropes
 * @var int   $count_genres
 * @var array $top_tropes    slug => ['name','count', …], top 10 by count.
 * @var array $top_genres    slug => ['name','count', …], top 10 by count.
 * @var array $shows_growth  cumulative growth series for shows.
 */

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
require_once plugin_dir_path( __DIR__ ) . 'partials/sparkline.php';

// A fixed, gently-rising representative sparkline for term-count cards
// (Tropes/Genres have no real time series — decorative only).
$shows_rep_series = array(
	array( 'count' => 2 ),
	array( 'count' => 3 ),
	array( 'count' => 5 ),
	array( 'count' => 6 ),
	array( 'count' => 8 ),
	array( 'count' => 9 ),
	array( 'count' => 11 ),
);

$shows_cards = array(
	array(
		'type'    => 'shows',
		'label'   => __( 'Shows', 'lwtv' ),
		'count'   => (int) $shows_count,
		'caption' => __( 'TV series & films', 'lwtv' ),
		'svg'     => 'tv.svg',
		'icon'    => 'svg-television',
		'points'  => lwtv_stats_sparkline_points( $shows_growth ),
	),
	array(
		'type'    => 'characters', // green family (Tropes).
		'label'   => __( 'Tropes', 'lwtv' ),
		'count'   => (int) $count_tropes,
		'caption' => __( 'Distinct tropes tracked', 'lwtv' ),
		'svg'     => 'tag.svg',
		'icon'    => 'svg-tag',
		'points'  => lwtv_stats_sparkline_points( $shows_rep_series ),
	),
	array(
		'type'    => 'actors', // amber family (Genres).
		'label'   => __( 'Genres', 'lwtv' ),
		'count'   => (int) $count_genres,
		'caption' => __( 'Distinct genres tracked', 'lwtv' ),
		'svg'     => 'theater_masks.svg',
		'icon'    => 'svg-theater-masks',
		'points'  => lwtv_stats_sparkline_points( $shows_rep_series ),
	),
);
?>

<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Shows at a Glance', 'lwtv' ); ?></p>

<div class="lwtv-metric-grid lwtv-metric-grid--3">
	<?php
	foreach ( $shows_cards as $shows_card ) {
		?>
		<div class="lwtv-metric-card bg-light card-header <?php echo esc_attr( $shows_card['type'] ); ?>">
			<div class="lwtv-metric-top">
				<span class="lwtv-stats-eyebrow"><?php echo esc_html( $shows_card['label'] ); ?></span>
				<span class="lwtv-metric-icon <?php echo esc_attr( $shows_card['type'] ); ?>">
					<?php echo lwtv_plugin()->get_symbolicon( svg: $shows_card['svg'], icon: $shows_card['icon'], max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
			</div>
			<span class="lwtv-metric-number" data-count-to="<?php echo (int) $shows_card['count']; ?>"><?php echo esc_html( number_format_i18n( $shows_card['count'] ) ); ?></span>
			<?php if ( '' !== $shows_card['points'] ) : ?>
				<svg class="lwtv-sparkline" viewBox="0 0 120 26" preserveAspectRatio="none" aria-hidden="true">
					<polyline points="<?php echo esc_attr( $shows_card['points'] ); ?>" fill="none" stroke="currentColor" stroke-width="1.5" />
				</svg>
			<?php endif; ?>
			<span class="lwtv-metric-caption"><?php echo esc_html( $shows_card['caption'] ); ?></span>
		</div>
		<?php
	}
	?>
</div>

<?php
// Top tropes / top genres panels (leader bars).
$shows_panels = array(
	array(
		'eyebrow' => __( 'Top Tropes', 'lwtv' ),
		'family'  => 'characters',
		'rows'    => $top_tropes,
		'base'    => '/trope/',
		'more'    => array(
			'label' => $count_tropes,
			'url'   => $baseurl . 'tropes/',
		),
	),
	array(
		'eyebrow' => __( 'Top Genres', 'lwtv' ),
		'family'  => 'actors',
		'rows'    => $top_genres,
		'base'    => '/genre/',
		'more'    => array(
			'label' => $count_genres,
			'url'   => $baseurl . 'genres/',
		),
	),
);
?>
<div class="lwtv-panels">
	<?php
	foreach ( $shows_panels as $shows_panel ) {
		$shows_top = ! empty( $shows_panel['rows'] ) ? max( array_map( fn( $r ) => (int) $r['count'], $shows_panel['rows'] ) ) : 0;
		?>
		<section class="lwtv-panel bg-light">
			<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php echo esc_html( $shows_panel['eyebrow'] ); ?></p>
			<div class="lwtv-bars lwtv-bars--<?php echo esc_attr( $shows_panel['family'] ); ?>">
				<?php
				foreach ( $shows_panel['rows'] as $shows_slug => $shows_row ) {
					$shows_pct   = ( $shows_count > 0 ) ? round( ( (int) $shows_row['count'] / $shows_count ) * 100, 1 ) : 0;
					$shows_width = ( $shows_top > 0 ) ? round( ( (int) $shows_row['count'] / $shows_top ) * 100, 1 ) : 0;
					?>
					<div class="lwtv-bar-row">
						<a class="lwtv-bar-name" href="<?php echo esc_url( site_url( $shows_panel['base'] . $shows_slug ) ); ?>"><?php echo esc_html( $shows_row['name'] ); ?></a>
						<div class="progress lwtv-bar-track">
							<div class="progress-bar" role="progressbar" style="width:0" data-grow-to="<?php echo esc_attr( $shows_width ); ?>" aria-valuenow="<?php echo esc_attr( (int) $shows_row['count'] ); ?>" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( $shows_top ); ?>"></div>
						</div>
						<span class="lwtv-bar-label"><?php echo esc_html( number_format_i18n( (int) $shows_row['count'] ) . ' · ' . $shows_pct . '%' ); ?></span>
					</div>
					<?php
				}
				?>
			</div>
			<a class="lwtv-panel-foot" href="<?php echo esc_url( $shows_panel['more']['url'] ); ?>">
				<?php
				printf(
					/* translators: %s: total count. */
					esc_html__( 'View all %s →', 'lwtv' ),
					esc_html( number_format_i18n( (int) $shows_panel['more']['label'] ) )
				);
				?>
			</a>
		</section>
		<?php
	}
	?>
</div>
