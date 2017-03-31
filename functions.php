<?php
/**
 * LezWatch TV functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package LezWatch_TV
 */

if ( ! function_exists( 'lwtv_underscore_setup' ) ) :
/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
function lwtv_underscore_setup() {
	/*
	 * Make theme available for translation.
	 * Translations can be filed in the /languages/ directory.
	 * If you're building a theme based on LezWatch TV, use a find and replace
	 * to change 'lwtv_underscore' to the name of your theme in all the template files.
	 */
	load_theme_textdomain( 'lwtv_underscore', get_template_directory() . '/languages' );

	// Add default posts and comments RSS feed links to head.
	add_theme_support( 'automatic-feed-links' );

	/*
	 * Let WordPress manage the document title.
	 * By adding theme support, we declare that this theme does not use a
	 * hard-coded <title> tag in the document head, and expect WordPress to
	 * provide it for us.
	 */
	add_theme_support( 'title-tag' );

	/*
	 * Enable support for Post Thumbnails on posts and pages.
	 *
	 * @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
	 */
	add_theme_support( 'post-thumbnails' );

	// This theme uses wp_nav_menu() in one location.
	register_nav_menus( array(
		'menu-1' => esc_html__( 'Primary', 'lwtv_underscore' ),
	) );

	/*
	 * Switch default core markup for search form, comment form, and comments
	 * to output valid HTML5.
	 */
	add_theme_support( 'html5', array(
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
	) );

	// Set up the WordPress core custom background feature.
	add_theme_support( 'custom-background', apply_filters( 'lwtv_underscore_custom_background_args', array(
		'default-color' => 'ffffff',
		'default-image' => '',
	) ) );

	// Add theme support for selective refresh for widgets.
	add_theme_support( 'customize-selective-refresh-widgets' );
}
endif;
add_action( 'after_setup_theme', 'lwtv_underscore_setup' );

/**
 * Set the content width in pixels, based on the theme's design and stylesheet.
 *
 * Priority 0 to make it available to lower priority callbacks.
 *
 * @global int $content_width
 */
function lwtv_underscore_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'lwtv_underscore_content_width', 640 );
}
add_action( 'after_setup_theme', 'lwtv_underscore_content_width', 0 );

/**
 * Register widget area.
 *
 * @link https://developer.wordpress.org/themes/functionality/sidebars/#registering-a-sidebar
 */
function lwtv_underscore_widgets_init() {
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
		'name'          => esc_html__( 'Header', 'lwtv_underscore' ),
		'id'            => 'header-1',
		'description'   => esc_html__( 'This is the header widget area. It typically appears next to the site title or logo. This widget area is not suitable to display every type of widget, and works best with a custom menu, a search form, or possibly a text widget.', 'lwtv_underscore' ),
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

}
add_action( 'widgets_init', 'lwtv_underscore_widgets_init' );

/**
 * Enqueue scripts and styles.
 */
function lwtv_underscore_scripts() {
	wp_enqueue_style( 'lwtv_underscore-style', get_stylesheet_uri() );
	wp_enqueue_style( 'lwtv_underscore-style-sidebar', get_template_directory_uri() . '/layouts/content-sidebar.css' );

	wp_enqueue_script( 'lwtv_underscore-navigation', get_template_directory_uri() . '/js/navigation.js', array(), '20151215', true );

	wp_enqueue_script( 'lwtv_underscore-skip-link-focus-fix', get_template_directory_uri() . '/js/skip-link-focus-fix.js', array(), '20151215', true );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'lwtv_underscore_scripts' );

/**
 * Implement the Custom Header feature.
 */
require get_template_directory() . '/inc/custom-header.php';

/**
 * Custom template tags for this theme.
 */
require get_template_directory() . '/inc/template-tags.php';

/**
 * Custom functions that act independently of the theme templates.
 */
require get_template_directory() . '/inc/extras.php';

/**
 * Customizer additions.
 */
require get_template_directory() . '/inc/customizer.php';

/**
 * Load Jetpack compatibility file.
 */
require get_template_directory() . '/inc/jetpack.php';

/**
 * Symbolicons should be defined by the plugin but just in case...
 */
if ( !defined( 'LWTV_SYMBOLICONS_URL' ) )
	define( 'LWTV_SYMBOLICONS_URL', WP_PLUGIN_URL . '/lwtv-plugin/symbolicons/images' );
if ( !defined( 'LWTV_SYMBOLICONS_PATH' ) )
	define( 'LWTV_SYMBOLICONS_PATH', WP_PLUGIN_DIR . '/lwtv-plugin/symbolicons/images' );

/**
 * Custom code
 */
include_once( get_stylesheet_directory() . '/inc/search.php' );       // Search
include_once( get_stylesheet_directory() . '/inc/amp.php' );          // AMP

/*
 * Custom Image Sizes
 *
 * character-img - used on show and character pages
 * show-img - used as header image for shows
 */
add_image_size( 'character-img', 225, 300, true );
add_image_size( 'show-img', 960, 400, true );

