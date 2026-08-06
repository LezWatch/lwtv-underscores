<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * "Bury Your Gays" death band — waffle chart edition.
 *
 * A 100-dot waffle (one dot per percent of all characters) makes the
 * death ratio visible at a glance, next to the "1 in N" figure.
 *
 * @package LezWatch.TV
 *
 * @var int $stats_dead
 * @var int $stats_characters
 * @var int $stats_dead_ratio
 */

if ( $stats_dead <= 0 || $stats_characters <= 0 ) {
	return;
}

// One filled dot per percent of characters who have died; never zero dots.
$stats_dead_pct = max( 1, (int) round( ( $stats_dead / $stats_characters ) * 100 ) );

$waffle = array(
	'filled' => $stats_dead_pct,
	'total'  => 100,
	'label'  => sprintf(
		/* translators: %d: the "1 in N" death ratio. */
		__( 'Waffle chart: 1 in %d characters has been killed off.', 'lwtv' ),
		(int) $stats_dead_ratio
	),
);
?>
<div class="lwtv-byg card-header bury-queers">
	<div class="lwtv-byg-figure">
		<?php
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __DIR__ ) . 'partials/waffle.php';
		?>
	</div>
	<div class="lwtv-byg-body">
		<span class="lwtv-stats-eyebrow">
			<?php echo lwtv_plugin()->get_symbolicon( svg: 'skull.svg', icon: 'svg-skull', max_size: '14' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php esc_html_e( 'Bury Your Gays', 'lwtv' ); ?>
		</span>
		<p class="lwtv-byg-ratio">
			<?php
			printf(
				/* translators: %d: the "1 in N" death ratio. */
				esc_html__( '1 in %d', 'lwtv' ),
				(int) $stats_dead_ratio
			);
			?>
		</p>
		<p class="lwtv-byg-line">
			<?php
			printf(
				/* translators: 1: number of dead characters (int, for the count-up animation), 2: total characters, 3: formatted number of dead characters. */
				wp_kses_post( __( '<strong data-count-to="%1$d">%3$s</strong> of %2$s characters have been killed off.', 'lwtv' ) ),
				(int) $stats_dead,
				esc_html( number_format_i18n( $stats_characters ) ),
				esc_html( number_format_i18n( $stats_dead ) )
			);
			?>
		</p>
		<p class="lwtv-byg-desc"><?php esc_html_e( 'The (sadly) best-known trope in queer TV.', 'lwtv' ); ?></p>
	</div>
	<a class="lwtv-byg-btn btn" href="<?php echo esc_url( home_url( '/statistics/death/' ) ); ?>">
		<?php esc_html_e( 'Death Statistics', 'lwtv' ); ?>
		<?php echo lwtv_plugin()->get_symbolicon( svg: 'caret-right.svg', icon: 'svg-arrow-right', max_size: '14' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</a>
</div>
