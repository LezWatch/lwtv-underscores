<?php
/**
 * YIKES Starter functions and definitions
 *
 * @package YIKES Starter
 */

/**
 * Catch for if the plugin is missing, because we really, really,
 * need it.
 */
if ( ! function_exists( 'lwtv_plugin' ) ) {
	add_action( 'admin_notices', 'lwtv_admin_notice_missing_plugin' );
}

function lwtv_admin_notice_missing_plugin() {
	?>
	<div class="notice notice-error is-dismissible">
		<p><?php esc_html_e( 'Error! The LWTV Plugin is missing. Please go grab an admin because everything will break.', 'lwtv-underscores' ); ?></p>
	</div>
	<?php
}

// Versioning for efficient developers.
if ( ! defined( 'LWTV_THEME_VERSION' ) ) {
	$versions = array(
		'lwtv-underscores' => '6.0.4',    // Bump this any time you make serious CSS changes.
		'font-awesome'     => '6.5.2',    // Bump when you update Font Awesome.
		'bootstrap'        => '5.3.3',    // Bump when you update bootstrap.
		'lwtv-blocks'      => '1.0.0',    // Bump when you update the blocks.
		'yikes-nav'        => '20240318', // Last date this was updated.
	);
	define( 'LWTV_THEME_VERSION', $versions );
}

/**
 * Set the content width based on the theme's design and stylesheet.
 */
if ( ! isset( $content_width ) ) {
	$content_width = 825; /* pixels */
}

/**
 * YIKES Stuff
 */

// YIKES Setup theme constants These will be used for server and web paths.
// so we don't have to reference functions every time.
if ( ! defined( 'LWTV_THEME_PATH' ) ) {
	define( 'LWTV_THEME_PATH', get_stylesheet_directory() );
}
if ( ! defined( 'LWTV_THEME_URL' ) ) {
	define( 'LWTV_THEME_URL', trailingslashit( get_stylesheet_directory_uri() ) );
}

/**
 * Get the title of the Posts page.
 */
function yikes_starter_blog_page_title() {
	if ( get_option( 'page_for_posts' ) ) {
		return get_the_title( get_option( 'page_for_posts' ) );
	}
}

/**
 * Excerpts
 *
 * @param string $more set the more ellipsis.
 */
function yks_excerpt_more( $more ) {
	$more = '...';
	return $more;
}
add_filter( 'excerpt_more', 'yks_excerpt_more' );

/**
 * Filter the except length to 20 words.
 *
 * @param int $length Excerpt length.
 * @return int (Maybe) modified excerpt length.
 */
function lwtv_custom_excerpt_length( $length ) {
	$length = 20;
	return $length;
}
add_filter( 'excerpt_length', 'lwtv_custom_excerpt_length', 999 );

/**
 * Widgets
 */
require_once 'inc/widgets/social-nav-widget.php';
require_once 'inc/widgets/character-widget.php';
require_once 'inc/widgets/show-widget.php';
require_once 'inc/widgets/filter-widget1.php';
require_once 'inc/widgets/filter-widget2.php';
require_once 'inc/widgets/otd-widget.php';


/**
 * Images
 */

/*
 * Custom Image Sizes
 *
 * character-img   - Used on show and character pages
 * show-img        - Used as header image for shows
 * postloop-img    - Used in the post loop
 * relatedshow-img - Used in the related shows section
 * headshot-tiny   - Used on relationships lists
 */
add_image_size( 'character-img', 350, 412, true );
add_image_size( 'show-img', 960, 400, true );
add_image_size( 'postloop-img', 525, 300, true );
add_image_size( 'relatedshow-img', 340, 150, true );
add_image_size( 'headshot-tiny', 50, 50, true );

/**
 * Comments
 */
require_once 'inc/walker-comment.php';

/**
 * Authors
 *
 * Add bio box.
 */
require_once 'inc/author-box.php';

/**
 * Archives
 *
 * @param string $title get rid of the “Category:”, “Tag:”, “Author:”, “Archives:”
 *  and “Other taxonomy name:” in the archive title.
 */
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

/**
 * Theme Setup
 */
function yikes_starter_add_editor_styles() {
	add_editor_style( 'style.css' );
	add_editor_style( 'custom-editor-style.css' );
}
add_action( 'admin_init', 'yikes_starter_add_editor_styles' );

