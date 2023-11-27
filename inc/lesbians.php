<?php
/**
 * Weird LWTV functions and definitions
 *
 * This is crazy shit Mika wrote to force everything to play
 * nicely with each other. Including cursing at Jetpack.
 *
 * @package LezWatch.TV
 */

/** THE SECURITY SECTION **/

/**
 * Filter THEME updates in case some idiot ever submits lwtv-underscores as a theme.
 * Look, this should never happen, but the last thing we want is for this theme to
 * get updated by some rando with a grudge.
 */
// @codingStandardsIgnoreStart
add_filter( 'http_request_args', function ( $response, $url ) {
	if ( 0 === strpos( $url, 'https://api.wordpress.org/themes/update-check' ) ) {
		$themes = json_decode( $response['body']['themes'] );
		unset( $themes->themes->{get_option( 'template' )} );
		unset( $themes->themes->{get_option( 'stylesheet' )} );
		$response['body']['themes'] = json_encode( $themes );
	}
	return $response;
}, 10, 2 );
// @codingStandardsIgnoreEnd

/**
 * Disable update notifications for your theme. This doesn't change auto
 * updates, but it does hide things.
 */
function lwtv_disable_theme_update_notification( $value ) {
	if ( isset( $value ) && is_object( $value ) ) {
		unset( $value->response['lwtv-underscores'] );
	}
	return $value;
}
add_filter( 'site_transient_update_themes', 'lwtv_disable_theme_update_notification' );

/** THE GENERAL SECTION **/

/**
 * Loved Shows Shuffle
 *
 * This puts the loved show in a random order so it'll be different
 * for reloads.
 */
// @codingStandardsIgnoreStart
add_filter( 'the_posts', function( $posts, \WP_Query $query ) {
	$pick = $query->get( '_loved_shuffle' );
	if ( is_numeric( $pick ) ) {
		shuffle( $posts );
		$posts = array_slice( $posts, 0, (int) $pick );
	}
		return $posts;
}, 10, 2 );
// @codingStandardsIgnoreEnd

/*
 * Filter Comment Status
 *
 * Remove comments from attachment pages. This is for SEO and spam
 * purposes. Why do spammers spam?
 */
function lwtv_yikes_filter_media_comment_status( $open, $post_id ) {
	if ( 'attachment' === get_post_type( $post_id ) ) {
		return false;
	}
	return $open;
}
add_filter( 'comments_open', 'lwtv_yikes_filter_media_comment_status', 10, 2 );

/*
 * Auto apply alt tags
 *
 * If an image has no alt tags, we automagically apply the parent
 * post title if that exists, falling back to the image title
 * itself if not. This is for accessibility.
 */
function lwtv_auto_alt_fix( $attributes, $attachment ) {
	if ( ! isset( $attributes['alt'] ) || '' === $attributes['alt'] ) {
		$parent_titles     = get_the_title( $attachment->post_parent );
		$attributes['alt'] = ( isset( $parent_titles ) && '' !== $parent_titles ) ? $parent_titles : get_the_title( $attachment->ID );
	}
	return $attributes;
}
add_filter( 'wp_get_attachment_image_attributes', 'lwtv_auto_alt_fix', 10, 2 );

/** THE JETPACK SECTION **/

/**
 * Jetpack Setup
 *
 * This allows for responsive videos when using the [video]
 * shortcode, or VideoPress
 */
function lwtv_yikes_jetpack_setup() {
	add_theme_support( 'jetpack-responsive-videos' );
}
add_action( 'after_setup_theme', 'lwtv_yikes_jetpack_setup' );

/*
 * Remove Jetpack default share because it's stupid.
 *
 * This stops Jetpack from adding sharing after every post
 * on a home page or archive if you use a custom loop.
 */
function lwtv_yikes_jetpack_remove_share() {
	remove_filter( 'the_content', 'sharing_display', 19 );
	remove_filter( 'the_excerpt', 'sharing_display', 19 );
	if ( class_exists( 'Jetpack_Likes' ) ) {
		remove_filter( 'the_content', array( Jetpack_Likes::init(), 'post_likes' ), 30, 1 );
	}
}
add_action( 'loop_start', 'lwtv_yikes_jetpack_remove_share' );

