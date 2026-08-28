<?php
/*
 * WP CLI Commands for LezWatch.TV
 *
 * These commands are 'calculation' tools.
 */

// Bail if directly accessed
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	die();
}

use LWTV\CPTs\Actors as CPT_Actors;
use LWTV\CPTs\Actors\Calculations as Actors_Calculations;
use LWTV\CPTs\Characters as CPT_Characters;
use LWTV\CPTs\Characters\Calculations as Characters_Calculations;
use LWTV\CPTs\Shows as CPT_Shows;
use LWTV\CPTs\Shows\Calculations as Shows_Calculations;
use LWTV\CPTs\Shows\Character_Score;
use LWTV\Theme\Show_Characters;

/**
 * LezWatch.TV commands to calculate data.
 */
class WP_CLI_LWTV_Calculate {

	/**
	 * @var string
	 */
	public $format;

	/**
	 * @var int
	 */
	public $post_id;

	/**
	 * Construct to obviate facet from munging results.
	 */
	public function __construct() {
		// phpcs:disable
		// Remove <!--fwp-loop--> from output
		add_filter( 'facetwp_is_main_query', function( $is_main_query, $query ) {
			return false;
		}, 10, 2 );
		// phpcs:enable
	}

	/**
	 * How many posts to process between memory resets.
	 */
	public const BATCH = 50;

	/**
	 * Post types the sweep can work on, in the order it processes them.
	 */
	public const SWEEP_TYPES = array(
		'shows'      => CPT_Shows::SLUG,
		'characters' => CPT_Characters::SLUG,
		'actors'     => CPT_Actors::SLUG,
	);