/*
 * Header Image
 */
function lwtv_underscore_header() {
	$header_image = get_stylesheet_directory_uri().'/images/true-toaster.png';
	printf( '<span role="img" aria-label="Toaster" title="Toaster" class="logo toaster"><a href="/"><img src="%s" alt="Rainbow Toaster" title="Rainbow Toaster" width="100px" height="100px" /></a></span>', $header_image );
}

/* 
 * Numeric Post Navigation
 */
function lwtv_underscore_numeric_posts_nav( $query = 'wp_query', $count = null ) {
	
	if( is_singular( 'post' ) )
		return;

	if ( $query == 'wp_query' ) {
		global $wp_query;
		$query = $wp_query;
	}

	$posts_per_page = get_option('posts_per_page');

	if ( $count == null ) {
		$post_type = ( $query->post_type == '' )? 'post' : $query->post_type; 
		$published_posts = wp_count_posts( $post_type )->publish;
		$page_number_max = ceil( $published_posts / $posts_per_page );
	} else {
		$published_posts = ceil( $count / $posts_per_page );
		$page_number_max = ceil( $count / $posts_per_page );
	}
	
	/** Stop execution if there's only 1 page */
	if( $page_number_max <= 1 )
		return;

	$paged = get_query_var( 'paged' ) ? absint( get_query_var( 'paged' ) ) : 1;
	$max   = intval( $published_posts );

	/**	Add current page to the array */
	if ( $paged >= 1 )
		$links[] = $paged;

	/**	Add the pages around the current page to the array */
	if ( $paged >= 3 ) {
		$links[] = $paged - 1;
		$links[] = $paged - 2;
	}

	if ( ( $paged + 2 ) <= $max ) {
		$links[] = $paged + 2;
		$links[] = $paged + 1;
	}

	echo '<div class="navigation"><ul>' . "\n";

	/**	Previous Post Link */
	if ( get_previous_posts_link() )
		printf( '<li>%s</li>' . "\n", get_previous_posts_link() );

	/**	Link to first page, plus ellipses if necessary */
	if ( ! in_array( 1, $links ) ) {
		$class = 1 == $paged ? ' class="active"' : '';

		printf( '<li%s><a href="%s">%s</a></li>' . "\n", $class, esc_url( get_pagenum_link( 1 ) ), '1' );

		if ( ! in_array( 2, $links ) )
			echo '<li>…</li>';
	}

	/**	Link to current page, plus 2 pages in either direction if necessary */
	sort( $links );
	foreach ( (array) $links as $link ) {
		$class = $paged == $link ? ' class="active"' : '';
		printf( '<li%s><a href="%s">%s</a></li>' . "\n", $class, esc_url( get_pagenum_link( $link ) ), $link );
	}

	/**	Link to last page, plus ellipses if necessary */
	if ( ! in_array( $max, $links ) ) {
		if ( ! in_array( $max - 1, $links ) )
			echo '<li>…</li>' . "\n";

		$class = $paged == $max ? ' class="active"' : '';
		printf( '<li%s><a href="%s">%s</a></li>' . "\n", $class, esc_url( get_pagenum_link( $max ) ), $max );
	}

	/**	Next Post Link */
	if ( get_next_posts_link() )
		printf( '<li>%s</li>' . "\n", get_next_posts_link() );

	echo '</ul></div>' . "\n";

}

/*
 * Archive Sort Order
 *
 * Everything BUT regular posts go alphabetical
 */
add_action( 'pre_get_posts', 'lwtv_underscore_archive_sort_order' ); 
function lwtv_underscore_archive_sort_order( $query ) {
	if ( is_archive() && !is_post_type_archive( 'post' ) ) {
		$query->set( 'order', 'ASC' );
		$query->set( 'orderby', 'title' );
	}
}

/*
 * Remove Jetpack becuase it's stupid. We'll add it back
 */
add_action( 'loop_start', 'lwtv_underscore_jetpack_remove_share' );
function lwtv_underscore_jetpack_remove_share() {
	remove_filter( 'the_content', 'sharing_display', 19 );
	remove_filter( 'the_excerpt', 'sharing_display', 19 );
	if ( class_exists( 'Jetpack_Likes' ) ) {
		remove_filter( 'the_content', array( Jetpack_Likes::init(), 'post_likes' ), 30, 1 );
	}
}

/*
 * Jetpack Post Meta
 *
 * Force Jetpack to be where we want it, not where it wants.
 */
function lwtv_underscore_jetpack_post_meta( ) {
	if ( function_exists( 'sharing_display' ) ) sharing_display( '', true );

	if ( class_exists( 'Jetpack_Likes' ) ) {
		$custom_likes = new Jetpack_Likes;
		$post_meta = $custom_likes->post_likes( '' );
	}
}
/*
 * Jetpack Post Meta
 *
 * Force Jetpack to be where we want it, not where it wants.
 */

function lwtv_underscore_author_box(){}