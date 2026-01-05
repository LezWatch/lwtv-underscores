<?php
/**
 * TMDB Batch Task Handler
 *
 * Handles batched TMDB API calls to avoid rate limiting
 * Processes multiple posts in batches with proper delays
 *
 * @package lwtv-plugin
 */

namespace LWTV\Schedulers;

use LWTV\_Components\CPTs;

/**
 * Class TMDB_Batch_Task
 */
class TMDB_Batch_Task {

	/**
	 * Action Scheduler hook name
	 */
	const AS_HOOK = 'lwtv_tmdb_batch_task';

	/**
	 * Action Scheduler group name
	 */
	const AS_GROUP = 'lwtv';

	/**
	 * TMDB API rate limits
	 */
	const RATE_LIMIT_REQUESTS = 40;
	const RATE_LIMIT_WINDOW   = 10; // seconds

	/**
	 * Batch processing limits (optimized for 4-core, 4GB server)
	 */
	const BATCH_SIZE            = 30;
	const DELAY_BETWEEN_BATCHES = 2; // seconds

	/**
	 * Constructor
	 */
	public function __construct() {
		// Register Action Scheduler hook
		add_action( self::AS_HOOK, array( $this, 'process_tmdb_batch' ) );
	}

	/**
	 * Queue a post for TMDB processing
	 *
	 * @param int $post_id The post ID to process
	 * @return bool Whether the post was queued successfully
	 */
	public function queue_post( int $post_id ): bool {
		$post_type = get_post_type( $post_id );

		if ( ! $post_type ) {
			lwtv_plugin()->debug_log( 'tmdb', "Invalid post ID: {$post_id}" );
			return false;
		}

		// Check if already has TMDB ID
		if ( $this->has_tmdb_id( $post_id, $post_type ) ) {
			lwtv_plugin()->debug_log( 'tmdb', "Post {$post_id} already has TMDB ID" );
			return false;
		}

		// Add to queue
		$queued_posts   = $this->get_queued_posts();
		$queued_posts[] = array(
			'post_id'   => $post_id,
			'post_type' => $post_type,
			'queued_at' => time(),
		);

		$this->set_queued_posts( $queued_posts );

		// Schedule batch processing if not already scheduled
		if ( ! as_next_scheduled_action( self::AS_HOOK ) ) {
			as_schedule_single_action( time() + 30, self::AS_HOOK, array(), self::AS_GROUP );
			lwtv_plugin()->debug_log( 'tmdb', 'Scheduled TMDB batch processing' );
		}

		lwtv_plugin()->debug_log( 'tmdb', "Queued post {$post_id} for TMDB processing" );
		return true;
	}

	/**
	 * Process TMDB batch (Action Scheduler handler)
	 *
	 * @return void
	 */
	public function process_tmdb_batch(): void {
		$queued_posts = $this->get_queued_posts();

		if ( empty( $queued_posts ) ) {
			lwtv_plugin()->debug_log( 'tmdb', 'No posts queued for TMDB processing' );
			return;
		}

		lwtv_plugin()->debug_log( 'tmdb', 'Processing ' . count( $queued_posts ) . ' posts for TMDB data' );

		// Process in batches
		$batches         = array_chunk( $queued_posts, self::BATCH_SIZE );
		$processed_count = 0;
		$success_count   = 0;

		foreach ( $batches as $batch_index => $batch ) {
			lwtv_plugin()->debug_log( 'tmdb', 'Processing batch ' . ( $batch_index + 1 ) . ' of ' . count( $batches ) );

			$batch_results    = $this->process_batch( $batch );
			$processed_count += count( $batch );
			$success_count   += $batch_results['success'];

			// Rate limiting delay between batches
			if ( $batch_index < count( $batches ) - 1 ) {
				sleep( self::DELAY_BETWEEN_BATCHES );
			}
		}

		// Clear the queue
		$this->set_queued_posts( array() );

		lwtv_plugin()->debug_log( 'tmdb', "Completed TMDB batch processing: {$success_count}/{$processed_count} successful" );

		// Schedule next batch if there are more posts in the queue
		$remaining_posts = $this->get_queued_posts();
		if ( ! empty( $remaining_posts ) ) {
			as_schedule_single_action( time() + 60, self::AS_HOOK, array(), self::AS_GROUP );
		}
	}

