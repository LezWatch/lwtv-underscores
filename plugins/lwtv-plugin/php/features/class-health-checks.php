<?php
/**
 * Health Checks
 *
 * Integrate with HealthChecks.io to monitor the site:
 *  - Create a check for each cron job and ping HealthChecks.io after the job runs.
 *  - If the check doesn't exist, create it.
 *  - If the check exists, update it if the schedule isn't correct.
 *
 * Due to the complex nature of this feature, it is heavily integrated with lwtv-plugin->error_log().
 *
 * Security Notes:
 *  - The API key is stored in the wp-config.php file.
 *  - The API Urls are IP restricted.
 *
 * @link https://health.ipstenu.com/docs/
 */

namespace LWTV\Features;

class Health_Checks {

	/**
	 * The API base URL
	 *
	 * @var string
	 */
	private $api_url = 'https://health.ipstenu.com';

	/**
	 * The API key
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * The API headers
	 *
	 * @var array
	 */
	private $api_headers = array(
		'X-Api-Key'    => '',
		'Content-Type' => 'application/json',
		'Referer'      => '',
		'Origin'       => '',
	);

	/**
	 * The prefix
	 *
	 * @var string
	 */
	private $prefix = '';

	/**
	 * The constructor
	 */
	public function __construct() {
		// If the site is in dev mode, don't run.
		if ( lwtv_plugin()->is_dev_site() ) {
			return;
		}

		// If the API key is not defined, don't run.
		if ( ! defined( 'HEALTHCHECKS_API_KEY' ) ) {
			return;
		} else {
			$this->api_key = HEALTHCHECKS_API_KEY;
		}

		// Set the API URL.
		$this->api_url = ( defined( 'HEALTHCHECKS_API_URL' ) ) ? HEALTHCHECKS_API_URL : $this->api_url;

		// Set the API headers.
		$this->api_headers['X-Api-Key'] = $this->api_key;
		$this->api_headers['Referer']   = home_url();
		$this->api_headers['Origin']    = home_url();

		// Get the prefix.
		$this->prefix = defined( 'HEALTHCHECKS_PREFIX' ) ? HEALTHCHECKS_PREFIX : $this->get_prefix();

		$this->init();
	}

	/**
	 * Initialize the Health Checks
	 */
	public function init() {
		add_filter( 'schedule_event', array( $this, 'maybe_register_healthcheck' ), 10, 1 );
	}

	/**
	 * Get the prefix.
	 *
	 * When there's no prefix defined, we use the domain name.
	 *
	 * @return string The prefix.
	 */
	private function get_prefix() {
		$prefix = 'healthchecks';
		$domain = wp_parse_url( home_url() );
		if ( isset( $domain['host'] ) ) {
			$prefix = str_replace( '.', '-', $domain['host'] );
		}

		return $prefix;
	}

	/**
	 * Maybe register the health check
	 *
	 * @param stdClass Object $event The event.
	 *
	 * @return stdClass|void Object The event.
	 */
	public function maybe_register_healthcheck( $event ) {
		// If the event is false or not an object, don't run.
		if ( false === $event || ! is_object( $event ) ) {
			return;
		}

		// Turn the event into an array and get the hook and interval.
		$array_event = (array) $event;
		$hook        = $array_event['hook'] ?? '';
		$interval    = $array_event['interval'] ?? '';

		// If there's no hook, don't run (this is a sanity check and should never happen).
		if ( empty( $hook ) ) {
			lwtv_plugin()->error_log( 'HealthCheck', 'Hook is empty' );
			return;
		}

		// If there's no interval, don't run as this is a one-time event.
		if ( empty( $interval ) ) {
			lwtv_plugin()->error_log( 'HealthCheck', 'Interval is empty. This is a one-time event.' );
			return $event;
		}

		$check_name = self::generate_check_name( $hook );
		$check      = self::get_or_create_check( $check_name, $interval );

		if ( empty( $check ) ) {
			lwtv_plugin()->error_log( 'HealthCheck', 'Check not found for ' . $check_name );
			return $event;
		}

		if ( ! isset( $check['ping_url'] ) ) {
			lwtv_plugin()->error_log( 'HealthCheck', 'Ping URL not found for ' . $check_name );
			return $event;
		}

		// Hook to ping HealthChecks after the job runs
		add_action(
			$hook,
			function () use ( $check ) {
				lwtv_plugin()->error_log( 'HealthCheck', 'Pinging ' . $check['ping_url'] );
				wp_remote_post( $check['ping_url'] );
			}
		);

		return $event;
	}

