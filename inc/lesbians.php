<?php
/**
 * Weird LQTV functions and definitions
 *
 * This is crazy shit Mika wrote to force everything to play 
 * nicely with each other. Including cursing at Jetpack.
 *
 * @package LezWatchTV
 */
 
/** THE GENERAL SECTION **/


/**
 * Loved Shows Shuffle
 *
 * This puts the loved show in a random order so it'll be different
 * for reloads. If we have more than three loved shows, it'll make
 * it even moar random.
 */
add_filter( 'the_posts', function( $posts, \WP_Query $query ) {
    if( $pick = $query->get( '_loved_shuffle' ) ) {
        shuffle( $posts );
        $posts = array_slice( $posts, 0, (int) $pick );
    }
    return $posts;
}, 10, 2 );

/*
 * Filter Comment Status
 *
 * Remove comments from attachment pages. This is for SEO and spam
 * purposes. Why do spammers spam?
 */
function lwtv_underscore_filter_media_comment_status( $open, $post_id ) {
	if ( get_post_type() == 'attachment' ) return false;
	return $open;
}
add_filter( 'comments_open', 'lwtv_underscore_filter_media_comment_status', 10 , 2 );


/**
 * Symbolicons Output
 *
 * Echos the default outputtable symbolicon, based on the SVG and FA icon passed to it.
 * 
 * @access public
 * @param string $svg (default: 'square.svg')
 * @param string $fontawesome (default: 'fa-square')
 * @return icon
 */
function lwtv_yikes_symbolicons( $svg = 'square.svg', $fontawesome = 'fa-square' ) {	

	$icon = '<i class="fa ' . $fontawesome . '" aria-hidden="true"></i>';

	if ( defined( 'LP_SYMBOLICONS_PATH' ) ) {
		$response      = wp_remote_get( LP_SYMBOLICONS_PATH );
		$response_code = wp_remote_retrieve_response_code( $response );
		
		if ( $response_code == '200' ) {
			$get_svg      = wp_remote_get( LP_SYMBOLICONS_PATH . $svg );
			$response_svg = wp_remote_retrieve_response_code( $get_svg );
			$icon         = ( $response_svg == '200' )? $get_svg['body'] : 'square.svg';
		}
	}

	return $icon;
}
 
/** THE JETPACK SECTION **/

/**
 * Jetpack Setup
 *
 * This allows for responsive videos when using the [video]
 * shortcode, or VideoPress
 */
function lwtv_underscore_jetpack_setup() {
	// Add theme support for Responsive Videos.
	add_theme_support( 'jetpack-responsive-videos' );
}
add_action( 'after_setup_theme', 'lwtv_underscore_jetpack_setup' );

/*
 * Remove Jetpack because it's stupid. 
 *
 * This stops Jetpack from adding sharing
 */
function lwtv_underscore_jetpack_remove_share() {
	remove_filter( 'the_content', 'sharing_display', 19 );
	remove_filter( 'the_excerpt', 'sharing_display', 19 );
	if ( class_exists( 'Jetpack_Likes' ) ) {
		remove_filter( 'the_content', array( Jetpack_Likes::init(), 'post_likes' ), 30, 1 );
	}
}
add_action( 'loop_start', 'lwtv_underscore_jetpack_remove_share' );

/*
 * Force Jetpack to be where we want it, not where it wants.
 *
 * This is manually called by post-content pages.
 */
function lwtv_underscore_jetpack_post_meta( ) {
	if ( function_exists( 'sharing_display' ) ) sharing_display( '', true );

	if ( class_exists( 'Jetpack_Likes' ) ) {
		$custom_likes = new Jetpack_Likes;
		$post_meta = $custom_likes->post_likes( '' );
	}
}


/** THE ARCHIVE SORT SECTION **/

/*
 * Archive Sort Order
 *
 * Everything BUT regular posts go alphabetical
 */
function lwtv_yikes_archive_sort_order( $query ) {
    if ( $query->is_main_query() && !is_admin() ) {
	    $posttypes  = array( 'post_type_characters', 'post_type_shows' );
	    $taxonomies = array( 'lez_cliches', 'lez_gender', 'lez_sexuality', 'lez_tropes', 'lez_country', 'lez_stations', 'lez_formats', 'lez_genres' );
		if ( is_post_type_archive( $posttypes ) || is_tax( $taxonomies ) ) {
	        $query->set( 'order', 'ASC' );
	        $query->set( 'orderby', 'title' );
		}
	}
}
add_action( 'pre_get_posts', 'lwtv_yikes_archive_sort_order' );

/**
 * Archive Query
 * 
 * Change posts-per-page for custom post type archives
 */
function lwtv_yikes_character_archive_query( $query ) {
	if ( $query->is_archive() && $query->is_main_query() && !is_admin() ) {
		// Character archive Pages get 24 per page vs the regular 10
		$taxonomies = array( 'lez_cliches', 'lez_gender', 'lez_sexuality' );
		if ( is_post_type_archive( 'post_type_characters' ) || is_tax( $taxonomies ) ) {
			$query->set( 'posts_per_page', 24 );
		}  
    }
}
add_action( 'pre_get_posts', 'lwtv_yikes_character_archive_query' );