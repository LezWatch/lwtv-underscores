<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * "Where queer TV lives" — top networks stacked-share panel.
 *
 * Mirrors the nations "around-the-world" panel: one proportional share bar
 * plus a legend, top networks named + an aggregated remainder to 100%.
 *
 * @package LezWatch.TV
 *
 * @var array $stats_top_stations
 * @var int   $stats_shows
 * @var int   $stats_total_stations
 */

$wtl_stations = is_array( $stats_top_stations ) ? $stats_top_stations : array();

// Build legend rows: named networks + an aggregated remainder to 100%.
$wtl_rows      = array();
$wtl_named_pct = 0.0;
foreach ( $wtl_stations as $wtl_station ) {
	$wtl_pct        = ( $stats_shows > 0 ) ? round( ( (int) $wtl_station['count'] / $stats_shows ) * 100, 1 ) : 0;
	$wtl_named_pct += $wtl_pct;
	$wtl_rows[]     = array(
		'name' => $wtl_station['name'],
		'pct'  => $wtl_pct,
	);
}
$wtl_other_count = max( 0, $stats_total_stations - count( $wtl_rows ) );
$wtl_other_pct   = max( 0, round( 100 - $wtl_named_pct, 1 ) );
if ( $wtl_other_count > 0 && $wtl_other_pct > 0 ) {
	$wtl_rows[] = array(
		/* translators: %s: number of remaining networks. */
		'name' => sprintf( _n( '%s other network', '%s other networks', $wtl_other_count, 'lwtv' ), number_format_i18n( $wtl_other_count ) ),
		'pct'  => $wtl_other_pct,
	);
}

// Raspberry ramp order: darkest (largest) → lightest (others). Matches SCSS nth-child.
$wtl_top_pct  = $wtl_rows[0]['pct'] ?? 0;
$wtl_top_name = $wtl_rows[0]['name'] ?? '';
$wtl_n_in_ten = ( $wtl_top_pct > 0 ) ? (int) round( $wtl_top_pct / 10 ) : 0;
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
					/* translators: 1: total shows, 2: total networks. */
					esc_html__( '%1$s shows across %2$s stations & networks:', 'lwtv' ),
					esc_html( number_format_i18n( $stats_shows ) ),
					esc_html( number_format_i18n( $stats_total_stations ) )
				);
				?>
			</p>
		</div>
	</header>

	<?php if ( $wtl_n_in_ten > 0 && '' !== $wtl_top_name ) : ?>
		<p class="lwtv-wtl-headline">
			<?php
			printf(
				/* translators: 1: "N in 10" numerator, 2: top nation name. */
				esc_html__( 'Around %1$d in 10 shows air on %2$s.', 'lwtv' ),
				(int) $wtl_n_in_ten,
				esc_html( $wtl_top_name )
			);
			?>
		</p>
	<?php endif; ?>

	<div class="lwtv-share-bar" role="img" aria-label="<?php esc_attr_e( 'Share of shows by network', 'lwtv' ); ?>">
		<?php
		foreach ( $wtl_rows as $wtl_row ) {
			printf(
				'<span class="lwtv-share-seg" style="width:0" data-grow-to="%1$s"></span>',
				esc_attr( $wtl_row['pct'] )
			);
		}
		?>
	</div>

	<ul class="lwtv-legend">
		<?php
		foreach ( $wtl_rows as $wtl_row ) {
			printf(
				'<li class="lwtv-legend-row"><span class="lwtv-legend-dot"></span><span class="lwtv-legend-name">%1$s</span><span class="lwtv-legend-pct">%2$s%%</span></li>',
				esc_html( $wtl_row['name'] ),
				esc_html( $wtl_row['pct'] )
			);
		}
		?>
	</ul>

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
