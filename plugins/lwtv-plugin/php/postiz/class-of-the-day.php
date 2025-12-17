<?php
/**
 * Of The Day
 *
 * @package lwtv-plugin
 */

namespace LWTV\Postiz;

class Of_The_Day extends Postiz {

	/**
	 * Action Scheduler hook name
	 */
	const AS_HOOK = 'lwtv_postiz_otd_post';

	/**
	 * Action Scheduler group name
	 */
	const AS_GROUP = 'lwtv_postiz';

	/**
	 * WordPress cron hook name (fallback)
	 */
	const WP_CRON_HOOK = 'lwtv_postiz_otd_cron';

	/**
	 * Delay in seconds before posting (5 minutes)
	 */
	const DELAY_SECONDS = 300;

	/**
	 * Constructor - register hook for OTD posts
	 */
	public function __construct() {
		parent::__construct();

		if ( $this->is_enabled() && $this->is_type_triggered_enabled( 'of_the_day' ) ) {
			add_action( 'lwtv_otd_added', array( $this, 'handle_otd_added' ), 10, 4 );

			// Register Action Scheduler hook if available
			if ( lwtv_plugin()->is_action_scheduler_available() ) {
				add_action( self::AS_HOOK, array( $this, 'process_scheduled_otd' ), 10, 4 );
			}

			// Always register WP cron hook as fallback
			add_action( self::WP_CRON_HOOK, array( $this, 'process_scheduled_otd' ), 10, 4 );
		}
	}

	/**
	 * Handle the lwtv_otd_added action
	 *
	 * Schedules the OTD post to be processed 5 minutes later via Action Scheduler
	 * or WordPress cron as fallback.
	 *
	 * @param string $type    Type of OTD (character, show)
	 * @param string $content The content to post
	 * @param int    $post_id The post ID
	 * @param array  $data    Additional data about the OTD
	 */
	public function handle_otd_added( $type, $content, $post_id, $data ) {
		$post_title     = get_the_title( $post_id );
		$scheduled_time = time() + self::DELAY_SECONDS;
		$args           = array( $type, $content, $post_id, $data );

		// Use Action Scheduler if available, otherwise fall back to WP cron
		if ( lwtv_plugin()->is_action_scheduler_available() ) {
			as_schedule_single_action( $scheduled_time, self::AS_HOOK, $args, self::AS_GROUP );
			lwtv_plugin()->debug_log( 'postiz', 'Scheduled OTD post for ' . $post_title . ' (#' . $post_id . ' ' . $content . ') via Action Scheduler for ' . self::DELAY_SECONDS . ' seconds from now' );
		} else {
			wp_schedule_single_event( $scheduled_time, self::WP_CRON_HOOK, $args );
			lwtv_plugin()->debug_log( 'postiz', 'Scheduled OTD post for ' . $post_title . ' (#' . $post_id . ' ' . $content . ') via WP cron for ' . self::DELAY_SECONDS . ' seconds from now' );
		}
	}

	/**
	 * Process the scheduled OTD post
	 *
	 * This is the callback for both Action Scheduler and WP cron hooks.
	 * Contains the actual logic to check and post to Postiz.
	 *
	 * @param string $type    Type of OTD (character, show)
	 * @param string $content The content to post
	 * @param int    $post_id The post ID
	 * @param array  $data    Additional data about the OTD
	 */
	public function process_scheduled_otd( $type, $content, $post_id, $data ) {
		lwtv_plugin()->debug_log( 'postiz', 'Processing scheduled OTD post: ' . wp_json_encode( $data ) );

		// Check if the OTD already exists in Postiz
		$exists = parent::post_exists( $content, $post_id );
		if ( $exists ) {
			parent::log_otd_message( 'OTD already exists in Postiz in at least one channel. Skipping', $type, $content, $post_id, $data );
			return;
		}

		try {
			lwtv_plugin()->debug_log( 'postiz', 'Posting OTD to Postiz' );
			$result = $this->post_of_the_day( $type, $content, $post_id );
			lwtv_plugin()->debug_log( 'postiz', 'Result of posting OTD to Postiz: ' . wp_json_encode( $result ) );

			// Log errors if any
			if ( is_wp_error( $result ) ) {
				lwtv_plugin()->debug_log(
					'postiz',
					sprintf(
						'Failed to post OTD to Postiz: %s',
						$result->get_error_message()
					)
				);
				return;
			}

			// Update the last Postiz post date for the post
			update_post_meta( $post_id, 'lwtv_last_postiz_post', time() );
		} catch ( \Throwable $th ) {
			lwtv_plugin()->error_log( 'postiz', 'Error posting OTD to Postiz: ' . $th->getMessage() );
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'postiz', 'Error posting OTD to Postiz: ' . $e->getMessage() );
		}
	}

	/**
	 * Post "Of The Day" content to Postiz
	 *
	 * @param string $type    Type of OTD (character, show)
	 * @param string $content The content to post
	 * @param int    $post_id The post ID
	 * @return array|WP_Error Response array or WP_Error on failure
	 */
	public function post_of_the_day( $type, $content, $post_id ) {

		// Get Images and Tags
		$images = parent::get_images( $post_id );
		$tags   = parent::get_tags( 'otd', $post_id, $type );

		// Options for the post
		$options = array(
			'group'     => 'otd_' . $type . '_' . gmdate( 'Y-m-d' ),
			'image'     => $images,
			'tags'      => $tags,
			'shortLink' => false,
		);

		// Create the post
		return parent::create_post( $content, $options );
	}

	/**
	 * Create a tag for the OTD
	 *
	 * @param string $type The type of OTD (character, show)
	 * @return array The tag array with 'value' and 'label' keys
	 */
	public function create_tag( $type ) {
		switch ( $type ) {
			case 'character':
				return array(
					'value' => '#LWTVcotd',
					'label' => '#LWTVcotd',
				);
			case 'show':
				return array(
					'value' => '#LWTVsotd',
					'label' => '#LWTVsotd',
				);
			default:
				return array();
		}
	}
}
