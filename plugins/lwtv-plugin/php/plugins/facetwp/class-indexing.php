<?php
/*
 * Description: FacetWP Indexing
 */

namespace LWTV\Plugins\FacetWP;

class Indexing {

	/**
	 * Constructor
	 */
	public function __construct() {

		// Filter data before saving it
		add_filter( 'facetwp_index_row', array( $this, 'facetwp_index_row' ), 10, 2 );

		// Filter Facet output
		add_filter(
			'facetwp_facet_html',
			function ( $output, $params ) {
				if ( 'show_airdates' === $params['facet']['name'] ) {
					$output = str_replace( 'Min', 'First Year', $output );
					$output = str_replace( 'Max', 'Last Year', $output );
				}
				return $output;
			},
			10,
			2,
		);

		// Adding a weird filter...
		add_filter(
			'facetwp_facet_sources',
			function ( $sources ) {
				$sources['custom_fields']['choices']['cf/lwtv_data'] = 'lwtv_data';
				return $sources;
			}
		);

		add_filter( 'facetwp_is_main_query', array( $this, 'facetwp_is_main_query' ), 10, 2 );
	}

	/**
	 * Force Facet to show sometimes
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
	 * Filter Data before it's saved
	 * Useful for serialized data but also capitalizing stars
	 *
	 * @since 1.1
	 */
	public function facetwp_index_row( $params, $facet_class ) {

		switch ( get_post_type( $params['post_id'] ) ) {
			case 'post_type_shows':
				$params = $this->facetwp_index_row_shows( $params, $facet_class );
				break;
			case 'post_type_actors':
				$params = $this->facetwp_index_row_actors( $params, $facet_class );
				break;
			case 'post_type_characters':
				$params = $this->facetwp_index_row_characters( $params, $facet_class );
				break;
		}

		return $params;
	}

	/**
	 * Indexing for Actors
	 */
	public function facetwp_index_row_actors( $params, $facet_class ) {
		// Is Queer
		// Change 'on' to 'yes'
		if ( 'is_queer' === $params['facet_name'] ) {
			$params['facet_value']         = ( '1' === $params['facet_value'] ) ? 'yes' : 'no';
			$params['facet_display_value'] = ( '1' === $params['facet_display_value'] ) ? 'Is Queer' : 'Is Not Queer';
			$facet_class->insert( $params );
			// skip default indexing
			$params['facet_value'] = '';
		}

		return $params;
	}

