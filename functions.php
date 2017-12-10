<?php
/**
 * YIKES Starter functions and definitions
 *
 * @package YIKES Starter
 */
 
// Define theme version so CSS shit doesn't break. Again.
if ( ! defined( 'LWTV_THEME_VERSION' ) ) {
	define( 'LWTV_THEME_VERSION', '3.1.3' );
}

/* Set the content width based on the theme's design and stylesheet.  */
if ( ! isset( $content_width ) ) {
	$content_width = 825; /* pixels */
}

/************* YIKES Stuff ********************/

// YIKES Setup theme constants These will be used for server and web paths so we don't have to reference functions every time
if ( ! defined( 'YKS_THEME_PATH' ) ) {
	define( 'YKS_THEME_PATH', get_stylesheet_directory() );
}
if ( ! defined( 'YKS_THEME_URL' ) ) {
	define( 'YKS_THEME_URL', trailingslashit( get_stylesheet_directory_uri() ) );
}

// Get the title of the Posts page
function yikes_starter_blog_page_title() {
	if ( get_option( 'page_for_posts' ) ) {
		return get_the_title( get_option( 'page_for_posts' ) );
	}
}

/**
* Get the featured image of the page defined for posts.
* 
* @param mixed | $image_size | A valid image size value. Could be a string like 'full' or an array with height/width values. 
*							   See the wp_get_attachment_image_url() function documentation for more details.
*
* @return Image URL if found, else false
*/
function yikes_starter_blog_page_featured_image( $image_size = 'full' ) {
	$page_id_for_posts = get_option( 'page_for_posts' );
	if ( ! empty( $page_id_for_posts ) && has_post_thumbnail( $page_id_for_posts ) ) {
		$post_thumbnail_id = get_post_thumbnail_id( $page_id_for_posts );
		return esc_url( wp_get_attachment_image_url( $post_thumbnail_id, $image_size ) );
	}

	// If no image is found, return false
	return false;
}

/**
 * Get the ID of a page based on the template it's using.
 *
 * @param string | $template	  | The name of a template, e.g. page-home.php
 * @param bool   | $use_transient | Whether we should set/check a transient value before querying
 * 
 * @return mixed | Page ID if found, else false
 **/
function yks_get_page_by_template( $template, $use_transient = false ) {

	if ( $use_transient === true ) {
		$page_id = get_transient( "wp_page_template_{$template}" );

		if ( ! empty( $page_id ) ) {
			return $page_id;
		}
	}
	
	if ( empty( $page_id ) ) {
		$pages = new WP_Query( array(
			'post_type'      => 'page',
			'posts_per_page' => '-1',
			'fields'         => 'ids',
		) );

		if ( $pages->have_posts() ) {
			foreach( $pages->posts as $page_id ) {
				$pagetemplate = get_post_meta( $page_id, '_wp_page_template', true );
				if ( $pagetemplate === $template ) {

					if ( $use_transient === true ) {
						set_transient( "wp_page_template_{$template}", $page_id, 1 * HOUR_IN_SECONDS );
					}

					return $page_id;
				}
			}
			wp_reset_postdata();
		}
	}

	return false;
}


/************* Excerpts *************/
function yks_excerpt_more( $more ) {
	return '...';
}
add_filter( 'excerpt_more', 'yks_excerpt_more' );

/************* Widgets *************/

// load my widgets
require_once 'inc/widgets/social-nav-widget.php';
require_once 'inc/widgets/character-widget.php';
require_once 'inc/widgets/show-widget.php';
require_once 'inc/widgets/filter-widget1.php';
require_once 'inc/widgets/filter-widget2.php';


/************* Theme Logo *************/

function yks_the_custom_logo() {
	
	if ( function_exists( 'the_custom_logo' ) ) {
		the_custom_logo();
	}

}


/************* Images *************/

/*
 * Custom Image Sizes
 *
 * character-img - used on show and character pages
 * show-img - used as header image for shows
 */
add_image_size( 'character-img', 350, 412, true );
add_image_size( 'show-img', 960, 400, true );
add_image_size( 'postloop-img', 525, 300, true ); // hard crop mode


/************* Comments *************/
// Comments Walker
require_once 'inc/walker-comment.php';


/************* Authors *************/
// Author bio box
require_once 'inc/author-box.php';


/************* Archives  *************/
// get rid of the “Category:”, “Tag:”, “Author:”, “Archives:” and “Other taxonomy name:” in the archive title

