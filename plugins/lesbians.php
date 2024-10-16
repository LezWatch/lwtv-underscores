<?php
/**
 * Weird LWTV functions and definitions
 *
 * This is crazy shit Mika wrote to force everything to play
 * nicely with each other. Including cursing at Jetpack.
 *
 * @package LezWatch.TV
 */


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
// PORT ALL TO PLUGINS AND CALL WITH lwtv_plugin()->METHOD()

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
	$return     = '<p>The LezWatch.TV API is temporarily unavailable.</p>';
	$last_death = lwtv_plugin()->get_json_last_death();
	if ( '' !== $last_death ) {
		$died_time = human_time_diff( $last_death['died'], (int) wp_date( 'U' ) );

		// If the death was within 24 hours, be more vague.
		$seconds = (int) time() - (int) $last_death['died'];
		if ( DAY_IN_SECONDS >= $seconds ) {
			$died_time = 'less than 24 hours';
		}

		$return  = '<p>' . sprintf( 'It has been %s since the last queer female, non-binary, or transgender death on television', '<strong>' . $died_time . '</strong> ' );
		$return .= ': <span><a href="' . $last_death['url'] . '">' . $last_death['name'] . '</a></span> - ' . gmdate( 'F j, Y', $last_death['died'] ) . '</p>';
		// NOTE! Add `class="hidden-death"` to the span above if you want to blur the display of the last death.
	}

	$return = '<div class="lezwatchtv last-death">' . $return . '</div>';

	return $return;
}
