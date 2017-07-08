<?php

/**
 * Register widget area.
 *
 * @link https://developer.wordpress.org/themes/functionality/sidebars/#registering-a-sidebar
 */
function lwtv_underscore_widgets_init() {
	register_sidebar( array(
		'name'          => esc_html__( 'Header', 'lwtv_underscore' ),
		'id'            => 'header-1',
		'description'   => esc_html__( 'This is the header widget area. It typically appears next to the site title or logo. This widget area is not suitable to display every type of widget, and works best with a custom menu, a search form, or possibly a text widget.', 'lwtv_underscore' ),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	) );

	register_sidebar( array(
		'name'          => esc_html__( 'Front Page Top', 'lwtv_underscore' ),
		'id'            => 'front-page-top',
		'description'   => esc_html__( 'This goes above the loop on the front page only. It\'s perfect for explaining what your site is about and presenting a welcome message.', 'lwtv_underscore' ),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	) );

	register_sidebar( array(
		'name'          => esc_html__( 'Front Page Bottom', 'lwtv_underscore' ),
		'id'            => 'front-page-bottom',
		'description'   => esc_html__( 'This goes below the loop on the front page only.', 'lwtv_underscore' ),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	) );

	register_sidebar( array(
		'name'          => esc_html__( 'Sidebar', 'lwtv_underscore' ),
		'id'            => 'sidebar-1',
		'description'   => esc_html__( 'This is the primary sidebar.', 'lwtv_underscore' ),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	) );

	register_sidebar( array(
		'name'          => esc_html__( 'Sidebar - Character Archives', 'lwtv_underscore' ),
		'id'            => 'archive-character-sidebar',
		'description'   => esc_html__( 'This is the sidebar for character archives.', 'lwtv_underscore' ),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	) );

	register_sidebar( array(
		'name'          => esc_html__( 'Sidebar - Show Archives', 'lwtv_underscore' ),
		'id'            => 'archive-show-sidebar',
		'description'   => esc_html__( 'This is the sidebar for show archives.', 'lwtv_underscore' ),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	) );

	register_sidebar( array(
		'name'          => esc_html__( 'After Entry', 'lwtv_underscore' ),
		'id'            => 'after-entry-1',
		'description'   => esc_html__( 'This goes below entries on single pages only.', 'lwtv_underscore' ),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	) );

	register_sidebar( array(
		'name'          => esc_html__( 'Footer', 'lwtv_underscore' ),
		'id'            => 'footer-1',
		'description'   => esc_html__( 'Footer widget area.', 'lwtv_underscore' ),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	) );

	register_sidebar( array(
		'name'          => esc_html__( 'After Footer', 'lwtv_underscore' ),
		'id'            => 'after-footer',
		'description'   => esc_html__( 'Below footer widget area.', 'lwtv_underscore' ),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	) );

}
add_action( 'widgets_init', 'lwtv_underscore_widgets_init' );

/**
 * Special Widget for Featured Custom Post Types
 */
require get_template_directory() . '/inc/featured-cpt.php';