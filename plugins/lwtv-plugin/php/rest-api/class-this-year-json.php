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
	 * Rest API init
	 *
	 * Creates callbacks
	 *   - /lwtv/v1/this-year/[shows|characters|death]/[simple|complex|years]
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

		// Year
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
	 * @return array
	 */
	public function rest_api_callback( $data ) {
		$params = $data->get_params();
		$type   = ( isset( $params['type'] ) && '' !== $params['type'] ) ? sanitize_title_for_query( $params['type'] ) : 'year';
		$year   = ( isset( $params['year'] ) && '' !== $params['year'] && ( $params['year'] >= LWTV_FIRST_YEAR && $params['year'] <= gmdate( 'Y' ) ) ) ? (int) $params['year'] : gmdate( 'Y' );

		switch ( $type ) {
			case 'year':
				$response = $this->this_year( 'year', $year );
				break;
			case 'ten-years':
				$response = $this->this_year( 'ten', $year );
				break;
		}

		if ( empty( $response ) ) {
			return new \WP_Error( 'not_found', 'Invalid year.' );
		}

		return $response;
	}

	/**
	 * Parse this year and call the data as needed
	 * @param  string  $type What kind of data for the year
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

		$year  = ( isset( $year ) ) ? (int) $year : gmdate( 'Y' );
		$array = array();

		switch ( $type ) {
			case 'year':
				$array = self::one_year( $year );
				break;
			case 'ten':
				$array = self::ten_years( $year );
				break;
		}

		return $array;
	}

	/**
	 * Get one year of data
	 * @param  int   $year  Year
	 * @return array        Array of data from one year
	 */
	public function one_year( $year ) {
		$this_year = (string) $year;
		$array     = array(
			'year'       => (int) $this_year,
			'characters' => ( new Characters_Builder() )->get_characters_for_year( $this_year ),
			'dead'       => ( new Characters_Builder() )->get_dead_characters_for_year( $this_year ),
			'shows'      => ( new Shows_Builder() )->get_shows_for_year( $this_year ),
			'started'    => ( new Shows_Builder() )->get_new_shows_for_year( $this_year ),
			'canceled'   => ( new Shows_Builder() )->get_ended_shows_for_year( $this_year ),
		);

		return $array;
	}

	public function ten_years( $year ) {

		$array      = array();
		$end_year   = ( $year >= LWTV_FIRST_YEAR ) ? $year : LWTV_FIRST_YEAR;
		$end_year   = ( $year <= gmdate( 'Y' ) ) ? $year : gmdate( 'Y' );
		$start_year = $end_year - 10;

		while ( $start_year <= $end_year ) {
			if ( ( $start_year >= LWTV_FIRST_YEAR && $start_year <= gmdate( 'Y' ) ) ) {
				$array[ $start_year ] = array(
					'characters' => ( new Characters_Builder() )->get_characters_for_year( $start_year ),
					'dead'       => ( new Characters_Builder() )->get_dead_characters_for_year( $start_year ),
					'shows'      => ( new Shows_Builder() )->get_shows_for_year( $start_year ),
					'started'    => ( new Shows_Builder() )->get_new_shows_for_year( $start_year ),
					'canceled'   => ( new Shows_Builder() )->get_ended_shows_for_year( $start_year ),
				);
			}
			++$start_year;
		}

		return $array;
	}
}