	/**
	 * Generate the check name
	 *
	 * Turns example.com to example-com and then appends the callback function name.
	 * For example, if the hook is lwtv_plugin_cron_job, the check name will be
	 * example-com-lwtv-plugin-cron-job.
	 *
	 * @param  string $hook The hook.
	 * @return string The check name.
	 */
	private function generate_check_name( $hook ) {
		$prefix = $this->prefix;

		return $prefix . '-' . strtolower( str_replace( '_', '-', $hook ) );
	}

	/**
	 * Get or create the check
	 *
	 * @param string $check_name The check name.
	 * @param string $interval   The interval of the check in seconds.
	 * @return array The check.
	 */
	private function get_or_create_check( $check_name, $interval ) {
		try {
			$list_checks = $this->list_checks();

			if ( empty( $list_checks ) ) {
				return array();
			}

			// Loop through the checks and see if we have one that matches the check name.
			foreach ( $list_checks['checks'] as $check ) {
				if ( $check['slug'] === $check_name ) {
					lwtv_plugin()->error_log( 'HealthCheck', 'Found check for ' . $check_name );
					$updated_check = $this->maybe_update_check( $check, $interval );
					return $updated_check;
				}
			}
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'HealthCheck', 'Error listing checks: ' . $e->getMessage() );
			return array();
		}

		// If we got here, there's no check, so we need to create one.
		lwtv_plugin()->error_log( 'HealthCheck', 'No check found for ' . $check_name . '. Creating...' );
		$new_check = $this->create_check( $check_name, $interval );

		if ( empty( $new_check ) ) {
			lwtv_plugin()->error_log( 'HealthCheck', 'Error creating check: ' . $check_name );
			return array();
		}

