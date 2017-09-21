<?php
/**
 * The Sidebar containing the main widget areas.
 *
 * @package YIKES Starter
 */

if ( ! is_active_sidebar( 'sidebar-2' ) ) {
	return;
}
?>

<div id="secondary" class="widget-area" role="complementary">
	<?php do_action( 'before_sidebar' ); ?>
	<?php dynamic_sidebar( 'sidebar-2' ); ?>
</div><!-- #secondary -->
