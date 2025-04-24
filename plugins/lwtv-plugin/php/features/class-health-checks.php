<?php
/**
 * Health Checks
 *
 * Integrate with HealthChecks.io to monitor the site.
 *
 * This will create a check for each cron job and ping HealthChecks.io after the job runs.
 *
 * @link https://health.ipstenu.com/docs/
 */

namespace LWTV\Features;

class Health_Checks {

	private $api_url = 'https://health.ipstenu.com';

	public function __construct() {
		$this->api_url = ( defined( 'HEALTHCHECKS_API_URL' ) ) ? HEALTHCHECKS_API_URL : $this->api_url;

		// If the API key is not defined, don't run.
		if ( ! defined( 'HEALTHCHECKS_API_KEY' ) ) {
			return;
		}

		// If the site is in dev mode, don't run.
		if ( defined( 'LWTV_DEV_SITE' ) && LWTV_DEV_SITE ) {
			return;
		}

		$this->init();
	}

	/**
	 * Initialize the Health Checks
	 */
	public function init() {
		add_filter( 'schedule_event', array( $this, 'maybe_register_healthcheck' ), 10, 3 );
	}

	/**
	 * Maybe register the healthcheck
	 *
	 * @param array  $event The event.
	 * @param string $recurrence The recurrence of the event.
	 * @param string $hook The hook of the event.
	 * @return array The event.
	 */
	public function maybe_register_healthcheck( $event, $recurrence = null, $hook = null ) {

		// If there's no recurrence, don't run.
		if ( empty( $recurrence ) ) {
			return $event;
		}

		// If there's no hook, don't run.
		if ( empty( $hook ) ) {
			return $event;
		}

		// If we can't get the callback function from the hook, don't run.
		$callback = self::get_callback_function_from_hook( $hook );
		if ( ! $callback ) {
			return $event;
		}

		$check_name = self::generate_check_name( $callback );
		$check      = self::get_or_create_check( $check_name, $recurrence );

		// Hook to ping HealthChecks after the job runs
		add_action(
			$hook,
			function () use ( $check ) {
				wp_remote_post( $check['ping_url'] );
			}
		);

		return $event;
	}

	/**
	 * Get the callback function from the hook
	 *
	 * @param string $hook The hook to get the callback function from.
	 * @return string|false The callback function or false if not found.
	 */
	private function get_callback_function_from_hook( $hook ) {
		$crons = _get_cron_array();
		if ( ! is_array( $crons ) ) {
			return false;
		}

		foreach ( $crons as $timestamp => $cron ) {
			if ( isset( $cron[ $hook ] ) ) {
				foreach ( $cron[ $hook ] as $job ) {
					if ( isset( $job['function'] ) ) {
						return is_string( $job['function'] ) ? $job['function'] : 'anonymous';
					}
				}
			}
		}
		return false;
	}

	/**
	 * Generate the check name
	 *
	 * Turns example.com to example-com and then appends the callback function name.
	 *
	 * @param string $callback The callback function.
	 * @return string The check name.
	 */
	private function generate_check_name( $callback ) {
		if ( ! defined( 'HEALTHCHECKS_PREFIX' ) ) {
			// If the prefix is not defined, use the domain from home URL.
			$domain = wp_parse_url( home_url(), PHP_URL_HOST );
			$prefix = str_replace( '.', '-', $domain );
		} else {
			$prefix = HEALTHCHECKS_PREFIX;
		}

		return $prefix . '-' . strtolower( str_replace( '_', '-', $callback ) );
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
				if ( $check['name'] === $check_name ) {
					return $check;
				}
			}
		} catch ( \Exception $e ) {
			return array();
		}

		// If we got here, there's no check, so we need to create one.
		$new_check = $this->create_check( $check_name, $recurrence );

		if ( empty( $new_check ) ) {
			return array();
		}

		return $new_check;
	}

	/**
	 * Create a check
	 *
	 * @param string $check_name The check name.
	 * @param string $recurrence The recurrence of the check.
	 * @return array The check.
	 */
	private function create_check( $check_name, $recurrence ) {
		$timeout  = self::calculate_timeout( $recurrence );
		$slug     = sanitize_title( $check_name );
		$schedule = self::get_schedule( $recurrence );

		$headers = array(
			'X-Api-Key'    => HEALTHCHECKS_API_KEY,
			'Content-Type' => 'application/json',
		);
		$body    = array(
			'name'     => $check_name,
			'tags'     => 'wp-cron lwtv',
			'slug'     => $slug,
			'timeout'  => $timeout,
			'grace'    => 300, // 5 minutes grace period
			'schedule' => $schedule,
		);

		try {
			$create = wp_remote_post(
				HEALTHCHECKS_API_URL,
				array(
					'headers' => $headers,
					'body'    => wp_json_encode( $body ),
				)
			);

			return json_decode( wp_remote_retrieve_body( $create ), true );
		} catch ( \Exception $e ) {
			return array();
		}
	}

	/* Get the checks
	 *
	 * @return array The checks.
	 */
	private function list_checks() {
		try {
			$response = wp_remote_get(
				$this->api_url,
				array(
					'headers' => array( 'X-Api-Key' => HEALTHCHECKS_API_KEY ),
				)
			);
			if ( is_wp_error( $response ) ) {
				throw new \Exception( $response->get_error_message() );
			}

			$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				throw new \Exception( 'Invalid JSON response' );
			}

			return $decoded;
		} catch ( \Exception $e ) {
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
		$schedules = wp_get_schedules();

		// Convert schedule into an array of $timing[$recurrence] = CRONTIME;
		$timing = array();
		foreach ( $schedules as $timing => $details ) {
			if ( is_numeric( $recurrence ) ) {
				// Then recurrence is in seconds so we need to convert it to a cron schedule.
				$minutes = ceil( $recurrence / 60 );
				$hours   = ceil( $recurrence / 3600 );
				$days    = ceil( $recurrence / 86400 );
				$weeks   = ceil( $recurrence / 604800 );
				$months  = ceil( $recurrence / 2592000 );

				if ( 0 === $minutes ) {
					// If the recurrence is 0, then we need to run the check every minute.
					return '0 * * * *';
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
			}

			// Otherwise we have to parse the WP schedule.
			$schedule[ $details['interval'] ] = match ( $timing ) {
				'hourly'     => '0 * * * *',
				'daily'      => '0 0 * * *',
				'twicedaily' => '0 */12 * * *',
				'weekly'     => '0 0 * * 1',
				'monthly'    => '0 0 1 * *',
				default      => '0 * * * *', // fallback hourly
			};
		}

		return $schedule[ $recurrence ];
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
			'hourly'     => 3600,
			'daily'      => 86400,
			'twicedaily' => 43200,
			'weekly'     => 604800,
			'monthly'    => 2592000,
			default      => 3600, // fallback 1 hour
		};

		return max( 600, $timeout );
	}
}
