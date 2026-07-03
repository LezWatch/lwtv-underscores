<?php
/**
 * Description: REST-API: What Happened
 *
 * The code that runs the What Happened API service
 * - What Happened: Outputs data based on what happened in a given year.
 */

namespace LWTV\Rest_API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Queeries\Post_Meta_And_Tax;
use LWTV\Queeries\Post_Type;
use LWTV\Rest_API\BYQ;
use LWTV\CPTs\Actors as CPT_Actors;
use LWTV\CPTs\Characters as CPT_Characters;
use LWTV\CPTs\Shows as CPT_Shows;
use LWTV\Statistics\Build\Dead;

class What_Happened_JSON {

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
	 *   - /lwtv/v1/what-happened/
	 *   - /lwtv/v1/what-happened/YYYY-MM-DD
	 *   - /lwtv/v1/what-happened/YYYY-MM
	 *   - /lwtv/v1/what-happened/YYYY
	 */
	public function rest_api_init() {
		register_rest_route(
			'lwtv/v1',
			'/what-happened/',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'what_happened_rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'lwtv/v1',
			'/what-happened/(?P<date>[\d]{4}-[\d]{2}-[\d]{2})',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'what_happened_rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'lwtv/v1',
			'/what-happened/(?P<date>[\d]{4}-[\d]{2})',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'what_happened_rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'lwtv/v1',
			'/what-happened/(?P<date>[\d]{4})',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'what_happened_rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Rest API Callback for What Happened
	 */
	public function what_happened_rest_api_callback( $data ) {

		// Create the date with regards to timezones
		$timestamp = time();
		$dt        = new \DateTime( 'now', new \DateTimeZone( LWTV_TIMEZONE ) ); //first argument "must" be a string
		$dt->setTimestamp( $timestamp ); //adjust the object to correct timestamp

		$params   = $data->get_params();
		$the_date = ( isset( $params['date'] ) && '' !== $params['date'] ) ? $params['date'] : $dt->format( 'Y' );
		$response = $this->what_happened( $the_date );
		return $response;
	}

	public function what_happened( $date = false ) {

		// Create the date with regards to timezones
		$timestamp = time();
		$dt        = new \DateTime( 'now', new \DateTimeZone( LWTV_TIMEZONE ) ); //first argument "must" be a string
		$dt->setTimestamp( $timestamp ); //adjust the object to correct timestamp

		$date        = ( ! $date ) ? $dt->format( 'Y' ) : $date;
		$count_array = array();

		// Figure out what date we're working with here...
		if ( preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $date ) ) {
			$format   = 'day';
			$datetime = $dt->createFromFormat( 'Y-m-d', $date );
		}
		if ( preg_match( '/^[0-9]{4}-[0-9]{2}$/', $date ) ) {
			$format   = 'month';
			$datetime = $dt->createFromFormat( 'Y-m', $date );
		}
		if ( preg_match( '/^[0-9]{4}$/', $date ) ) {
			$format   = 'year';
			$datetime = $dt->createFromFormat( 'Y', $date );
		}

		if ( empty( $format ) || empty( $datetime ) ) {
			return new \WP_Error( 'invalid_date', 'The date provided is not valid.' );
		}

		// If it's the future, be smarter than Alexa...
		if ( $datetime->format( 'Y' ) > gmdate( 'Y' ) ) {
			$datetime->modify( '-1 year' );
		}

		// If it's before LWTV_FIRST_YEAR then we have issues....
		if ( $datetime->format( 'Y' ) < LWTV_FIRST_YEAR ) {
			return new \WP_Error( 'too_soon', 'This year is before the first year any queers were on TV.' );
		}

		// Calculate death
		$death_query_year         = ( new Dead() )->get_dead_characters_for_year( $datetime->format( 'Y' ) );
		$count_array['dead_year'] = count( $death_query_year );

		switch ( $format ) {
			case 'year':
				$count_array['dead'] = count( $death_query_year );
				break;
			case 'month':
				$death_query       = $death_query_year;
				$death_list_array  = ( new BYQ() )->list_of_dead_characters( $death_query );
				$death_query_count = 0;
				foreach ( $death_list_array as $the_dead ) {
					if ( $datetime->format( 'm' ) === gmdate( 'm', $the_dead['died'] ) ) {
						++$death_query_count;
					}
				}
				$count_array['dead'] = $death_query_count;
				break;
			case 'day':
				global $wpdb;
				// ACF's date_picker stores raw postmeta as Ymd; legacy pre-migration
				// rows that haven't been re-saved may still be in Y-m-d format.
				$date_ymd      = $datetime->format( 'Ymd' );
				$date_legacy   = $datetime->format( 'Y-m-d' );
				$meta_key_like = $wpdb->esc_like( 'lezchars_death_year_' ) . '%' . $wpdb->esc_like( '_date' );
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$dead_ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm
						INNER JOIN {$wpdb->term_relationships} tr ON pm.post_id = tr.object_id
						INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
						INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
						WHERE pm.meta_key LIKE %s
						AND pm.meta_value IN ( %s, %s )
						AND tt.taxonomy = 'lez_cliches' AND t.slug = 'dead'",
						$meta_key_like,
						$date_ymd,
						$date_legacy
					)
				);
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$count_array['dead'] = count( $dead_ids );
				break;
			default:
				$count_array['dead'] = 0;
		}

