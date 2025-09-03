<?php
/**
 * Calendar Database CLI Commands
 *
 * CLI commands for testing and managing database optimization.
 *
 * @package lwtv-plugin
 */

namespace LWTV\WP_CLI;

use WP_CLI;
use LWTV\_Helpers\Calendar_Database_Optimizer;

// Bail if directly accessed
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	die();
}

/**
 * Calendar Database CLI Commands
 */
class CLI_Calendar {

	/**
	 * Construct to obviate facet from munging results.
	 */
	public function __construct() {
		// phpcs:disable
		// Remove <!--fwp-loop--> from output
		add_filter( 'facetwp_is_main_query', function( $is_main_query, $query ) {
			return false;
		}, 10, 2 );
		// phpcs:enable
	}

	/**
	 * Create database indexes for calendar optimization
	 *
	 * ## EXAMPLES
	 *
	 *     wp calendar create-indexes
	 *
	 * @when after_wp_load
	 */
	public function create_indexes() {
		WP_CLI::log( 'Creating database indexes for calendar optimization...' );

		$results = Calendar_Database_Optimizer::create_indexes();

		if ( ! empty( $results['success'] ) ) {
			foreach ( $results['success'] as $message ) {
				WP_CLI::success( $message );
			}
		}

		if ( ! empty( $results['errors'] ) ) {
			foreach ( $results['errors'] as $error ) {
				WP_CLI::error( $error );
			}
		}

		WP_CLI::log( 'Database index creation completed.' );
	}

	/**
	 * Check database index status
	 *
	 * ## EXAMPLES
	 *
	 *     wp calendar check-indexes
	 *
	 * @when after_wp_load
	 */
	public function check_indexes() {
		WP_CLI::log( 'Checking database index status...' );

		$status = Calendar_Database_Optimizer::get_index_status();

		foreach ( $status as $table => $indexes ) {
			WP_CLI::log( "Table: {$table}" );

			foreach ( $indexes as $index_name => $index_info ) {
				$status_icon = $index_info['exists'] ? '✓' : '✗';
				$columns     = implode( ', ', $index_info['columns'] );
				WP_CLI::log( "  {$status_icon} {$index_name} ({$columns}) - {$index_info['description']}" );
			}
		}
	}

	/**
	 * Get database performance statistics
	 *
	 * ## EXAMPLES
	 *
	 *     wp calendar performance-stats
	 *
	 * @when after_wp_load
	 */
	public function performance_stats() {
		WP_CLI::log( 'Getting database performance statistics...' );

		$stats = Calendar_Database_Optimizer::get_performance_stats();

		WP_CLI::log( 'Table Sizes:' );
		WP_CLI::log( "  Posts: {$stats['posts_size_mb']} MB" );
		WP_CLI::log( "  Post Meta: {$stats['postmeta_size_mb']} MB" );

		WP_CLI::log( 'Index Status:' );
		foreach ( $stats['indexes'] as $table => $indexes ) {
			WP_CLI::log( "  {$table}:" );
			foreach ( $indexes as $index_name => $index_info ) {
				$status = $index_info['exists'] ? 'Active' : 'Missing';
				WP_CLI::log( "    {$index_name}: {$status}" );
			}
		}
	}

	/**
	 * Optimize database tables
	 *
	 * ## EXAMPLES
	 *
	 *     wp calendar optimize-tables
	 *
	 * @when after_wp_load
	 */
	public function optimize_tables() {
		WP_CLI::log( 'Optimizing database tables...' );

		$results = Calendar_Database_Optimizer::optimize_tables();

		if ( ! empty( $results['success'] ) ) {
			foreach ( $results['success'] as $message ) {
				WP_CLI::success( $message );
			}
		}

		if ( ! empty( $results['errors'] ) ) {
			foreach ( $results['errors'] as $error ) {
				WP_CLI::error( $error );
			}
		}

		WP_CLI::log( 'Table optimization completed.' );
	}

	/**
	 * Test calendar query performance
	 *
	 * ## EXAMPLES
	 *
	 *     wp calendar test-queries
	 *
	 * @when after_wp_load
	 */
	public function test_queries() {
		WP_CLI::log( 'Testing calendar query performance...' );

		global $wpdb;

		// Test post meta query
		$meta_query    = "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN (1,2,3) AND meta_key IN ('lezshows_tvmaze_timezone', 'lezshows_tvmaze_id')";
		$meta_analysis = Calendar_Database_Optimizer::analyze_query( $meta_query );

		WP_CLI::log( 'Post Meta Query Analysis:' );
		WP_CLI::log( "  Query: {$meta_analysis['query']}" );
		foreach ( $meta_analysis['explain'] as $row ) {
			WP_CLI::log( "    Type: {$row->select_type}, Key: {$row->key}, Rows: {$row->rows}" );
		}

		// Test posts query
		$posts_query    = "SELECT p.ID, pm.meta_value as thumbnail_id FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_thumbnail_id' WHERE p.ID IN (1,2,3)";
		$posts_analysis = Calendar_Database_Optimizer::analyze_query( $posts_query );

		WP_CLI::log( 'Posts Query Analysis:' );
		WP_CLI::log( "  Query: {$posts_analysis['query']}" );
		foreach ( $posts_analysis['explain'] as $row ) {
			WP_CLI::log( "    Type: {$row->select_type}, Key: {$row->key}, Rows: {$row->rows}" );
		}
	}
}

// Register CLI commands
WP_CLI::add_command( 'calendar', 'LWTV\WP_CLI\Calendar_Database_CLI' );
