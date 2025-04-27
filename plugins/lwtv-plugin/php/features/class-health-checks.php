<?php
/**
 * Health Checks
 *
 * Integrate with HealthChecks.io to monitor the site.
 *
 * This will create a check for each cron job and ping HealthChecks.io after the job runs.
 *
 * Due to the complex nature of this feature, it is heavily integrated with lwtv-plugin->error_log().
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
	
	private $kill_cron = array(
		'jetpack_sync_cron',
		'jetpack_sync_full_cron',
		'jetpack_clean_nonces',
		'jp_purge_transients_cron',
		'jetpack_waf_rules_update_cron',
		'jetpack_v2_heartbeat',
	);

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
	 * Get the prefix
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
		// Turn the event into an array and get the hook, recurrence, and interval.
		$array_event = (array) $event;
		$hook        = $array_event['hook'] ?? '';
		$recurrence  = $array_event['schedule'] ?? '';
		$interval    = $array_event['interval'] ?? '';

		// If there's no hook, don't run.
		if ( empty( $hook ) ) {
			lwtv_plugin()->error_log( 'Health check', 'Hook is empty' );
			return;
		}
		
		// If this is in the kill list, don't do it!
		if ( in_array( $hook, $this->kill_cron ) ) {
			lwtv_plugin()->error_log( 'Health Check', 'On the Kill List. Unscheduling ' . $hook )
			wp_clear_scheduled_hook( $hook );
			return;
		}

		// If there's no recurrence, don't run.
		if ( empty( $recurrence ) || empty( $interval ) ) {
			lwtv_plugin()->error_log( 'Health check', 'Recurrence is empty' );
			return $event;
		}

		$check_name = self::generate_check_name( $hook );
		$check      = self::get_or_create_check( $check_name, $recurrence );

		if ( empty( $check ) ) {
			lwtv_plugin()->error_log( 'Health check', 'Check not found for ' . $check_name );
			return $event;
		}

		if ( ! isset( $check['ping_url'] ) ) {
			lwtv_plugin()->error_log( 'Health check', 'Ping URL not found for ' . $check_name );
			return $event;
		}

		// Hook to ping HealthChecks after the job runs
		add_action(
			$hook,
			function () use ( $check ) {
				lwtv_plugin()->error_log( 'Health check', 'Pinging ' . $check['ping_url'] );
				wp_remote_post( $check['ping_url'] );
			}
		);

		return $event;
	}

	/**
	 * Generate the check name
	 *
	 * Turns example.com to example-com and then appends the callback function name.
	 *
	 * @param string $hook The hook.
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
	 * @param string $recurrence The recurrence of the check.
	 * @return array The check.
	 */
	private function get_or_create_check( $check_name, $recurrence ) {
		try {
			$list_checks = $this->list_checks();

			if ( empty( $list_checks ) ) {
				return array();
			}

			foreach ( $list_checks['checks'] as $check ) {
				if ( $check['slug'] === $check_name ) {
					lwtv_plugin()->error_log( 'Health check', 'Found check for ' . $check_name );
					$updated_check = $this->maybe_update_check( $check_name, $check, $recurrence );
					return $updated_check;
				}
			}
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'Health check', 'Error listing checks: ' . $e->getMessage() );
			return array();
		}

		// If we got here, there's no check, so we need to create one.
		lwtv_plugin()->error_log( 'Health check', 'No check found for ' . $check_name . '. Creating...' );
		$new_check = $this->create_check( $check_name, $recurrence );

		if ( empty( $new_check ) ) {
			lwtv_plugin()->error_log( 'Health check', 'Error creating check: ' . $check_name );
			return array();
		}

		return $new_check;
	}
	
	/**
	 * Maybe Update Check
		*
		* If the time and recurrance don't seem to match what we have, let's edit.
		*/
	private function maybe_update_check( $check_name, $check, $recurrence ) {
			$schedule = $check['schedule'] ?? '0 10 * * *';
			
			// Compare the recurrance to the check.
			// Every minute is easy
			// For daily, check what time NOW IS to adjust the second digit.
		
			return $check;
		}

	/**
	 * Create a check
	 *
	 * @param string $check_name The check name.
	 * @param string $recurrence The recurrence of the check.
	 * @return array The check.
	 */
	private function create_check( $check_name, $recurrence ) {
		// Since we're creating a check, we need to delete the transient.
		lwtv_plugin()->delete_transient( 'lwtv_healthchecks_list' );

		$prefix   = $this->prefix;
		$timeout  = self::calculate_timeout( $recurrence );
		$slug     = sanitize_title( $check_name );
		$schedule = self::get_schedule( $recurrence );
		$name     = str_replace( $prefix . '-', '', $check_name );
		$name     = str_replace( '-', ' ', $name );
		$body     = array(
			'name'     => ucwords( $name ),
			'tags'     => 'wp-cron lwtv',
			'slug'     => $slug,
			'timeout'  => $timeout,
			'grace'    => 6000, // 1 hour grace period
			'schedule' => $schedule,
			'tz'       => 'America/Los_Angeles',
		);

		try {
			// Post to the checks endpoint.
			$create = wp_remote_post(
				$this->api_url . '/api/v3/checks/',
				array(
					'headers' => $this->api_headers,
					'body'    => wp_json_encode( $body ),
				)
			);

			if ( is_wp_error( $create ) ) {
				lwtv_plugin()->error_log( 'Health check', 'Error creating check on ' . $check_name . ': ' . $create->get_error_message() );
				return array();
			}

			return json_decode( wp_remote_retrieve_body( $create ), true );
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'Health check', 'Error creating check on ' . $check_name . ': ' . $e->getMessage() );
			return array();
		}
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
				lwtv_plugin()->error_log( 'Health check', 'Error listing checks: ' . $response->get_error_message() );
				throw new \Exception( $response->get_error_message() );
			}

			$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				lwtv_plugin()->error_log( 'Health check', 'Invalid JSON response' );
				throw new \Exception( 'Invalid JSON response' );
			}

			// Set the transient.
			lwtv_plugin()->set_transient( 'lwtv_healthchecks_list', $decoded, 60 * 60 * 24 );

			return $decoded;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'Health check', 'Error listing checks: ' . $e->getMessage() );
			return array();
		}

		return array();
	}

	/**
	 * Get the schedule for the check
	 *
	 * Convert the WP format to regular cron.
	 *
	 * @param string $recurrence The recurrence of the check.
	 * @return string The schedule for the check.
	 */
	private function get_schedule( $recurrence ) {
		$all_schedules = wp_get_schedules();
		$schedules     = array();
		$default       = '0 * * * *';  // fallback hourly

		// Convert schedule into an array of $timing[$recurrence] = CRONTIME;
		$timing = array();
		foreach ( $all_schedules as $timing => $details ) {
			if ( is_numeric( $recurrence ) ) {
				return $this->get_numeric_schedule( $recurrence );
			}

			// Otherwise we have to parse the WP schedule.
			$schedules[ $details['interval'] ] = match ( $timing ) {
				'searchwp_cron_interval' => '*/5 * * * *',
				'every_minute'           => '* * * * *',
				'fifteen_minutes'        => '*/15 * * * *',
				'hourly'                 => '0 * * * *',
				'daily'                  => '0 0 * * *',
				'twicedaily'             => '0 */12 * * *',
				'weekly'                 => '0 0 * * 1',
				'monthly'                => '0 0 1 * *',
				default                  => $default,
			};
		}

		return $schedules[ $recurrence ] ?? $default;
	}

	/**
	 * Get the numeric schedule
	 *
	 * @param string $recurrence The recurrence of the check.
	 * @return string The schedule for the check.
	 */
	private function get_numeric_schedule( $recurrence ) {
		// Then recurrence is in seconds so we need to convert it to a cron schedule.
		$minutes = ceil( $recurrence / 60 );
		$hours   = ceil( $recurrence / 3600 );
		$days    = ceil( $recurrence / 86400 );
		$weeks   = ceil( $recurrence / 604800 );
		$months  = ceil( $recurrence / 2592000 );

		if ( 0 === $minutes ) {
			// If the recurrence is 0, then we need to run the check every minute.
			return '* * * * *';
		} elseif ( $minutes < 60 ) {
			// If the recurrence is less than 60 minutes, then we need to run the check every $minutes.
			return "0/$minutes * * * *";
		} elseif ( $hours < 24 ) {
			// If the recurrence is less than 24 hours, then we need to run the check every $hours.
			return "0 */$hours * * *";
		} elseif ( $days < 7 ) {
			// If the recurrence is less than 7 days, then we need to run the check every $days.
			return "0 0 */$days * *";
		} elseif ( $weeks < 4 ) {
			// If the recurrence is less than 4 weeks, then we need to run the check every $weeks.
			return "0 0 * */$weeks *";
		} elseif ( $months < 12 ) {
			// If the recurrence is less than 12 months, then we need to run the check every $months.
			return "0 0 1 */$months *";
		} else {
			// If the recurrence is greater than 12 months, then we need to run the check every year.
			return '0 0 1 1 *';
		}

		// If we got here, then we need to run the check every hour.
		return '0 * * * *';
	}

	/**
	 * Calculate the timeout for the check
	 *
	 * @param string $recurrence The recurrence of the check.
	 * @return int The timeout for the check.
	 */
	private function calculate_timeout( $recurrence ) {

		// If the recurrence is a number, then multiply by 2 and return which is greater: 600 or the result.
		if ( is_numeric( $recurrence ) ) {
			return max( 600, $recurrence * 2 );
		}

		$timeout = match ( $recurrence ) {
			'fifteen_minutes' => 900,
			'hourly'          => 3600,
			'daily'           => 86400,
			'twicedaily'      => 43200,
			'weekly'          => 604800,
			'monthly'         => 2592000,
			default           => 3600, // fallback 1 hour
		};

		return max( 600, $timeout );
	}
}
