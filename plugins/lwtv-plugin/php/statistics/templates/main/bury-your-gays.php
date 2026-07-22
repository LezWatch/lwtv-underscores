<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * "Bury Your Gays" death callout band.
 *
 * @package LezWatch.TV
 *
 * @var int $stats_dead
 * @var int $stats_dead_ratio
 */

if ( $stats_dead <= 0 ) {
	return;
}
?>
<div class="lwtv-byg card-header bury-queers">
	<span class="lwtv-byg-icon">
		<?php echo lwtv_plugin()->get_symbolicon( svg: 'skull.svg', icon: 'svg-skull', max_size: '22' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</span>
	<div class="lwtv-byg-body">
		<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Bury Your Gays', 'lwtv' ); ?></span>
		<p class="lwtv-byg-line">
			<?php
			printf(
				/* translators: 1: number of dead characters, 2: the "1 in N" ratio. */
				wp_kses_post( __( '<strong data-count-to="%1$d">%3$s</strong> characters (1 in %2$d) have been killed off.', 'lwtv' ) ),
				(int) $stats_dead,
				(int) $stats_dead_ratio,
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
