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
 * @var int   $trope_buried  count of shows tagged with the buried/dead-queers trope.
 * @var int   $trope_happy   count of shows tagged with the happy-ending trope.
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
		<div class="lwtv-metric-card card-header <?php echo esc_attr( $shows_card['type'] ); ?>">
			<div class="lwtv-metric-top">
				<span class="lwtv-stats-eyebrow"><?php echo esc_html( $shows_card['label'] ); ?></span>
				<span class="lwtv-metric-icon <?php echo esc_attr( $shows_card['type'] ); ?>">
					<?php echo lwtv_plugin()->get_symbolicon( svg: $shows_card['svg'], icon: $shows_card['icon'], max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
			</div>
			<span class="lwtv-metric-number" data-count-to="<?php echo (int) $shows_card['count']; ?>"><?php echo esc_html( number_format_i18n( $shows_card['count'] ) ); ?></span>
			<?php if ( '' !== $shows_card['points'] ) : ?>
				<svg class="lwtv-sparkline" viewBox="0 0 120 26" preserveAspectRatio="none" aria-hidden="true">
					<polygon class="lwtv-sparkline-area" points="<?php echo esc_attr( $shows_card['points'] . ' 120,26 0,26' ); ?>" fill="currentColor" fill-opacity="0.15" stroke="none" />
					<polyline points="<?php echo esc_attr( $shows_card['points'] ); ?>" fill="none" stroke="currentColor" stroke-width="1.5" />
				</svg>
			<?php endif; ?>
			<span class="lwtv-metric-caption"><?php echo esc_html( $shows_card['caption'] ); ?></span>
		</div>
		<?php
	}
	?>
</div>