	/**
	 * Indexing for Characters
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
	 *
	 * Saves one value for each actor
	 *
	 * EXAMPLE INPUT: a:1:{i:0;s:13:"Rachel Bilson";}
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
	 *
	 * Saves one value for each show
	 *
	 * EXAMPLE INPUT: a:1:{i:0;a:3:{s:4:"show";s:3:"655";s:4:"type";s:9:"recurring";s:7:"appears";a:1:{i:0;s:4:"2017";}}}
	 */
	public function facetwp_index_row_characters_shows( $params, $facet_class ) {
		$values = (array) $params['facet_value'];
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
	 *
	 * Saves one value for each show.
	 *
	 * EXAMPLE INPUT: a:1:{i:0;a:3:{s:4:"show";s:3:"655";s:4:"type";s:9:"recurring";s:7:"appears";a:1:{i:0;s:4:"2017";}}}
	 */
	public function facetwp_index_row_characters_roles( $params, $facet_class ) {
		$values = (array) $params['facet_value'];
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
	 * Saves one value for each year
	 * Years is a sub array of the array, because I was thinking clever and forgot what a metric PITA this is.
	 *
	 * EXAMPLE INPUT: a:1:{i:0;a:3:{s:4:"show";s:3:"655";s:4:"type";s:9:"recurring";s:7:"appears";a:1:{i:0;s:4:"2017";}}}
	 */
	public function facetwp_index_row_characters_years( $params, $facet_class ) {
		$values = (array) $params['facet_value'];
		foreach ( $values as $val ) {
			if ( ! isset( $val['appears'] ) ) {
				continue;
			}
			foreach ( $val['appears'] as $year ) {
				$params['facet_value']         = $year;
				$params['facet_display_value'] = $year;
			}
			$facet_class->insert( $params );
		}
		// skip default indexing
		$params['facet_value'] = '';
		return $params;
	}

	/**
	 * Indexing for Shows
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
				$params = $this->facetwp_index_row_shows_missing( $params, $facet_class );
				break;
		}
		return $params;
	}

	/**
	 * Indexing for Stars
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
	 * a:2:{s:5:"start";s:4:"1994";s:6:"finish";s:4:"2009";}
	 */
	public function facetwp_index_row_shows_airdates( $params, $facet_class ) {
		// Parse start and end dates  (use 'now' if 'current' or empty)
		$values = (array) $params['facet_value'];
		$start  = ( isset( $values['start'] ) ) ? $values['start'] : '';
		$end    = ( isset( $values['finish'] ) && lcfirst( $values['finish'] ) !== 'current' ) ? $values['finish'] : gmdate( 'Y' );

		$params_start = $params;
		$params_end   = $params;

		// Add start date
		$params_start['facet_value']         = $start;
		$params_start['facet_display_value'] = $start;
		$facet_class->insert( $params_start );

		// Add end date
		$params_end['facet_value']         = $end;
		$params_end['facet_display_value'] = $end;
		$facet_class->insert( $params_end );

		// Extra check for is it currently on air
		$params_on_air = $params;
		$on_air        = 'no';
		$on_air_meta   = get_post_meta( $params['post_id'], 'lezshows_on_air', true );

		// phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
		if ( isset( $on_air_meta ) && in_array( $on_air_meta, array( 'yes', 'no' ) ) ) {
			$on_air = $on_air_meta;
		} elseif ( 'current' === lcfirst( $end ) || $end > gmdate( 'Y' ) ) {
			$on_air = 'yes';
		}

		$params_on_air['facet_name']          = 'show_on_air';
		$params_on_air['facet_value']         = $on_air;
		$params_on_air['facet_display_value'] = ucfirst( $on_air );
		$facet_class->insert( $params_on_air );

		// skip default indexing
		$params['facet_value'] = '';

		return $params;
	}

	/**
	 * Indexing for Missing
	 *
	 * There are a few things that we want to make sure are always indexed when they LACK data.
	 */
	public function facetwp_index_row_shows_missing( $params, $facet_class ) {

		// If we do not love the show...
		$loved = get_post_meta( $params['post_id'], 'lezshows_worthit_show_we_love', true );
		if ( empty( $loved ) ) {
			$params_loved                        = $params;
			$params_loved['facet_name']          = 'show_loved';
			$params_loved['facet_source']        = 'cf/lezshows_worthit_show_we_love';
			$params_loved['facet_value']         = 'no';
			$params_loved['facet_display_value'] = 'No';
			$facet_class->insert( $params_loved );
		}

		// If there are no warnings
		$warn = get_the_terms( $params['post_id'], 'lez_triggers' );
		if ( empty( $warn ) ) {
			$params_warn                        = $params;
			$params_warn['facet_name']          = 'show_trigger_warning';
			$params_warn['facet_source']        = 'tax/lez_triggers';
			$params_warn['facet_value']         = 'none';
			$params_warn['facet_display_value'] = 'None';
			$facet_class->insert( $params_warn );
		}

		// If there are no stars
		$stars = get_the_terms( $params['post_id'], 'lez_stars' );
		if ( empty( $stars ) ) {
			$params_stars                        = $params;
			$params_stars['facet_name']          = 'show_stars';
			$params_stars['facet_source']        = 'tax/lez_stars';
			$params_stars['facet_value']         = 'none';
			$params_stars['facet_display_value'] = 'None';
			$facet_class->insert( $params_stars );
		}

		// skip default indexing
		$params['facet_value'] = '';

		return $params;
	}
}