	/**
	 * Re-run calculations for scores and character counts.
	 *
	 * ## OPTIONS
	 *
	 * [<post_id>]
	 * : Post ID to calculate. Omit and pass --all to sweep every post.
	 *
	 * [--all]
	 * : Recalculate every published post of the chosen type. This is a data
	 *   migration, not routine maintenance -- normally the on-save hooks and the
	 *   scheduler keep everything current. Use it after a change to the scoring
	 *   itself, which nothing else will pick up: scores live in post meta, so a
	 *   change to how they are calculated does nothing until they are rewritten.
	 *
	 * [--post-type=<type>]
	 * : Which post type to sweep.
	 * ---
	 * default: shows
	 * options:
	 *   - shows
	 *   - characters
	 *   - actors
	 *   - all
	 * ---
	 *
	 * [--dry-run]
	 * : Report what would be recalculated, and the flag state it would run under.
	 *   Writes nothing.
	 *
	 * [--offset=<n>]
	 * : Skip the first N posts. For resuming an interrupted sweep.
	 *
	 * [--limit=<n>]
	 * : Stop after N posts. 0 for no limit.
	 * ---
	 * default: 0
	 * ---
	 *
	 * [--with-third-party]
	 * : Also refresh TMDB/TVMaze scores. OFF by default, and leave it off unless
	 *   you specifically want them: on a transient miss each show makes a live API
	 *   request, so a full sweep is thousands of unthrottled calls, far past
	 *   TVMaze's allowance. A change to our scoring does not affect theirs.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     # One post.
	 *     $ wp lwtv calc 1234
	 *
	 *     # What would a full show sweep do, and under which flags?
	 *     $ wp lwtv calc --all --dry-run
	 *
	 *     # Seed the corpus after a scoring change.
	 *     $ wp lwtv calc --all
	 *
	 *     # Resume after an interruption at roughly show 1800.
	 *     $ wp lwtv calc --all --offset=1800
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( $args, $assoc_args = array() ) {

		$this->format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		if ( (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false ) ) {
			$this->run_sweep( $assoc_args );
			return;
		}

		if ( empty( $args[0] ) ) {
			\WP_CLI::error( 'Pass a post ID, or --all. Try: wp lwtv calc --all --dry-run' );
		}

		$this->post_id = $args[0];

		try {
			$this->run_calculations( $this->post_id );
		} catch ( Exception $exception ) {
			\WP_CLI::error( $exception->getMessage(), false );
		}
	}

	/**
	 * Recalculate every published post of one or more types.
	 *
	 * @param array $assoc_args Flags.
	 */
	private function run_sweep( array $assoc_args ): void {
		$requested   = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'post-type', 'shows' );
		$dry_run     = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$third_party = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'with-third-party', false );
		$offset      = max( 0, (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'offset', 0 ) );
		$limit       = max( 0, (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', 0 ) );

		$types = ( 'all' === $requested )
			? self::SWEEP_TYPES
			: array( $requested => self::SWEEP_TYPES[ $requested ] ?? '' );

		if ( in_array( '', $types, true ) ) {
			\WP_CLI::error( 'Invalid --post-type. Use: shows, characters, actors, all' );
		}

		$queued = array();
		foreach ( $types as $label => $slug ) {
			$ids = get_posts(
				array(
					'post_type'        => $slug,
					'post_status'      => 'publish',
					'posts_per_page'   => ( $limit > 0 ) ? $limit : -1,
					'offset'           => $offset,
					'fields'           => 'ids',
					'orderby'          => 'ID',
					'order'            => 'ASC',
					'suppress_filters' => true,
				)
			);

			$queued[ $label ] = is_array( $ids ) ? $ids : array();
			\WP_CLI::log( sprintf( '%-11s %6d posts', $label, count( $queued[ $label ] ) ) );
		}

		$total = array_sum( array_map( 'count', $queued ) );

		if ( 0 === $total ) {
			\WP_CLI::error( 'Nothing to recalculate.' );
		}

		\WP_CLI::log( '' );
		\WP_CLI::log( 'Third-party (TMDB/TVMaze) refresh: ' . ( $third_party ? 'ON -- expect API calls per show' : 'off' ) );

		if ( isset( $queued['characters'] ) && ! empty( $queued['characters'] ) && isset( $queued['shows'] ) ) {
			\WP_CLI::warning( 'Characters and shows are both queued. Recalculating a character also recalculates every show it belongs to, so the shows will be done more than once. Sweeping shows alone is usually what you want.' );
		}

		if ( $dry_run ) {
			\WP_CLI::log( '' );
			\WP_CLI::success( sprintf( 'Dry run: %d posts would be recalculated. Nothing was written.', $total ) );
			return;
		}

		\WP_CLI::log( '' );
		\WP_CLI::confirm( sprintf( 'Recalculate and overwrite meta for %d posts?', $total ), $assoc_args );

		if ( ! $third_party ) {
			add_filter( 'lwtv_recalculate_third_party_scores', '__return_false' );
		}

		$started  = microtime( true );
		$done     = 0;
		$failed   = array();
		$progress = \WP_CLI\Utils\make_progress_bar( 'Recalculating ' . $total . ' posts', $total );

		foreach ( $queued as $label => $ids ) {
			foreach ( $ids as $post_id ) {
				try {
					$this->calculate_quietly( (int) $post_id, $label );
				} catch ( \Throwable $error ) {
					// One bad post must not end a two-thousand-post migration.
					// Collected and reported at the end so the failures are
					// visible rather than scrolling past inside a progress bar.
					$failed[ (int) $post_id ] = $error->getMessage();
				}

				++$done;

				if ( 0 === $done % self::BATCH ) {
					$this->free_memory();
				}

				$progress->tick();
			}
		}

		$progress->finish();
		$this->free_memory();

		$elapsed = microtime( true ) - $started;
		\WP_CLI::log(
			sprintf(
				'Done in %dm %ds (%.1f posts/sec).',
				(int) floor( $elapsed / 60 ),
				(int) fmod( $elapsed, 60 ),
				$done / max( 0.001, $elapsed )
			)
		);

		if ( ! empty( $failed ) ) {
			\WP_CLI::warning( count( $failed ) . ' posts failed:' );
			foreach ( $failed as $post_id => $message ) {
				\WP_CLI::log( '  #' . $post_id . ' ' . $message );
			}
		}

		\WP_CLI::success( sprintf( '%d recalculated, %d failed.', $done - count( $failed ), count( $failed ) ) );

		if ( ! $third_party ) {
			\WP_CLI::log( 'Third-party scores were left alone. The daily cron and the on-save hooks will refresh them.' );
		}
	}

	/**
	 * Run one post's calculation without the per-post success line.
	 *
	 * run_calculations() prints a summary per post, which is right for a single
	 * post and unreadable 2,000 times underneath a progress bar.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $label   shows, characters or actors.
	 */
	private function calculate_quietly( int $post_id, string $label ): void {
		switch ( $label ) {
			case 'shows':
				( new Shows_Calculations() )->do_the_math( $post_id );
				break;
			case 'characters':
				( new Characters_Calculations() )->do_the_math( $post_id, true );
				break;
			case 'actors':
				( new Actors_Calculations() )->do_the_math( $post_id );
				break;
		}
	}

	/**
	 * Drop the in-process caches that grow across a long sweep.
	 *
	 * Deliberately NOT wp_cache_flush(): with a persistent object cache that
	 * would empty the whole site's cache, taking the front end down with it for
	 * the sake of a maintenance script. This clears only this process's local
	 * copy, so a persistent backend is untouched and simply gets re-read.
	 */
	private function free_memory(): void {
		global $wpdb, $wp_object_cache;

		// Our own per-show memos. Both are flushed for a single show at the top of
		// that show's do_the_math(), which is correct for one calculation and means
		// nothing is ever evicted across a sweep -- every show's entry accumulates
		// for the length of the run. Clearing them wholesale is safe precisely
		// because each show re-flushes its own key before using it.
		Shows_Calculations::flush_counts();
		Show_Characters::flush_cache();

		// Only populated when SAVEQUERIES is on, but it grows without bound when
		// it is, and a debug-enabled sweep is exactly when memory runs out.
		$wpdb->queries = array();

		if ( ! is_object( $wp_object_cache ) ) {
			return;
		}

		foreach ( array( 'group_ops', 'stats', 'memcache_debug', 'cache' ) as $property ) {
			if ( property_exists( $wp_object_cache, $property ) ) {
				$wp_object_cache->$property = array();
			}
		}

		// Redis/Memcached drop-ins expose this to re-establish their connection
		// after the local cache is dropped.
		if ( method_exists( $wp_object_cache, '__remoteset' ) ) {
			$wp_object_cache->__remoteset();
		}
	}

	/**
	 * Re-run calculations
	 *
	 * @param int    $post_id    Post ID.
	 */
	public function run_calculations( $post_id ) {

		// Bail ASAP if the post ID is invalid.
		if ( false === get_post_status( $post_id ) ) {
			\WP_CLI::error( $post_id . ' is not a valid post.' );
		}

		$valid_types = array( 'actor', 'character', 'show' );
		$post_type   = rtrim( str_replace( 'post_type_', '', get_post_type( $post_id ) ), 's' );

		// Last sanity check: Is the post ID a member of THIS post type...
		if ( ! in_array( $post_type, $valid_types, true ) ) {
			$display_types = implode( ' or ', $valid_types );
			if ( 3 >= count( $valid_types ) ) {
				$last          = array_pop( $valid_types );
				$display_types = implode( ', ', $valid_types ) . ' or ' . $last;
			}
			\WP_CLI::error( 'You can only run calculations on ' . $display_types . ' post types, but ' . get_the_title( $post_id ) . ' (#' . $post_id . ') is a ' . $post_type . '.' );
		}

		$score = '';

		// Switch to run the commands since they're different.
		switch ( $post_type ) {
			case 'actor':
				// Recount characters and flag queerness
				delete_post_meta( $post_id, 'lezactors_char_count' );
				delete_post_meta( $post_id, 'lezactors_dead_count' );
				( new Actors_Calculations() )->do_the_math( $post_id );
				$queer = ( get_post_meta( $post_id, 'lezactors_queer', true ) ) ? 'Yes' : 'No';
				$chars = get_post_meta( $post_id, 'lezactors_char_count', true );
				$deads = get_post_meta( $post_id, 'lezactors_dead_count', true );
				$score = 'Is Queer (' . $queer . ') Chars (' . $chars . ') Dead (' . $deads . ')';
				break;
			case 'character':
				( new Characters_Calculations() )->do_the_math( $post_id, true );
				$last_death = get_post_meta( $post_id, 'lezchars_last_death', true );
				$score      = 'Last Death (' . ( $last_death ?: 'none' ) . ')';
				break;
			case 'show':
				delete_post_meta( $post_id, 'lezshows_char_count' );
				delete_post_meta( $post_id, 'lezshows_dead_count' );
				( new Shows_Calculations() )->do_the_math( $post_id );
				$chars = get_post_meta( $post_id, 'lezshows_char_count', true );
				$dead  = get_post_meta( $post_id, 'lezshows_dead_count', true );
				$score = 'Score (' . get_post_meta( $post_id, 'lezshows_the_score', true ) . ') Chars (' . $chars . ') Dead (' . $dead . ')';
				break;
		}

		\WP_CLI::success( 'Calculations run for ' . get_the_title( $post_id ) . ': ' . $score );
	}
}

\WP_CLI::add_command( 'lwtv calc', 'WP_CLI_LWTV_Calculate' );
