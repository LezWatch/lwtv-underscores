<?php
/**
 * Name: Custom Post Types
 *
 */

namespace LWTV\_Components;

use LWTV\CPTs\{ Actors, Characters, Shows, Post_Meta, Related_Posts, TVMaze };
use LWTV\CPTs\Shows\Shows_Like_This;

/**
 * Controls for all CPTs.
 */
class CPTs implements Component, Templater {

	/**
	 * Our Post Types
	 */
	const POST_TYPES = array( 'post_type_actors', 'post_type_characters', 'post_type_shows' );

	/**
	 * Constructor
	 */
	public function init() {
		add_filter( 'bulk_actions-edit-member', array( $this, 'remove_member_bulk_actions' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'remove_quick_edit' ), 10, 2 );

		// Init what we'll need - DO NOT REMOVE, it will break the theme!
		new Post_Meta();
		new Actors();
		new Characters();
		new Shows();
		new TVMaze();
	}

	/**
	 * Gets tags to expose as methods accessible through `lwtv_plugin()`.
	 *
	 * @return array Associative array of $method_name => $callback_info pairs. Each $callback_info must either be
	 *               a callable or an array with key 'callable'. This approach is used to reserve the possibility of
	 *               adding support for further arguments in the future.
	 */
	public function get_template_tags(): array {
		return array(
			'get_cpt_related_posts'      => array( $this, 'get_cpt_related_posts' ),
			'get_related_archive_header' => array( $this, 'get_related_archive_header' ),
			'get_shows_like_this_show'   => array( $this, 'get_shows_like_this_show' ),
			'has_cpt_related_posts'      => array( $this, 'has_cpt_related_posts' ),
			'hide_actor_data'            => array( $this, 'hide_actor_data' ),
			'the_actor_privacy_warning'  => array( $this, 'the_actor_privacy_warning' ),
			'maybe_show_actor_note'      => array( $this, 'maybe_show_actor_note' ),
			'has_new_char'               => array( $this, 'has_new_char' ),
		);
	}

