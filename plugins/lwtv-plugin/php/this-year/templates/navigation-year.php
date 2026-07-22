<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * This Year year navigator: prev/next + dropdown + live chip + delta caption.
 *
 * The prev/next arrows are plain links and work without JavaScript; the
 * dropdown relies on its onchange handler, so no separate no-JS fallback is
 * provided for it beyond the arrows.
 *
 * @package LezWatch.TV
 *
 * @var int    $this_year
 * @var int    $current_year
 * @var int    $first_year
 * @var string $view
 */

$lwtv_view_suffix = ( 'overview' === $view ) ? '' : $view . '/';
$lwtv_year_url    = function ( $yr ) use ( $current_year, $lwtv_view_suffix ) {
	$base = ( (int) $yr === (int) $current_year ) ? '/this-year/' : '/this-year/' . (int) $yr . '/';
	return home_url( $base . $lwtv_view_suffix );
};
$lwtv_at_min      = ( $this_year <= $first_year );
$lwtv_at_max      = ( $this_year >= $current_year );
?>
<div class="lwtv-ty-yearnav">
	<div class="lwtv-ty-yearnav-controls">
		<?php if ( ! $lwtv_at_min ) : ?>
			<a class="lwtv-ty-yearnav-arrow" href="<?php echo esc_url( $lwtv_year_url( $this_year - 1 ) ); ?>" aria-label="<?php esc_attr_e( 'Previous year', 'lwtv' ); ?>"><?php echo lwtv_plugin()->get_symbolicon( svg: 'caret-left.svg', icon: 'svg-caret-left', max_size: '16' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
		<?php else : ?>
			<span class="lwtv-ty-yearnav-arrow is-disabled" aria-hidden="true"><?php echo lwtv_plugin()->get_symbolicon( svg: 'caret-left.svg', icon: 'svg-caret-left', max_size: '16' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<?php endif; ?>

		<label class="screen-reader-text" for="lwtv-ty-year"><?php esc_html_e( 'Choose year', 'lwtv' ); ?></label>
		<select id="lwtv-ty-year" class="lwtv-ty-yearnav-select" data-base="<?php echo esc_attr( home_url( '/this-year/%y/' . $lwtv_view_suffix ) ); ?>" onchange="window.location=this.dataset.base.replace('%y', this.value)">
			<?php for ( $lwtv_y = $current_year; $lwtv_y >= $first_year; $lwtv_y-- ) : ?>
				<option value="<?php echo (int) $lwtv_y; ?>"<?php selected( $lwtv_y, $this_year ); ?>><?php echo esc_html( (string) $lwtv_y ); ?></option>
			<?php endfor; ?>
		</select>

		<?php if ( ! $lwtv_at_max ) : ?>
			<a class="lwtv-ty-yearnav-arrow" href="<?php echo esc_url( $lwtv_year_url( $this_year + 1 ) ); ?>" aria-label="<?php esc_attr_e( 'Next year', 'lwtv' ); ?>"><?php echo lwtv_plugin()->get_symbolicon( svg: 'caret-right.svg', icon: 'svg-caret-right', max_size: '16' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
		<?php else : ?>
			<span class="lwtv-ty-yearnav-arrow is-disabled" aria-hidden="true"><?php echo lwtv_plugin()->get_symbolicon( svg: 'caret-right.svg', icon: 'svg-caret-right', max_size: '16' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<?php endif; ?>

		<?php
		if ( $this_year === $current_year ) {
			?>
			<span class="lwtv-ty-yearnav-live"><?php esc_html_e( 'Live · current year', 'lwtv' ); ?></span>
			<?php
		} elseif ( $lwtv_at_min ) {
			?>
			<span class="lwtv-ty-yearnav-live"><?php esc_html_e( 'First tracked year', 'lwtv' ); ?></span>
			<?php
		}
		?>
	</div>
	<?php if ( $this_year > $first_year ) : ?>
		<div class="lwtv-ty-yearnav-caption">
			<?php
			/* translators: %s: the prior year. */
			printf( esc_html__( 'Deltas compare against %s', 'lwtv' ), esc_html( (string) ( $this_year - 1 ) ) );
			?>
		</div>
	<?php endif; ?>
</div>
