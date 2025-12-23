<?php
/**
 * Calendar Database Optimizer
 *
 * Manages database indexes for optimal calendar performance.
 * This provides significant performance improvements by optimizing query execution.
 *
 * @package lwtv-plugin
 */

namespace LWTV\_Helpers;

class Calendar_Database_Optimizer {

	/**
	 * Required indexes for calendar performance
	 *
	 * @var array
	 */
	private static $required_indexes = array(
		'wp_postmeta' => array(
			'post_id_meta_key' => array(
				'columns'     => array( 'post_id', 'meta_key' ),
				'type'        => 'INDEX',
				'description' => 'Optimizes post meta queries by post_id and meta_key',
			),
			'meta_key_value'   => array(
				'columns'     => array( 'meta_key', 'meta_value' ),
				'type'        => 'INDEX',
				'description' => 'Optimizes meta value lookups',
			),
		),
		'wp_posts'    => array(
			'post_type_status' => array(
				'columns'     => array( 'post_type', 'post_status' ),
				'type'        => 'INDEX',
				'description' => 'Optimizes post type and status queries',
			),
			'post_name_type'   => array(
				'columns'     => array( 'post_name', 'post_type' ),
				'type'        => 'INDEX',
				'description' => 'Optimizes get_page_by_path queries',
			),
		),
	);

	/**
	 * Initialize database optimization
	 *
	 * @return void
	 */
	public static function init(): void {
		// Add activation hook for index creation
		add_action( 'lwtv_plugin_activated', array( __CLASS__, 'create_indexes' ) );

		// Add deactivation hook for index cleanup (optional)
		add_action( 'lwtv_plugin_deactivated', array( __CLASS__, 'remove_indexes' ) );
	}

	/**
	 * Create database indexes for calendar optimization
	 *
	 * @return array Results of index creation
	 */
	public static function create_indexes(): array {
		global $wpdb;

		$results = array(
			'success' => array(),
			'errors'  => array(),
		);

		foreach ( self::$required_indexes as $table => $indexes ) {
			$full_table_name = $wpdb->prefix . str_replace( 'wp_', '', $table );

			foreach ( $indexes as $index_name => $index_config ) {
				$result = self::create_index( $full_table_name, $index_name, $index_config );

				if ( $result['success'] ) {
					$results['success'][] = "Index {$index_name} created on {$full_table_name}";
				} else {
					$results['errors'][] = "Failed to create index {$index_name} on {$full_table_name}: " . $result['error'];
				}
			}
		}

		// Log results
		if ( ! empty( $results['success'] ) ) {
			lwtv_plugin()->debug_log( 'calendar', 'Database indexes created: ' . implode( ', ', $results['success'] ) );
		}

		if ( ! empty( $results['errors'] ) ) {
			lwtv_plugin()->debug_log( 'calendar', 'Database index errors: ' . implode( ', ', $results['errors'] ) );
		}

		return $results;
	}

	/**
	 * Create a single database index
	 *
	 * @param  string $table_name Full table name
	 * @param  string $index_name Index name
	 * @param  array  $config     Index configuration
	 * @return array              Result of index creation
	 */
	private static function create_index( string $table_name, string $index_name, array $config ): array {
		global $wpdb;

		// Check if index already exists
		if ( self::index_exists( $table_name, $index_name ) ) {
			return array(
				'success' => true,
				'message' => 'Index already exists',
			);
		}

		$columns = implode( ', ', $config['columns'] );
		$sql     = "CREATE {$config['type']} {$index_name} ON {$table_name} ({$columns})";

		$result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( false === $result ) {
			return array(
				'success' => false,
				'error'   => $wpdb->last_error,
			);
		}

		return array( 'success' => true );
	}

	/**
	 * Check if an index exists
	 *
	 * @param  string $table_name Full table name
	 * @param  string $index_name Index name
	 * @return bool
	 */
	private static function index_exists( string $table_name, string $index_name ): bool {
		global $wpdb;

		$sql = $wpdb->prepare(
			'SELECT COUNT(*) FROM information_schema.statistics
			WHERE table_schema = %s
			AND table_name = %s
			AND index_name = %s',
			DB_NAME,
			$table_name,
			$index_name
		);

		$count = $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return (int) $count > 0;
	}

	/**
	 * Remove database indexes (for cleanup)
	 *
	 * @return array Results of index removal
	 */
	public static function remove_indexes(): array {
		global $wpdb;

		$results = array(
			'success' => array(),
			'errors'  => array(),
		);

		foreach ( self::$required_indexes as $table => $indexes ) {
			$full_table_name = $wpdb->prefix . str_replace( 'wp_', '', $table );

			foreach ( $indexes as $index_name => $index_config ) {
				if ( self::index_exists( $full_table_name, $index_name ) ) {
					$sql = "DROP INDEX {$index_name} ON {$full_table_name}";

					$result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

					if ( false === $result ) {
						$results['errors'][] = "Failed to remove index {$index_name} from {$full_table_name}: " . $wpdb->last_error;
					} else {
						$results['success'][] = "Index {$index_name} removed from {$full_table_name}";
					}
				}
			}
		}

		return $results;
	}

	/**
	 * Get current index status
	 *
	 * @return array Current index information
	 */
	public static function get_index_status(): array {
		global $wpdb;

		$status = array();

		foreach ( self::$required_indexes as $table => $indexes ) {
			$full_table_name = $wpdb->prefix . str_replace( 'wp_', '', $table );

			$status[ $full_table_name ] = array();

			foreach ( $indexes as $index_name => $index_config ) {
				$exists = self::index_exists( $full_table_name, $index_name );

				$status[ $full_table_name ][ $index_name ] = array(
					'exists'      => $exists,
					'description' => $index_config['description'],
					'columns'     => $index_config['columns'],
				);
			}
		}

		return $status;
	}

	/**
	 * Analyze query performance
	 *
	 * @param  string $query SQL query to analyze
	 * @return array         Query analysis results
	 */
	public static function analyze_query( string $query ): array {
		global $wpdb;

		$explain_query = "EXPLAIN {$query}";
		$results       = $wpdb->get_results( $explain_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'query'   => $query,
			'explain' => $results,
		);
	}

	/**
	 * Get database performance statistics
	 *
	 * @return array Performance statistics
	 */
	public static function get_performance_stats(): array {
		global $wpdb;

		$stats = array();

		// Get table sizes
		$tables = array( 'posts', 'postmeta' );
		foreach ( $tables as $table ) {
			$table_name = $wpdb->prefix . $table;
			$size_query = "SELECT
				ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size_MB'
				FROM information_schema.tables
				WHERE table_schema = '" . DB_NAME . "'
				AND table_name = '{$table_name}'";

			$size = $wpdb->get_var( $size_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			$stats[ $table . '_size_mb' ] = $size ? $size : 0;
		}

		// Get index information
		$stats['indexes'] = self::get_index_status();

		return $stats;
	}

	/**
	 * Optimize database tables
	 *
	 * @return array Optimization results
	 */
	public static function optimize_tables(): array {
		global $wpdb;

		$results = array(
			'success' => array(),
			'errors'  => array(),
		);

		$tables = array( 'posts', 'postmeta' );

		foreach ( $tables as $table ) {
			$table_name = $wpdb->prefix . $table;
			$sql        = "OPTIMIZE TABLE {$table_name}";

			$result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			if ( false === $result ) {
				$results['errors'][] = "Failed to optimize {$table_name}: " . $wpdb->last_error;
			} else {
				$results['success'][] = "Table {$table_name} optimized";
			}
		}

		return $results;
	}
}
