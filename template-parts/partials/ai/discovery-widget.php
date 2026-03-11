<?php
/**
 * Template part: AI Discovery Floating Widget
 *
 * Floating trigger button + sliding drawer containing the Discovery panel.
 * Used on global pages. Uses tv.svg icon and "Find your next favorite show" tooltip.
 *
 * @package YIKES Starter
 */

$mood_chips = $args['mood_chips'] ?? array();
?>

<button type="button" id="lwtv-chat-trigger" class="lwtv-chat-trigger" aria-label="<?php esc_attr_e( 'Find your next favorite show', 'lwtv-underscores' ); ?>" title="<?php esc_attr_e( 'Find your next favorite show', 'lwtv-underscores' ); ?>">
	<?php
	if ( function_exists( 'lwtv_plugin' ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo lwtv_plugin()->get_symbolicon( svg: 'tv.svg', icon: 'svg-tv', max_size: '24' );
	} else {
		echo '&#9733;'; // Fallback sparkle/star.
	}
	?>
</button>

<?php
get_template_part(
	'template-parts/partials/ai/discovery-panel',
	null,
	array(
		'context'        => 'global',
		'heading'        => __( 'Find your next favorite show', 'lwtv-underscores' ),
		'initial_prompt' => '',
		'mood_chips'     => $mood_chips,
		'failed_query'   => '',
	)
);
?>