function yikes_archive_title( $title ) {
	if ( is_category() ) {
		$title = single_cat_title( '', false );
	} elseif ( is_tag() ) {
		$title = single_tag_title( '', false );
	} elseif ( is_author() ) {
		$title = '<span class="vcard">' . get_the_author() . '</span>';
	} elseif ( is_post_type_archive() ) {
		$title = post_type_archive_title( '', false );
	} elseif ( is_tax() ) {
		$title = single_term_title( '', false );
	}
  
	return $title;
}
 
add_filter( 'get_the_archive_title', 'yikes_archive_title' );


/************* Theme Setup *************/

function yikes_starter_add_editor_styles() {
	add_editor_style( 'custom-editor-style.css' );
}
add_action( 'admin_init', 'yikes_starter_add_editor_styles' );

if ( ! function_exists( 'yikes_starter_setup' ) ) {
	/* Sets up theme defaults and registers support for various WordPress features. */
	function yikes_starter_setup() {

		/* Set up Nav menus */
		register_nav_menus( array(
			'primary' => __( 'Primary Menu', 'yikes_starter' ),
			'social_menu' => __( 'Social Menu', 'yikes_starter' ),
		) );

		 /*
		  Make theme available for translation.
		 * Translations can be filed in the /languages/ directory.
		 * If you're building a theme based on yikes starter, use a find and replace
		 * to change 'yikes_starter' to the name of your theme in all the template files
		 */
		load_theme_textdomain( 'yikes_starter', get_template_directory() . '/languages' );

		 /*
		 * Let WordPress manage the document title.
		 * By adding theme support, we declare that this theme does not use a
		 * hard-coded <title> tag in the document head, and expect WordPress to
		 * provide it for us.
		 */
		add_theme_support( 'title-tag' );

		/* Add default posts and comments RSS feed links to head */
		add_theme_support( 'automatic-feed-links' );

		/* Enable support for Post Thumbnails on posts and pages */
		add_theme_support( 'post-thumbnails' );

		/* Enable support for a Theme Logo */
		add_theme_support( 'custom-logo' );

		/* Switch default core markup for search form, comment form, and comments to output valid HTML5.  */
		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption' ) );

		// Enable shortcodes in text widgets
		add_filter('widget_text','do_shortcode');

		/*
		 Enable support for Post Formats */
		// add_theme_support( 'post-formats', array( 'aside', 'image', 'video', 'quote', 'link' ) );
	}
}// End if().
add_action( 'after_setup_theme', 'yikes_starter_setup' );


/* Register widgetized areas and update sidebar with default widgets  */
function yikes_starter_widgets_init() {
	// Home Sidebar
	register_sidebar( array(
		'name'          => __( 'Home Page Sidebar', 'yikes_starter' ),
		'id'            => 'sidebar-1',
		'description'   => 'The sidebar for the home page',
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget'  => '</aside>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	) );
	// Sub Page Sidebar
	register_sidebar( array(
		'name'          => __( 'Sub Page Sidebar', 'yikes_starter' ),
		'id'            => 'sidebar-2',
		'description'   => 'The sidebar for sub pages',
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget'  => '</aside>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	) );
	// Dead Character Widget
	register_sidebar( array(
		'name'          => __( 'Widget Area of Death', 'yikes_starter' ),
		'id'            => 'dead-1',
		'description'   => 'The home page header widget with the last dead character.',
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget'  => '</aside>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	) );
	// Footer Widget 1
	register_sidebar( array(
		'name'          => __( 'Footer Widget Area One', 'yikes_starter' ),
		'id'            => 'footer-1',
		'description'   => 'The first footer widget area.',
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget'  => '</aside>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	) );
	// Footer Widget 2
	register_sidebar( array(
		'name'          => __( 'Footer Widget Area Two', 'yikes_starter' ),
		'id'            => 'footer-2',
		'description'   => 'The second footer widget area.',
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget'  => '</aside>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	) );
	// Footer Widget 3
	register_sidebar( array(
		'name'          => __( 'Footer Widget Area Three', 'yikes_starter' ),
		'id'            => 'footer-3',
		'description'   => 'The third footer widget area.',
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget'  => '</aside>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	) );
	// Footer Widget 4
	register_sidebar( array(
		'name'          => __( 'Footer Widget Area Four', 'yikes_starter' ),
		'id'            => 'footer-4',
		'description'   => 'The third footer widget area.',
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget'  => '</aside>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	) );
	// Credits Widget
	register_sidebar( array(
		'name'          => __( 'Bottom Footer Widget Area', 'yikes_starter' ),
		'id'            => 'subfooter-1',
		'description'   => 'The bottom footer credits area.',
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget'  => '</aside>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	) );
	// Sidebar for Actore Archives
	register_sidebar( array(
		'name'          => esc_html__( 'Sidebar - Actor Archives', 'lwtv_yikes' ),
		'id'            => 'archive-actor-sidebar',
		'description'   => esc_html__( 'This is the sidebar for actor archives.', 'lwtv_yikes' ),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	) );
	// Sidebar for Character Archives
	register_sidebar( array(
		'name'          => esc_html__( 'Sidebar - Character Archives', 'lwtv_yikes' ),
		'id'            => 'archive-character-sidebar',
		'description'   => esc_html__( 'This is the sidebar for character archives.', 'lwtv_yikes' ),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	) );
	// Sidebar for Show Archives
	register_sidebar( array(
		'name'          => esc_html__( 'Sidebar - Show Archives', 'lwtv_yikes' ),
		'id'            => 'archive-show-sidebar',
		'description'   => esc_html__( 'This is the sidebar for show archives.', 'lwtv_yikes' ),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	) );
}
add_action( 'widgets_init', 'yikes_starter_widgets_init' );

