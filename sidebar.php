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

<aside id="secondary" class="widget-area" role="complementary">
	<?php 
		// Show the right sidebar for the page type:
		if ( !is_singular() ) {
			switch ( get_post_type() ) {
				case 'post_type_characters':
					dynamic_sidebar( 'archive-character-sidebar' );
					break;
				case 'post_type_shows':
					dynamic_sidebar( 'archive-show-sidebar' );
					break;
				default:
					dynamic_sidebar( 'sidebar-2' );
			}
		} else {
			switch ( get_post_type() ) {
				case 'post_type_characters':
				case 'post_type_shows':
					get_template_part( 'template-parts/sidebar', get_post_type() );
					break;
				case 'page':
					if ( is_page( 'role' ) ) {
						dynamic_sidebar( 'archive-character-sidebar' );
					} else {
						dynamic_sidebar( 'sidebar-2' );						
					}
					break;
				default:
					dynamic_sidebar( 'sidebar-2' );
			}
		}
	?>
</aside><!-- #secondary -->