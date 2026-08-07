<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Reusable "matchup card" grid: stacked cards pairing two terms with a
 * shows-together count.
 *
 * Genre Stats' alternative to the lollipop-list Common Pairings treatment
 * used by Tropes/Intersectionality — same underlying Intersection_Pairs
 * data, a denser card layout instead of a ranked list. Deliberately
 * unlinked by default: pass a 'url' per item only once a FacetWP
 * multi-value param is confirmed for the taxonomy in question.
 *
 * @package LezWatch.TV
 *
 * @var array $matchup {
 *   @type array  $items  [ { 'a'=>string, 'b'=>string, 'count'=>int }, … ]. No link support yet —
 *                         add one once a FacetWP multi-value param is confirmed for the taxonomy.
 *   @type string $family Color family (matches .lwtv-panel-icon.<family> / .lwtv-bars--<family>).
 *   @type string $svg    Header icon sprite file.
 *   @type string $icon   Header icon fallback key.
 *   @type string $title  Panel heading.
 *   @type string $sub    Panel sub-line (optional).
 *   @type string $unit   Trailing label under each count (e.g. "shows together").
 *   @type array  $footer Optional small stat appended at the bottom of this same
 *                        panel, below a double-line rule — for a caveat number
 *                        that doesn't belong in the grid above (e.g. items this
 *                        taxonomy's minimum-pairing-size excludes entirely).
 *                        { 'title'=>string, 'number'=>string }.
 * }
 */

$matchup_items = ( isset( $matchup['items'] ) && is_array( $matchup['items'] ) ) ? $matchup['items'] : array();

if ( empty( $matchup_items ) ) {
	return;
}
?>
<section class="lwtv-panel bg-light lwtv-matchups lwtv-bars--<?php echo esc_attr( $matchup['family'] ?? 'shows' ); ?>">
	<header class="lwtv-panel-head">
		<span class="lwtv-panel-icon <?php echo esc_attr( $matchup['family'] ?? 'shows' ); ?>">
			<?php echo lwtv_plugin()->get_symbolicon( svg: $matchup['svg'] ?? 'tag.svg', icon: $matchup['icon'] ?? 'svg-tag', max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</span>
		<div>
			<h2 class="lwtv-panel-title"><?php echo esc_html( $matchup['title'] ?? '' ); ?></h2>
			<?php if ( ! empty( $matchup['sub'] ) ) : ?>
				<p class="lwtv-panel-sub"><?php echo esc_html( $matchup['sub'] ); ?></p>
			<?php endif; ?>
		</div>
	</header>
	<div class="lwtv-matchup-grid">
		<?php foreach ( $matchup_items as $matchup_item ) : ?>
			<div class="lwtv-matchup-row">
				<span class="lwtv-matchup-count">
					<?php echo esc_html( number_format_i18n( (int) ( $matchup_item['count'] ?? 0 ) ) ); ?>
					<?php if ( ! empty( $matchup['unit'] ) ) : ?>
						<span class="screen-reader-text"><?php echo esc_html( $matchup['unit'] ); ?></span>
					<?php endif; ?>
				</span>
				<span class="lwtv-matchup-pair">
					<?php echo esc_html( $matchup_item['a'] ?? '' ); ?>
					<span class="lwtv-matchup-plus" aria-hidden="true">+</span>
					<?php echo esc_html( $matchup_item['b'] ?? '' ); ?>
				</span>
			</div>
		<?php endforeach; ?>
	</div>
	<?php if ( ! empty( $matchup['footer']['number'] ) ) : ?>
		<div class="lwtv-matchup-footer">
			<?php if ( ! empty( $matchup['footer']['title'] ) ) : ?>
				<h3 class="lwtv-matchup-footer-title"><?php echo esc_html( $matchup['footer']['title'] ); ?></h3>
			<?php endif; ?>
			<span class="lwtv-matchup-footer-number"><?php echo esc_html( $matchup['footer']['number'] ); ?></span>
		</div>
	<?php endif; ?>
</section>
