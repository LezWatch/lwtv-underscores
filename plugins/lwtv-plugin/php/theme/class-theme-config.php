<?php
/**
 * Theme Config
 *
* Weird LWTV functions and definitions
 *
 * This is crazy shit Mika wrote to force everything to play
 * nicely with each other. Including cursing at Jetpack.
 */

namespace LWTV\Theme;

class Theme_Config {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'comments_open', array( $this, 'filter_media_comment_status' ), 10, 2 );
		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'auto_alt_fix' ), 10, 2 );
		add_action( 'pre_get_posts', array( $this, 'archive_sort_order' ) );
		add_action( 'pre_get_posts', array( $this, 'character_archive_query' ) );
		add_filter( 'the_posts', array( $this, 'loved_show_shuffle' ), 10, 2 );

		if ( is_search() ) {
			add_filter( 'excerpt_length', array( $this, 'search_custom_excerpt_length' ), 999 );
		}
	}

	/**
	 * Filter Comment Status
	 *
	 * Remove comments from attachment pages. This is for SEO and spam
	 * purposes. Why do spammers spam?
	 */
	public function filter_media_comment_status( $open, $post_id ) {
		if ( 'attachment' === get_post_type( $post_id ) ) {
			return false;
		}
		return $open;
	}

	/**
	 * Auto apply alt tags
	 *
	 * If an image has no alt tags, we automagically apply the parent
	 * post title if that exists, falling back to the image title
	 * itself if not. This is for accessibility.
	 */
	public function auto_alt_fix( $attributes, $attachment ) {
		if ( ! isset( $attributes['alt'] ) || '' === $attributes['alt'] ) {
			$parent_titles     = get_the_title( $attachment->post_parent );
			$attributes['alt'] = ( isset( $parent_titles ) && '' !== $parent_titles ) ? $parent_titles : get_the_title( $attachment->ID );
		}
		return $attributes;
	}


	/**
	 * Archive Sort Order
	 *
	 * Characters, shows, and certain taxonomies will use a
	 * special order: ASC by title
	 */
	public function archive_sort_order( $query ) {
		if ( $query->is_main_query() && ! is_admin() && ! is_feed() ) {
			$posttypes  = array( 'post_type_characters', 'post_type_shows', 'post_type_actors' );
			$taxonomies = array( 'lez_cliches', 'lez_gender', 'lez_sexuality', 'lez_tropes', 'lez_country', 'lez_stations', 'lez_formats', 'lez_genres', 'lez_stars', 'lez_triggers', 'lez_actor_gender', 'lez_actor_sexuality' );
			if ( is_post_type_archive( $posttypes ) || is_tax( $taxonomies ) ) {
				$query->set( 'order', 'ASC' );
				$query->set( 'orderby', 'title' );
			}
		}
	}

	/**
	 * Archive Query
	 *
	 * Characters and certain taxonomies show 24 posts per
	 * page on archives instead of the normal 10.
	 */
	public function character_archive_query( $query ) {
		if ( $query->is_archive() && $query->is_main_query() && ! is_admin() && ! is_feed() ) {
			$taxonomies = array( 'lez_cliches', 'lez_gender', 'lez_sexuality', 'lez_romantic' );
			if ( is_post_type_archive( 'post_type_characters' ) || is_tax( $taxonomies ) ) {
				$query->set( 'posts_per_page', 24 );
			}
		}
	}

	/**
	 * Loved Shows Shuffle
	 *
	 * This puts the loved show in a random order so it'll be different
	 * for reloads. Except for the whole cache thing.
	 */
	public function loved_show_shuffle( $posts, \WP_Query $query ) {
		$pick = $query->get( '_loved_shuffle' );
		if ( is_numeric( $pick ) ) {
			shuffle( $posts );
			$posts = array_slice( $posts, 0, (int) $pick );
		}
		return $posts;
	}

	/**
	 * Filter the except length to 25 words.
	 *
	 * @param int $length Excerpt length.
	 * @return int (Maybe) modified excerpt length.
	 */
	public function search_custom_excerpt_length( $length ) {
		$length = 25;
		return $length;
	}
}
