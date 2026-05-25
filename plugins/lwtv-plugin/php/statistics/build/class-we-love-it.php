<?php
/**
 * We Love It Statistics Build Class - Optimized Version
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class We_Love_It {

	/**
	 * Generate we love it statistics
	 *
	 * @param string $format Output format
	 * @return array We love it statistics data
	 */
	public function generate( $format = 'array' ) {
		lwtv_plugin()->debug_log( 'statistics', 'Generating we love it statistics for format: ' . $format );
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
	 * Get all we love it data
	 *
	 * @return array All we love it data
	 */
	public function generate_all_data() {
		// Create cache key
		$cache_key   = 'we_love_it_data';
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		// If cached data is found, return it
		if ( false !== $cached_data ) {
			lwtv_plugin()->debug_log( 'statistics', 'Cached data found for ' . $cache_key );
			return $cached_data;
		}

		try {
			// Get total shows count
			$total_shows = $this->get_total_shows_count();

			// Get shows we love count
			$shows_we_love = $this->get_shows_we_love_count();

			// Calculate shows we do not love
			$shows_we_do_not_love = $total_shows - $shows_we_love;

			$data = array(
				'we_love'        => array(
					'count' => $shows_we_love,
					'name'  => 'Loved',
					'url'   => home_url( '/shows/?fwp_show_loved=on' ),
				),
				'we_do_not_love' => array(
					'count' => $shows_we_do_not_love,
					'name'  => 'Not Loved',
					'url'   => home_url( '/shows/?fwp_show_loved=off' ),
				),
			);

			// Cache the results for 1 day
			lwtv_plugin()->set_transient( $cache_key, $data, DAY_IN_SECONDS );

			return $data;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'statistics', 'Error generating we love it statistics: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Get total shows count
	 *
	 * @return int Total shows count
	 */
	private function get_total_shows_count() {
		global $wpdb;

		// Create cache key
		$cache_key   = 'total_shows_count';
		$cached_data = lwtv_plugin()->get_transient( $cache_key );

		// If cached data is found, return it
		if ( false !== $cached_data ) {
			return $cached_data;
		}

		try {
			$query = $wpdb->prepare(
				"SELECT COUNT(*) as count
				FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_status = 'publish'",
				'post_type_shows'
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$result = $wpdb->get_var( $query );

			// Cache the result for 1 day
			lwtv_plugin()->set_transient( $cache_key, (int) $result, DAY_IN_SECONDS );

			return (int) $result;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'statistics', 'Error getting total shows count: ' . $e->getMessage() );
			return 0;
		}
	}

	/**
	 * Get shows we love count
	 *
	 * @return int Shows we love count
	 */
	private function get_shows_we_love_count() {
		global $wpdb;

		// Create cache key
		$cache_key   = 'shows_we_love_count';
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
				'lezshows_worthit_show_we_love',
				'on'
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$result = $wpdb->get_var( $query );

			// Cache the result for 1 day
			lwtv_plugin()->set_transient( $cache_key, (int) $result, DAY_IN_SECONDS );

			return (int) $result;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'statistics', 'Error getting shows we love count: ' . $e->getMessage() );
			return 0;
		}
	}

	/**
	 * Format piechart
	 *
	 * @param array $all_data All we love it data
	 * @return array Piechart data
	 */
	public function format_piechart( $all_data ) {
		$data = array();
		foreach ( $all_data as $item ) {
			$data[ $item['name'] ] = $item['count'];
		}

		lwtv_plugin()->debug_log( 'statistics', 'Piechart data: ' . wp_json_encode( $data ) );
		return $data;
	}

	/**
	 * Format percentage
	 *
	 * @param array $all_data All we love it data
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
