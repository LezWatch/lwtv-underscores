<?php
/**
 * The sidebar containing the main widget area
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package LezWatch_TV
 */

if ( ! is_active_sidebar( 'sidebar-1' ) ) {
	return;
}
?>

<aside id="secondary" class="widget-area" role="complementary">
	<?php 
		// Currently only used for shows, but in case archives and
		// single pages need different sidebars:
		$type = 'archive';
		if ( is_single() )  $type = 'single';

		// Show the right sidebar for the page type:
		if ( get_post_type() == 'post_type_characters' && !is_search() ) {
			get_template_part( 'template-parts/sidebar/post_type_characters' );
		} elseif ( get_post_type() == 'post_type_shows' && !is_search() ) {
			get_template_part( 'template-parts/sidebar/post_type_shows-' . $type );
		} else { 
			dynamic_sidebar( 'sidebar-1' );
		}
	?>
</aside><!-- #secondary -->
