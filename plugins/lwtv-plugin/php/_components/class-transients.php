<?php
/*
 * Transients
 *
 */
namespace LWTV\_Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Transients implements Component, Templater {

	const CACHE_DURATION = HOUR_IN_SECONDS / 2;

	/**
	 * Queue of cache invalidation requests to process at shutdown.
	 *
	 * @var array
	 */
	private static $invalidation_queue = array();

	/**
	 * Whether the shutdown hook has been registered.
	 *
	 * @var bool
	 */
	private static $shutdown_registered = false;

	/*
	 * Init
	 */
	public function init(): void {
		// Null
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
			'delete_transient'            => array( $this, 'delete_transient' ),
			'get_transient'               => array( $this, 'get_transient' ),
			'set_transient'               => array( $this, 'set_transient' ),
			'invalidate_statistics_cache' => array( $this, 'invalidate_statistics_cache' ),
			'get_cache_dependencies'      => array( $this, 'get_cache_dependencies' ),
			'get_cache_statistics'        => array( $this, 'get_cache_statistics' ),
		);
	}

	/**
	 * Get Transient
	 *
	 * A wrapper to default to false if you're developing.
	 *
	 * @param  string      $transient The Transient name
	 * @return string|bool            Transient value (or false)
	 */
	public static function get_transient( $transient ) {
		if ( defined( 'LWTV_DISABLE_TRANSIENTS' ) && LWTV_DISABLE_TRANSIENTS ) {
			return false;
		}

		return get_transient( $transient );
	}

	/**
	 * Set Transient
	 *
	 * A wrapper to default to false if you're developing.
	 *
	 * @param  string      $transient The Transient name
	 * @return void
	 */
	public static function set_transient( $transient, $value, $expiration = 60 * 60 * 24 ) {
		set_transient( $transient, $value, $expiration );
	}

	/**
	 * Delete Transient
	 *
	 * A wrapper to delete a transient.
	 *
	 * @param  string      $transient The Transient name
	 * @return void
	 */
	public static function delete_transient( $transient ) {
		delete_transient( $transient );
	}

	/**
	 * Cache dependency mapping for statistics
	 *
	 * Maps content types to affected cache patterns
	 *
	 * @return array
	 */
	public function get_cache_dependencies(): array {
		// NOTE: Patterns use a single '*' as the wildcard. clear_cache_tier() and
		// get_cache_statistics() translate '*' -> SQL LIKE '%'; any other regex-style
		// metacharacter (e.g. '.') is treated literally by LIKE and will match nothing.
		return array(
			// Tier 1: Critical Counts (1 hour cache)
			'counts'  => array(
				'patterns' => array(
					'taxonomy_opt_*',
					'taxonomy_comp_*',
					'taxonomy_counts_*',
					'taxonomy_terms_*',
					'batch_taxonomy_*',
					'bulk_char_counts_*',
					'bulk_show_counts_*',
					'actor_chars_*',
					'stats_meta_*',
					'total_shows_count',
					'total_formats',
					'total_dead_shows',
				),
				'priority' => 'immediate',
				'duration' => HOUR_IN_SECONDS,
			),

			// Tier 2: Derived Statistics (24 hour cache)
			'derived' => array(
				'patterns' => array(
					'scores_*',
					'dead_*',
					'show_roles_*',
					'on_air_stats_*',
					'build_characters_on_air_*',
					'build_shows_on_air_*',
					'this_year_*',
					'actor_char_*',
					'complex_taxonomy_*',
					'queer_irl_characters',
					'cliche_leaders_characters_*',
					'worth_it_*',
					'we_love_it_data',
					'shows_we_love_count',
					'nation_*',
					'station_*',
					'top_nations_*',
					'top_stations_*',
				),
				'priority' => 'background',
				'duration' => DAY_IN_SECONDS,
			),

			// Tier 3: Stable Data (7 day cache)
			// Reserved for caches that should survive content edits. The 'preserve'
			// priority is skipped by process_deferred_cache_invalidation(), so any
			// pattern listed here is intentionally never cleared on save_post.
			'stable'  => array(
				'patterns' => array(),
				'priority' => 'preserve',
				'duration' => WEEK_IN_SECONDS,
			),
		);
	}

	/**
	 * Invalidate statistics cache based on content type
	 *
	 * This method queues the invalidation for processing at shutdown to avoid
	 * blocking the save operation with expensive SQL DELETE queries.
	 * Multiple requests are deduplicated automatically.
	 *
	 * @param string $content_type The type of content that changed
	 * @param int    $post_id      The post ID that changed (optional)
	 * @return void
	 */
	public function invalidate_statistics_cache( string $content_type, int $post_id = 0 ): void {
		// Queue the invalidation request for deferred processing.
		$queue_key = $content_type . '_' . $post_id;

		// Avoid duplicate entries for the same content type and post.
		if ( ! isset( self::$invalidation_queue[ $queue_key ] ) ) {
			self::$invalidation_queue[ $queue_key ] = array(
				'content_type' => $content_type,
				'post_id'      => $post_id,
			);
		}

		// Register shutdown hook if not already done.
		if ( ! self::$shutdown_registered ) {
			add_action( 'shutdown', array( $this, 'process_deferred_cache_invalidation' ) );
			self::$shutdown_registered = true;
		}

		lwtv_plugin()->debug_log( 'caching', "Queued cache invalidation for {$content_type} (post ID: {$post_id})" );
	}

	/**
	 * Process deferred cache invalidation at shutdown
	 *
	 * This runs after the response is sent to the user, so it doesn't
	 * affect perceived save performance.
	 *
	 * @return void
	 */
	public function process_deferred_cache_invalidation(): void {
		if ( empty( self::$invalidation_queue ) ) {
			return;
		}

		$dependencies = $this->get_cache_dependencies();

		// Collect all unique patterns to clear across all queued requests.
		$patterns_to_clear = array();
		$warming_requests  = array();

		foreach ( self::$invalidation_queue as $request ) {
			$tiers_to_invalidate = $this->get_tiers_for_content_type( $request['content_type'] );

			foreach ( $tiers_to_invalidate as $tier ) {
				if ( ! isset( $dependencies[ $tier ] ) ) {
					continue;
				}

				$tier_config = $dependencies[ $tier ];

				// Skip 'preserve' tier.
				if ( 'preserve' === $tier_config['priority'] ) {
					continue;
				}

				// Collect patterns to clear (deduplicated).
				foreach ( $tier_config['patterns'] as $pattern ) {
					$patterns_to_clear[ $pattern ] = true;
				}

				// Track warming requests.
				if ( 'immediate' === $tier_config['priority'] ) {
					$warming_requests[] = array(
						'tier'    => $tier,
						'post_id' => $request['post_id'],
					);
				} elseif ( 'background' === $tier_config['priority'] ) {
					$this->schedule_cache_warming( $tier, $request['post_id'] );
				}
			}
		}

		// Clear all patterns in batch.
		if ( ! empty( $patterns_to_clear ) ) {
			$this->clear_cache_tier( array_keys( $patterns_to_clear ) );
		}

		// Warm immediate caches.
		foreach ( $warming_requests as $request ) {
			$this->warm_cache_tier( $request['tier'], $request['post_id'] );
		}

		$count = count( self::$invalidation_queue );
		lwtv_plugin()->debug_log( 'caching', "Processed {$count} deferred cache invalidation requests" );

		// Clear the queue.
		self::$invalidation_queue = array();
	}

	/**
	 * Get tiers to invalidate based on content type
	 *
	 * @param string $content_type
	 * @return array
	 */
	private function get_tiers_for_content_type( string $content_type ): array {
		$tier_mapping = array(
			'post_type_characters' => array( 'counts', 'derived' ),
			'post_type_shows'      => array( 'counts', 'derived' ),
			'post_type_actors'     => array( 'counts', 'derived' ),
			'taxonomy'             => array( 'counts', 'derived', 'stable' ),
			'score'                => array( 'derived' ),
		);

		return $tier_mapping[ $content_type ] ?? array( 'derived' );
	}

	/**
	 * Clear cache tier by pattern matching
	 *
	 * @param array $patterns
	 * @return void
	 */
	private function clear_cache_tier( array $patterns ): void {
		global $wpdb;

		foreach ( $patterns as $pattern ) {
			$sql_pattern = str_replace( '*', '%', $pattern );
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					'_transient_' . $sql_pattern
				)
			);
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					'_transient_timeout_' . $sql_pattern
				)
			);
		}
	}

	/**
	 * Warm cache tier immediately
	 *
	 * @param string $tier
	 * @param int    $post_id
	 * @return void
	 */
	private function warm_cache_tier( string $tier, int $post_id ): void {
		// For now, just log the warming request
		// In a full implementation, this would trigger immediate cache regeneration
		lwtv_plugin()->debug_log( 'caching', "Warming {$tier} cache tier for post ID: {$post_id}" );
	}

	/**
	 * Schedule cache warming for background processing
	 *
	 * @param string $tier
	 * @param int    $post_id
	 * @return void
	 */
	private function schedule_cache_warming( string $tier, int $post_id ): void {
		if ( lwtv_plugin()->is_action_scheduler_available() ) {
			as_schedule_single_action(
				time() + self::CACHE_DURATION,
				'lwtv_warm_statistics_cache',
				array( $tier, $post_id ),
				'lwtv'
			);
		}
	}

	/**
	 * Get cache statistics for monitoring
	 *
	 * @return array
	 */
	public function get_cache_statistics(): array {
		global $wpdb;

		$dependencies = $this->get_cache_dependencies();
		$stats        = array();

		foreach ( $dependencies as $tier => $config ) {
			$tier_stats = array(
				'tier'        => $tier,
				'priority'    => $config['priority'],
				'duration'    => $config['duration'],
				'cache_count' => 0,
				'cache_keys'  => array(),
			);

			foreach ( $config['patterns'] as $pattern ) {
				$sql_pattern = str_replace( '*', '%', $pattern );
				$cache_keys  = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT option_name FROM {$wpdb->options}
						WHERE option_name LIKE %s
						AND option_name NOT LIKE %s",
						'_transient_' . $sql_pattern,
						'%_timeout_%'
					)
				);

				foreach ( $cache_keys as $cache ) {
					$key                        = str_replace( '_transient_', '', $cache->option_name );
					$tier_stats['cache_keys'][] = $key;
					++$tier_stats['cache_count'];
				}
			}

			$stats[ $tier ] = $tier_stats;
		}

		return $stats;
	}
}