/************* Bootstrap Stuff *************/

// Navigation Walker
require_once 'inc/wp_bootstrap_navwalker.php';

/*
  Pagination
*	@usage
*	1) setup your WP_Query with a $paged variable (https://codex.wordpress.org/Pagination#Adding_the_.22paged.22_parameter_to_a_query)
*	2) Wherever you'd like the pagination to appear, add <?php echo page_navi( $query ); ?> where $query is the entire $query setup in the previous step
*/
require_once 'inc/wp_bootstrap_pagination.php';

// Add classes to “next_post_link” and “previous_post_link”

add_filter('next_post_link', 'post_link_attributes');
add_filter('previous_post_link', 'post_link_attributes');

function post_link_attributes($output) {
	$code = 'class="page-link"';
	return str_replace('<a href=', '<a '.$code.' href=', $output);
}

add_filter('next_posts_link_attributes', 'posts_link_attributes');
add_filter('previous_posts_link_attributes', 'posts_link_attributes');

function posts_link_attributes() {
	return 'class="page-link"';
}


/*************  Enqueue scripts and styles *************/

function yikes_starter_scripts() {
	wp_enqueue_style( 'yikes-starter-style', get_stylesheet_directory_uri() . '/style.min.css', array(), LWTV_THEME_VERSION );

	// combined + minified
	// navigation.js & skip-link-focus-fix.js
	wp_enqueue_script( 'yikes-starter-navigation', get_template_directory_uri() . '/inc/js/yikes-theme-scripts.min.js', array(), '20120206', true );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}

	wp_enqueue_style( 'bootstrap', get_template_directory_uri() . '/inc/bootstrap/css/bootstrap.min.css', array(), '4.0.0-beta', 'all' );
	wp_enqueue_style( 'fontawesome', get_template_directory_uri() . '/inc/css/fontawesome-5.min.css', array(), '5.0.1', 'all' );
	wp_enqueue_style( 'open-sans', '//fonts.googleapis.com/css?family=Open+Sans:400,600,700', false );
	wp_enqueue_style( 'oswald', '//fonts.googleapis.com/css?family=Oswald:400,500', false );
	wp_enqueue_script( 'yikes-popper-script', get_template_directory_uri() . '/inc/js/popper.min.js',  array(), '1.11.0', 'all', true );
	wp_enqueue_script( 'bootstrap', get_template_directory_uri() . '/inc/bootstrap/js/bootstrap.min.js', array( 'jquery' ), '4.0.0-beta', 'all', true );
}

add_action( 'wp_enqueue_scripts', 'yikes_starter_scripts' );

/************* Admin *************/

// Block the admin bar for non-admins
if ( ! current_user_can( 'administrator' ) ) {
	show_admin_bar( false );
}

/* Custom template tags for this theme. */
require get_template_directory() . '/inc/template-tags.php';

/* Custom functions that act independently of the theme templates. */
require get_template_directory() . '/inc/extras.php';

/* Customizer additions. */
require get_template_directory() . '/inc/customizer.php';


/************* Optional Items *************/

/* Implement the Custom Header feature. */
require get_template_directory() . '/inc/custom-header.php';

/* Remove custom background support */
remove_theme_support( 'custom-background' );

/* Load AMP functionality file. */
require get_template_directory() . '/inc/amp.php';

/* Load FacetWP compatibility file. */
require get_template_directory() . '/inc/facet.php';

/* Load Mika's Queer compatibility file. */
require get_template_directory() . '/inc/lesbians.php';