	/**
	 * Process a batch of posts
	 *
	 * @param array $batch Array of post data
	 * @return array Results with success count
	 */
	private function process_batch( array $batch ): array {
		$success_count  = 0;
		$rate_limit_hit = false;

		foreach ( $batch as $post_data ) {
			// Check rate limiting
			if ( $this->is_rate_limited() ) {
				lwtv_plugin()->debug_log( 'tmdb', 'Rate limit hit, pausing batch processing' );
				$rate_limit_hit = true;
				break;
			}

			$result = $this->process_single_post( $post_data );
			if ( $result ) {
				++$success_count;
			}

			// Small delay between individual requests
			usleep( 250000 ); // 0.25 seconds
		}

		// If rate limited, reschedule remaining posts
		if ( $rate_limit_hit ) {
			$remaining_batch = array_slice( $batch, $success_count );
			$queued_posts    = $this->get_queued_posts();
			$this->set_queued_posts( array_merge( $queued_posts, $remaining_batch ) );

			// Reschedule with exponential backoff
			$backoff_delay = min( 300, pow( 2, $this->get_rate_limit_count() ) );
			as_schedule_single_action( time() + $backoff_delay, self::AS_HOOK, array(), self::AS_GROUP );
		}

		return array(
			'success'      => $success_count,
			'rate_limited' => $rate_limit_hit,
		);
	}

