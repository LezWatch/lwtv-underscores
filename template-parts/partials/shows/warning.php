<?php
/**
 * Template part for displaying show warnings
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

$show_id = $args['show_id'] ?? null;

if ( ! $show_id ) {
	return;
}

// The Game of Thrones Flag of Gratuitous Violence.
// This shows a notice if the show has trigger warnings.
$warning    = lwtv_plugin()->get_show_content_warning( $show_id );
$warn_image = lwtv_plugin()->get_symbolicon( 'hand.svg', 'fa-hand-paper' );

if ( is_array( $warning ) && 'none' !== $warning['card'] ) {
	?>
	<section id="trigger-warning" class="trigger-warning-container">
		<div class="alert alert-<?php echo esc_attr( $warning['card'] ); ?>" role="alert">
			<span class="callout-<?php echo esc_attr( $warning['card'] ); ?>" role="img" aria-label="Warning Hand" title="Warning Hand"><?php echo $warn_image; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<?php echo wp_kses_post( $warning['content'] ); ?>
		</div>
	</section>
	<?php
}
?>
