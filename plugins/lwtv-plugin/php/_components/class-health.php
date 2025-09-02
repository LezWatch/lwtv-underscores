<?php
/*
 * LezWatch.TV Health
 *
 */

namespace LWTV\_Components;

use LWTV\Health\Health_Checks;
use LWTV\Health\Transient_Health_Monitor;

class Health implements Component, Templater {

	public function init() {
		new Health_Checks();
		new Transient_Health_Monitor();
	}

	/**
	 * Gets tags to expose as methods accessible through `lwtv_plugin()`.
	 *
	 * @return array Associative array of $method_name => $callback_info pairs. Each $callback_info must either be
	 *               a callable or an array with key 'callable'. This approach is used to reserve the possibility of
	 *               adding support for further arguments in the future.
	 */
	public function get_template_tags(): array {
		return array(
			'get_transient_health_status' => array( $this, 'get_transient_health_status' ),
			'get_health_overview'         => array( $this, 'get_health_overview' ),
		);
	}

	/**
	 * Get transient health status
	 *
	 * @return array Transient health status information
	 */
	public function get_transient_health_status(): array {
		$monitor = new Transient_Health_Monitor();
		return $monitor->get_health_status();
	}

	/**
	 * Get overall health overview
	 *
	 * @return array Overall health status information
	 */
	public function get_health_overview(): array {
		$health_checks     = new Health_Checks();
		$transient_monitor = new Transient_Health_Monitor();

		return array(
			'external_checks'  => $health_checks->get_health_status(),
			'transient_health' => $transient_monitor->get_health_status(),
			'overall_status'   => $this->calculate_overall_status(),
		);
	}

	/**
	 * Calculate overall health status
	 *
	 * @return string Overall health status (good, warning, critical)
	 */
	private function calculate_overall_status(): string {
		$transient_status = $this->get_transient_health_status();

		// Check for critical issues
		if ( $transient_status['expired_count'] > 100 ) {
			return 'critical';
		}

		// Check for warnings
		if ( $transient_status['expired_count'] > 50 ) {
			return 'warning';
		}

		return 'good';
	}
}
