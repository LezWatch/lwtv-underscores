<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * "Around the world" — nations stacked-share panel.
 *
 * @package LezWatch.TV
 *
 * @var array $stats_top_nations
 * @var int   $stats_shows
 * @var int   $stats_total_nations
 */

$atw_nations = is_array( $stats_top_nations ) ? $stats_top_nations : array();

// Build legend rows: up to 4 named nations + an aggregated remainder to 100%.
$atw_rows      = array();
$atw_named_pct = 0.0;
foreach ( $atw_nations as $atw_nation ) {
	$atw_pct        = ( $stats_shows > 0 ) ? round( ( (int) $atw_nation['count'] / $stats_shows ) * 100, 1 ) : 0;
	$atw_named_pct += $atw_pct;
	$atw_rows[]     = array(
		'name' => $atw_nation['name'],
		'pct'  => $atw_pct,
	);
}
$atw_other_count = max( 0, $stats_total_nations - count( $atw_rows ) );
$atw_other_pct   = max( 0, round( 100 - $atw_named_pct, 1 ) );
if ( $atw_other_count > 0 && $atw_other_pct > 0 ) {
	$atw_rows[] = array(
		/* translators: %s: number of remaining nations. */
		'name' => sprintf( _n( '%s other nation', '%s other nations', $atw_other_count, 'lwtv' ), number_format_i18n( $atw_other_count ) ),
		'pct'  => $atw_other_pct,
	);
}

// Raspberry ramp order: darkest (largest) → lightest (others). Matches SCSS nth-child.
$atw_top_pct  = $atw_rows[0]['pct'] ?? 0;
$atw_top_name = $atw_rows[0]['name'] ?? '';
$atw_n_in_ten = ( $atw_top_pct > 0 ) ? (int) round( $atw_top_pct / 10 ) : 0;
?>
<section class="lwtv-panel bg-light">
	<header class="lwtv-panel-head">
		<span class="lwtv-panel-icon">
			<?php echo lwtv_plugin()->get_symbolicon( svg: 'globe.svg', icon: 'svg-globe', max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</span>
		<div>
			<h2 class="lwtv-panel-title"><?php esc_html_e( 'Around the world', 'lwtv' ); ?></h2>
			<p class="lwtv-panel-sub">
				<?php
				printf(
					/* translators: 1: total shows, 2: total nations. */
					esc_html__( '%1$s shows across %2$s nations', 'lwtv' ),
					esc_html( number_format_i18n( $stats_shows ) ),
					esc_html( number_format_i18n( $stats_total_nations ) )
				);
				?>
			</p>
		</div>
	</header>

	<?php if ( $atw_n_in_ten > 0 && '' !== $atw_top_name ) : ?>
		<p class="lwtv-atw-headline">
			<?php
			printf(
				/* translators: 1: "N in 10" numerator, 2: top nation name. */
				esc_html__( 'Nearly %1$d in 10 shows come from %2$s.', 'lwtv' ),
				(int) $atw_n_in_ten,
				esc_html( $atw_top_name )
			);
			?>
		</p>
	<?php endif; ?>

	<div class="lwtv-share-bar" role="img" aria-label="<?php esc_attr_e( 'Share of shows by nation', 'lwtv' ); ?>">
		<?php
		foreach ( $atw_rows as $atw_index => $atw_row ) {
			printf(
				'<span class="lwtv-share-seg" style="width:0" data-grow-to="%1$s"></span>',
				esc_attr( $atw_row['pct'] )
			);
		}
		?>
	</div>

	<ul class="lwtv-legend">
		<?php
		foreach ( $atw_rows as $atw_row ) {
			printf(
				'<li class="lwtv-legend-row"><span class="lwtv-legend-dot"></span><span class="lwtv-legend-name">%1$s</span><span class="lwtv-legend-pct">%2$s%%</span></li>',
				esc_html( $atw_row['name'] ),
				esc_html( $atw_row['pct'] )
			);
		}
		?>
	</ul>

	<a class="lwtv-panel-foot" href="<?php echo esc_url( home_url( '/statistics/nations/' ) ); ?>">
		<?php
		printf(
			/* translators: %s: total number of nations. */
			esc_html__( 'View all %s nations →', 'lwtv' ),
			esc_html( number_format_i18n( $stats_total_nations ) )
		);
		?>
	</a>
</section>
