<?php
/**
 * Description: REST-API - Stats output
 *
 * So other people can access our stats data
 */

namespace LWTV\Rest_API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\_Components\Statistics_Optimized as Base_Stats;
use LWTV\Queeries\Is_Actor_Queer;
use LWTV\Queeries\Post_Type;
use LWTV\CPTs\Actors as CPT_Actors;
use LWTV\CPTs\Characters as CPT_Characters;
use LWTV\CPTs\Shows as CPT_Shows;

class Stats_JSON {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
	}

	/**
	 * Rest API init
	 *
	 * Creates callbacks
	 *   - /lwtv/v1/stats/[shows|characters|death]/[simple|complex|years]
	 */
	public function rest_api_init() {

		// Basic Stats
		register_rest_route(
			'lwtv/v1',
			'/stats/',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'stats_rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);

		// Stat Types
		register_rest_route(
			'lwtv/v1',
			'/stats/(?P<type>[a-zA-Z.\-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'stats_rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);

		// Stat Types and Format
		register_rest_route(
			'lwtv/v1',
			'/stats/(?P<type>[a-zA-Z]+)/(?P<format>[a-zA-Z]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'stats_rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);

		// Stat Types and Format AND PER PAGE
		register_rest_route(
			'lwtv/v1',
			'/stats/(?P<type>[a-zA-Z]+)/(?P<format>[a-zA-Z]+)/(?P<page>[0-9.\-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'stats_rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Rest API Callback for Statistics
	 *
	 * @access public
	 * @param mixed $data - string.
	 * @return array
	 */
	public function stats_rest_api_callback( $data ) {
		$params    = $data->get_params();
		$stat_type = ( isset( $params['type'] ) && '' !== $params['type'] ) ? sanitize_title_for_query( $params['type'] ) : 'none';
		$format    = ( isset( $params['format'] ) && '' !== $params['format'] ) ? sanitize_title_for_query( $params['format'] ) : 'simple';
		$page      = ( isset( $params['page'] ) && '' !== $params['page'] ) ? intval( $params['page'] ) : '1';
		$response  = $this->statistics( $stat_type, $format, $page );

		if ( false === $response ) {
			return new \WP_Error( 'not_found', 'No route was found matching the URL and request method.' );
		}

		return $response;
	}

	/**
	 * Generate Statistics
	 *
	 * @return array with stats data
	 */
	public function statistics( $stat_type = 'characters', $format = 'simple', $page = 1 ) {

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

		// Valid Data
		$valid_type   = array( 'characters', 'actors', 'shows', 'death', 'first-year', 'stations', 'nations', 'none' );
		$valid_format = array( 'simple', 'complex', 'years', 'cliches', 'tropes', 'worth-it', 'stars', 'formats', 'triggers', 'loved', 'nations', 'sexuality', 'gender', 'romantic', 'genres', 'queer-irl', 'intersections', 'id' );

		// Per Page Check
		if ( 0 === $page ) {
			$page = 1;
		}

		// Sanity Check
		if ( ! in_array( $stat_type, $valid_type, true ) || ! in_array( $format, $valid_format, true ) ) {
			return new \WP_Error( 'not_found', 'No route was found matching the URL and request method' );
		}

		switch ( $stat_type ) {
			case 'first-year':
				$stats_array = array(
					'first' => LWTV_FIRST_YEAR,
				);
				break;
			case 'shows':
				$stats_array = self::get_shows( $format, $page );
				break;
			case 'characters':
				$stats_array = self::get_characters( $format, $page );
				break;
			case 'actors':
				$stats_array = self::get_actors( $format, $page );
				break;
			case 'death':
				$stats_array = self::get_death( $format );
				break;
			case 'stations':
				$stats_array = self::get_show_taxonomy( 'stations' );
				break;
			case 'nations':
				$stats_array = self::get_show_taxonomy( 'country' );
				break;
			case 'none':
			default:
				$stats_array = self::get_characters( 'simple' );
		}

		return $stats_array;
	}

	/**
	 * get_actors function.
	 *
	 * @access public
	 * @static
	 * @param string $format (default: 'simple')
	 * @return array
	 */
	public function get_actors( $format = 'simple', $page = 1 ) {

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

		// Validate Data
		$valid_format = array( 'simple', 'complex', 'sexuality', 'gender', 'queer-irl', 'id' );

		// Sanity Check
		if ( ! in_array( $format, $valid_format, true ) ) {
			return new \WP_Error( 'not_found', 'No route was found matching the URL and request method' );
		}

		$stats_array = array();

		switch ( $format ) {
			case 'id':
				$stats_array = self::format_id( 'actor', $page );
				break;
			case 'queer-irl':
				$stats_array = ( new Base_Stats() )->generate_actors_statistics( 'array', 'queer-irl' );
				break;
			case 'gender':
				$stats_array = ( new Base_Stats() )->generate_actors_statistics( 'array', 'gender' );
				break;
			case 'sexuality':
				$stats_array = ( new Base_Stats() )->generate_actors_statistics( 'array', 'sexuality' );
				break;
			case 'complex':
				$queery = ( new Post_Type() )->make( CPT_Actors::SLUG, $page );

				if ( ! is_object( $queery ) || ! $queery->have_posts() ) {
					return $stats_array;
				}

				$actors = wp_list_pluck( $queery->posts, 'ID' );

				foreach ( $actors as $actor ) {
					$stats_array[ get_the_title( $actor ) ] = array(
						'id'         => $actor,
						'characters' => get_post_meta( $actor, 'lezactors_char_count', true ),
						'dead_chars' => get_post_meta( $actor, 'lezactors_dead_count', true ),
						'gender'     => implode( ', ', wp_get_post_terms( $actor, 'lez_actor_gender', array( 'fields' => 'names' ) ) ),
						'sexuality'  => implode( ', ', wp_get_post_terms( $actor, 'lez_actor_sexuality', array( 'fields' => 'names' ) ) ),
						'queer'      => ( ( new Is_Actor_Queer() )->make( $actor ) ) ? 'yes' : 'no',
						'url'        => get_the_permalink( $actor ),
					);
				}
				break;
			case 'simple':
				$stats_array = array(
					'total'     => wp_count_posts( CPT_Actors::SLUG )->publish,
					'gender'    => wp_count_terms( 'lez_actor_gender' ),
					'sexuality' => wp_count_terms( 'lez_actor_sexuality' ),
				);
				break;
		}

		return $stats_array;
	}

	/**
	 * get_characters function.
	 *
	 * @access public
	 * @static
	 * @param string $format (default: 'simple')
	 * @return array
	 */
	public function get_characters( $format = 'simple', $page = 1 ) {

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

		// Validate Data
		$valid_format = array( 'simple', 'complex', 'sexuality', 'gender', 'romantic', 'cliches' );

		// Sanity Check
		if ( ! in_array( $format, $valid_format, true ) ) {
			return new \WP_Error( 'not_found', 'No route was found matching the URL and request method' );
		}

		$stats_array = array();

		switch ( $format ) {
			case 'id':
				$stats_array = self::format_id( 'character', $page );
				break;
			case 'cliches':
				$stats_array = ( new Base_Stats() )->generate_characters_statistics( 'array', 'cliches' );
				break;
			case 'sexuality':
				$stats_array = ( new Base_Stats() )->generate_characters_statistics( 'array', 'sexuality' );
				break;
			case 'gender':
				$stats_array = ( new Base_Stats() )->generate_characters_statistics( 'array', 'gender' );
				break;
			case 'romantic':
				$stats_array = ( new Base_Stats() )->generate_characters_statistics( 'array', 'romantic' );
				break;
			case 'complex':
				$charactersloop = ( new Post_Type() )->make( CPT_Characters::SLUG, $page );

				if ( ! is_object( $charactersloop ) || ! $charactersloop->have_posts() ) {
					return $stats_array;
				}

				$characters = wp_list_pluck( $charactersloop->posts, 'ID' );

				foreach ( $characters as $character ) {
					if ( CPT_Characters::SLUG !== get_post_type( $character ) ) {
						continue;
					}

					$death_rows = get_field( 'lezchars_death_year', $character );
					$died       = is_array( $death_rows ) ? array_filter( array_column( $death_rows, 'date' ) ) : array();
					$shows  = count( get_post_meta( $character, 'lezchars_show_group', true ) );
					$actors = count( get_post_meta( $character, 'lezchars_actor', true ) );
					$gender = implode(
						', ',
						wp_get_post_terms(
							$character,
							'lez_gender',
							array(
								'fields' => 'names',
							)
						)
					);
					$sexual = implode(
						', ',
						wp_get_post_terms(
							$character,
							'lez_sexuality',
							array(
								'fields' => 'names',
							)
						)
					);

					$stats_array[ get_the_title( $character ) ] = array(
						'id'        => $character,
						'died'      => $died,
						'actors'    => $actors,
						'shows'     => $shows,
						'gender'    => $gender,
						'sexuality' => $sexual,
						'url'       => get_the_permalink( $character ),
					);
				}
				break;
			case 'simple':
				$dead_count  = get_term_by( 'slug', 'dead', 'lez_cliches' );
				$stats_array = array(
					'total'                => (int) wp_count_posts( CPT_Characters::SLUG )->publish,
					'dead'                 => $dead_count->count,
					'genders'              => (int) wp_count_terms( 'lez_gender' ),
					'sexualities'          => (int) wp_count_terms( 'lez_sexuality' ),
					'romantic_orientation' => (int) wp_count_terms( 'lez_romantic' ),
					'cliches'              => (int) wp_count_terms( 'lez_cliches' ),
				);
				break;
		}

		return $stats_array;
	}

	/**
	 * get_death function.
	 *
	 * @access public
	 * @static
	 * @param string $format (default: 'simple')
	 * @return void
	 */
	public function get_death( $format = 'simple' ) {
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

		// Validate Data
		$valid_format = array( 'simple', 'complex', 'years' );
		$format       = ( ! in_array( $format, $valid_format, true ) ) ? 'simple' : $format;
		$stats_array  = array();

		switch ( $format ) {
			case 'complex':
				$stats_array = array(
					'shows'     => ( new Base_Stats() )->generate_dead_statistics( 'characters', 'shows', 'array' ),
					'sexuality' => ( new Base_Stats() )->generate_dead_statistics( 'characters', 'sexuality', 'array' ),
					'gender'    => ( new Base_Stats() )->generate_dead_statistics( 'characters', 'gender', 'array' ),
				);
				break;
			case 'years':
				$stats_array = ( new Base_Stats() )->generate_dead_statistics( 'characters', 'years', 'array' );
				break;
			case 'list':
				$stats_array = ( new Base_Stats() )->generate_dead_statistics( 'characters', 'list', 'array' );
				break;
			case 'simple':
				$dead_chars  = get_term_by( 'slug', 'dead', 'lez_cliches' );
				$dead_shows  = get_term_by( 'slug', 'dead-queers', 'lez_tropes' );
				$stats_array = array(
					'characters' => array(
						'dead'  => $dead_chars->count,
						'alive' => ( ( new Base_Stats() )->generate_total_counts( 'characters' ) - $dead_chars->count ),
					),
					'shows'      => array(
						'death'    => $dead_shows->count,
						'no-death' => ( ( new Base_Stats() )->generate_total_counts( 'shows' ) - $dead_shows->count ),
					),
				);
				break;
		}

		return $stats_array;
	}

	/**
	 * get_shows function.
	 *
	 * @access public
	 * @static
	 * @param string $format (default: 'simple')
	 * @return array
	 */
	public function get_shows( $format = 'simple', $page = 1 ) {
		// phpcs:disable
		// Remove <!--fwp-loop--> from output
		add_filter(
			'facetwp_is_main_query',
			function ( $is_main_query, $query ) {
				return false;
			},
			10,
			2
		);
		// phpcs:enable

		// Validate Data
		$valid_format = array( 'simple', 'complex', 'nations', 'formats', 'stars', 'triggers', 'loved', 'worth-it', 'tropes', 'genres', 'id', 'name' );

		// Sanity Check
		if ( ! in_array( $format, $valid_format, true ) ) {
			return new \WP_Error( 'not_found', 'No route was found matching the URL and request method' );
		}

		$stats_array = array();

		switch ( $format ) {
			case 'id':
				$stats_array = self::format_id( 'show', $page );
				break;
			case 'name':
				$stats_array = self::format_slug( 'show', $page );
				break;
			case 'tropes':
				$stats_array = ( new Base_Stats() )->generate_shows_statistics( 'array', 'tropes' );
				break;
			case 'nations':
				$stats_array = ( new Base_Stats() )->generate_shows_statistics( 'array', 'nations' );
				break;
			case 'genres':
				$stats_array = ( new Base_Stats() )->generate_shows_statistics( 'array', 'genres' );
				break;
			case 'triggers':
				$stats_array = ( new Base_Stats() )->generate_shows_statistics( 'array', 'triggers' );
				break;
			case 'formats':
				$stats_array = ( new Base_Stats() )->generate_shows_statistics( 'array', 'formats' );
				break;
			case 'stars':
				$stats_array = ( new Base_Stats() )->generate_shows_statistics( 'array', 'stars' );
				break;
			case 'loved':
				$stats_array = ( new Base_Stats() )->generate_shows_statistics( 'array', 'we-love-it' );
				break;
			case 'worth-it':
				$stats_array = ( new Base_Stats() )->generate_shows_statistics( 'array', 'worth-it' );
				break;
			case 'intersections':
				$stats_array = ( new Base_Stats() )->generate_shows_statistics( 'array', 'intersections' );
				break;
			case 'complex':
				$showsloop = ( new Post_Type() )->make( CPT_Shows::SLUG, $page );

				if ( ! is_object( $showsloop ) || ! $showsloop->have_posts() ) {
					return $stats_array;
				}

				$shows = wp_list_pluck( $showsloop->posts, 'ID' );

				foreach ( $shows as $show ) {
					$stats_array[ get_the_title( $show ) ] = array(
						'id'              => $show,
						'nations'         => implode( ', ', wp_get_post_terms( $show, 'lez_country', array( 'fields' => 'names' ) ) ),
						'stations'        => implode( ', ', wp_get_post_terms( $show, 'lez_stations', array( 'fields' => 'names' ) ) ),
						'worth_it'        => get_post_meta( $show, 'lezshows_worthit_rating', true ),
						'trigger'         => implode( ', ', wp_get_post_terms( $show, 'lez_triggers', array( 'fields' => 'names' ) ) ),
						'star'            => implode( ', ', wp_get_post_terms( $show, 'lez_stars', array( 'fields' => 'names' ) ) ),
						'loved'           => ( ( get_post_meta( $show, 'lezshows_worthit_show_we_love', true ) ) ? 'yes' : 'no' ),
						'chars_total'     => get_post_meta( $show, 'lezshows_char_count', true ),
						'chars_dead'      => get_post_meta( $show, 'lezshows_dead_count', true ),
						'chars_sexuality' => get_post_meta( $show, 'lezshows_char_sexuality', true ),
						'chars_gender'    => get_post_meta( $show, 'lezshows_char_gender', true ),
						'url'             => get_the_permalink( $show ),
					);
				}
				break;
			case 'simple':
				$stats_array = array(
					'total'    => ( new Base_Stats() )->generate_total_counts( 'shows' ),
					'stations' => wp_count_terms( 'lez_stations' ),
					'nations'  => wp_count_terms( 'lez_country' ),
					'formats'  => wp_count_terms( 'lez_formats' ),
					'genres'   => wp_count_terms( 'lez_genres' ),
				);
				break;
		}

		return $stats_array;
	}

	/**
	 * format_slug function.
	 *
	 * Get show/actor/character by slug and return data.
	 *
	 * @access public
	 * @static
	 * @return array
	 */
	public function format_slug( $post_type, $slug ) {

		// If there's no name or it's not a valid post type, bail.
		if ( ! $slug || ! in_array( $post_type, array( 'actors', 'characters', 'shows' ), true ) ) {
			return false;
		}

		$post = get_page_by_path( $slug, OBJECT, 'post_type_' . $post_type . 's' );

		$stats_array = self::format_id( $post_type, $post->ID );

		return $stats_array;
	}

	/**
	 * format_id function.
	 *
	 * Get show/actor/character by ID and return data.
	 *
	 * @access public
	 * @static
	 * @return array
	 */
	public function format_id( $cpt, $id = 1 ) {

		$post_status = get_post_status( $id );
		$post_type   = get_post_type( $id );

		if ( ! $post_status || 'post_type_' . $cpt . 's' !== $post_type ) {
			$stats_array = array( 'Error: Invalid ' . ucfirst( $cpt ) . ' ID provided.' );
			return $stats_array;
		}

		switch ( $cpt ) {
			case 'actor':
				$stats_array = array(
					'id'         => $id,
					'name'       => get_the_title( $id ),
					'characters' => get_post_meta( $id, 'lezactors_char_count', true ),
					'dead_chars' => get_post_meta( $id, 'lezactors_dead_count', true ),
					'gender'     => implode( ', ', wp_get_post_terms( $id, 'lez_actor_gender', array( 'fields' => 'names' ) ) ),
					'sexuality'  => implode( ', ', wp_get_post_terms( $id, 'lez_actor_sexuality', array( 'fields' => 'names' ) ) ),
					'queer'      => ( ( new Is_Actor_Queer() )->make( $id ) ) ? 'yes' : 'no',
					'url'        => get_the_permalink( $id ),
				);
				break;
			case 'character':
				$death_rows  = get_field( 'lezchars_death_year', $id );
				$died        = is_array( $death_rows ) ? array_filter( array_column( $death_rows, 'date' ) ) : array();
				$stats_array = array(
					'id'        => $id,
					'name'      => get_the_title( $id ),
					'died'      => $died,
					'actors'    => count( get_post_meta( $id, 'lezchars_actor', true ) ),
					'shows'     => count( get_post_meta( $id, 'lezchars_show_group', true ) ),
					'gender'    => implode( ', ', wp_get_post_terms( $id, 'lez_gender', array( 'fields' => 'names' ) ) ),
					'sexuality' => implode( ', ', wp_get_post_terms( $id, 'lez_sexuality', array( 'fields' => 'names' ) ) ),
					'url'       => get_the_permalink(),
				);
				break;
			case 'show':
				$stats_array = array(
					'id'              => $id,
					'title'           => get_the_title( $id ),
					'nations'         => implode( ', ', wp_get_post_terms( $id, 'lez_country', array( 'fields' => 'names' ) ) ),
					'stations'        => implode( ', ', wp_get_post_terms( $id, 'lez_stations', array( 'fields' => 'names' ) ) ),
					'worth_it'        => get_post_meta( $id, 'lezshows_worthit_rating', true ),
					'trigger'         => implode( ', ', wp_get_post_terms( $id, 'lez_triggers', array( 'fields' => 'names' ) ) ),
					'star'            => implode( ', ', wp_get_post_terms( $id, 'lez_stars', array( 'fields' => 'names' ) ) ),
					'loved'           => ( ( get_post_meta( $id, 'lezshows_worthit_show_we_love', true ) ) ? 'yes' : 'no' ),
					'chars_total'     => get_post_meta( $id, 'lezshows_char_count', true ),
					'chars_dead'      => get_post_meta( $id, 'lezshows_dead_count', true ),
					'chars_sexuality' => get_post_meta( $id, 'lezshows_char_sexuality', true ),
					'chars_gender'    => get_post_meta( $id, 'lezshows_char_gender', true ),
					'url'             => get_the_permalink( $id ),
				);
				break;
		}

		return $stats_array;
	}

	/**
	 * get_show_taxonomy function.
	 *
	 * @access public
	 * @static
	 * @param string $type Type of taxonomy (stations, country)
	 * @return array
	 */
	public function get_show_taxonomy( $type ) {

		$valid_types = array( 'stations', 'country' );

		// Early bail
		if ( ! in_array( $type, $valid_types, true ) ) {
			return new \WP_Error( 'not_found', 'No route was found matching the URL and request method' );
		}

		// Map type to method
		$method = ( 'stations' === $type ) ? 'generate_station_statistics' : 'generate_nation_statistics';

		// Use the optimized statistics system
		return ( new Base_Stats() )->$method( 'all', 'all', 'array' );
	}
}
