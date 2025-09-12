<?php
/**
 * Worth It Statistics Build Class - Optimized Version
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

class Worth_It {

	/**
	 * Generate worth it statistics
	 *
	 * @param string $format Output format
	 * @return array Worth it statistics data
	 */
	public function generate( $format = 'array' ) {
		lwtv_plugin()->error_log( 'worth-it-debug', 'Generating worth it statistics for format: ' . $format );
		$all_data = $this->generate_all_data();
		switch ( $format ) {
			case 'count':
				return count( $all_data );
			case 'piechart':
				return $this->format_piechart( $all_data );
			case 'percentage':
				return $this->format_percentage( $all_data );
			default:
				return $all_data;
		}
	}

	/**
	 * Get all worth it data
	 *
	 * @return array All worth it data
	 */
	public function generate_all_data() {
		// Create cache key
		$cache_key   = 'worth_it_data';
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		// If cached data is found, return it
		if ( false !== $cached_data ) {
			lwtv_plugin()->error_log( 'worth-it-debug', 'Cached data found for ' . $cache_key );
			return $cached_data;
		}

		try {
			// Get counts for each category
			$yes_count = $this->get_rating_count( 'yes' );
			$no_count  = $this->get_rating_count( 'no' );
			$meh_count = $this->get_rating_count( 'meh' );
			$tbd_count = $this->get_rating_count( 'tbd' );

			$data = array(
				'yes' => array(
					'count' => $yes_count,
					'name'  => 'Worth It',
					'url'   => home_url( '/shows/?fwp_show_worthit=yes' ),
				),
				'no'  => array(
					'count' => $no_count,
					'name'  => 'Not Worth It',
					'url'   => home_url( '/shows/?fwp_show_worthit=no' ),
				),
				'meh' => array(
					'count' => $meh_count,
					'name'  => 'Meh',
					'url'   => home_url( '/shows/?fwp_show_worthit=meh' ),
				),
				'tbd' => array(
					'count' => $tbd_count,
					'name'  => 'To Be Determined',
					'url'   => home_url( '/shows/?fwp_show_worthit=tbd' ),
				),
			);

			// Cache the results for 1 day
			lwtv_plugin()->set_transient( $cache_key, $data, DAY_IN_SECONDS );

			return $data;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'worth-it-debug', 'Error generating worth it statistics: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Get count for specific rating
	 *
	 * @param string $rating Rating value ('yes', 'no', 'meh', 'tbd')
	 * @return int Rating count
	 */
	private function get_rating_count( $rating ) {
		global $wpdb;

		// Create cache key
		$cache_key   = 'worth_it_' . $rating . '_count';
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		// If cached data is found, return it
		if ( false !== $cached_data ) {
			return $cached_data;
		}

		try {
			$query = $wpdb->prepare(
				"SELECT COUNT(*) as count
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = %s
				AND p.post_status = 'publish'
				AND pm.meta_key = %s
				AND pm.meta_value = %s",
				'post_type_shows',
				'lezshows_worthit_rating',
				$rating
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$result = $wpdb->get_var( $query );

			// Cache the result for 1 day
			lwtv_plugin()->set_transient( $cache_key, (int) $result, DAY_IN_SECONDS );

			return (int) $result;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'worth-it-debug', 'Error getting ' . $rating . ' rating count: ' . $e->getMessage() );
			return 0;
		}
	}

	/**
	 * Format piechart
	 *
	 * @param array $all_data All worth it data
	 * @return array Piechart data
	 */
	public function format_piechart( $all_data ) {
		$data = array();
		foreach ( $all_data as $item ) {
			$data[ $item['name'] ] = $item['count'];
		}

		lwtv_plugin()->error_log( 'worth-it-debug', 'Piechart data: ' . wp_json_encode( $data ) );
		return $data;
	}

	/**
	 * Format percentage
	 *
	 * @param array $all_data All worth it data
	 * @return array Percentage data
	 */
	public function format_percentage( $all_data ) {
		$data = array();
		foreach ( $all_data as $key => $item ) {
			$data[ $key ] = array(
				'name'  => $item['name'],
				'count' => $item['count'],
				'url'   => $item['url'],
			);
		}

		return $data;
	}
}