		// This is calculating how much content we've added since the site started.
		if ( $datetime->format( 'Y' ) > LWTV_CREATED_YEAR ) {
			// Calculate characters and shows
			$valid_post_types = array(
				'posts'      => 'post',
				'shows'      => CPT_Shows::SLUG,
				'characters' => CPT_Characters::SLUG,
				'actors'     => CPT_Actors::SLUG,
			);

			switch ( $format ) {
				case 'day':
					$date_args = array(
						'year'  => $datetime->format( 'Y' ),
						'month' => $datetime->format( 'm' ),
						'day'   => $datetime->format( 'd' ),
					);
					break;
				case 'month':
					$date_args = array(
						'year'  => $datetime->format( 'Y' ),
						'month' => $datetime->format( 'm' ),
					);
					break;
				default:
					$date_args = array(
						'year' => $datetime->format( 'Y' ),
					);
					break;
			}

			foreach ( $valid_post_types as $name => $type ) {
				$post_args            = array(
					'post_type'      => $type,
					'posts_per_page' => '-1',
					'orderby'        => 'date',
					'order'          => 'DESC',
					'date_query'     => array( $date_args ),
					'no_found_rows'  => true,
				);
				$queery               = new \WP_Query( $post_args );
				$count_array[ $name ] = $queery->post_count;
				wp_reset_postdata();
			}
		}

		// Information for shows
		$show_data = self::count_shows( $datetime->format( 'Y' ) );

		if ( is_null( $show_data ) ) {
			$show_data = array(
				'for_year' => $datetime->format( 'Y' ),
				'current'  => 0,
				'started'  => 0,
				'ended'    => 0,
			);
		}

		$count_array['on_air'] = array(
			'for_year' => $show_data['for_year'],
			'current'  => $show_data['current'],
			'started'  => $show_data['started'],
			'ended'    => $show_data['ended'],
		);

		return $count_array;
	}

	/**
	 * count_shows function.
	 *
	 * @access public
	 * @static
	 * @return void
	 */
	public function count_shows( $thisyear = false ) {

		// Create the date with regards to timezones
		$timestamp = time();
		$dt        = new \DateTime( 'now', new \DateTimeZone( LWTV_TIMEZONE ) ); //first argument "must" be a string
		$dt->setTimestamp( $timestamp ); //adjust the object to correct timestamp

		$thisyear        = ( ! $thisyear ) ? $dt->format( 'Y' ) : $thisyear;
		$shows_queery    = ( new Post_Type() )->make( CPT_Shows::SLUG );
		$shows_this_year = array(
			'current' => 0,
			'ended'   => 0,
			'started' => 0,
		);

		if ( ! is_object( $shows_queery ) || ! $shows_queery->have_posts() ) {
			return $shows_this_year;
		}

		while ( $shows_queery->have_posts() ) {
			$shows_queery->the_post();

			$show_id = get_the_ID();

			// Shows Currently Airing
			$ad_start  = get_post_meta( $show_id, 'lezshows_airdates_start', true );
			$ad_finish = get_post_meta( $show_id, 'lezshows_airdates_finish', true );
			if ( empty( $ad_start ) || empty( $ad_finish ) ) {
				$legacy    = get_post_meta( $show_id, 'lezshows_airdates', true );
				$ad_start  = $ad_start ?: ( is_array( $legacy ) ? ( $legacy['start'] ?? '' ) : '' );
				$ad_finish = $ad_finish ?: ( is_array( $legacy ) ? ( $legacy['finish'] ?? '' ) : '' );
			}
			if ( ! empty( $ad_start ) && ! empty( $ad_finish ) ) {
				if (
					( 'current' === $ad_finish && $thisyear === $dt->format( 'Y' ) )
					|| ( $ad_finish >= $thisyear && $ad_start <= $thisyear ) // Airdates between
				) {
					// Currently Airing Shows shows for the current year only
					++$shows_this_year['current'];
				}

				// Shows that ended this year
				if ( $ad_finish === $thisyear ) {
					++$shows_this_year['ended'];
				}

				// Shows that STARTED this year
				if ( $ad_start === $thisyear ) {
					++$shows_this_year['started'];
				}
			}
		}

		return $shows_this_year;
	}
}