	/**
	 * Get TMDB Info for actor or show
	 *
	 * @param  int   $post_id
	 * @return mixed $body     - the response body or false
	 */
	public function get_tmdb_info( $post_id ): mixed {
		// Check if we have the API key.
		if ( ! defined( 'TMDB_API' ) ) {
			lwtv_plugin()->error_log( 'tmdb', 'TMDB API not defined' );
			return false;
		}

		// Get the post type.
		$post_type = match ( get_post_type( $post_id ) ) {
			'post_type_actors'     => 'person',
			'post_type_shows'      => 'tv',
			default                => false,
		};

		// If there's no post type, we shouldn't be looking here!
		if ( ! $post_type ) {
			lwtv_plugin()->error_log( 'tmdb', 'Invalid post type got TMDB info: ' . get_post_type( $post_id ) );
			return false;
		}

		// Get the TMDB ID.
		$tmdb_id = match ( get_post_type( $post_id ) ) {
			'post_type_actors'     => get_post_meta( $post_id, 'lezactors_tmdb_id', true ),
			'post_type_shows'      => get_post_meta( $post_id, 'lezshows_tmdb_id', true ),
			default                => false,
		};

		// Get the IMDB ID.
		$imdb_id = match ( get_post_type( $post_id ) ) {
			'post_type_actors'     => get_post_meta( $post_id, 'lezactors_imdb', true ),
			'post_type_shows'      => get_post_meta( $post_id, 'lezshows_imdb', true ),
			default                => false,
		};

		// If we don't have either, bail.
		if ( ! $tmdb_id && ! $imdb_id ) {
			lwtv_plugin()->error_log( 'tmdb', 'No TMDB or IMDB ID found for post ID: ' . $post_id );
			return false;
		}

		try {
			// Get the response URL.
			$response_url = 'https://api.themoviedb.org/3/';

			// If we have a TMDB ID, use it.
			if ( $tmdb_id ) {
				$response_url .= $post_type . '/' . $tmdb_id . '?api_key=' . TMDB_API;
			} else {
				// If we have an IMDB ID, use it.
				$response_url .= 'find/' . $imdb_id . '?api_key=' . TMDB_API . '&external_source=imdb_id';
			}

			// Get the response.
			$response = wp_remote_get( $response_url );

			// Bail if we don't have a response.
			if ( ! is_array( $response ) || is_wp_error( $response ) ) {
				lwtv_plugin()->error_log( 'tmdb', 'Error getting TMDB info: ' . $response );
				return false;
			}

			// Get the body.
			$body = json_decode( $response['body'], true ); // use the content

			// If there's a status message, it's an error:
			if ( isset( $body['status_message'] ) ) {
				lwtv_plugin()->error_log( 'tmdb', 'Error getting TMDB info: ' . $body['status_message'] );
				return false;
			}

			return $body;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'tmdb', 'Error getting TMDB info: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Related Content Archive
	 *
	 * @param  int $tag_id
	 * @return string
	 */
	public function get_related_archive_header( $tag_id ): string {
		return ( new Related_Posts() )->related_archive_header( $tag_id );
	}

	/**
	 * Get the related posts
	 *
	 * @param  mixed  $slug
	 * @param  string $type
	 * @return mixed
	 */
	public function get_cpt_related_posts( $slug, $max_posts = 3, $type = '' ): mixed {
		if ( is_numeric( $slug ) ) {
			$slug = get_post_field( 'post_name', get_post( $slug ) );
		}

		return ( new Related_Posts() )->related_posts( $slug, $max_posts, $type );
	}

	/**
	 * Get Shows Like this show
	 *
	 * @param  int $post_id
	 * @return void
	 */
	public function get_shows_like_this_show( $post_id ): mixed {
		return ( new Shows_Like_This() )->make( $post_id );
	}

	/**
	 * Does a CPT have related posts?
	 *
	 * @param  mixed $slug
	 * @return bool
	 */
	public function has_cpt_related_posts( $slug ): bool {
		if ( is_numeric( $slug ) ) {
			$slug = get_post_field( 'post_name', get_post( $slug ) );
		}

		return ( new Related_Posts() )->are_there_posts( $slug );
	}

	/**
	 * Hide actor data
	 *
	 * @param  int    $post_id
	 * @param  string $type     - type of data to hide
	 * @return bool
	 */
	public function hide_actor_data( $post_id, $type ): bool {
		return ( new Actors() )->hide_data( $post_id, $type );
	}

	/**
	 * The Actor Privacy Warning
	 *
	 * @param  int $post_id
	 * @param  bool $return_echo
	 * @return void
	 */
	public function the_actor_privacy_warning( $post_id, $return_echo = true ): void {
		( new Actors() )->privacy_warning( $post_id, $return_echo );
	}

	/**
	 * Maybe show the actor notes
	 *
	 * @param  int $post_id
	 * @param  bool $return_echo
	 * @return void|array
	 */
	public function maybe_show_actor_note( $post_id, $return_echo = true ) {
		( new Characters() )->privacy_warning( $post_id, $return_echo );
	}

	/**
	 * Remove Quick Edit if it's one of our CPTs.
	 *
	 * @param array  $actions The potential actions on the page.
	 * @param object $post    Post Object
	 *
	 * @return array $actions Modified actions.
	 */
	public function remove_quick_edit( $actions, $post ): array {
		if ( in_array( get_post_type( $post->ID ), self::POST_TYPES, true ) ) {
			unset( $actions['inline hide-if-no-js'] );
		}
		return $actions;
	}

	/**
	 * Remove Bulk Actions if it's one of our CPTs.
	 *
	 * @param array  $actions The potential actions on the page.
	 * @param object $post    Post Object
	 *
	 * @return array $actions Modified actions.
	 */
	public function remove_member_bulk_actions( $actions, $post ): array {
		if ( in_array( get_post_type( $post->ID ), self::POST_TYPES, true ) ) {
			unset( $actions['edit'] );
		}
		return $actions;
	}

	/**
	 * Has new character
	 *
	 * Check if the post is new OR if it has a new character and
	 * the last updated is within 24 hours.
	 *
	 * @param  int $post_id
	 * @return bool
	 */
	public function maybe_has_new_characters( $post_id ): bool {
		$has_new_char      = get_post_meta( $post_id, 'lwtv_has_new_char', true );
		$char_last_updated = get_post_meta( $post_id, 'lwtv_characters_last_updated', true );
		$pub_last_updated  = ( time() - get_the_time( 'U', $post_id ) );
		$char_within_24h   = $char_last_updated && ( time() - $char_last_updated ) < DAY_IN_SECONDS;

		// Return true if the post is new or if it has a new character and the last updated is within 24 hours.
		return ( $pub_last_updated < DAY_IN_SECONDS ) || ( $has_new_char && $char_within_24h );
	}
}