		return $new_check;
	}

	/**
	 * Maybe Update Check
		*
		* If the time and interval don't seem to match what we have, let's edit.
		*/
	private function maybe_update_check( $check, $interval ) {
		$schedule   = $check['schedule'] ?? '';
		$check_name = $check['slug'] ?? '';

		$current_schedule = self::get_schedule( $interval );

		if ( $current_schedule === $schedule ) {
			lwtv_plugin()->error_log( 'HealthCheck', 'Schedule is correct for ' . $check_name );
			return $check;
		}

		lwtv_plugin()->error_log( 'HealthCheck', 'Schedule is incorrect for ' . $check_name . '. Updating...' );

		try {
			$body = $this->create_check_body( $check_name, $interval );

			$update = wp_remote_post(
				$this->api_url . '/api/v3/checks/' . $check_name,
				array(
					'headers' => $this->api_headers,
					'body'    => wp_json_encode( $body ),
				)
			);

			if ( is_wp_error( $update ) ) {
				lwtv_plugin()->error_log( 'HealthCheck', 'Error updating check: ' . $check_name );
				return $check;
			}

			return json_decode( wp_remote_retrieve_body( $update ), true );
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'HealthCheck', 'Error updating check: ' . $check_name );
			return $check;
		}
	}

	/**
	 * Create a check
	 *
	 * @param string $check_name The check name.
	 * @param string $interval   The interval of the check in seconds.
	 * @return array The check.
	 */
	private function create_check( $check_name, $interval ) {
		// Since we're creating a check, we need to delete the transient.
		lwtv_plugin()->delete_transient( 'lwtv_healthchecks_list' );

		try {
			// Post to the checks endpoint.
			$body   = $this->create_check_body( $check_name, $interval );
			$create = wp_remote_post(
				$this->api_url . '/api/v3/checks/',
				array(
					'headers' => $this->api_headers,
					'body'    => wp_json_encode( $body ),
				)
			);

			if ( is_wp_error( $create ) ) {
				lwtv_plugin()->error_log( 'HealthCheck', 'Error creating check on ' . $check_name . ': ' . $create->get_error_message() );
				return array();
			}

			return json_decode( wp_remote_retrieve_body( $create ), true );
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'HealthCheck', 'Error creating check on ' . $check_name . ': ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Create the check body
	 *
	 * @param string $check_name The check name.
	 * @param string $interval   The interval of the check in seconds.
	 *
	 * @return array The check body.
	 */
	private function create_check_body( $check_name, $interval ) {
		$slug     = sanitize_title( $check_name );
		$prefix   = $this->prefix;
		$schedule = self::get_schedule( $interval );
		$timeout  = self::calculate_timeout( $interval );
		$name     = str_replace( $prefix . '-', '', $check_name );
		$name     = str_replace( '-', ' ', $name );

		return array(
			'name'     => ucwords( $name ),
			'tags'     => 'wp-cron lwtv',
			'slug'     => $slug,
			'timeout'  => $timeout,
			'grace'    => $interval * 1.5, // +50% of the interval
			'schedule' => $schedule,
			'tz'       => 'America/Los_Angeles',
			'unique'   => array( 'name', 'slug' ),
		);
	}

	/* Get the checks
	 *
	 * @return array The checks.
	 */
	private function list_checks() {
		// See if the transient exists.
		$transient = lwtv_plugin()->get_transient( 'lwtv_healthchecks_list' );

		if ( false !== $transient ) {
			return $transient;
		}

		try {
			$response = wp_remote_get(
				$this->api_url . '/api/v3/checks/',
				array(
					'headers' => $this->api_headers,
				)
			);

			if ( is_wp_error( $response ) ) {
				lwtv_plugin()->error_log( 'HealthCheck', 'Error listing checks: ' . $response->get_error_message() );
				throw new \Exception( $response->get_error_message() );
			}

			$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				lwtv_plugin()->error_log( 'HealthCheck', 'Invalid JSON response' );
				throw new \Exception( 'Invalid JSON response' );
			}

			// Set the transient.
			lwtv_plugin()->set_transient( 'lwtv_healthchecks_list', $decoded, 60 * 60 * 24 );

			return $decoded;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'HealthCheck', 'Error listing checks: ' . $e->getMessage() );
			return array();
		}

		return array();
	}

	/**
	 * Get the schedule for the check
	 *
	 * Convert the WP format to regular cron.
	 *
	 * @param int $interval   The interval of the check in seconds.
	 *
	 * @return string The schedule for the check.
	 */
	private function get_schedule( int $interval ) {
		$default = '0 0 * * *';

		// $interval is in seconds, so we need to convert it to cron format.
		// For example, 600 seconds is 10 minutes which is */10 in cron format.
		$seconds = $interval;

		// If the interval is not a multiple of 60, use the default.
		if ( 0 !== $seconds % 60 ) {
			lwtv_plugin()->error_log( 'HealthCheck', 'Interval in seconds is not a multiple of 60: ' . $interval );
			return $default;
		}

		// If the interval is less than 60 seconds, run every minute.
		if ( $seconds <= 60 ) {
			return '* * * * *';
		}

		$minutes = $seconds / 60;

		// If the interval is not a multiple of 60, use the default.
		if ( 0 !== $minutes % 60 ) {
			lwtv_plugin()->error_log( 'HealthCheck', 'Interval in minutes is not a multiple of 60: ' . $interval );
			return $default;
		}

		// If the interval is less than an hour (1 to 59 minutes), run every $minutes.
		if ( $minutes < 60 ) {
			return '*/' . $minutes . ' * * * *';
		}

		$hours = $minutes / 60;

		// Check if it's an even number of days.
		if ( 0 !== $hours % 24 ) {
			lwtv_plugin()->error_log( 'HealthCheck', 'Interval in hours is not a multiple of 24: ' . $interval );
			return $default;
		}

		// If the interval is less than a day (1 to 23 hours), run every $hours.
		if ( $hours < 24 ) {
			return '0 */' . $hours . ' * * *';
		}

		$days = $hours / 24;

		// If the interval is not a multiple of 7, use the default.
		if ( 0 !== $days % 7 ) {
			lwtv_plugin()->error_log( 'HealthCheck', 'Interval in days is not a multiple of 7: ' . $interval );
			return $default;
		}

		// If the interval is less than a week (1 to 6 days), run every $days.
		if ( $days < 7 ) {
			return '0 0 */' . $days . ' * *';
		}

		$weeks = $days / 7;

		// If the interval is a week, run every week on 'today' at 00:00.
		if ( 1 === $weeks ) {
			$today = gmdate( 'w' );
			return '0 0 * * ' . $today;
		}

		lwtv_plugin()->error_log( 'HealthCheck', 'Interval cannot be processed, using default. Interval: ' . $interval );

		return $default;
	}

	/**
	 * Calculate the timeout for the check
	 *
	 * @param int  $interval The interval of the check in seconds.
	 *
	 * @return int The timeout for the check.
	 */
	private function calculate_timeout( $interval ) {
		return max( 600, $interval * 2 );
	}
}
