<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Generic callout-boxes row (reuses the .lwtv-trend-callout* styling).
 *
 * @package LezWatch.TV
 *
 * @var array $lwtv_callouts List of callouts: [ { 'label'=>string, 'text'=>string, 'icon'=>string (svg filename) } ].
 *                           'text' is already-assembled (and translated) copy; this partial escapes it.
 */

if ( empty( $lwtv_callouts ) || ! is_array( $lwtv_callouts ) ) {
	return;
}
?>
<div class="lwtv-trend-callouts">
	<?php foreach ( $lwtv_callouts as $lwtv_callout ) : ?>
		<div class="lwtv-trend-callout">
			<div class="lwtv-trend-callout-body">
				<span class="lwtv-stats-eyebrow"><?php echo esc_html( $lwtv_callout['label'] ?? '' ); ?></span>
				<p class="lwtv-trend-callout-text"><?php echo esc_html( $lwtv_callout['text'] ?? '' ); ?></p>
			</div>
			<?php if ( ! empty( $lwtv_callout['icon'] ) ) : ?>
				<span class="lwtv-trend-callout-icon <?php echo esc_attr( $lwtv_callout['family'] ?? 'generic' ); ?>"><?php echo lwtv_plugin()->get_symbolicon( svg: $lwtv_callout['icon'], icon: 'svg-' . str_replace( '.svg', '', $lwtv_callout['icon'] ), max_size: '24' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>
<?php
