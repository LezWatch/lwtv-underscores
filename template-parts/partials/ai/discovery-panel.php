<?php
/**
 * Template part: AI Discovery Panel
 *
 * Reusable Discovery chat UI. Used inline on 404/no-results or inside the floating drawer.
 *
 * @package YIKES Starter
 *
 * @param string $context       Context: global, 404, no-results.
 * @param string $heading      Optional heading text.
 * @param string $initial_prompt Optional pre-filled prompt.
 * @param array  $mood_chips   Optional array of quick-start prompt strings.
 */

$context        = $args['context'] ?? 'global';
$heading        = $args['heading'] ?? __( 'Find your next favorite show', 'lwtv-underscores' );
$initial_prompt = $args['initial_prompt'] ?? '';
$mood_chips     = $args['mood_chips'] ?? array();
$failed_query   = $args['failed_query'] ?? '';
?>

<div id="lwtv-chat-box" class="lwtv-discovery-panel lwtv-discovery-<?php echo esc_attr( $context ); ?>" role="dialog" aria-label="<?php echo esc_attr( $heading ); ?>">
	<div id="lwtv-chat-header" class="lwtv-chat-header">
		<h3 class="lwtv-chat-title"><?php echo esc_html( $heading ); ?></h3>
		<button type="button" id="lwtv-close" class="lwtv-chat-close" aria-label="<?php esc_attr_e( 'Close', 'lwtv-underscores' ); ?>">&times;</button>
	</div>

	<div class="lwtv-chat-vibe-check">
		<label class="lwtv-onair-toggle" for="lwtv-onair-toggle">
			<input type="checkbox" id="lwtv-onair-toggle" aria-describedby="lwtv-happy-ending-desc">
			<span class="lwtv-toggle-slider"></span>
			<span id="lwtv-happy-ending-desc" class="lwtv-toggle-label"><?php esc_html_e( 'Prioritize shows on air', 'lwtv-underscores' ); ?></span>
		</label>
	</div>

	<?php if ( ! empty( $mood_chips ) ) : ?>
		<div class="lwtv-mood-chips" data-mood-chips="<?php echo esc_attr( wp_json_encode( $mood_chips ) ); ?>">
			<?php foreach ( $mood_chips as $chip ) : ?>
				<button type="button" class="lwtv-mood-chip" data-prompt="<?php echo esc_attr( $chip ); ?>"><?php echo esc_html( $chip ); ?></button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div id="lwtv-chat-msgs" class="lwtv-chat-msgs" role="log" aria-live="polite"></div>

	<div id="lwtv-chat-input-wrap" class="lwtv-chat-input-wrap">
		<input type="text" id="lwtv-chat-input" class="lwtv-chat-input" placeholder="<?php esc_attr_e( 'e.g. British drama', 'lwtv-underscores' ); ?>" autocomplete="off" aria-label="<?php esc_attr_e( 'Search for shows', 'lwtv-underscores' ); ?>" value="<?php echo esc_attr( $initial_prompt ); ?>" data-initial-prompt="<?php echo esc_attr( $initial_prompt ); ?>" data-failed-query="<?php echo esc_attr( $failed_query ); ?>">
		<button type="button" id="lwtv-chat-send" class="lwtv-chat-send" aria-label="<?php esc_attr_e( 'Send', 'lwtv-underscores' ); ?>"><?php esc_html_e( 'Find', 'lwtv-underscores' ); ?></button>
	</div>
</div>
