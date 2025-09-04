<?php

namespace LWTV\Health;

class Ping {

	/**
	 * The API URL
	 *
	 * @var string
	 */
	private $api_url;

	/**
	 * The constructor
	 */
	public function __construct() {
		$this->api_url = Health_Checks::API_URL . Health_Checks::API_VERSION;
	}

	/**
	 * Ping the health check
	 *
	 * @param string $check_name The name of the health check.
	 * @param string $check_url  The URL of the health check.
	 *
	 * @return void
	 */
	public function ping( $check_name = '', $check_url = '' ): object {

		if ( empty( $check_url ) && empty( $check_name ) ) {
			lwtv_plugin()->error_log( 'HealthCheck', 'No check URL or name provided. Skipping ping.' );
			return new \WP_Error( 'no_check_url_or_name', 'No check URL or name provided. Skipping ping.' );
		}

		$check_name = sanitize_title( $check_name );
		$check_url  = $check_url ? $check_url : $this->api_url . $check_name . '/ping/';

		try {
			lwtv_plugin()->error_log( 'HealthCheck', 'Pinging ' . $check_url );
			$response = wp_remote_post( $check_url );

			if ( is_wp_error( $response ) ) {
				lwtv_plugin()->error_log( 'HealthCheck', 'Error pinging health check: ' . $response->get_error_message() );
				throw new \Exception( $response->get_error_message() );
			}

			return $response;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'HealthCheck', 'Error pinging health check: ' . $e->getMessage() );

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \Exception( $e->getMessage() );
		}
	}
}
