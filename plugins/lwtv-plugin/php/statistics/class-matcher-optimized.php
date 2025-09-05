<?php
/**
 * Name: Statistics Matcher - Optimized Version
 */

namespace LWTV\Statistics;

class Matcher_Optimized {
	/**
	 * Array of Data types and their associated classes.
	 * Updated to use optimized classes where available.
	 */
	const BUILD_CLASS_MATCHER = array(
		'actor_char_roles'    => 'Actor_Char_Role',
		'actor_char_dead'     => 'Actor_Char_Dead',
		'actor_gender'        => 'Taxonomy_Optimized',
		'actor_sexuality'     => 'Taxonomy_Optimized',
		'cliches'             => 'Taxonomy_Optimized',
		'dead'                => 'Dead_Basic',
		'dead-gender'         => 'Dead_Taxonomy',
		'dead-list'           => 'Dead_List',
		'dead-nations'        => 'Dead_Complex_Taxonomy',
		'dead-role'           => 'Dead_Role',
		'dead-sex'            => 'Dead_Taxonomy',
		'dead-shows'          => 'Dead_Shows',
		'dead-stations'       => 'Dead_Complex_Taxonomy',
		'dead-years'          => 'Dead_Year',
		'formats'             => 'Taxonomy_Optimized',
		'gender'              => 'Taxonomy_Optimized',
		'genres'              => 'Taxonomy_Optimized',
		'intersections'       => 'Taxonomy_Optimized',
		'on-air'              => 'On_Air',
		'per-actor'           => 'Actor_Chars',
		'per-char'            => 'Actor_Chars',
		'roles'               => 'Meta',
		'romantic'            => 'Taxonomy_Optimized',
		'scores'              => 'Scores',
		'sexuality'           => 'Taxonomy_Optimized',
		'stars'               => 'Complex_Taxonomy',
		'taxonomy_breakdowns' => 'Taxonomy_Breakdowns',
		'thumbs'              => 'Meta',
		'this-year'           => 'This_Year',
		'triggers'            => 'Complex_Taxonomy',
		'tropes'              => 'Taxonomy_Optimized',
		'queer-irl'           => 'Complex_Taxonomy',
		'weloveit'            => 'Yes_No',
	);

	// Array of Formats and their classes:
	const FORMAT_CLASS_MATCHER = array(
		'average'    => 'Averages',
		'barchart'   => 'Barcharts',
		'high'       => 'Averages',
		'list'       => 'Lists',
		'low'        => 'Averages',
		'percentage' => 'Percentages',
		'piechart'   => 'Piecharts',
		'stackedbar' => 'Barcharts_Stacked',
		'trendline'  => 'Trendline',
	);

	// Array of custom meta data used.
	const META_PARAMS = array(
		'roles'  => array(
			'array'   => array( 'regular', 'recurring', 'guest' ),
			'key'     => 'lezchars_show_group',
			'compare' => 'LIKE',
		),
		'thumbs' => array(
			'array'   => array( 'Yes', 'No', 'Meh', 'TBD' ),
			'key'     => 'lezshows_worthit_rating',
			'compare' => null,
		),
	);

	/**
	 * Get optimized build class for a given data type
	 *
	 * @param string $data_type The data type
	 * @return string The optimized build class name
	 */
	public static function get_optimized_build_class( $data_type ) {
		return self::BUILD_CLASS_MATCHER[ $data_type ] ?? 'Taxonomy_Optimized';
	}

	/**
	 * Check if a data type uses optimized taxonomy queries
	 *
	 * @param string $data_type The data type
	 * @return bool True if optimized
	 */
	public static function is_optimized_taxonomy( $data_type ) {
		return 'Taxonomy_Optimized' === ( self::BUILD_CLASS_MATCHER[ $data_type ] ?? '' );
	}

	/**
	 * Get all taxonomy-based data types that can be optimized
	 *
	 * @return array Array of data types that use taxonomy queries
	 */
	public static function get_taxonomy_data_types() {
		return array_keys(
			array_filter(
				self::BUILD_CLASS_MATCHER,
				function ( $taxonomy_class ) {
					return in_array( $taxonomy_class, array( 'Taxonomy', 'Taxonomy_Optimized' ), true );
				}
			)
		);
	}
}
