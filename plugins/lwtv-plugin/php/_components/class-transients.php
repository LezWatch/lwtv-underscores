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

	/**
	 * Option name for the stats-cache index: a map of stats transient key =>
	 * unix time it was last built. Because a persistent object cache (e.g.
	 * Redis) stores transients OUTSIDE wp_options, this index is what lets us
	 * both (a) evict stats transients by pattern through the object-cache-aware
	 * delete_transient(), and (b) report an honest "last calculated" time on
	 * the statistics pages.
	 *
	 * @var string
	 */
	const STATS_INDEX_OPTION = 'lwtv_stats_cache_index';

	/**
	 * Action Scheduler hook + group for the debounced statistics warm.
	 */
	const WARM_HOOK  = 'lwtv_warm_statistics_cache';
	const WARM_GROUP = 'lwtv';

	/**
	 * Option holding the hard deadline (unix time) for the in-progress warm
	 * burst. 0/absent means no burst is currently open.
	 *
	 * @var string
	 */
	const WARM_DEADLINE_OPTION = 'lwtv_stats_warm_deadline';

	/**
	 * Trailing debounce: fire the warm this long after the LAST edit in a burst.
	 */
	const WARM_DEBOUNCE_DELAY = 2 * MINUTE_IN_SECONDS;

	/**
	 * Hard cap: never defer the warm more than this past the FIRST edit in a burst.
	 */
	const WARM_MAX_DELAY = 10 * MINUTE_IN_SECONDS;

	/**
	 * Memoised, flattened list of stats key patterns tracked in the index.
	 *
	 * @var array|null
	 */
	private static $tracked_patterns_cache = null;

	/**
	 * In-request buffer of stats keys that were (re)built this request, written
	 * to the index once on shutdown to avoid an option write per transient.
	 *
	 * @var array
	 */
	private static $index_buffer = array();

	/**
	 * Whether the index-flush shutdown hook has been registered.
	 *
	 * @var bool
	 */
	private static $index_shutdown_registered = false;

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
			'delete_transient'             => array( $this, 'delete_transient' ),
			'get_transient'                => array( $this, 'get_transient' ),
			'set_transient'                => array( $this, 'set_transient' ),
			'invalidate_statistics_cache'  => array( $this, 'invalidate_statistics_cache' ),
			'get_cache_dependencies'       => array( $this, 'get_cache_dependencies' ),
			'get_cache_statistics'         => array( $this, 'get_cache_statistics' ),
			'get_this_year_generated_time' => array( $this, 'get_this_year_generated_time' ),
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

		// Record stats transients in the index so we can evict them from a
		// persistent object cache and report when they were last built.
		if ( self::is_tracked_stats_key( (string) $transient ) ) {
			self::record_stats_key( (string) $transient );
		}
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
					'bulk_first_years_*',
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
					'we_love_*',
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

		$dependencies      = $this->get_cache_dependencies();
		$patterns_to_clear = array();

		foreach ( self::$invalidation_queue as $request ) {
			foreach ( $this->get_tiers_for_content_type( $request['content_type'] ) as $tier ) {
				if ( ! isset( $dependencies[ $tier ] ) ) {
					continue;
				}

				$tier_config = $dependencies[ $tier ];

				// 'preserve' tiers are intentionally never cleared on save.
				if ( 'preserve' === $tier_config['priority'] ) {
					continue;
				}

				foreach ( $tier_config['patterns'] as $pattern ) {
					$patterns_to_clear[ $pattern ] = true;
				}
			}
		}

		// Clear all affected patterns in one batch.
		if ( ! empty( $patterns_to_clear ) ) {
			$this->clear_cache_tier( array_keys( $patterns_to_clear ) );
		}

		// Schedule ONE debounced, comprehensive warm. A burst of edits reschedules
		// the same job forward, so the whole burst warms the final state once.
		$this->schedule_stats_warm();

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

		// Object-cache-aware pass. With a persistent object cache (Redis) the
		// transients live outside wp_options, so the raw SQL below never
		// reaches them. Walk the index instead and delete each known key via
		// delete_transient(), which routes to the object cache when one is
		// active and to the DB otherwise.
		$index   = self::current_stats_index();
		$changed = false;

		foreach ( $patterns as $pattern ) {
			foreach ( array_keys( $index ) as $key ) {
				if ( self::key_matches_pattern( $key, $pattern ) ) {
					delete_transient( $key );
					unset( $index[ $key ], self::$index_buffer[ $key ] );
					$changed = true;
				}
			}

			// Fallback for DB-stored transients (no persistent object cache), and
			// to sweep any keys that predate the index. Under a persistent object
			// cache (Redis) transients live outside wp_options, so this SQL pass
			// matches nothing — skip it entirely to avoid a needless DELETE storm
			// on every content edit.
			if ( ! wp_using_ext_object_cache() ) {
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

		if ( $changed ) {
			update_option( self::STATS_INDEX_OPTION, $index, false );
		}
	}

	/**
	 * Schedule (or reschedule) the single debounced statistics warm.
	 *
	 * A burst of edits collapses into one job: each call unschedules the pending
	 * warm and reschedules it to next_stats_warm_time(), which trails the last
	 * edit by WARM_DEBOUNCE_DELAY but never past first_edit + WARM_MAX_DELAY.
	 * No-ops when Action Scheduler is unavailable — the daily cron backstop and
	 * lazy rebuild still keep pages correct.
	 *
	 * @return void
	 */
	private function schedule_stats_warm(): void {
		if ( ! lwtv_plugin()->is_action_scheduler_available() ) {
			return;
		}

		$deadline = (int) get_option( self::WARM_DEADLINE_OPTION, 0 );
		$next     = self::next_stats_warm_time( time(), $deadline, self::WARM_DEBOUNCE_DELAY, self::WARM_MAX_DELAY );

		// Reschedule the single pending warm forward to the new target.
		as_unschedule_all_actions( self::WARM_HOOK, array(), self::WARM_GROUP );
		as_schedule_single_action( $next['target'], self::WARM_HOOK, array(), self::WARM_GROUP );

		update_option( self::WARM_DEADLINE_OPTION, $next['deadline'], false );

		lwtv_plugin()->debug_log( 'caching', 'Scheduled debounced statistics warm for ' . $next['target'] );
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

	/**
	 * Get the time the /this-year/ caches for a given year were last built.
	 *
	 * Reads the stats-cache index for that year's per-year keys
	 * (lwtv_*_year_*_<year>) and returns the OLDEST build time among them — the
	 * page is only as fresh as its stalest piece. The index alone is
	 * authoritative: the /this-year/ page rebuilds all of its data on every
	 * render, so an indexed key is by definition current when this runs. We
	 * deliberately do NOT probe get_transient() to confirm the value is live —
	 * that wrapper is forced to return false in development
	 * (LWTV_DISABLE_TRANSIENTS), which would suppress the note there for no
	 * real benefit. Read-only: it never writes the option.
	 *
	 * @param  int      $year The calendar year.
	 * @return int|null       Unix timestamp, or null if nothing is indexed yet.
	 */
	public function get_this_year_generated_time( int $year ): ?int {
		$index  = self::current_stats_index();
		$suffix = '_' . $year;
		$oldest = null;

		foreach ( $index as $key => $built ) {
			if ( ! str_starts_with( (string) $key, 'lwtv_' ) || ! str_ends_with( (string) $key, $suffix ) ) {
				continue;
			}

			$oldest = ( null === $oldest ) ? (int) $built : min( $oldest, (int) $built );
		}

		return $oldest;
	}

	/**
	 * Compute when the debounced statistics warm should next fire.
	 *
	 * Pure arithmetic (no WordPress calls) so it is unit-testable. A burst of
	 * edits keeps pushing the target forward by $delay, but never past a hard
	 * deadline of first_edit + $max, so a long editing session still warms.
	 *
	 * @param int $now      Current unix time.
	 * @param int $deadline Existing burst deadline (0/absent = no burst open).
	 * @param int $delay    Trailing debounce delay in seconds.
	 * @param int $max      Max seconds to defer past the first edit in a burst.
	 * @return array { 'target' => int, 'deadline' => int }
	 */
	public static function next_stats_warm_time( int $now, int $deadline, int $delay, int $max ): array {
		if ( $deadline <= 0 ) {
			// No burst in progress: open a new one.
			$deadline = $now + $max;
		}

		$target = min( $now + $delay, $deadline );

		return array(
			'target'   => $target,
			'deadline' => $deadline,
		);
	}

	/**
	 * End the current warm-burst window. Called once the comprehensive warm has
	 * actually run (see Statistics_Cache_Warming::warm_all()).
	 *
	 * @return void
	 */
	public static function clear_stats_warm_deadline(): void {
		delete_option( self::WARM_DEADLINE_OPTION );
	}

	/**
	 * Record that a stats transient was just (re)built. Buffered in-request and
	 * written to the index once on shutdown.
	 *
	 * @param  string $key The transient key.
	 * @return void
	 */
	private static function record_stats_key( string $key ): void {
		self::$index_buffer[ $key ] = time();

		if ( ! self::$index_shutdown_registered ) {
			add_action( 'shutdown', array( __CLASS__, 'flush_stats_index' ), 5 );
			self::$index_shutdown_registered = true;
		}
	}

	/**
	 * Flush the in-request index buffer to the stored option (shutdown hook).
	 *
	 * @return void
	 */
	public static function flush_stats_index(): void {
		if ( empty( self::$index_buffer ) ) {
			return;
		}

		$index = get_option( self::STATS_INDEX_OPTION, array() );
		$index = is_array( $index ) ? $index : array();
		$index = array_merge( $index, self::$index_buffer );

		update_option( self::STATS_INDEX_OPTION, $index, false );

		self::$index_buffer = array();
	}

	/**
	 * The stored stats-cache index merged with anything buffered this request,
	 * so reads see keys built earlier in the same request (e.g. a cold render
	 * that both builds the data and prints the "last calculated" note).
	 *
	 * @return array
	 */
	private static function current_stats_index(): array {
		$index = get_option( self::STATS_INDEX_OPTION, array() );
		$index = is_array( $index ) ? $index : array();

		if ( ! empty( self::$index_buffer ) ) {
			$index = array_merge( $index, self::$index_buffer );
		}

		return $index;
	}

	/**
	 * Whether a transient key belongs to the statistics system (and so should
	 * be tracked in the index).
	 *
	 * @param  string $key The transient key.
	 * @return bool
	 */
	private static function is_tracked_stats_key( string $key ): bool {
		foreach ( self::all_tracked_patterns() as $pattern ) {
			if ( self::key_matches_pattern( $key, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * All tracked stats key patterns: the tiered dependency patterns plus the
	 * /this-year/ module's per-year keys (which live outside the tier map).
	 * Memoised for the request.
	 *
	 * @return array
	 */
	private static function all_tracked_patterns(): array {
		if ( null !== self::$tracked_patterns_cache ) {
			return self::$tracked_patterns_cache;
		}

		$flat = array();
		foreach ( ( new self() )->get_cache_dependencies() as $tier ) {
			foreach ( $tier['patterns'] as $pattern ) {
				$flat[] = $pattern;
			}
		}

		self::$tracked_patterns_cache = array_merge( $flat, self::extra_stats_patterns() );

		return self::$tracked_patterns_cache;
	}

	/**
	 * Stats key patterns tracked for freshness/eviction that live outside the
	 * tiered dependency map — the /this-year/ module's per-year caches.
	 *
	 * @return array
	 */
	private static function extra_stats_patterns(): array {
		return array(
			'lwtv_characters_year_*',
			'lwtv_characters_shows_year_*',
			'lwtv_shows_year_*',
			'lwtv_shows_characters_year_*',
			'lwtv_this_year_trends_*',
			'lwtv_overview_char_stats_year_*',
		);
	}

	/**
	 * Match a transient key against a dependency pattern. Patterns use a single
	 * trailing '*' wildcard (prefix match) or are matched exactly.
	 *
	 * @param  string $key     The transient key.
	 * @param  string $pattern The pattern.
	 * @return bool
	 */
	private static function key_matches_pattern( string $key, string $pattern ): bool {
		if ( str_ends_with( $pattern, '*' ) ) {
			return str_starts_with( $key, substr( $pattern, 0, -1 ) );
		}

		return $key === $pattern;
	}
}
