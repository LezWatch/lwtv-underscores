<?php
/**
 * Powers the This-Year Rest API
 */

namespace LWTV\Rest_API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\This_Year\Build\Characters_Builder;
use LWTV\This_Year\Build\Shows_Builder;

class This_Year_JSON {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
	}

	/**
	 * The slices of one year that have their own route, mapped to
	 * [ builder class, list method ].
	 */
	const SLICES = array(
		'characters' => array( Characters_Builder::class, 'get_characters_for_year' ),
		'dead'       => array( Characters_Builder::class, 'get_dead_characters_for_year' ),
		'shows'      => array( Shows_Builder::class, 'get_shows_for_year' ),
		'started'    => array( Shows_Builder::class, 'get_new_shows_for_year' ),
		'canceled'   => array( Shows_Builder::class, 'get_ended_shows_for_year' ),
	);

	/**
	 * Rest API init
	 *
	 * Creates callbacks
	 *   - /lwtv/v1/this-year/                          Counts for this year
	 *   - /lwtv/v1/this-year/{year}/                   Counts for one year
	 *   - /lwtv/v1/this-year/year/{year}/              Counts for one year
	 *   - /lwtv/v1/this-year/ten-years/{end year}/     Counts for eleven years ending in {end year}
	 *   - /lwtv/v1/this-year/{slice}/{year}/           The full list for one slice (see SLICES)
	 */
	public function rest_api_init() {

		// Basic Stats
		register_rest_route(
			'lwtv/v1',
			'/this-year/',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);

		// Types
		register_rest_route(
			'lwtv/v1',
			'/this-year/(?P<type>[a-zA-Z.\-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);

		// Year only (same as year/{year})
		register_rest_route(
			'lwtv/v1',
			'/this-year/(?P<year>[\d]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);

		// Type and Year
		register_rest_route(
			'lwtv/v1',
			'/this-year/(?P<type>[a-zA-Z.\-]+)/(?P<year>[\d]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Rest API Callback for This Year
	 *
	 * @param mixed $data - string.
	 * @return array|\WP_Error
	 */
	public function rest_api_callback( $data ) {
		$params = $data->get_params();
		$type   = ( isset( $params['type'] ) && '' !== $params['type'] ) ? sanitize_title_for_query( $params['type'] ) : 'year';
		$year   = (int) wp_date( 'Y' );

		if ( isset( $params['year'] ) && '' !== $params['year'] ) {
			$year = (int) $params['year'];
			if ( $year < (int) LWTV_FIRST_YEAR || $year > (int) wp_date( 'Y' ) ) {
				return new \WP_Error( 'invalid_year', 'Years run from ' . LWTV_FIRST_YEAR . ' to ' . wp_date( 'Y' ) . '.', array( 'status' => 400 ) );
			}
		}

		if ( 'year' === $type ) {
			$response = $this->this_year( 'year', $year );
		} elseif ( 'ten-years' === $type ) {
			$response = $this->this_year( 'ten', $year );
		} elseif ( isset( self::SLICES[ $type ] ) ) {
			$response = $this->this_year( $type, $year );
		} else {
			return new \WP_Error( 'not_found', 'No route was found matching the URL and request method', array( 'status' => 404 ) );
		}

		return $response;
	}

	/**
	 * Parse this year and call the data as needed
	 * @param  string  $type year, ten, or a key of SLICES
	 * @param  int     $year What year
	 * @return array   Array of data
	 */
	public function this_year( $type, $year ) {

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

		$year  = ( isset( $year ) ) ? (int) $year : (int) wp_date( 'Y' );
		$array = array();

		switch ( $type ) {
			case 'year':
				$array = array( 'year' => $year ) + self::year_counts( $year );
				break;
			case 'ten':
				$array = self::ten_years( $year );
				break;
			default:
				if ( isset( self::SLICES[ $type ] ) ) {
					list( $builder, $method ) = self::SLICES[ $type ];
					$array                    = array(
						'year' => $year,
						$type  => ( new $builder() )->$method( $year ),
					);
				}
		}

		return $array;
	}

	/**
	 * The documented per-year counts.
	 *
	 * @param  int $year Year
	 * @return array     array{characters:int, dead:int, shows:int, started:int, canceled:int}
	 */
	public function year_counts( $year ) {
		$characters = new Characters_Builder();
		$shows      = new Shows_Builder();

		return array(
			'characters' => $characters->get_character_count_for_year( $year ),
			'dead'       => $characters->get_dead_character_count_for_year( $year ),
			'shows'      => $shows->get_show_count_for_year( $year ),
			'started'    => $shows->get_started_show_count_for_year( $year ),
			'canceled'   => $shows->get_ended_show_count_for_year( $year ),
		);
	}

	/**
	 * Counts for eleven years: $year and the ten before it, from the first year on.
	 *
	 * @param  int $year End year
	 * @return array     Counts keyed by year
	 */
	public function ten_years( $year ) {

		$array    = array();
		$end_year = min( max( (int) $year, (int) LWTV_FIRST_YEAR ), (int) wp_date( 'Y' ) );
		$start    = max( $end_year - 10, (int) LWTV_FIRST_YEAR );

		for ( $this_year = $start; $this_year <= $end_year; $this_year++ ) {
			$array[ $this_year ] = self::year_counts( $this_year );
		}

		return $array;
	}
}
