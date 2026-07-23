<?php
/**
 * Description: REST-API: Export
 *
 * Customized for Wikidata based on conversations
 * - https://docs.google.com/document/d/17CmI01aM0kuOa-YVZxpbX7NoMemutjtu8FPXYGVaVSk/edit
 * - https://tools.wmflabs.org/mix-n-match/import.php
 *
 * URL will be https://lezwatchtv.com/wp-json/lwtv/v1/export/actor/my-name/
 */

namespace LWTV\Rest_API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Queeries\Post_Type;
use LWTV\Queeries\Taxonomy_Optimized as Queery_Taxonomy;
use LWTV\CPTs\Characters as CPT_Characters;
use LWTV\CPTs\Shows as CPT_Shows;
use LWTV\CPTs\Actors as CPT_Actors;

class Export_JSON {
	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Rest API init
	 *
	 * Creates callbacks
	 *   - /lwtv/v1/export/
	 *
	 * Doc: https://docs.lezwatchtv.com/api/global/export/
	 *
	 * @since 1.0
	 */
	public function rest_api_init() {

		register_rest_route(
			'lwtv/v1',
			'/export/',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'lwtv/v1',
			'/export/(?P<type>[a-zA-Z]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'lwtv/v1',
			'/export/(?P<type>[a-zA-Z0-9-]+)/(?P<item>[a-zA-Z0-9-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'lwtv/v1',
			'/export/(?P<type>[a-zA-Z0-9-]+)/(?P<item>[a-zA-Z0-9-]+)/(?P<tax>[a-zA-Z0-9-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'lwtv/v1',
			'/export/(?P<type>[a-zA-Z0-9-]+)/(?P<item>[a-zA-Z0-9-]+)/(?P<tax>[a-zA-Z0-9-]+)/(?P<term>[a-zA-Z0-9-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Rest API Callback
	 *
	 * @since 1.0
	 */
	public function rest_api_callback( $data ) {
		$params = $data->get_params();

		// Type of Custom Post (show, actor, character)
		$type = ( isset( $params['type'] ) && '' !== $params['type'] ) ? sanitize_title_for_query( $params['type'] ) : 'actor';

		// Specific item (actor name etc)
		$item = ( isset( $params['item'] ) && '' !== $params['item'] ) ? sanitize_title_for_query( $params['item'] ) : 'unknown';

		// Taxonomy
		$tax = ( isset( $params['tax'] ) && '' !== $params['tax'] ) ? sanitize_title_for_query( $params['tax'] ) : 'none';

		// Term
		$term = ( isset( $params['term'] ) && '' !== $params['term'] ) ? sanitize_title_for_query( $params['term'] ) : 'none';

		$response = $this->export( $type, $item, $tax, $term );

		return $response;
	}

	/*
	 * export function
	 */
	public function export( $type = 'actor', $item = 'unknown', $tax = '', $term = '' ) {

		// Sanitize (the switch will check the type)
		$type = sanitize_text_field( $type );
		$item = sanitize_text_field( $item );
		$tax  = sanitize_text_field( $tax );
		$term = sanitize_text_field( $term );

		// Create the array
		switch ( $type ) {
			case 'actor':
			case 'actors':
				$return_array = self::export_actor( $item );
				break;
			case 'character':
			case 'characters':
				$return_array = self::export_character( $item );
				break;
			case 'show':
			case 'shows':
				$return_array = self::export_show( $item );
				break;
			case 'list':
			case 'wiki':
				$return_array = self::export_list( $item );
				break;
			case 'raw':
				$return_array = self::export_raw( $item );
				break;
			case 'full':
				$return_array = self::export_full( $item, $tax, $term );
				break;
			default:
				$return_array = '';
				break;
		}

		if ( empty( $return_array ) ) {
			return new \WP_Error( 'no_type', 'Invalid content type (' . $type . ') or name (' . $item . ') given.', array( 'status' => 400 ) );
		}

		// No errors! Return array
		return $return_array;
	}

	/**
	 * Export list of all data with basic output
	 * @param  string $item characters, actors, or shows.
	 * @return array        json array.
	 */
	public function export_list( $item ) {
		// Generate cache key
		$cache_key = 'lwtv_export_list_' . $item . '_' . $this->get_data_version_hash( 'post_type_' . $item );

		// Try to get from cache first
		$cached_result = lwtv_plugin()->get_transient( $cache_key );
		if ( false !== $cached_result ) {
			return $cached_result;
		}

		// Default to empty. This will properly error later.
		$return = array();

		if ( in_array( $item, array( 'characters', 'shows', 'actors' ), true ) ) {
			// Use optimized query
			$posts = get_posts(
				array(
					'post_type'      => 'post_type_' . $item,
					'posts_per_page' => -1,
					'post_status'    => 'publish',
					'fields'         => 'ids',
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			);

			if ( ! empty( $posts ) ) {
				// Bulk fetch all needed data
				$bulk_data = $this->get_bulk_export_data( $posts, $item );

				foreach ( $posts as $post_id ) {
					switch ( $item ) {
						case 'actor':
						case 'actors':
							$return[] = $this->export_actor_optimized( $post_id, 'wiki', $bulk_data );
							break;
						case 'character':
						case 'characters':
							$return[] = $this->export_character_optimized( $post_id, 'wiki', $bulk_data );
							break;
						case 'show':
						case 'shows':
							$return[] = $this->export_show_optimized( $post_id, 'wiki', $bulk_data );
							break;
					}
				}
			}
		}

		// Cache the result for 1 hour
		lwtv_plugin()->set_transient( $cache_key, $return, HOUR_IN_SECONDS );

		return $return;
	}

	/**
	 * Export list of all data in  more raw way.
	 * @param  string $item actors or shows.
	 * @return array        json array.
	 */
	public function export_raw( $item ) {

		// Default to empty. This will properly error later.
		$return = array();

		if ( in_array( $item, array( 'characters', 'shows', 'actors' ), true ) ) {
			$the_loop = ( new Post_Type() )->make( 'post_type_' . $item );

			if ( ! is_object( $the_loop ) || ! $the_loop->have_posts() ) {
				return $return;
			}

			while ( $the_loop->have_posts() ) {
				$the_loop->the_post();
				$post = get_post();

				switch ( $item ) {
					case 'actor':
					case 'actors':
						$return[] = self::export_actor( $post->post_name, 'raw' );
						break;
					case 'character':
					case 'characters':
						$return[] = self::export_character( $post->post_name, 'raw' );
						break;
					case 'show':
					case 'shows':
						$return[] = self::export_show( $post->post_name, 'raw' );
						break;
				}
			}
		}

		return $return;
	}

	/**
	 * Exports lists of data with more details, based on specific.
	 * @param  string $item actors or shows.
	 * @return array        json array.
	 */
	public function export_full( $item, $tax, $term ) {

		// phpcs:disable
		// Remove <!--fwp-loop--> from output
		add_filter(
			'facetwp_is_main_query',
			function( $is_main_query, $query ) {
				return false;
			},
			10,
			2
		);
		// phpcs:enable

		// Prep Return
		$response_array = array();

		// Valid Data
		$valid_item = array( 'characters' );
		$valid_tax  = array( 'cliches', 'gender', 'sexuality', 'romantic' );

		// If it's not in the array, we have to fail.
		if ( ! in_array( $item, $valid_item, true ) ) {
			return new \WP_Error( 'not_found', 'Full listing only supports "characters" at this time.' );
		}

		if ( ! isset( $tax ) || 'none' === $tax ) {
			// If there is no Taxonomy, we list the groups we allow and their counts
			$term_params = array(
				'hide_empty' => false,
				'parent'     => 0,
			);
			foreach ( $valid_tax as $this_tax ) {
				$term_params['taxonomy']     = 'lez_' . $this_tax;
				$response_array[ $this_tax ] = wp_count_terms( $term_params );
			}
		} elseif ( ! in_array( $tax, $valid_tax, true ) ) {
			// Failure
			return new \WP_Error( 'not_found', 'No route was found matching the URL and request method: ' . $tax );
		} elseif ( ! isset( $term ) || 'none' === $term ) {
			// Make list of all terms
			$terms = get_terms(
				array(
					'taxonomy' => 'lez_' . $tax,
				),
			);

			// Process list to show term slug and count
			foreach ( $terms as $one_term ) {
				$response_array[ $one_term->slug ] = $one_term->count;
			}
		} else {
			$response_array = self::get_full_list( $item, $tax, $term );
		}

		return $response_array;
	}

	/**
	 * get_full_list function.
	 *
	 * @access public
	 * @return array
	 */
	public function get_full_list( $type, $group, $term ) {
		switch ( $type ) {
			case 'character':
			case 'characters':
				$response = self::get_full_list_characters( $group, $term );
				break;
		}

		if ( ! isset( $response ) ) {
			return new \WP_Error( 'not_found', 'Currently we can only list data on characters. The rest is coming soon.' );
		} else {
			return $response;
		}
	}

	/**
	 * get_full_list_characters function.
	 *
	 * @access public
	 * @return array
	 */
	public function get_full_list_characters( $group, $term ) {

		$the_loop = ( new Queery_Taxonomy() )->make( CPT_Characters::SLUG, 'lez_' . $group, 'slug', $term );

		if ( ! is_object( $the_loop ) || ! $the_loop->have_posts() ) {
			return new \WP_Error( 'not_found', 'No route was found matching the URL and request method: ' . $term );
		}

		// Make empty array for later
		$characters = array();

		$characters_list = wp_list_pluck( $the_loop->posts, 'ID' );
		update_object_term_cache( $characters_list, CPT_Characters::SLUG );

		foreach ( $characters_list as $character ) {
			// Gender -- array of all applicable
			$gender       = array();
			$gender_terms = get_the_terms( $character, 'lez_gender', true );
			if ( $gender_terms && ! is_wp_error( $gender_terms ) ) {
				foreach ( $gender_terms as $gender_term ) {
					$gender[] = $gender_term->name;
				}
			}

			// Sexuality -- array of all applicable
			$sexuality       = array();
			$sexuality_terms = get_the_terms( $character, 'lez_sexuality', true );
			if ( $sexuality_terms && ! is_wp_error( $sexuality_terms ) ) {
				foreach ( $sexuality_terms as $sexuality_term ) {
					$sexuality[] = $sexuality_term->name;
				}
			}

			// Cliches -- array of all applicable
			$cliches     = array();
			$lez_cliches = get_the_terms( $character, 'lez_cliches' );
			if ( $lez_cliches && ! is_wp_error( $lez_cliches ) ) {
				foreach ( $lez_cliches as $the_cliche ) {
					$cliches[] = $the_cliche->name;
				}
			}
			$cliches_clean = implode( '; ', $cliches );

			// Shows
			$shows_full  = get_field( 'lezchars_show_group', $character );
			$shows_array = array();
			foreach ( (array) $shows_full as $show ) {
				// Remove the Array.
				if ( is_array( $show['show'] ) ) {
					$show['show'] = $show['show'][0];
				}

				$appears       = implode( ';', $show['appears'] );
				$shows_array[] = get_the_title( $show['show'] ) . ' - ' . $show['type'] . ' (' . $appears . ')';
			}
			$shows_clean = implode( '; ', $shows_array );

			$char_name = get_the_title( $character );
			$char_url  = get_the_permalink( $character );

			$characters[ sanitize_title( $char_name ) ] = array(
				'id'        => $character,
				'name'      => $char_name,
				'url'       => $char_url,
				'image'     => wp_get_attachment_url( get_post_thumbnail_id( $character ) ),
				'sexuality' => $sexuality,
				'gender'    => $gender,
				'cliches'   => $cliches_clean,
				'shows'     => $shows_clean,
			);
		}

		if ( ! isset( $characters ) || empty( $characters ) ) {
			return new \WP_Error( 'not_found', 'No route was found matching the URL and request method: ' . $term );
		} else {
			return $characters;
		}
	}

	/**
	 * Export Show
	 *
	 * Export format:
	 * Entry ID: {slug}
	 * Entry Name: {post title}
	 * Entry Description: {lez_formats} airing in {lez_nations} from {lezshows_airdates}
	 * Links: {IMDB}
	 *
	 * @access public
	 * @param string $item - name or ID of show
	 * @param string $format - plain or detailed
	 * @return array
	 * @since 1.0
	 */
	public function export_show( $item, $format = 'wiki' ) {

		// Default to empty. This will properly error later.
		$return = array();

		if ( is_numeric( $item ) ) {
			// If it's numeric, we shall assume it's the post ID.
			$page = get_post( $item );
		} else {
			// Let's get the ID by the title
			$page = get_page_by_path( $item, OBJECT, CPT_Shows::SLUG );
		}

		// If page doesn't exist, let's try by SQL.
		if ( ! isset( $page ) || null === $page ) {
			global $wpdb;
			$item_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s", $item ) );
			$page    = get_post( $item_id );
		}

		// Let's make sure it exists, is a show, and is published.
		if ( isset( $page ) && CPT_Shows::SLUG === get_post_type( $page->ID ) && 'publish' === get_post_status( $page->ID ) ) {

			// Empty to start
			$data = array();

			// Basic data
			$return = array(
				'uid'  => $page->ID,
				'id'   => $page->post_name,
				'name' => $page->post_title,
			);

			// Show Formats
			$format_terms    = get_the_terms( $page->ID, 'lez_formats', true );
			$data['formats'] = ( $format_terms && ! is_wp_error( $format_terms ) ) ? join( ', ', wp_list_pluck( $format_terms, 'name' ) ) : '';

			// Nations
			$data['nations'] = '';
			$nation_terms    = get_the_terms( $page->ID, 'lez_country', true );
			$nation_array    = ( $nation_terms && ! is_wp_error( $nation_terms ) ) ? wp_list_pluck( $nation_terms, 'name' ) : '';
			if ( is_array( $nation_array ) ) {
				if ( count( $nation_array ) > 1 && 'wiki' === $format ) {
					$last_element = array_pop( $nation_array );
					array_push( $nation_array, 'and ' . $last_element );
				}
				$data['nations'] = implode( ', ', $nation_array );
			}

			// Stations
			$station_terms    = get_the_terms( $page->ID, 'lez_stations', true );
			$data['stations'] = ( $station_terms && ! is_wp_error( $station_terms ) ) ? join( ', ', wp_list_pluck( $station_terms, 'name' ) ) : '';

			// Airdates
			$ad_start  = get_post_meta( $page->ID, 'lezshows_airdates_start', true );
			$ad_finish = get_post_meta( $page->ID, 'lezshows_airdates_finish', true );
			if ( empty( $ad_start ) || empty( $ad_finish ) ) {
				$legacy    = get_post_meta( $page->ID, 'lezshows_airdates', true );
				$ad_start  = $ad_start ?: ( is_array( $legacy ) ? ( $legacy['start'] ?? '' ) : '' );
				$ad_finish = $ad_finish ?: ( is_array( $legacy ) ? ( $legacy['finish'] ?? '' ) : '' );
			}
			if ( ! empty( $ad_start ) && ! empty( $ad_finish ) ) {
				$ad_finish           = ( 'current' === $ad_finish ) ? 'now' : $ad_finish;
				$data['dates_raw']   = $ad_start . '-' . $ad_finish;
				$data['dates_plain'] = 'from ' . $data['dates_raw'];
				if ( $ad_start === $ad_finish ) {
					$data['dates_raw']   = $ad_finish;
					$data['dates_plain'] = 'in ' . $data['dates_raw'];
				}
			}

			// IMDB
			$data['imdb'] = ( get_post_meta( $page->ID, 'lezshows_imdb', true ) ) ? 'https://imdb.com/title/' . get_post_meta( $page->ID, 'lezshows_imdb', true ) : '';

			switch ( $format ) {
				case 'wiki':
					$return['description'] = $data['formats'] . ' airing in ' . $data['nations'] . ' ' . $data['dates_plain'] . '.';
					break;
				case 'raw':
					$return['format']   = $data['formats'];
					$return['nation']   = $data['nations'];
					$return['airdates'] = $data['dates_raw'];
					$return['stations'] = $data['stations'];
					$return['imdb']     = $data['imdb'];
			}
		}

		return $return;
	}

	/**
	 * Export Character
	 *
	 * Export format:
	 * Entry ID: {slug}
	 * Entry Name: {post title}
	 * Entry Description: A {lezchars_sexuality} {lezchars_gender} character on {*show(s)}. Played by {actor}.
	 * Links: {N/A -- there are no extra links.}
	 *
	 * @access public
	 * @param string $item - name or ID of character
	 * @return array
	 * @since 1.0
	 */
	public function export_character( $item, $format = 'wiki' ) {

		// Default to empty. This will properly error later.
		$return = array();

		if ( is_numeric( $item ) ) {
			// If it's numeric, we shall assume it's the post ID.
			$page = get_post( $item );
		} else {
			// Let's get the ID by the title
			$page = get_page_by_path( $item, OBJECT, CPT_Characters::SLUG );
		}

		// If page doesn't exist, let's try by SQL.
		if ( ! isset( $page ) || null === $page ) {
			global $wpdb;
			$item_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s", $item ) );
			$page    = get_post( $item_id );
		}

		// Let's make sure it exists, is a character, and is published.
		if ( isset( $page ) && CPT_Characters::SLUG === get_post_type( $page->ID ) && 'publish' === get_post_status( $page->ID ) ) {

			// Basic Data we always need
			$return = array(
				'uid'  => $page->ID,
				'id'   => $page->post_name,
				'name' => $page->post_title,
			);

			$data = array();

			// Sexuality
			$sexuality_terms   = get_the_terms( $page->ID, 'lez_sexuality', true );
			$data['sexuality'] = ( $sexuality_terms && ! is_wp_error( $sexuality_terms ) ) ? join( ', ', wp_list_pluck( $sexuality_terms, 'name' ) ) : '';

			// Gender
			$gender_terms = get_the_terms( $page->ID, 'lez_gender', true );
			if ( $gender_terms && ! is_wp_error( $gender_terms ) ) {
				$gender_string = implode( ', ', wp_list_pluck( $gender_terms, 'name' ) );
				$gender_string = ( 'Cisgender' === $gender_string ) ? 'Cisgender Female' : $gender_string;
			}
			$data['gender'] = ( isset( $gender_string ) ) ? $gender_string : '';

			// Shows
			$all_shows = get_field( 'lezchars_show_group', $page->ID );
			if ( ! empty( $all_shows ) ) {
				foreach ( $all_shows as $a_show ) {
					// Remove the Array.
					if ( is_array( $a_show['show'] ) ) {
						$a_show['show'] = $a_show['show'][0];
					}

					$the_shows[] = '\'' . get_the_title( $a_show['show'] ) . '\'';
				}

				if ( isset( $the_shows ) ) {
					if ( count( $the_shows ) > 1 && 'wiki' === $format ) {
						$last_element = array_pop( $the_shows );
						array_push( $the_shows, 'and ' . $last_element );
					}
					$show = implode( ', ', $the_shows );
				}
			}
			$data['show'] = ( isset( $show ) ) ? $show : '';

			// Actors
			$all_actors = get_field( 'lezchars_actor', $page->ID ) ?: array();

			// Add each actor's name to the array.
			foreach ( $all_actors as $an_actor ) {
				// Only add the actor if they're NOT hidden.
				if ( ! lwtv_plugin()->hide_actor_data( $an_actor, 'all' ) ) {
					$the_actors[] = get_the_title( $an_actor );
				}
			}

			if ( isset( $the_actors ) ) {
				if ( count( $the_actors ) > 1 && 'wiki' === $format ) {
					$last_element = array_pop( $the_actors );
					array_push( $the_actors, 'and ' . $last_element );
				}
				$actor = implode( ', ', $the_actors );
			}
			$data['actor'] = ( isset( $actor ) ) ? $actor : '';

			switch ( $format ) {
				case 'wiki':
					$return['description'] = 'A ' . $data['sexuality'] . ' ' . $data['gender'] . ' character on ' . $data['show'] . '. Played by ' . $data['actor'] . '.';
					break;
				case 'raw':
					$return['sexuality'] = $data['sexuality'];
					$return['gender']    = $data['gender'];
					$return['show']      = $data['show'];
					$return['actor']     = $data['actor'];
					break;
			}
		}

		return $return;
	}

	/**
	 * Export Actor
	 *
	 * Export format:
	 * Entry ID: {slug}
	 * Entry Name: {post title}
	 * Entry Description (plain): {lez_actor_sexuality} {lez_actor_gender} actor b. {lezactors_birth} and died {lezactors_death}. {lezactors_wikipedia}
	 * Detailed: {twitter, instagram, etc}
	 *
	 * @access public
	 * @param string $item - name or ID of actor
	 * @return array
	 * @since 1.0
	 */
	public function export_actor( $item, $format = 'wiki' ) {

		// Default to empty. This will properly error later.
		$return = array();

		if ( is_numeric( $item ) ) {
			// If it's numeric, we shall assume it's the post ID.
			$page = get_post( $item );
		} else {
			// Let's get the ID by the title
			$page = get_page_by_path( $item, OBJECT, CPT_Actors::SLUG );

			// If page doesn't exist, let's try by SQL.
			if ( null === $page ) {
				global $wpdb;
				$item_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s", $item ) );
				$page    = get_post( $item_id );
			}
		}

		// Let's make sure.
		if ( isset( $page ) && CPT_Actors::SLUG === get_post_type( $page->ID ) ) {

			// If the actor has asked to be private, we respect that.
			if ( lwtv_plugin()->hide_actor_data( $page->ID, 'all' ) ) {
				return array();
			}

			// Empty Array
			$data = array();

			// Sexuality
			$sexuality_terms   = get_the_terms( $page->ID, 'lez_actor_sexuality', true );
			$data['sexuality'] = ( $sexuality_terms && ! is_wp_error( $sexuality_terms ) ) ? join( ', ', wp_list_pluck( $sexuality_terms, 'name' ) ) : '';

			// Gender
			$gender_terms   = get_the_terms( $page->ID, 'lez_actor_gender', true );
			$data['gender'] = ( $gender_terms && ! is_wp_error( $gender_terms ) ) ? join( ', ', wp_list_pluck( $gender_terms, 'name' ) ) : '';

			// If the actor has asked to keep their DoB private, we honor it.
			if ( ! lwtv_plugin()->hide_actor_data( $page->ID, 'dob' ) ) {
				// Born
				if ( get_post_meta( $page->ID, 'lezactors_birth', true ) ) {
					$get_birth = new \DateTime( get_post_meta( $page->ID, 'lezactors_birth', true ) );
				}
			}

			// Died
			if ( get_post_meta( $page->ID, 'lezactors_death', true ) ) {
				$get_dead = new \DateTime( get_post_meta( $page->ID, 'lezactors_death', true ) );
			}

			// Homepage
			$data['website'] = ( get_post_meta( $page->ID, 'lezactors_homepage', true ) ) ? esc_url( get_post_meta( $page->ID, 'lezactors_homepage', true ) ) : '';

			// Wikipedia
			$data['wikipedia'] = ( get_post_meta( $page->ID, 'lezactors_wikipedia', true ) ) ? esc_url( get_post_meta( $page->ID, 'lezactors_wikipedia', true ) ) : '';

			// IMdb
			$data['imdb'] = ( get_post_meta( $page->ID, 'lezactors_imdb', true ) ) ? 'https://imdb.com/name/' . get_post_meta( $page->ID, 'lezactors_imdb', true ) : '';

			// If the actor has asked to keep their Socials private, we honor it.
			if ( ! lwtv_plugin()->hide_actor_data( $page->ID, 'socials' ) ) {
				// Twitter
				$data['twitter'] = ( get_post_meta( $page->ID, 'lezactors_twitter', true ) ) ? 'https://twitter.com/' . get_post_meta( $page->ID, 'lezactors_twitter', true ) : '';

				// Instagram
				$data['instagram'] = ( get_post_meta( $page->ID, 'lezactors_instagram', true ) ) ? 'https://instagram.com/' . get_post_meta( $page->ID, 'lezactors_instagram', true ) : '';
			}

			$return = array(
				'uid'  => $page->ID,
				'id'   => $page->post_name,
				'name' => $page->post_title,
			);

			switch ( $format ) {
				case 'wiki':
					// Collect oddities
					if ( ! lwtv_plugin()->hide_actor_data( $page->ID, 'dob' ) ) {
						$data['born'] = ( isset( $get_birth ) ) ? ' b. ' . date_format( $get_birth, 'F d, Y' ) : '';
					}
					$data['died'] = ( isset( $get_dead ) ) ? ' and died ' . date_format( $get_dead, 'F d, Y' ) : '';

					// Now Build
					$return['description'] = $data['sexuality'] . ' ' . $data['gender'] . ' actor' . $data['born'] . $data['died'] . '. ' . $data['wikipedia'];
					break;
				case 'raw':
					if ( ! lwtv_plugin()->hide_actor_data( $page->ID, 'dob' ) ) {
						$return['born'] = ( isset( $get_birth ) ) ? date_format( $get_birth, 'F d, Y' ) : '';
					}
					$return['died']      = ( isset( $get_dead ) ) ? date_format( $get_dead, 'F d, Y' ) : '';
					$return['sexuality'] = $data['sexuality'];
					$return['gender']    = $data['gender'];
					$return['website']   = $data['website'];
					$return['imdb']      = $data['imdb'];
					$return['wikipedia'] = $data['wikipedia'];
					if ( ! lwtv_plugin()->hide_actor_data( $page->ID, 'socials' ) ) {
						$return['twitter']   = $data['twitter'];
						$return['instagram'] = $data['instagram'];
					}
					break;
			}
		}

		return $return;
	}

	/**
	 * Adds actions, filters, etc. to WP
	 *
	 * @access public
	 * @return void
	 * @since 1.1.0
	 */
	public function init() {
		// Plugin requires permalink usage - Only setup handling if permalinks enabled
		if ( '' !== get_option( 'permalink_structure' ) ) {

			// tell WP not to override query vars
			add_action( 'query_vars', array( $this, 'query_vars' ) );

			// add filter for pages
			add_action( 'template_redirect', array( $this, 'template_redirect' ) );

			$views = array( 'actor', 'character', 'show' );

			foreach ( $views as $a_view ) {
				add_rewrite_rule(
					'^' . $a_view . '/([^/]+)(?:/([0-9]+))?/wikidata/?$',
					'index.php?post_type_' . $a_view . 's&post_type=post_type_' . $a_view . 's&name=$matches[1]&export=wikidata',
					'top'
				);
				add_rewrite_rule(
					'^wikidata/' . $a_view . '/?$',
					'index.php?&exportname=' . $a_view . '&export=wikilist',
					'top'
				);
			}
		}
	}

	/**
	 * Add the query variables so WordPress won't override it
	 *
	 * @return $vars
	 * @since 1.1.0
	 */
	public function query_vars( $vars ) {
		$vars[] = 'export';
		$vars[] = 'exportname';
		return $vars;
	}

	/**
	 * Adds a custom template to the query queue.
	 *
	 * @return $templates
	 * @since 1.1.1
	 */
	public function template_redirect() {
		if ( get_query_var( 'export' ) ) {
			// phpcs:disable
			add_filter( 'template_include', function() {
				return __DIR__ . '/templates/export-json.php';
			});
			// phpcs:enable
		}
	}

	/**
	 * Get bulk export data for multiple posts
	 *
	 * @param array  $post_ids Array of post IDs
	 * @param string $type Post type
	 * @return array Bulk data organized by post ID
	 */
	private function get_bulk_export_data( $post_ids, $type ) {
		if ( empty( $post_ids ) ) {
			return array();
		}

		global $wpdb;

		// Sanitize IDs
		$post_ids = array_map( 'intval', $post_ids );
		$post_ids = array_filter( $post_ids );

		if ( empty( $post_ids ) ) {
			return array();
		}

		$bulk_data       = array();
		$id_placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

		// Get basic post data
		$posts_query = $wpdb->prepare( "SELECT ID, post_title, post_name FROM {$wpdb->posts} WHERE ID IN ($id_placeholders)", ...$post_ids ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$posts       = $wpdb->get_results( $posts_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		foreach ( $posts as $post ) {
			$bulk_data[ $post->ID ] = array(
				'post_title' => $post->post_title,
				'post_name'  => $post->post_name,
			);
		}

		// Get meta data based on type
		$meta_keys = array();
		switch ( $type ) {
			case 'actors':
				$meta_keys = array( 'lezactors_birth', 'lezactors_death', 'lezactors_wikipedia', 'lezactors_imdb' );
				break;
			case 'characters':
				$meta_keys = array( 'lezchars_actor' );
				break;
			case 'shows':
				$meta_keys = array( 'lezshows_airdates', 'lezshows_imdb' );
				break;
		}

		if ( ! empty( $meta_keys ) ) {
			$key_placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
			$meta_query       = $wpdb->prepare( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ($id_placeholders) AND meta_key IN ($key_placeholders)", ...array_merge( $post_ids, $meta_keys ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$meta_results     = $wpdb->get_results( $meta_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			foreach ( $meta_results as $row ) {
				$bulk_data[ $row->post_id ]['meta'][ $row->meta_key ] = maybe_unserialize( $row->meta_value );
			}
		}

		// For characters, fetch ACF repeater show group subfields in bulk.
		// lezchars_show_group stores a count in its parent key post-migration;
		// actual show IDs live in lezchars_show_group_{n}_show rows.
		if ( 'characters' === $type ) {
			$show_group_query   = $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ($id_placeholders) AND meta_key LIKE %s", ...array_merge( $post_ids, array( 'lezchars_show_group_%_show' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.LikeWildcardsInQuery
			$show_group_results = $wpdb->get_results( $show_group_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			foreach ( $show_group_results as $row ) {
				$bulk_data[ $row->post_id ]['meta']['lezchars_show_group'][] = array( 'show' => (int) $row->meta_value );
			}
		}

		// Get taxonomy data
		$taxonomies = array();
		switch ( $type ) {
			case 'actors':
				$taxonomies = array( 'lez_actor_sexuality', 'lez_actor_gender' );
				break;
			case 'characters':
				$taxonomies = array( 'lez_sexuality', 'lez_gender', 'lez_cliches' );
				break;
			case 'shows':
				$taxonomies = array( 'lez_formats', 'lez_country', 'lez_stations' );
				break;
		}

		if ( ! empty( $taxonomies ) ) {
			$tax_placeholders = implode( ', ', array_fill( 0, count( $taxonomies ), '%s' ) );
			$tax_query        = "SELECT tr.object_id, tt.taxonomy, t.name, t.slug
				FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
				WHERE tr.object_id IN ($ids_string) AND tt.taxonomy IN ($tax_placeholders)";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- IDs are sanitized; taxonomies are internal constants
			$tax_results = $wpdb->get_results( $wpdb->prepare( $tax_query, ...$taxonomies ) );

			foreach ( $tax_results as $row ) {
				$bulk_data[ $row->object_id ]['taxonomies'][ $row->taxonomy ][] = array(
					'name' => $row->name,
					'slug' => $row->slug,
				);
			}
		}

		return $bulk_data;
	}

	/**
	 * Optimized actor export using bulk data
	 *
	 * @param int    $post_id Post ID
	 * @param string $format Export format
	 * @param array  $bulk_data Pre-fetched bulk data
	 * @return array
	 */
	private function export_actor_optimized( $post_id, $format = 'wiki', $bulk_data = array() ) {
		if ( ! isset( $bulk_data[ $post_id ] ) ) {
			return array();
		}

		$data = $bulk_data[ $post_id ];
		$meta = $data['meta'] ?? array();

		// Check if actor should be hidden
		if ( lwtv_plugin()->hide_actor_data( $post_id, 'all' ) ) {
			return array();
		}

		$return = array(
			'uid'  => $post_id,
			'id'   => $data['post_name'],
			'name' => $data['post_title'],
		);

		// Get sexuality
		$sexuality_terms = $data['taxonomies']['lez_actor_sexuality'] ?? array();
		$sexuality       = ! empty( $sexuality_terms ) ? implode( ', ', wp_list_pluck( $sexuality_terms, 'name' ) ) : '';

		// Get gender
		$gender_terms = $data['taxonomies']['lez_actor_gender'] ?? array();
		$gender       = ! empty( $gender_terms ) ? implode( ', ', wp_list_pluck( $gender_terms, 'name' ) ) : '';

		// Build description based on format
		if ( 'wiki' === $format ) {
			$description_parts = array();
			if ( $sexuality ) {
				$description_parts[] = $sexuality;
			}
			if ( $gender ) {
				$description_parts[] = $gender;
			}
			$description_parts[] = 'actor';

			// Add birth/death if not hidden
			if ( ! lwtv_plugin()->hide_actor_data( $post_id, 'dob' ) && ! empty( $meta['lezactors_birth'] ) ) {
				$birth_date          = new \DateTime( $meta['lezactors_birth'] );
				$description_parts[] = 'b. ' . $birth_date->format( 'Y' );
			}

			if ( ! empty( $meta['lezactors_death'] ) ) {
				$death_date          = new \DateTime( $meta['lezactors_death'] );
				$description_parts[] = 'and died ' . $death_date->format( 'Y' );
			}

			$return['description'] = implode( ' ', $description_parts ) . '.';
		}

		return $return;
	}

	/**
	 * Optimized character export using bulk data
	 *
	 * @param int    $post_id Post ID
	 * @param string $format Export format
	 * @param array  $bulk_data Pre-fetched bulk data
	 * @return array
	 */
	private function export_character_optimized( $post_id, $format = 'wiki', $bulk_data = array() ) {
		if ( ! isset( $bulk_data[ $post_id ] ) ) {
			return array();
		}

		$data = $bulk_data[ $post_id ];
		$meta = $data['meta'] ?? array();

		$return = array(
			'uid'  => $post_id,
			'id'   => $data['post_name'],
			'name' => $data['post_title'],
		);

		// Get sexuality
		$sexuality_terms = $data['taxonomies']['lez_sexuality'] ?? array();
		$sexuality       = ! empty( $sexuality_terms ) ? implode( ', ', wp_list_pluck( $sexuality_terms, 'name' ) ) : '';

		// Get gender
		$gender_terms = $data['taxonomies']['lez_gender'] ?? array();
		$gender       = ! empty( $gender_terms ) ? implode( ', ', wp_list_pluck( $gender_terms, 'name' ) ) : '';

		// Get shows
		$shows = '';
		if ( ! empty( $meta['lezchars_show_group'] ) ) {
			$show_titles = array();
			foreach ( $meta['lezchars_show_group'] as $show_data ) {
				if ( isset( $show_data['show'] ) ) {
					$show_id = is_array( $show_data['show'] ) ? $show_data['show'][0] : $show_data['show'];
					if ( $show_id ) {
						$show_titles[] = get_the_title( $show_id );
					}
				}
			}
			if ( ! empty( $show_titles ) ) {
				if ( count( $show_titles ) > 1 && 'wiki' === $format ) {
					$last_element = array_pop( $show_titles );
					array_push( $show_titles, 'and ' . $last_element );
				}
				$shows = implode( ', ', $show_titles );
			}
		}

		// Get actors
		$actors = '';
		if ( ! empty( $meta['lezchars_actor'] ) ) {
			$actor_titles = array();
			$all_actors   = is_array( $meta['lezchars_actor'] ) ? $meta['lezchars_actor'] : array( $meta['lezchars_actor'] );

			foreach ( $all_actors as $actor_id ) {
				if ( ! lwtv_plugin()->hide_actor_data( $actor_id, 'all' ) ) {
					$actor_titles[] = get_the_title( $actor_id );
				}
			}

			if ( ! empty( $actor_titles ) ) {
				if ( count( $actor_titles ) > 1 && 'wiki' === $format ) {
					$last_element = array_pop( $actor_titles );
					array_push( $actor_titles, 'and ' . $last_element );
				}
				$actors = implode( ', ', $actor_titles );
			}
		}

		if ( 'wiki' === $format ) {
			$description_parts = array();
			if ( $sexuality ) {
				$description_parts[] = 'A ' . $sexuality;
			}
			if ( $gender ) {
				$description_parts[] = $gender;
			}
			$description_parts[] = 'character';
			if ( $shows ) {
				$description_parts[] = 'on ' . $shows;
			}
			if ( $actors ) {
				$description_parts[] = '. Played by ' . $actors;
			}

			$return['description'] = implode( ' ', $description_parts ) . '.';
		}

		return $return;
	}

	/**
	 * Optimized show export using bulk data
	 *
	 * @param int    $post_id Post ID
	 * @param string $format Export format
	 * @param array  $bulk_data Pre-fetched bulk data
	 * @return array
	 */
	private function export_show_optimized( $post_id, $format = 'wiki', $bulk_data = array() ) {
		if ( ! isset( $bulk_data[ $post_id ] ) ) {
			return array();
		}

		$data = $bulk_data[ $post_id ];
		$meta = $data['meta'] ?? array();

		$return = array(
			'uid'  => $post_id,
			'id'   => $data['post_name'],
			'name' => $data['post_title'],
		);

		// Get formats
		$format_terms = $data['taxonomies']['lez_formats'] ?? array();
		$formats      = ! empty( $format_terms ) ? implode( ', ', wp_list_pluck( $format_terms, 'name' ) ) : '';

		// Get nations
		$nation_terms = $data['taxonomies']['lez_country'] ?? array();
		$nations      = '';
		if ( ! empty( $nation_terms ) ) {
			$nation_names = wp_list_pluck( $nation_terms, 'name' );
			if ( count( $nation_names ) > 1 && 'wiki' === $format ) {
				$last_element = array_pop( $nation_names );
				array_push( $nation_names, 'and ' . $last_element );
			}
			$nations = implode( ', ', $nation_names );
		}

		// Get stations
		$station_terms = $data['taxonomies']['lez_stations'] ?? array();
		$stations      = ! empty( $station_terms ) ? implode( ', ', wp_list_pluck( $station_terms, 'name' ) ) : '';

		// Get airdates
		$dates_plain = '';
		if ( ! empty( $meta['lezshows_airdates'] ) ) {
			$airdates           = $meta['lezshows_airdates'];
			$airdates['finish'] = ( 'current' === $airdates['finish'] ) ? 'now' : $airdates['finish'];

			if ( $airdates['start'] === $airdates['finish'] ) {
				$dates_plain = 'in ' . $airdates['finish'];
			} else {
				$dates_plain = 'from ' . $airdates['start'] . '-' . $airdates['finish'];
			}
		}

		// Get IMDB
		$imdb = ! empty( $meta['lezshows_imdb'] ) ? 'https://imdb.com/title/' . $meta['lezshows_imdb'] : '';

		if ( 'wiki' === $format ) {
			$description_parts = array();
			if ( $formats ) {
				$description_parts[] = $formats;
			}
			$description_parts[] = 'airing';
			if ( $nations ) {
				$description_parts[] = 'in ' . $nations;
			}
			if ( $dates_plain ) {
				$description_parts[] = $dates_plain;
			}

			$return['description'] = implode( ' ', $description_parts ) . '.';
		}

		return $return;
	}

	/**
	 * Get data version hash for cache invalidation
	 *
	 * @param string $post_type Post type
	 * @return string Hash based on last modification time
	 */
	private function get_data_version_hash( $post_type ) {
		$cache_key   = 'lwtv_export_data_version_' . $post_type;
		$cached_hash = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_hash ) {
			return $cached_hash;
		}

		global $wpdb;
		$last_modified = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(post_modified) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
				$post_type
			)
		);

		$hash = md5( $last_modified );
		lwtv_plugin()->set_transient( $cache_key, $hash, HOUR_IN_SECONDS );

		return $hash;
	}
}
