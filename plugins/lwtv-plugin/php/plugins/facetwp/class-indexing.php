<?php
/*
 * Description: FacetWP Indexing
 */

namespace LWTV\Plugins\FacetWP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Indexing {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Filter data before saving it
		add_filter( 'facetwp_index_row', array( $this, 'facetwp_index_row' ), 10, 2 );

		// Filter Facet output
		add_filter( 'facetwp_facet_html', array( $this, 'facetwp_facet_html' ), 10, 2 );

		// Adding a weird filter...
		add_filter( 'facetwp_facet_sources', array( $this, 'facetwp_facet_sources' ), 10, 2 );

		// Force Facet to show sometimes
		add_filter( 'facetwp_is_main_query', array( $this, 'facetwp_is_main_query' ), 10, 2 );
	}

	/**
	 * Filter Data before it's saved
	 *
	 * This is how we break apart arrays and save them as individual values, as well as
	 * reformatting some of the data.
	 *
	 * @param array $params
	 * @param object $facet_class
	 *
	 * @return array
	 */
	public function facetwp_index_row( $params, $facet_class ) {
		switch ( get_post_type( $params['post_id'] ) ) {
			case 'post_type_actors':
				$params = $this->facetwp_index_row_actors( $params, $facet_class );
				break;
			case 'post_type_characters':
				$params = $this->facetwp_index_row_characters( $params, $facet_class );
				break;
			case 'post_type_shows':
				$params = $this->facetwp_index_row_shows( $params, $facet_class );
				break;
		}

		return $params;
	}

	/**
	 * Filter Facet output
	 *
	 * Sometimes the default labels are not what we want.
	 *
	 * @param string $output
	 * @param array $params
	 *
	 * @return string
	 */
	public function facetwp_facet_html( $output, $params ) {
		// Change the labels for the airdates facet
		if ( 'show_airdates' === $params['facet']['name'] ) {
			$output = str_replace( 'Min', 'First Year', $output );
			$output = str_replace( 'Max', 'Last Year', $output );
		}

		return $output;
	}

	/**
	 * Filter Facet Sources
	 *
	 * I don't remember WHY this is needed.
	 *
	 * @param array $sources
	 * @param array $params
	 *
	 * @return array
	 */
	public function facetwp_facet_sources( $sources ) {
		$sources['custom_fields']['choices']['cf/lwtv_data'] = 'lwtv_data';

		return $sources;
	}

	/**
	 * Force Facet to show sometimes
	 *
	 * @since 1.1
	 *
	 * @param bool $is_main_query
	 * @param WP_Query $query
	 *
	 * @return bool
	 */
	public function facetwp_is_main_query( $is_main_query, $query ) {
		if ( is_admin() ) {
			$is_main_query = false;
		} elseif ( isset( $query->query_vars['facetwp'] ) ) {
			$is_main_query = true;
		}
		return $is_main_query;
	}

	/**
	 * Indexing for Actors
	 *
	 * @param array $params
	 * @param object $facet_class
	 *
	 * @return array
	 */
	public function facetwp_index_row_actors( $params, $facet_class ) {
		switch ( $params['facet_name'] ) {
			case 'is_queer':
				$params = $this->facetwp_index_row_actors_queer( $params, $facet_class );
				break;
		}

		return $params;
	}

	/**
	 * Indexing for Actors - Queer
	 * Change 'on' to 'yes'
	 *
	 * This is a checkbox, so it's a little different than the others.
	 *
	 * @param array $params
	 * @param object $facet_class
	 *
	 * @return array
	 */
	public function facetwp_index_row_actors_queer( $params, $facet_class ) {
		$params['facet_value']         = ( '1' === $params['facet_value'] ) ? 'yes' : 'no';
		$params['facet_display_value'] = ( '1' === $params['facet_display_value'] ) ? 'Is Queer' : 'Is Not Queer';
		$facet_class->insert( $params );

		// skip default indexing
		$params['facet_value'] = '';

		return $params;
	}

	/**
	 * Indexing for Characters
	 *
	 * @param array $params
	 * @param object $facet_class
	 *
	 * @return array
	 */
	public function facetwp_index_row_characters( $params, $facet_class ) {
		switch ( $params['facet_name'] ) {
			case 'char_actors':
				$params = $this->facetwp_index_row_characters_actors( $params, $facet_class );
				break;
			case 'char_shows':
				$params = $this->facetwp_index_row_characters_shows( $params, $facet_class );
				break;
			case 'char_roles':
				$params = $this->facetwp_index_row_characters_roles( $params, $facet_class );
				break;
			case 'char_years':
				$params = $this->facetwp_index_row_characters_years( $params, $facet_class );
				break;
		}

		return $params;
	}

	/**
	 * Indexing for Characters - Actors
	 * Saves one value for each actor
	 *
	 * EXAMPLE INPUT: a:1:{i:0;s:13:"Rachel Bilson";}
	 *
	 * @param array $params
	 * @param object $facet_class
	 *
	 * @return array
	 */
	public function facetwp_index_row_characters_actors( $params, $facet_class ) {
		$values = (array) $params['facet_value'];
		foreach ( $values as $val ) {
			if ( empty( $val ) ) {
				continue;
			}
			$params['facet_value']         = $val;
			$params['facet_display_value'] = get_the_title( $val );
			$facet_class->insert( $params );
		}
		// skip default indexing
		$params['facet_value'] = '';
		return $params;
	}

	/**
	 * Indexing for Characters - Shows
	 * Saves one value for each show
	 *
	 * @param array $params
	 * @param object $facet_class
	 *
	 * @return array
	 */
	public function facetwp_index_row_characters_shows( $params, $facet_class ) {
		$values = get_field( 'lezchars_show_group', $params['post_id'] );
		if ( ! is_array( $values ) ) {
			$params['facet_value'] = '';
			return $params;
		}
		foreach ( $values as $val ) {
			if ( ! isset( $val['show'] ) ) {
				continue;
			}
			$params['facet_value']         = $val['show'];
			$params['facet_display_value'] = get_the_title( $val['show'] );
			$facet_class->insert( $params );
		}
		// skip default indexing
		$params['facet_value'] = '';
		return $params;
	}

	/**
	 * Indexing for Characters - Roles
	 * Saves one value for each show.
	 *
	 * @param array $params
	 * @param object $facet_class
	 *
	 * @return array
	 */
	public function facetwp_index_row_characters_roles( $params, $facet_class ) {
		$values = get_field( 'lezchars_show_group', $params['post_id'] );
		if ( ! is_array( $values ) ) {
			$params['facet_value'] = '';
			return $params;
		}
		foreach ( $values as $val ) {
			if ( ! isset( $val['type'] ) ) {
				continue;
			}
			$params['facet_value']         = $val['type'];
			$params['facet_display_value'] = ucfirst( $val['type'] );
			$facet_class->insert( $params );
		}
		// skip default indexing
		$params['facet_value'] = '';
		return $params;
	}

	/**
	 * Indexing for Characters - Years
	 *
	 * Saves one value for each year a character appeared.
	 *
	 * @param array $params
	 * @param object $facet_class
	 *
	 * @return array
	 */
	public function facetwp_index_row_characters_years( $params, $facet_class ) {
		$values = get_field( 'lezchars_show_group', $params['post_id'] );
		if ( ! is_array( $values ) ) {
			$params['facet_value'] = '';
			return $params;
		}
		foreach ( $values as $val ) {
			if ( ! isset( $val['appears'] ) || ! is_array( $val['appears'] ) ) {
				continue;
			}
			foreach ( $val['appears'] as $year ) {
				$params['facet_value']         = $year;
				$params['facet_display_value'] = $year;
				$facet_class->insert( $params );
			}
		}
		// skip default indexing
		$params['facet_value'] = '';
		return $params;
	}

	/**
	 * Indexing for Shows
	 *
	 * @param array $params
	 * @param object $facet_class
	 *
	 * @return array
	 */
	public function facetwp_index_row_shows( $params, $facet_class ) {
		switch ( $params['facet_name'] ) {
			case 'show_stars':
				$params = $this->facetwp_index_row_shows_stars( $params, $facet_class );
				break;
			case 'show_loved':
				$params = $this->facetwp_index_row_shows_loved( $params, $facet_class );
				break;
			case 'show_death':
				$params = $this->facetwp_index_row_shows_death( $params, $facet_class );
				break;
			case 'show_trigger_warning':
				$params = $this->facetwp_index_row_shows_trigger_warning( $params, $facet_class );
				break;
			case 'show_airdates':
				$params = $this->facetwp_index_row_shows_airdates( $params, $facet_class );
				break;
			case 'all_the_missing':
				$params = $this->facetwp_index_row_shows_empty( $params, $facet_class );
				break;
		}
		return $params;
	}

	/**
	 * Indexing for Stars
	 *
	 * Capitalize the first letter of the stars
	 *
	 * @param array $params
	 * @param object $facet_class
	 *
	 * @return array
	 */
	public function facetwp_index_row_shows_stars( $params, $facet_class ) {
		$params['facet_value']         = $params['facet_value'];
		$params['facet_display_value'] = ucfirst( $params['facet_display_value'] );
		$facet_class->insert( $params );

		// skip default indexing
		$params['facet_value'] = '';

		return $params;
	}

	/**
	 * Indexing for Loved
	 * Change 'on' to 'yes'
	 *
	 * @param array $params
	 * @param object $facet_class
	 *
	 * @return array
	 */
	public function facetwp_index_row_shows_loved( $params, $facet_class ) {
		$params['facet_value']         = ( 'on' === $params['facet_value'] ) ? 'yes' : 'no';
		$params['facet_display_value'] = ( 'on' === $params['facet_display_value'] ) ? 'Yes' : 'No';
		$facet_class->insert( $params );

		// skip default indexing
		$params['facet_value'] = '';

		return $params;
	}

	/**
	 * Indexing for Death
	 * Change 'on' to 'yes'
	 *
	 * @param array $params
	 * @param object $facet_class
	 *
	 * @return array
	 */
	public function facetwp_index_row_shows_death( $params, $facet_class ) {
		$params['facet_value']         = ( $params['facet_value'] >= 1 ) ? 'yes' : 'no';
		$params['facet_display_value'] = ( $params['facet_display_value'] >= 1 ) ? 'Yes' : 'No';
		$facet_class->insert( $params );

		// skip default indexing
		$params['facet_value'] = '';

		return $params;
	}

	/**
	 * Indexing for Trigger Warning
	 * Change 'on' to 'high'
	 *
	 * @param array $params
	 * @param object $facet_class
	 *
	 * @return array
	 */
	public function facetwp_index_row_shows_trigger_warning( $params, $facet_class ) {
		$params['facet_value']         = ( 'on' === $params['facet_display_value'] ) ? 'high' : $params['facet_display_value'];
		$params['facet_display_value'] = ( 'on' === $params['facet_display_value'] ) ? 'High' : ucfirst( $params['facet_display_value'] );
		$facet_class->insert( $params );

		// skip default indexing
		$params['facet_value'] = '';

		return $params;
	}

	/**
	 * Indexing for Airdates
	 *
	 * Saves two values for two sources
	 * Also saves on_air as yes or no
	 *
	 * Reads from new ACF flat fields (lezshows_airdates_start / lezshows_airdates_finish)
	 * first, then falls back to the legacy serialized lezshows_airdates array.
	 *
	 * @param array $params
	 * @param object $facet_class
	 *
	 * @return array
	 */
	public function facetwp_index_row_shows_airdates( $params, $facet_class ) {
		$post_id = $params['post_id'];

		// Read from new ACF flat fields first, fall back to legacy serialized array.
		$start   = get_post_meta( $post_id, 'lezshows_airdates_start', true );
		$raw_end = get_post_meta( $post_id, 'lezshows_airdates_finish', true );

		if ( empty( $start ) || empty( $raw_end ) ) {
			$legacy = get_post_meta( $post_id, 'lezshows_airdates', true );
			if ( is_array( $legacy ) ) {
				if ( empty( $start ) ) {
					$start = $legacy['start'] ?? '';
				}
				if ( empty( $raw_end ) ) {
					$raw_end = $legacy['finish'] ?? '';
				}
			}
		}

		// Normalize 'current' or empty end to the current year for facet storage.
		$end = ( ! empty( $raw_end ) && 'current' !== lcfirst( $raw_end ) ) ? $raw_end : gmdate( 'Y' );

		// Build default params
		$params_start  = $params;
		$params_end    = $params;
		$params_on_air = $params;

		// Add start date
		$params_start['facet_value']         = $start;
		$params_start['facet_display_value'] = $start;
		$facet_class->insert( $params_start );

		// Add end date
		$params_end['facet_value']         = $end;
		$params_end['facet_display_value'] = $end;
		$facet_class->insert( $params_end );

		// Based on the dates, is this on air?
		$on_air      = 'no';
		$on_air_meta = get_post_meta( $post_id, 'lezshows_on_air', true );

		// phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
		if ( isset( $on_air_meta ) && in_array( $on_air_meta, array( 'yes', 'no' ) ) ) {
			// If the meta is set and is either yes or no, use that.
			$on_air = $on_air_meta;
		} elseif ( 'current' === lcfirst( $raw_end ) || $end > gmdate( 'Y' ) ) {
			// If the end date is 'current' or in the future, it's on air.
			$on_air = 'yes';
		}

		// Add on air status
		$params_on_air['facet_name']          = 'show_on_air';
		$params_on_air['facet_value']         = $on_air;
		$params_on_air['facet_display_value'] = ucfirst( $on_air );
		$facet_class->insert( $params_on_air );

		// skip default indexing
		$params['facet_value'] = '';

		return $params;
	}

	/**
	 * Indexing for Empty Data
	 *
	 * There are a few things that we want to make sure are always indexed when they LACK data.
	 *
	 * @param array $params
	 * @param object $facet_class
	 *
	 * @return array
	 */
	public function facetwp_index_row_shows_empty( $params, $facet_class ) {

		$missing_data = array(
			'show_stars'           => array(
				'type'    => 'terms',
				'key'     => 'lez_stars',
				'source'  => 'tax/lez_stars',
				'value'   => 'none',
				'display' => 'None',
			),
			'show_loved'           => array(
				'type'    => 'post_meta',
				'key'     => 'lezshows_worthit_show_we_love',
				'source'  => 'cf/lezshows_worthit_show_we_love',
				'value'   => 'no',
				'display' => 'No',
			),
			'show_trigger_warning' => array(
				'type'    => 'terms',
				'key'     => 'lez_triggers',
				'source'  => 'tax/lez_triggers',
				'value'   => 'none',
				'display' => 'None',
			),
		);

		foreach ( $missing_data as $name => $data ) {
			switch ( $data['type'] ) {
				case 'terms':
					$raw_data = get_the_terms( $params['post_id'], $data['key'] );
					break;
				case 'post_meta':
					$raw_data = get_post_meta( $params['post_id'], $data['key'], true );
					break;
			}

			// If there's no raw data, break early.
			if ( ! isset( $raw_data ) ) {
				return $params;
			}

			// If the raw data is empty, then it's really 'none' and we need to force-add the value.
			if ( empty( $raw_data ) ) {
				$params_new                        = $params;
				$params_new['facet_name']          = $name;
				$params_new['facet_source']        = $data['source'];
				$params_new['facet_value']         = $data['value'];
				$params_new['facet_display_value'] = $data['display'];
				$facet_class->insert( $params_new );
			}
		}

		// skip default indexing
		$params['facet_value'] = '';

		return $params;
	}
}