if ( ! function_exists( 'yikes_starter_setup' ) ) {
	/**
	 * Sets up theme defaults and registers support for various WordPress features. */
	function yikes_starter_setup() {

		/**
		 * Set up Nav menus */
		register_nav_menus(
			array(
				'primary'     => __( 'Primary Menu', 'lwtv-underscores' ),
				'social_menu' => __( 'Social Menu', 'lwtv-underscores' ),
			)
		);

		/**
		 * Make theme available for translation.
		 * Translations can be filed in the /languages/ directory.
		 * If you're building a theme based on yikes starter, use a find and replace
		 * to change 'yikes_starter' to the name of your theme in all the template files
		 */
		load_theme_textdomain( 'yikes_starter', get_template_directory() . '/languages' );

		/**
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

		// Enable shortcodes in text widgets.
		add_filter( 'widget_text', 'do_shortcode' );

		// Enable editor styles.
		add_theme_support( 'editor-styles' );
	}
}

add_action( 'after_setup_theme', 'yikes_starter_setup' );

/**
 * Register widgetized areas and update sidebar with default widgets
 */
function yikes_starter_widgets_init() {
	// Home Sidebar.
	register_sidebar(
		array(
			'name'          => __( 'Home Page Sidebar', 'lwtv-underscores' ),
			'id'            => 'sidebar-1',
			'description'   => 'The sidebar for the home page',
			'before_widget' => '<aside id="%1$s" class="widget %2$s">',
			'after_widget'  => '</aside>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
	// Sub Page Sidebar.
	register_sidebar(
		array(
			'name'          => __( 'Sub Page Sidebar', 'lwtv-underscores' ),
			'id'            => 'sidebar-2',
			'description'   => 'The sidebar for sub pages',
			'before_widget' => '<aside id="%1$s" class="widget %2$s">',
			'after_widget'  => '</aside>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
	// Footer Widget 1.
	register_sidebar(
		array(
			'name'          => __( 'Footer Widget Area One', 'lwtv-underscores' ),
			'id'            => 'footer-1',
			'description'   => 'The first footer widget area.',
			'before_widget' => '<aside id="%1$s" class="widget %2$s">',
			'after_widget'  => '</aside>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
	// Footer Widget 2.
	register_sidebar(
		array(
			'name'          => __( 'Footer Widget Area Two', 'lwtv-underscores' ),
			'id'            => 'footer-2',
			'description'   => 'The second footer widget area.',
			'before_widget' => '<aside id="%1$s" class="widget %2$s">',
			'after_widget'  => '</aside>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
	// Footer Widget 3.
	register_sidebar(
		array(
			'name'          => __( 'Footer Widget Area Three', 'lwtv-underscores' ),
			'id'            => 'footer-3',
			'description'   => 'The third footer widget area.',
			'before_widget' => '<aside id="%1$s" class="widget %2$s">',
			'after_widget'  => '</aside>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
	// Footer Widget 4.
	register_sidebar(
		array(
			'name'          => __( 'Footer Widget Area Four', 'lwtv-underscores' ),
			'id'            => 'footer-4',
			'description'   => 'The third footer widget area.',
			'before_widget' => '<aside id="%1$s" class="widget %2$s">',
			'after_widget'  => '</aside>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
	// Credits Widget.
	register_sidebar(
		array(
			'name'          => __( 'Bottom Footer Widget Area', 'lwtv-underscores' ),
			'id'            => 'subfooter-1',
			'description'   => 'The bottom footer credits area.',
			'before_widget' => '<aside id="%1$s" class="widget %2$s">',
			'after_widget'  => '</aside>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
	// Sidebar for Actor Archives.
	register_sidebar(
		array(
			'name'          => esc_html__( 'Sidebar - Actor Archives', 'lwtv-underscores' ),
			'id'            => 'archive-actor-sidebar',
			'description'   => esc_html__( 'This is the sidebar for actor archives.', 'lwtv-underscores' ),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
	// Sidebar for Character Archives.
	register_sidebar(
		array(
			'name'          => esc_html__( 'Sidebar - Character Archives', 'lwtv-underscores' ),
			'id'            => 'archive-character-sidebar',
			'description'   => esc_html__( 'This is the sidebar for character archives.', 'lwtv-underscores' ),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
	// Sidebar for Show Archives.
	register_sidebar(
		array(
			'name'          => esc_html__( 'Sidebar - Show Archives', 'lwtv-underscores' ),
			'id'            => 'archive-show-sidebar',
			'description'   => esc_html__( 'This is the sidebar for show archives.', 'lwtv-underscores' ),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
}

add_action( 'widgets_init', 'yikes_starter_widgets_init' );

/**
 * Bootstrap Stuff
 */
// Navigation Walker.
/**
 * Register Custom Navigation Walker
 */
function register_navwalker() {
	require_once 'inc/class-wp-bootstrap-5-navwalker.php';
}
add_action( 'after_setup_theme', 'register_navwalker' );

/*
 * Pagination
 *  @usage
 *    1) setup WP_Query with a $paged variable
 *      (https://codex.wordpress.org/Pagination#Adding_the_.22paged.22_parameter_to_a_query)
 *    2) Wherever you'd like the pagination to appear, add <?php echo page_navi( $query ); ?>
 *       where $query is the entire $query setup in the previous step
*/
require_once 'inc/wp_bootstrap_pagination.php';

// Add classes to “next_post_link” and “previous_post_link”.
add_filter( 'next_post_link', 'post_link_attributes' );
add_filter( 'previous_post_link', 'post_link_attributes' );

/**
 * Pagination
 *
 *  @param string $output add classes to links.
 */
function post_link_attributes( $output ) {
	$code = 'class="page-link"';
	return str_replace( '<a href=', '<a ' . $code . ' href=', $output );
}

add_filter( 'next_posts_link_attributes', 'posts_link_attributes' );
add_filter( 'previous_posts_link_attributes', 'posts_link_attributes' );

/**
 * Pagination
 *
 *  Add classes to pagination links
 */
function posts_link_attributes() {
	return 'class="page-link"';
}

/**
 *  Scripts and styles
 */
function yikes_starter_scripts() {

	$get_theme_vers   = LWTV_THEME_VERSION;
	$lwtv_underscores = $get_theme_vers['lwtv-underscores'];
	$font_awesome     = $get_theme_vers['font-awesome'];
	$bootstrap        = $get_theme_vers['bootstrap'];
	$yikes_nav        = $get_theme_vers['yikes-nav'];

	// combined + minified.
	// navigation.js, skip-link-focus-fix.js & a11y.js.
	wp_enqueue_script( 'yikes-starter-navigation', get_template_directory_uri() . '/inc/js/yikes-theme-scripts.min.js', array(), $yikes_nav, true );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}

	// Bootstrap
	wp_enqueue_style( 'bootstrap', get_template_directory_uri() . '/inc/bootstrap/css/bootstrap.min.css', array(), $bootstrap, 'all', false );
	wp_enqueue_script( 'bootstrap', get_template_directory_uri() . '/inc/bootstrap/js/bootstrap.bundle.min.js', array( 'jquery' ), $bootstrap, 'all', true );

	// Font Awesome PRO.
	wp_enqueue_script( 'font-awesome', get_template_directory_uri() . '/inc/fontawesome/js/all.min.js', array(), $font_awesome, 'all', false );
	wp_add_inline_script( 'font-awesome', 'FontAwesomeConfig = { searchPseudoElements: true };', 'before' );

	// Fonts
	wp_enqueue_style( 'open-sans', '//fonts.googleapis.com/css?family=Open+Sans:400,600,700', array(), $lwtv_underscores, false );
	wp_enqueue_style( 'oswald', '//fonts.googleapis.com/css?family=Oswald:400,500', array(), $lwtv_underscores, false );

	// This has to be at the bottom to override Bootstrap 4.x.
	wp_enqueue_style( 'yikes-starter-style', get_stylesheet_directory_uri() . '/style.min.css', array(), $bootstrap, false );
}

add_action( 'wp_enqueue_scripts', 'yikes_starter_scripts' );

/**
 * Enqueue block styles in the editor.
 */
function yikes_block_editor_styles() {
	wp_enqueue_style( 'yikes-block-editor-styles', get_stylesheet_directory_uri() . '/style-editor.min.css', array(), LWTV_THEME_VERSION['lwtv-blocks'], true );
}
add_action( 'enqueue_block_editor_assets', 'yikes_block_editor_styles' );


/* Custom template tags for this theme. */
require get_template_directory() . '/inc/template-tags.php';

/* Custom functions that act independently of the theme templates. */
require get_template_directory() . '/inc/extras.php';

/* Customizer additions. */
require get_template_directory() . '/inc/customizer.php';


/**
 * Optional Items
 */

/* Implement the Custom Header feature. */
require get_template_directory() . '/inc/custom-header.php';

/* Remove custom background support */
remove_theme_support( 'custom-background' );

/* Load FacetWP compatibility file. */
require get_template_directory() . '/inc/facet.php';

/* Load Mika's Queer compatibility file. */
require get_template_directory() . '/inc/lesbians.php';