<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'The Trope Gap', 'lwtv' ); ?></p>
<div class="lwtv-pullstats">
	<div class="lwtv-tropegap card-header dead-characters">
		<div class="lwtv-tropegap-top">
			<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Bury Your Queers', 'lwtv' ); ?></span>
			<span class="lwtv-tropegap-icon"><?php echo lwtv_plugin()->get_symbolicon( svg: 'hand-holding-skull.svg', icon: 'svg-skull', max_size: '22' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</div>
		<span class="lwtv-tropegap-number" data-count-to="<?php echo (int) $trope_buried; ?>"><?php echo esc_html( number_format_i18n( $trope_buried ) ); ?></span>
		<p class="lwtv-tropegap-desc"><?php esc_html_e( 'shows kill off a queer character — the most common harmful trope in the catalogue.', 'lwtv' ); ?></p>
		<a class="lwtv-tropegap-link" href="<?php echo esc_url( site_url( '/trope/dead-queers/' ) ); ?>"><?php esc_html_e( 'See these shows', 'lwtv' ); ?> <span aria-hidden="true">&#8599;</span></a>
	</div>
	<div class="lwtv-tropegap card-header happy-endings">
		<div class="lwtv-tropegap-top">
			<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Happy Endings', 'lwtv' ); ?></span>
			<span class="lwtv-tropegap-icon"><?php echo lwtv_plugin()->get_symbolicon( svg: 'heart-circle.svg', icon: 'svg-heart', max_size: '22' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</div>
		<span class="lwtv-tropegap-number" data-count-to="<?php echo (int) $trope_happy; ?>"><?php echo esc_html( number_format_i18n( $trope_happy ) ); ?></span>
		<p class="lwtv-tropegap-desc"><?php esc_html_e( 'shows give their queer characters a happy ending.', 'lwtv' ); ?></p>
		<a class="lwtv-tropegap-link" href="<?php echo esc_url( site_url( '/trope/happy-ending/' ) ); ?>"><?php esc_html_e( 'See these shows', 'lwtv' ); ?> <span aria-hidden="true">&#8599;</span></a>
	</div>
</div>

<?php
// Top tropes / top genres panels: top-5 leader bars + a tail name/count table.
$shows_panels = array(
	array(
		'title'  => __( 'Top Tropes', 'lwtv' ),
		'family' => 'characters',
		'svg'    => 'tag.svg',
		'icon'   => 'svg-tag',
		'rows'   => $top_tropes,
		'base'   => '/trope/',
		/* translators: %s: total number of tropes. */
		'sub'    => sprintf( __( 'Most common of %s tropes, by number of shows', 'lwtv' ), number_format_i18n( (int) $count_tropes ) ),
		/* translators: %s: total number of tropes. */
		'all'    => sprintf( __( 'View all %s tropes →', 'lwtv' ), number_format_i18n( (int) $count_tropes ) ),
		'more'   => $baseurl . 'tropes/',
	),
	array(
		'title'  => __( 'Top Genres', 'lwtv' ),
		'family' => 'actors',
		'svg'    => 'theater_masks.svg',
		'icon'   => 'svg-theater-masks',
		'rows'   => $top_genres,
		'base'   => '/genre/',
		/* translators: %s: total number of genres. */
		'sub'    => sprintf( __( 'Most common of %s genres, by number of shows', 'lwtv' ), number_format_i18n( (int) $count_genres ) ),
		/* translators: %s: total number of genres. */
		'all'    => sprintf( __( 'View all %s genres →', 'lwtv' ), number_format_i18n( (int) $count_genres ) ),
		'more'   => $baseurl . 'genres/',
	),
);
?>
<div class="lwtv-panels">
	<?php
	foreach ( $shows_panels as $shows_panel ) {
		$shows_rows    = is_array( $shows_panel['rows'] ) ? $shows_panel['rows'] : array();
		$shows_leaders = array_slice( $shows_rows, 0, 5, true );
		$shows_tail    = array_slice( $shows_rows, 5, null, true );
		?>
		<section class="lwtv-panel bg-light">
			<header class="lwtv-panel-head">
				<span class="lwtv-panel-icon <?php echo esc_attr( $shows_panel['family'] ); ?>">
					<?php echo lwtv_plugin()->get_symbolicon( svg: $shows_panel['svg'], icon: $shows_panel['icon'], max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<div>
					<h2 class="lwtv-panel-title"><?php echo esc_html( $shows_panel['title'] ); ?></h2>
					<p class="lwtv-panel-sub"><?php echo esc_html( $shows_panel['sub'] ); ?></p>
				</div>
			</header>

			<div class="lwtv-leaders lwtv-bars--<?php echo esc_attr( $shows_panel['family'] ); ?>">
				<?php
				foreach ( $shows_leaders as $shows_slug => $shows_row ) {
					$shows_count_row = (int) $shows_row['count'];
					// Bar width is the true share of all shows, so it matches the label.
					$shows_pct = ( $shows_count > 0 ) ? round( ( $shows_count_row / $shows_count ) * 100, 1 ) : 0;
					?>
					<div class="lwtv-leader-row">
						<div class="lwtv-leader-head">
							<a class="lwtv-leader-name" href="<?php echo esc_url( site_url( $shows_panel['base'] . $shows_slug ) ); ?>"><?php echo esc_html( $shows_row['name'] ); ?></a>
							<span class="lwtv-leader-value"><?php echo esc_html( number_format_i18n( $shows_count_row ) . ' · ' . $shows_pct . '%' ); ?></span>
						</div>
						<div class="progress lwtv-leader-track">
							<div class="progress-bar" role="progressbar" style="width:0" data-grow-to="<?php echo esc_attr( (string) $shows_pct ); ?>" aria-valuenow="<?php echo esc_attr( (string) $shows_count_row ); ?>" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( (string) $shows_count ); ?>"></div>
						</div>
					</div>
					<?php
				}
				?>
			</div>

			<?php if ( ! empty( $shows_tail ) ) : ?>
				<ul class="lwtv-tail">
					<?php
					foreach ( $shows_tail as $shows_slug => $shows_row ) {
						?>
						<li class="lwtv-tail-row">
							<a class="lwtv-tail-name" href="<?php echo esc_url( site_url( $shows_panel['base'] . $shows_slug ) ); ?>"><?php echo esc_html( $shows_row['name'] ); ?></a>
							<span class="lwtv-tail-count"><?php echo esc_html( number_format_i18n( (int) $shows_row['count'] ) ); ?></span>
						</li>
						<?php
					}
					?>
				</ul>
			<?php endif; ?>

			<a class="lwtv-panel-foot" href="<?php echo esc_url( $shows_panel['more'] ); ?>"><?php echo esc_html( $shows_panel['all'] ); ?></a>
		</section>
		<?php
	}
	?>
</div>
