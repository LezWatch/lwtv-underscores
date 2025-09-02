<?php
/**
 * Transient Health Monitor
 *
 * Monitors transient usage patterns and provides health status information
 *
 * @package lwtv-plugin
 */

namespace LWTV\Health;

/**
 * Class Transient_Health_Monitor
 */
class Transient_Health_Monitor {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Initialize monitoring
		$this->init_monitoring();
	}

	/**
	 * Initialize monitoring
	 *
	 * @return void
	 */
	private function init_monitoring(): void {
		// Track transient creation
		add_action( 'set_transient', array( $this, 'track_transient_creation' ), 10, 3 );
		add_action( 'set_site_transient', array( $this, 'track_transient_creation' ), 10, 3 );

		// Track transient deletion
		add_action( 'delete_transient', array( $this, 'track_transient_deletion' ), 10, 1 );
		add_action( 'delete_site_transient', array( $this, 'track_transient_deletion' ), 10, 1 );
	}

	/**
	 * Track transient creation
	 *
	 * @param string $transient_name Transient name
	 * @param mixed  $value          Transient value
	 * @param int    $expiration     Expiration time
	 * @return void
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function track_transient_creation( string $transient_name, $value, int $expiration ): void {
		$stats = $this->get_tracking_stats();

		// Increment creation count
		++$stats['created_count'];
		++$stats['created_today'];

		// Track by plugin/source
		$source = $this->identify_transient_source( $transient_name );
		if ( ! isset( $stats['sources'][ $source ] ) ) {
			$stats['sources'][ $source ] = 0;
		}
		++$stats['sources'][ $source ];

		// Track size
		$size                 = strlen( is_string( $value ) ? $value : wp_json_encode( $value ) );
		$stats['total_size'] += $size;
		$stats['size_today'] += $size;

		// Update stats
		$this->update_tracking_stats( $stats );
	}

	/**
	 * Track transient deletion
	 *
	 * @param string $transient_name Transient name
	 * @return void
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	public function track_transient_deletion( string $transient_name ): void {
		$stats = $this->get_tracking_stats();

		// Increment deletion count
		++$stats['deleted_count'];
		++$stats['deleted_today'];

		// Update stats
		$this->update_tracking_stats( $stats );
	}

	/**
	 * Get transient health status
	 *
	 * @return array Health status information
	 */
	public function get_health_status(): array {
		global $wpdb;

		// Get current transient statistics
		$total_transients = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->options WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'" );
		$total_size       = $wpdb->get_var( "SELECT SUM(LENGTH(option_value)) FROM $wpdb->options WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'" );

		// Get expired transients
		$expired_transients = $wpdb->get_results( $wpdb->prepare( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s AND option_value < %d", $wpdb->esc_like( '_transient_timeout_' ) . '%', time() ) );
		$expired_count      = count( $expired_transients );

		// Get LWTV-specific transients
		$lwtv_transients = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->options WHERE option_name LIKE '_transient_lwtv_%' OR option_name LIKE '_site_transient_lwtv_%'" );
		$lwtv_size       = $wpdb->get_var( "SELECT SUM(LENGTH(option_value)) FROM $wpdb->options WHERE option_name LIKE '_transient_lwtv_%' OR option_name LIKE '_site_transient_lwtv_%'" );

		// Get tracking stats
		$tracking_stats = $this->get_tracking_stats();

		// Calculate health metrics
		$expired_percentage = $total_transients > 0 ? ( $expired_count / $total_transients ) * 100 : 0;
		$lwtv_percentage    = $total_transients > 0 ? ( $lwtv_transients / $total_transients ) * 100 : 0;

		// Determine health status
		$health_status = $this->calculate_health_status( $expired_count, $expired_percentage );

		return array(
			'total_transients'   => (int) $total_transients,
			'total_size'         => (int) $total_size,
			'expired_count'      => $expired_count,
			'expired_percentage' => round( $expired_percentage, 2 ),
			'lwtv_transients'    => (int) $lwtv_transients,
			'lwtv_size'          => (int) $lwtv_size,
			'lwtv_percentage'    => round( $lwtv_percentage, 2 ),
			'health_status'      => $health_status,
			'tracking_stats'     => $tracking_stats,
			'last_updated'       => time(),
		);
	}

	/**
	 * Calculate health status
	 *
	 * @param int   $expired_count      Number of expired transients
	 * @param float $expired_percentage Percentage of expired transients
	 * @return string Health status (good, warning, critical)
	 */
	private function calculate_health_status( int $expired_count, float $expired_percentage ): string {
		// Critical: More than 100 expired or more than 50% expired
		if ( $expired_count > 100 || $expired_percentage > 50 ) {
			return 'critical';
		}

		// Warning: More than 50 expired or more than 25% expired
		if ( $expired_count > 50 || $expired_percentage > 25 ) {
			return 'warning';
		}

		return 'good';
	}

	/**
	 * Identify transient source/plugin
	 *
	 * @param string $transient_name Transient name
	 * @return string Source identifier
	 */
	private function identify_transient_source( string $transient_name ): string {
		// Remove transient prefix
		$name = str_replace( array( '_transient_', '_site_transient_' ), '', $transient_name );

		// Identify common patterns
		if ( strpos( $name, 'lwtv_' ) === 0 ) {
			return 'lwtv';
		}
		if ( strpos( $name, 'wpseo_' ) === 0 ) {
			return 'yoast';
		}
		if ( strpos( $name, 'woocommerce_' ) === 0 ) {
			return 'woocommerce';
		}
		if ( strpos( $name, 'jetpack_' ) === 0 ) {
			return 'jetpack';
		}
		if ( strpos( $name, 'wordpress_' ) === 0 ) {
			// phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText
			return 'wordpress';
		}

		// Extract first part as source
		$parts = explode( '_', $name );
		return $parts[0] ?? 'unknown';
	}

	/**
	 * Get tracking statistics
	 *
	 * @return array Tracking statistics
	 */
	private function get_tracking_stats(): array {
		$stats = lwtv_plugin()->get_transient( 'lwtv_transient_tracking_stats' );

		if ( false === $stats ) {
			$stats = array(
				'created_count' => 0,
				'deleted_count' => 0,
				'created_today' => 0,
				'deleted_today' => 0,
				'total_size'    => 0,
				'size_today'    => 0,
				'sources'       => array(),
				'last_reset'    => time(),
			);
		}

		// Reset daily counters if needed
		if ( gmdate( 'Y-m-d', $stats['last_reset'] ) !== gmdate( 'Y-m-d' ) ) {
			$stats['created_today'] = 0;
			$stats['deleted_today'] = 0;
			$stats['size_today']    = 0;
			$stats['last_reset']    = time();
		}

		return $stats;
	}

	/**
	 * Update tracking statistics
	 *
	 * @param array $stats Statistics to update
	 * @return void
	 */
	private function update_tracking_stats( array $stats ): void {
		lwtv_plugin()->set_transient( 'lwtv_transient_tracking_stats', $stats, DAY_IN_SECONDS );
	}

	/**
	 * Get transient cleanup recommendations
	 *
	 * @return array Cleanup recommendations
	 */
	public function get_cleanup_recommendations(): array {
		$status          = $this->get_health_status();
		$recommendations = array();

		if ( $status['expired_count'] > 0 ) {
			$recommendations[] = array(
				'type'     => 'cleanup',
				'priority' => $status['expired_count'] > 50 ? 'high' : 'medium',
				'message'  => sprintf( 'Clean up %d expired transients', $status['expired_count'] ),
				'action'   => 'run_cleanup',
				'impact'   => 'Reduce database size and improve performance',
			);
		}

		if ( $status['expired_percentage'] > 25 ) {
			$recommendations[] = array(
				'type'     => 'monitoring',
				'priority' => 'high',
				'message'  => 'High percentage of expired transients indicates cleanup issues',
				'action'   => 'investigate_cleanup',
				'impact'   => 'Prevent future accumulation of expired transients',
			);
		}

		// Check for problematic sources
		$sources = $status['tracking_stats']['sources'] ?? array();
		foreach ( $sources as $source => $count ) {
			if ( $count > 100 ) {
				$recommendations[] = array(
					'type'     => 'source',
					'priority' => 'medium',
					'message'  => sprintf( 'Source "%s" creating many transients (%d)', $source, $count ),
					'action'   => 'investigate_source',
					'impact'   => 'Optimize transient usage from this source',
				);
			}
		}

		return $recommendations;
	}
}
