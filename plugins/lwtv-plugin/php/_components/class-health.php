<?php
/*
 * LezWatch.TV Health
 *
 */

namespace LWTV\_Components;

use LWTV\Health\Health_Checks;

class Health implements Component, Templater {

	public function init() {
		new Health_Checks();
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
			'get_health_overview' => array( $this, 'get_health_overview' ),
		);
	}


	/**
	 * Get overall health overview
	 *
	 * @return array Overall health status information
	 */
	public function get_health_overview(): array {
		$health_checks = new Health_Checks();

		return array(
			'external_checks' => $health_checks->get_health_status(),
		);
	}
}
