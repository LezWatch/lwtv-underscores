<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Reusable "Headlines" section: one promoted lead plate + a color-spine rail
 * of linked mini-stats, one per subpage.
 *
 * Extracted from shows/overview.php's original inline "The Headlines" block
 * (the pattern that page introduced first) once Characters needed the exact
 * same shape — every figure here should already come from the same cached
 * transform its own subpage runs, so this stays in lockstep with them
 * rather than computing anything new.
 *
 * @package LezWatch.TV
 *
 * @var array $headlines {
 *   @type array $lead  Optional. { 'eyebrow'=>string, 'figure'=>string, 'text'=>string, 'url'=>string }.
 *                       Rendered as the fixed-style hero plate regardless of which stat it is.
 *   @type array $items [ key => { 'eyebrow'=>string, 'figure'=>string, 'text'=>string, 'url'=>string } ].
 *                       key must match a color entry in $lwtv-hl-families / $lwtv-hl-families-dark
 *                       (scss/addons/_stats.scss) or the rail item renders without a spine color.
 * }
 */

$headlines_lead  = ( isset( $headlines['lead'] ) && is_array( $headlines['lead'] ) ) ? $headlines['lead'] : array();
$headlines_items = ( isset( $headlines['items'] ) && is_array( $headlines['items'] ) ) ? $headlines['items'] : array();

// Fewer than four stats with data would render a lead plus a stub — skip
// the whole module instead, same edge-case rule the original block used.
$headlines_total = count( $headlines_items ) + ( empty( $headlines_lead ) ? 0 : 1 );

if ( $headlines_total < 4 ) {
	return;
}
?>
<section class="lwtv-hl-section" aria-labelledby="lwtv-hl-heading">
	<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section" id="lwtv-hl-heading"><?php esc_html_e( 'The Headlines', 'lwtv' ); ?></p>
	<div class="lwtv-hl bg-light">
		<?php if ( ! empty( $headlines_lead ) ) : ?>
			<a class="lwtv-hl-lead" href="<?php echo esc_url( $headlines_lead['url'] ); ?>">
				<span class="lwtv-hl-lead-figwrap">
					<span class="lwtv-hl-lead-label"><?php echo esc_html( $headlines_lead['eyebrow'] ); ?></span>
					<span class="lwtv-hl-lead-fig"><?php echo esc_html( $headlines_lead['figure'] ); ?></span>
				</span>
				<span class="lwtv-hl-lead-text"><?php echo esc_html( $headlines_lead['text'] ); ?> <span class="lwtv-hl-arrow" aria-hidden="true">&#8599;</span></span>
			</a>
		<?php endif; ?>
		<ul class="lwtv-hl-rail">
			<?php foreach ( $headlines_items as $headlines_key => $headlines_item ) : ?>
				<li>
					<a class="lwtv-hl-item lwtv-hl-item--<?php echo esc_attr( $headlines_key ); ?>" href="<?php echo esc_url( $headlines_item['url'] ); ?>">
						<span class="lwtv-hl-spine" aria-hidden="true"></span>
						<span class="lwtv-hl-body">
							<span class="lwtv-hl-head">
								<span class="lwtv-hl-label"><?php echo esc_html( $headlines_item['eyebrow'] ); ?></span>
								<span class="lwtv-hl-arrow" aria-hidden="true">&#8599;</span>
							</span>
							<span class="lwtv-hl-fig"><?php echo esc_html( $headlines_item['figure'] ); ?></span>
							<span class="lwtv-hl-text"><?php echo esc_html( $headlines_item['text'] ); ?></span>
						</span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