/*
 * Force Jetpack to be where we want it, not where it wants.
 *
 * This is manually called by post-content pages.
 */
function lwtv_yikes_jetpack_post_meta() {
	if ( function_exists( 'sharing_display' ) ) {
		sharing_display( '', true );
	}

	if ( class_exists( 'Jetpack_Likes' ) ) {
		$custom_likes = new Jetpack_Likes();
		$post_meta    = $custom_likes->post_likes( '' );
	}
}


/** THE ARCHIVE SECTION **/

/*
 * https://wordpress.stackexchange.com/questions/172645/get-the-post-type-a-taxonomy-is-attached-to
 */
function lwtv_yikes_get_post_types_by_taxonomy( $tax = 'category' ) {
	$out        = '';
	$post_types = get_post_types();
	foreach ( $post_types as $post_type ) {
		$taxonomies = get_object_taxonomies( $post_type );
		if ( in_array( $tax, $taxonomies, true ) ) {
			// There should only be one (Highlander)
			$out = $post_type;
		}
	}
	return $out;
}

/*
 * Archive Sort Order
 *
 * Characters, shows, and certain taxonomies will use a
 * special order: ASC by title
 */
function lwtv_yikes_archive_sort_order( $query ) {
	if ( $query->is_main_query() && ! is_admin() && ! is_feed() ) {
		$posttypes  = array( 'post_type_characters', 'post_type_shows', 'post_type_actors' );
		$taxonomies = array( 'lez_cliches', 'lez_gender', 'lez_sexuality', 'lez_tropes', 'lez_country', 'lez_stations', 'lez_formats', 'lez_genres', 'lez_stars', 'lez_triggers', 'lez_actor_gender', 'lez_actor_sexuality' );
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
 * Characters and certain taxonomies show 24 posts per
 * page on archives instead of the normal 10.
 */
function lwtv_yikes_character_archive_query( $query ) {
	if ( $query->is_archive() && $query->is_main_query() && ! is_admin() && ! is_feed() ) {
		$taxonomies = array( 'lez_cliches', 'lez_gender', 'lez_sexuality', 'lez_romantic' );
		if ( is_post_type_archive( 'post_type_characters' ) || is_tax( $taxonomies ) ) {
			$query->set( 'posts_per_page', 24 );
		}
	}
}
add_action( 'pre_get_posts', 'lwtv_yikes_character_archive_query' );

/**
 * Taxonomy Archive Title
 *
 * Take the data from the taxonomy to determine a dynamic title.
 *
 * @access public
 * @param string $location
 * @param string $post_type
 * @param string $taxonomy
 *
 * @return string $title_adjustment -- Adjusted title.
 */
function lwtv_yikes_tax_archive_title( $location, $post_type, $taxonomy ) {
	if ( class_exists( 'LWTV_Theme_Taxonomy_Archive_Title' ) ) {
		$title_adjustment = ( new LWTV_Theme_Taxonomy_Archive_Title() )->make( $location, $post_type, $taxonomy );
	}

	if ( isset( $title_adjustment ) && ! empty( $title_adjustment ) ) {
		return $title_adjustment;
	}
}


/** THE SEARCH SECTION **/

/**
 * Filter the except length to 25 words.
 *
 * @param int $length Excerpt length.
 * @return int (Maybe) modified excerpt length.
 */
function lwtv_search_custom_excerpt_length( $length ) {
	$length = 25;
	return $length;
}

if ( is_search() ) {
	add_filter( 'excerpt_length', 'lwtv_search_custom_excerpt_length', 999 );
}


/** THE DISPLAY SECTION **/

/**
 * Show star
 *
 * If a show has a star, let's show it.
 *
 * @access public
 * @return void
 */
function lwtv_yikes_show_star( $show_id ) {
	if ( class_exists( 'LWTV_Theme_Show_Stars' ) ) {
		$star = ( new LWTV_Theme_Show_Stars() )->make( $show_id );
	}

	if ( isset( $star ) && ! empty( $star ) ) {
		return $star;
	}
}

/**
 * Show content warning
 *
 * If a show has a content warning, let's show it.
 *
 * @access public
 * @return void
 */
function lwtv_yikes_content_warning( $show_id ) {
	if ( isset( $show_id ) && class_exists( 'LWTV_Theme_Content_Warning' ) ) {
		$warning_array = ( new LWTV_Theme_Content_Warning() )->make( $show_id );
	}

	if ( isset( $warning_array ) && is_array( $warning_array ) ) {
		return $warning_array;
	}
}

/**
 * Character Data
 *
 * Called on character pages to generate certain data bits
 *
 * @access public
 * @param mixed $the_id: the character ID
 * @param mixed $data:
 * @return void
 */
function lwtv_yikes_chardata( $the_id, $data ) {

	if ( isset( $the_id ) && class_exists( 'LWTV_Theme_Data_Character' ) ) {
		$character_data = ( new LWTV_Theme_Data_Character() )->make( $the_id, $data );
	}

	if ( isset( $character_data ) ) {
		return $character_data;
	}
}

/**
 * Actor Data
 *
 * Called on actor pages to generate certain data bits
 *
 * @access public
 * @param mixed $the_id: the actor post ID
 * @param mixed $data:
 * @return void
 */
function lwtv_yikes_actordata( $the_id, $data ) {
	if ( isset( $the_id ) && class_exists( 'LWTV_Theme_Data_Actor' ) ) {
		$actor_data = ( new LWTV_Theme_Data_Actor() )->make( $the_id, $data );
	}

	if ( isset( $actor_data ) ) {
		return $actor_data;
	}
}

/**
 * Is the actor queer function
 *
 * @access public
 * @param string $the_id
 * @return bool
 */
function lwtv_yikes_is_queer( $the_id ) {
	$is_queer = false;
	if ( method_exists( 'LWTV_Features_Loops', 'is_actor_queer' ) ) {
		$is_queer = ( 'yes' === ( new LWTV_Features_Loops() )->is_actor_queer( $the_id ) ) ? true : false;
	}

	return $is_queer;
}

/**
 * Is the actor having a birthday right now?
 *
 * @access public
 * @param string $the_id
 * @return bool
 */
function lwtv_yikes_is_birthday( $the_id ) {
	$happy_birthday = false;
	if ( class_exists( 'LWTV_Theme_Actor_Birthday' ) ) {
		$happy_birthday = ( new LWTV_Theme_Actor_Birthday() )->make( $the_id );
	}
	return $happy_birthday;
}

/** THE SEO SECTION **/

/**
 * Fix microformats
 * We have to have author, updated, and entry-title IN the hentry data.
 *
 * @access public
 * @param mixed $post_id
 * @return void
 */
function lwtv_microformats_fix( $post_id ) {
	$valid_types = array( 'post_type_authors', 'post_type_characters', 'post_type_shows' );
	if ( in_array( get_post_type( $post_id ), $valid_types, true ) ) {
		echo '<div class="hatom-extra" style="display:none;visibility:hidden;">
			<span class="entry-title">' . esc_html( get_the_title( $post_id ) ) . '</span>
			<span class="updated">' . esc_html( get_the_modified_time( 'F jS, Y', $post_id ) ) . '</span>
			<span class="author vcard"><span class="fn">' . esc_html( get_option( 'blogname' ) ) . '</span></span>
		</div>';
	}
}

/** LWTV Plugin **/
// This section includes all the code we call from the LWTV plugin, with sanity checks.

/**
 * Check if Symbolicons exists and then call it
 * @param  string $svg the name of the SVG symbolicon we want to run
 * @param  string $fa  the name of the fallback Font Awesome icon
 * @return string      Whatever we end up with
 */
function lwtv_symbolicons( $svg, $fa ) {
	if ( method_exists( 'LWTV_Features', 'symbolicons' ) ) {
		$return = ( new LWTV_Features() )->symbolicons( $svg, $fa );
	} else {
		$return = '<span class="symbolicon" role="img"><svg aria-hidden="true" focusable="false" data-prefix="fas" data-icon="spinner" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" class="svg-inline--fa fa-spinner fa-w-16 fa-3x"><path fill="currentColor" d="M304 48c0 26.51-21.49 48-48 48s-48-21.49-48-48 21.49-48 48-48 48 21.49 48 48zm-48 368c-26.51 0-48 21.49-48 48s21.49 48 48 48 48-21.49 48-48-21.49-48-48-48zm208-208c-26.51 0-48 21.49-48 48s21.49 48 48 48 48-21.49 48-48-21.49-48-48-48zM96 256c0-26.51-21.49-48-48-48S0 229.49 0 256s21.49 48 48 48 48-21.49 48-48zm12.922 99.078c-26.51 0-48 21.49-48 48s21.49 48 48 48 48-21.49 48-48c0-26.509-21.491-48-48-48zm294.156 0c-26.51 0-48 21.49-48 48s21.49 48 48 48 48-21.49 48-48c0-26.509-21.49-48-48-48zM108.922 60.922c-26.51 0-48 21.49-48 48s21.49 48 48 48 48-21.49 48-48-21.491-48-48-48z" class=""></path></svg></span>';
	}
	return $return;
}

/**
 * List all characters
 * @param  int     $post_id Post ID we're trying to process
 * @param  string  $output  format of output
 * @return mixed   number or array listing the characters
 */
function lwtv_list_characters( $post_id, $output ) {
	if ( class_exists( 'LWTV_Theme_List_Characters' ) ) {
		return ( new LWTV_Theme_List_Characters() )->make( $post_id, $output );
	}
}

/**
 * Data on characters
 * @param  int    $post_id post ID
 * @param  int    $count   number of characters
 * @param  string $roll    role of characters
 * @return array           List of all the characters
 */
function lwtv_get_chars_for_show( $post_id, $count, $roll ) {
	$return = '';
	if ( method_exists( 'LWTV_CPT_Characters', 'get_chars_for_show' ) ) {
		$return = ( new LWTV_CPT_Characters() )->get_chars_for_show( $post_id, $count, $roll );
	}
	return $return;
}

/**
 * Get Last Updated
 *
 * @param string $post_ID
 *
 * @return n/a
 */
function lwtv_last_updated_date( $post_id ) {
	$updated_date = get_the_modified_time( 'F jS, Y', $post_id );
	$last_updated = '<div class="last-updated"><small class="text-muted">This page was last edited on ' . $updated_date . '.</small></div>';

	echo wp_kses_post( $last_updated );
}

/**
 * Show the last death
 *
 * @return n/a
 */
function lwtv_last_death() {
	$return = '<p>The LezWatch.TV API is temporarily unavailable.</p>';
	if ( class_exists( 'LWTV_Rest_API_BYQ' ) ) {
		$last_death = ( new LWTV_Rest_API_BYQ() )->last_death();
		if ( '' !== $last_death ) {
			$return  = '<p>' . sprintf( 'It has been %s since the last queer female, non-binary, or transgender death on television', '<strong>' . human_time_diff( $last_death['died'], (int) wp_date( 'U' ) ) . '</strong> ' );
			$return .= ': <span><a href="' . $last_death['url'] . '">' . $last_death['name'] . '</a></span> - ' . gmdate( 'F j, Y', $last_death['died'] ) . '</p>';
			// NOTE! Add `class="hidden-death"` to the span above if you want to blur the display of the last death.
		}
	}

	$return = '<div class="lezwatchtv last-death">' . $return . '</div>';

	return $return;
}

/**
 * Author Social Media
 *
 * @param int     $author Author ID
 * @return string $details Output of author social.
 */
function lwtv_author_social( $author ) {
	$return = '';
	if ( class_exists( 'LWTV_Theme_Data_Author' ) ) {
		$return = ( new LWTV_Theme_Data_Author() )->make( $author, 'social' );
	}
	return $return;
}

/**
 * Author Favourite Shows
 *
 * @param int     $author Author ID
 * @return string $details Output of author fav shows.
 */
function lwtv_author_favourite_shows( $author ) {
	$return = '';
	if ( class_exists( 'LWTV_Theme_Data_Author' ) ) {
		$return = ( new LWTV_Theme_Data_Author() )->make( $author, 'favorite_shows' );
	}
	return $return;
}
