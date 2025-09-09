<?php

namespace LWTV\Statistics\Build;

class Meta {

	/*
	 * Statistics Simple Meta Array - Optimized with batch queries
	 *
	 * Generate array to parse post meta data using single optimized query
	 *
	 * @param string $post_type Post Type to be search
	 * @param array $meta_array Meta terms to loop through
	 * @param string $key Post Meta Key name (i.e. lezchars_gender)
	 * @param string $data The data 'subject' - used to generate the URLs
	 * @param string $compare The type of comparison (default =)
	 *
	 * @return array
	 */
	public function make( $post_type, $meta_array, $key, $data, $compare = '=' ) {
		try {
			$transient = 'stats_meta_' . $key;
			$array     = lwtv_plugin()->get_transient( $transient );

			if ( false === $array ) {
				$array = $this->build_meta_batch( $post_type, $meta_array, $key, $data, $compare );

				// save array as transient for a reason.
				if ( ! empty( $array ) ) {
					lwtv_plugin()->set_transient( $transient, $array, DAY_IN_SECONDS );
				}
			}

			return $array;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'meta-error', 'Error building meta statistics: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Build meta statistics using batch query to eliminate N+1 pattern
	 *
	 * @param string $post_type Post type to query
	 * @param array  $meta_array Meta values to count
	 * @param string $key Meta key to search
	 * @param string $data URL data subject
	 * @param string $compare Comparison operator
	 * @return array
	 */
	private function build_meta_batch( $post_type, $meta_array, $key, $data, $compare ) {
		global $wpdb;

		try {
			$results_array = array();

			// Validate input parameters
			if ( empty( $meta_array ) || ! is_array( $meta_array ) ) {
				lwtv_plugin()->error_log( 'meta-error', 'Invalid meta_array provided' );
				return array();
			}

			// Sanitize meta values
			$sanitized_values = array_map( 'sanitize_text_field', $meta_array );
			$placeholders     = implode( ',', array_fill( 0, count( $sanitized_values ), '%s' ) );

			// Build comparison condition
			$comparison_condition = $this->get_comparison_condition( $compare );

			// Single optimized query to get counts for all meta values
			// phpcs:disable
			$queery = $wpdb->prepare(
				"SELECT pm.meta_value, COUNT(DISTINCT p.ID) as post_count
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				 WHERE p.post_type = %s
				 AND p.post_status = 'publish'
				 AND pm.meta_key = %s
				 AND pm.meta_value {$comparison_condition} ({$placeholders})
				 GROUP BY pm.meta_value",
				$post_type,
				$key,
				...$sanitized_values
			);
			// phpcs:enable

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$results = $wpdb->get_results( $queery, ARRAY_A );

			// Format results
			$counts = array();
			foreach ( $results as $row ) {
				$counts[ $row['meta_value'] ] = (int) $row['post_count'];
			}

			// Build final array with all requested values
			foreach ( $meta_array as $value ) {
				$results_array[ $value ] = array(
					'count' => $counts[ $value ] ?? 0,
					'name'  => ucfirst( $value ),
					'url'   => home_url( '/' . $data . '/' . lcfirst( $value ) . '/' ),
				);
			}

			return $results_array;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'meta-error', 'Error building meta batch statistics: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Get SQL comparison condition based on operator
	 *
	 * @param string $compare Comparison operator
	 * @return string
	 */
	private function get_comparison_condition( $compare ): string {
		switch ( $compare ) {
			case 'LIKE':
				return 'LIKE';
			case 'NOT IN':
				return 'NOT IN';
			case 'IN':
				return 'IN';
			default:
				return '=';
		}
	}
}
