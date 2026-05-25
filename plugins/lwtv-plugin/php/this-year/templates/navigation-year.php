<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Navigation for the This Year pages
 *
 * @package LezWatch.TV
 *
 * Required variables:
 * @var string $this_year
 * @var string $baseurl
 * @var string $view
 */

if ( 'overview' === $view ) {
	$view = '';
}

$start_year = LWTV_FIRST_YEAR;
$baseurl    = str_replace( $this_year . '/', '', $baseurl );

?>

<nav aria-label="This Year Navigation" role="navigation" class="lwtv-pagination">
	<ul class="pagination justify-content-center">

		<?php
		// If it's not the oldest year there were queers, we can show the first year we have queers.
		if ( $this_year !== $start_year ) {
			?>
			<li class="page-item first me-auto"><a href="<?php echo esc_url( $baseurl . $start_year . '/' . $view ); ?>" class="page-link"><?php echo lwtv_plugin()->get_symbolicon( svg: 'caret-left-circle.svg', icon: 'svg-chevron-circle-left', max_size: '14' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> First (<?php echo (int) $start_year; ?>)</a></li>
			<li class="page-item"><a href="<?php echo esc_url( $baseurl . ( $this_year - 1 ) . '/' . $view ); ?>" title="previous year" class="page-link"><?php echo lwtv_plugin()->get_symbolicon( svg: 'caret-left.svg', icon: 'svg-chevron-left', max_size: '14' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> Previous</a></li>
			<li class="page-item"><a href="<?php echo esc_url( $baseurl . ( $this_year - 2 ) . '/' . $view ); ?>" class="page-link"><?php echo (int) ( $this_year - 2 ); ?></a></li>
			<li class="page-item"><a href="<?php echo esc_url( $baseurl . ( $this_year - 1 ) . '/' . $view ); ?>" class="page-link"><?php echo (int) ( $this_year - 1 ); ?></a></li>
			<?php
		}
		?>

		<li class="page-item active"><span class="active page-link"><?php echo (int) $this_year; ?></span></li>

		<?php
		if ( gmdate( 'Y' ) !== $this_year ) {
			?>
			<li class="page-item"><a href="<?php echo esc_url( $baseurl . ( $this_year + 1 ) . '/' . $view ); ?>" class="page-link"><?php echo (int) ( $this_year + 1 ); ?></a></li>
			<li class="page-item"><a href="<?php echo esc_url( $baseurl . ( $this_year + 1 ) . '/' . $view ); ?>" class="page-link" title="next year">Next <?php echo lwtv_plugin()->get_symbolicon( svg: 'caret-right.svg', icon: 'svg-chevron-right', max_size: '14' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a></li>
			<li class="page-item last ms-auto"><a href="<?php echo esc_url( $baseurl . gmdate( 'Y' ) . '/' . $view ); ?>" class="page-link">Last (<?php echo (int) gmdate( 'Y' ); ?>) <?php echo lwtv_plugin()->get_symbolicon( svg: 'caret-right-circle.svg', icon: 'svg-chevron-circle-right', max_size: '14' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a></li>
			<?php
		}
		?>
	</ul>
</nav><!-- .navigation -->