	/**
	 * Process a single post
	 *
	 * @param array $post_data Post data array
	 * @return bool Whether processing was successful
	 */
	private function process_single_post( array $post_data ): bool {
		$post_id   = $post_data['post_id'];
		$post_type = $post_data['post_type'];

		try {
			// Get TMDB data
			$tmdb_data = ( new CPTs() )->get_tmdb_info( $post_id );

			if ( ! $tmdb_data ) {
				lwtv_plugin()->debug_log( 'tmdb', "No TMDB data found for post {$post_id}" );
				return false;
			}

			// Extract and save TMDB ID
			$tmdb_id = $this->extract_tmdb_id( $tmdb_data, $post_type );

			if ( $tmdb_id ) {
				$this->save_tmdb_id( $post_id, $post_type, $tmdb_id );
				lwtv_plugin()->debug_log( 'tmdb', "Successfully saved TMDB ID {$tmdb_id} for post {$post_id}" );
				return true;
			} else {
				lwtv_plugin()->debug_log( 'tmdb', "No TMDB ID found in data for post {$post_id}" );
				return false;
			}
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'tmdb', "Error processing post {$post_id}: " . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Check if TMDB API is rate limited
	 *
	 * @return bool
	 */
	private function is_rate_limited(): bool {
		$request_count = $this->get_request_count();
		$window_start  = $this->get_rate_limit_window_start();

		// Reset counter if window has passed
		if ( time() - $window_start > self::RATE_LIMIT_WINDOW ) {
			$this->reset_rate_limit_counter();
			return false;
		}

		// Check if we've hit the limit
		if ( $request_count >= self::RATE_LIMIT_REQUESTS ) {
			$this->increment_rate_limit_count();
			return true;
		}

		// Increment request counter
		$this->increment_request_count();
		return false;
	}

	/**
	 * Get queued posts from transient
	 *
	 * @return array
	 */
	private function get_queued_posts(): array {
		$queued = lwtv_plugin()->get_transient( 'lwtv_tmdb_batch_queue' );
		return is_array( $queued ) ? $queued : array();
	}

	/**
	 * Set queued posts in transient
	 *
	 * @param array $posts
	 * @return void
	 */
	private function set_queued_posts( array $posts ): void {
		lwtv_plugin()->set_transient( 'lwtv_tmdb_batch_queue', $posts, HOUR_IN_SECONDS );
	}

	/**
	 * Check if post already has TMDB ID
	 *
	 * @param int    $post_id   The post ID
	 * @param string $post_type The post type
	 * @return bool
	 */
	private function has_tmdb_id( int $post_id, string $post_type ): bool {
		$meta_key = match ( $post_type ) {
			'post_type_actors' => 'lezactors_tmdb_id',
			'post_type_shows'  => 'lezshows_tmdb_id',
			default            => false,
		};

		if ( ! $meta_key ) {
			return false;
		}

		$tmdb_id = get_post_meta( $post_id, $meta_key, true );
		return ! empty( $tmdb_id );
	}

	/**
	 * Extract TMDB ID from API response
	 *
	 * @param array  $tmdb_data The TMDB API response
	 * @param string $post_type The post type
	 * @return string|false The TMDB ID or false
	 */
	private function extract_tmdb_id( array $tmdb_data, string $post_type ) {
		return match ( $post_type ) {
			'post_type_actors' => $this->extract_actor_tmdb_id( $tmdb_data ),
			'post_type_shows'  => $this->extract_show_tmdb_id( $tmdb_data ),
			default            => false,
		};
	}

	/**
	 * Extract TMDB ID for actors
	 *
	 * @param array $tmdb_data The TMDB API response
	 * @return string|false The TMDB ID or false
	 */
	private function extract_actor_tmdb_id( array $tmdb_data ) {
		if ( isset( $tmdb_data['id'] ) ) {
			return $tmdb_data['id'];
		}

		if ( isset( $tmdb_data['person_results'][0]['id'] ) ) {
			return $tmdb_data['person_results'][0]['id'];
		}

		return false;
	}

	/**
	 * Extract TMDB ID for shows
	 *
	 * @param array $tmdb_data The TMDB API response
	 * @return string|false The TMDB ID or false
	 */
	private function extract_show_tmdb_id( array $tmdb_data ) {
		if ( isset( $tmdb_data['id'] ) ) {
			return $tmdb_data['id'];
		}

		if ( isset( $tmdb_data['tv_results'][0]['id'] ) ) {
			return $tmdb_data['tv_results'][0]['id'];
		}

		return false;
	}

	/**
	 * Save TMDB ID to post meta
	 *
	 * @param int    $post_id   The post ID
	 * @param string $post_type The post type
	 * @param string $tmdb_id   The TMDB ID to save
	 * @return void
	 */
	private function save_tmdb_id( int $post_id, string $post_type, string $tmdb_id ): void {
		$meta_key = match ( $post_type ) {
			'post_type_actors' => 'lezactors_tmdb_id',
			'post_type_shows'  => 'lezshows_tmdb_id',
			default            => false,
		};

		if ( $meta_key ) {
			update_post_meta( $post_id, $meta_key, $tmdb_id );
		}
	}

	/**
	 * Get current request count
	 *
	 * @return int
	 */
	private function get_request_count(): int {
		return (int) lwtv_plugin()->get_transient( 'lwtv_tmdb_request_count' );
	}

	/**
	 * Increment request count
	 *
	 * @return void
	 */
	private function increment_request_count(): void {
		$count = $this->get_request_count();
		lwtv_plugin()->set_transient( 'lwtv_tmdb_request_count', $count + 1, self::RATE_LIMIT_WINDOW );
	}

	/**
	 * Get rate limit window start time
	 *
	 * @return int
	 */
	private function get_rate_limit_window_start(): int {
		return (int) lwtv_plugin()->get_transient( 'lwtv_tmdb_window_start' );
	}

	/**
	 * Reset rate limit counter
	 *
	 * @return void
	 */
	private function reset_rate_limit_counter(): void {
		lwtv_plugin()->set_transient( 'lwtv_tmdb_request_count', 0, self::RATE_LIMIT_WINDOW );
		lwtv_plugin()->set_transient( 'lwtv_tmdb_window_start', time(), self::RATE_LIMIT_WINDOW );
	}

	/**
	 * Get rate limit hit count
	 *
	 * @return int
	 */
	private function get_rate_limit_count(): int {
		return (int) lwtv_plugin()->get_transient( 'lwtv_tmdb_rate_limit_count' );
	}

	/**
	 * Increment rate limit hit count
	 *
	 * @return void
	 */
	private function increment_rate_limit_count(): void {
		$count = $this->get_rate_limit_count();
		lwtv_plugin()->set_transient( 'lwtv_tmdb_rate_limit_count', $count + 1, HOUR_IN_SECONDS );
	}

	/**
	 * Get batch processing status
	 *
	 * @return array
	 */
	public function get_batch_status(): array {
		$queued_posts   = $this->get_queued_posts();
		$next_scheduled = as_next_scheduled_action( self::AS_HOOK );

		return array(
			'queued_posts_count'      => count( $queued_posts ),
			'next_scheduled'          => $next_scheduled,
			'rate_limit_window_start' => $this->get_rate_limit_window_start(),
			'current_request_count'   => $this->get_request_count(),
			'rate_limit_hits'         => $this->get_rate_limit_count(),
		);
	}
}
