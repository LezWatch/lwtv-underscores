<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * "Where queer TV lives" — top networks panel.
 *
 * @package LezWatch.TV
 *
 * @var array $stats_top_stations
 * @var int   $stats_shows
 * @var int   $stats_total_stations
 */

$wtl_stations = is_array( $stats_top_stations ) ? $stats_top_stations : array();
$wtl_top      = ! empty( $wtl_stations ) ? max( array_map( fn( $s ) => (int) $s['count'], $wtl_stations ) ) : 0;
?>
<section class="lwtv-panel bg-light">
	<header class="lwtv-panel-head">
		<span class="lwtv-panel-icon">
			<?php echo lwtv_plugin()->get_symbolicon( svg: 'satellite-signal.svg', icon: 'svg-bullhorn', max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</span>
		<div>
			<h2 class="lwtv-panel-title"><?php esc_html_e( 'Where queer TV lives', 'lwtv' ); ?></h2>
			<p class="lwtv-panel-sub">
				<?php
				printf(
					/* translators: 1: number shown (7), 2: total networks. */
					esc_html__( 'Shows by network. The top %1$d of %2$s stations & networks:', 'lwtv' ),
					(int) count( $wtl_stations ),
					esc_html( number_format_i18n( $stats_total_stations ) )
				);
				?>
			</p>
		</div>
	</header>

	<div class="lwtv-bars">
		<?php
		foreach ( $wtl_stations as $wtl_station ) {
			$wtl_count = (int) $wtl_station['count'];
			$wtl_pct   = ( $stats_shows > 0 ) ? round( ( $wtl_count / $stats_shows ) * 100, 1 ) : 0;
			$wtl_width = ( $wtl_top > 0 ) ? round( ( $wtl_count / $wtl_top ) * 100, 1 ) : 0;
			?>
			<div class="lwtv-bar-row">
				<a class="lwtv-bar-name" href="<?php echo esc_url( home_url( '/statistics/stations/?station=' . $wtl_station['slug'] ) ); ?>"><?php echo esc_html( $wtl_station['name'] ); ?></a>
				<div class="progress lwtv-bar-track">
					<div class="progress-bar" role="progressbar" style="width:0" data-grow-to="<?php echo esc_attr( $wtl_width ); ?>" aria-valuenow="<?php echo esc_attr( $wtl_count ); ?>" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( $wtl_top ); ?>"></div>
				</div>
				<span class="lwtv-bar-label"><?php echo esc_html( number_format_i18n( $wtl_count ) . ' · ' . $wtl_pct . '%' ); ?></span>
			</div>
			<?php
		}
		?>
	</div>

	<a class="lwtv-panel-foot" href="<?php echo esc_url( home_url( '/statistics/stations/' ) ); ?>">
		<?php
		printf(
			/* translators: %s: total number of networks. */
			esc_html__( 'View all %s networks →', 'lwtv' ),
			esc_html( number_format_i18n( $stats_total_stations ) )
		);
		?>
	</a>
</section>
