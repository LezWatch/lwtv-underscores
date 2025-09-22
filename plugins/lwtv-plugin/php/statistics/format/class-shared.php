<?php
/**
 * Shared functions for the new optimized statistics
 *
 * @package LWTV\Statistics\Format
 */
namespace LWTV\Statistics\Format;

class Shared {

	/**
	 * Sort data
	 *
	 * @param array  $data Data to sort
	 * @param string $clean_view Clean view
	 * @return array Sorted data
	 */
	public static function sort_data( $data, $clean_view ) {
		$clean_view = ltrim( $clean_view, '_' );
		if ( empty( $data ) ) {
			return array();
		}

		// Change all - to _ for clean_view
		$clean_view = str_replace( '-', '_', $clean_view );

		// Get the data for the clean_view
		$data = $data[ $clean_view ];

		// For on_air, we want to sort by the name column so the years go in ascending order (1951 -> 2025)
		if ( in_array( $clean_view, array( 'on_air', 'years' ), true ) ) {
			usort(
				$data,
				function ( $a, $b ) {
					return $a['name'] <=> $b['name'];
				}
			);

			return $data;
		}

		// Remove all 0 values first
		$data = array_filter(
			$data,
			function ( $value ) {
				return $value['count'] > 0;
			}
		);

		// Sort by count in descending order so highest is on top
		usort(
			$data,
			function ( $a, $b ) {
				return $b['count'] - $a['count'];
			}
		);

		return $data;
	}
}
