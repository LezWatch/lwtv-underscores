<?php
/**
 * Statistics Array Builder - Optimized Version
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics;

use LWTV\Statistics\Matcher_Optimized;
use LWTV\Statistics\Build\Taxonomy_Optimized as Build_Taxonomy_Optimized;

class The_Array_Optimized {

	/**
	 * Make the array for statistics
	 *
	 * @param string $subject Subject type
	 * @param string $data Data type
	 * @param string $format Output format
	 * @param int $post_id Post ID
	 * @param array $custom_array Custom array
	 * @param int $count Count
	 * @param mixed $maybe_deep Deep data
	 * @param string $data_original Original data
	 * @return array
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function make( $subject, $data, $format, $post_id, $custom_array, $count, $maybe_deep, $data_original = null ) {
		// Get the data class from matcher
		$data_class = Matcher_Optimized::BUILD_CLASS_MATCHER[ $data ];

		// Get post type
		$post_type = 'post_type_' . $subject;

		// Get taxonomy
		$taxonomy = $this->get_taxonomy_for_data( $data );

		// OPTIMIZED: Use optimized taxonomy builder for taxonomy-based statistics
		if ( 'Taxonomy_Optimized' === $data_class ) {
			$optimized_taxonomy = new Build_Taxonomy_Optimized();
			return $optimized_taxonomy->make_comprehensive( $post_type, $taxonomy, false );
		}

		// Fallback to original logic for non-taxonomy data
		return array();
	}

	/**
	 * Get taxonomy name for data type
	 *
	 * @param string $data Data type
	 * @return string Taxonomy name
	 */
	private function get_taxonomy_for_data( $data ) {
		$taxonomy_map = array(
			'actor_gender'    => 'lez_actor_gender',
			'actor_sexuality' => 'lez_actor_sexuality',
			'cliches'         => 'lez_cliches',
			'formats'         => 'lez_formats',
			'gender'          => 'lez_gender',
			'genres'          => 'lez_genres',
			'intersections'   => 'lez_intersections',
			'on-air'          => 'lez_onair',
			'romantic'        => 'lez_romantic',
			'sexuality'       => 'lez_sexuality',
			'tropes'          => 'lez_tropes',
		);

		return $taxonomy_map[ $data ] ?? 'lez_' . $data;
	}
}
