<?php
/*
 * WP CLI Commands for LezWatch.TV Debug Tools
 *
 * These commands are 'debug' tools that mirror the admin validation interface.
 */

// Bail if directly accessed
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	die();
}

use LWTV\Debugger\Actors;
use LWTV\Debugger\Characters;
use LWTV\Debugger\Dupes;
use LWTV\Debugger\Queers;
use LWTV\Debugger\Shows;
use LWTV\Debugger\OnAir;
use LWTV\Debugger\Status;

/**
 * LezWatch.TV commands to debug and validate content.
 */
class WP_CLI_LWTV_Debug {

	/**
	 * @var string
	 */
	public $format;

	/**
	 * @var string
	 */
	public $debug_type;

	/**
	 * @var bool
	 */
	public $fix_it = false;

	/**
	 * @var bool
	 */
	public $force = false;

	/**
	 * Construct to block facet from munging results.
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
	 * The check registry.
	 *
	 * One entry per debug type. Keeps the transient key, the scanner callable,
	 * and the display copy in a single place so the admin views, the cron
	 * rotation, and this command can never drift apart on key names again.
	 *
	 * - transient: the transient constant on the scanner class.
	 * - status:    key inside the debugger status option, for cache-age reporting.
	 * - scanner:   array( class, method ) run to produce fresh findings.
	 * - fixer:     optional array( class, method ) called per item with --fix-it.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_checks(): array {
		return array(
			'queers'     => array(
				'transient' => Queers::TRANSIENT_QUEERCHECK,
				'status'    => 'queercheck',
				'scanner'   => array( Queers::class, 'find_queer_chars' ),
				'running'   => 'Running queer consistency check...',
				'clean'     => 'Excellent! All queer character/actor relationships are consistent.',
				'dirty'     => 'character(s) need attention for queer consistency.',
				'done'      => 'Queer consistency check complete.',
			),
			'dupes'      => array(
				'transient' => Dupes::TRANSIENT_DUPES,
				'status'    => 'duplicates',
				'scanner'   => array( Dupes::class, 'find_duplicates' ),
				'running'   => 'Running duplicate check...',
				'clean'     => 'Excellent! No duplicate actors or shows found.',
				'dirty'     => 'duplicate(s) found.',
				'done'      => 'Duplicate check complete.',
			),
			'byq'        => array(
				'transient' => Characters::TRANSIENT_BYQ,
				'status'    => 'byq_problems',
				'scanner'   => array( Characters::class, 'find_byq_problems' ),
				'running'   => 'Running BYQ (Bury Your Queers) check...',
				'clean'     => 'Excellent! All death data looks good and consistent.',
				'dirty'     => 'character(s) have BYQ-related issues.',
				'done'      => 'BYQ check complete.',
			),
			'actors'     => array(
				'transient' => Actors::TRANSIENT_PROBLEMS,
				'status'    => 'actor_problems',
				'scanner'   => array( Actors::class, 'find_actors_problems' ),
				'running'   => 'Running actor data completeness check...',
				'clean'     => 'Excellent! All actor data is complete and correct.',
				'dirty'     => 'actor(s) need attention.',
				'done'      => 'Actor check complete.',
			),
			'chars'      => array(
				'transient' => Characters::TRANSIENT_PROBLEMS,
				'status'    => 'character_problems',
				'scanner'   => array( Characters::class, 'find_characters_problems' ),
				'running'   => 'Running character data completeness check...',
				'clean'     => 'Excellent! All character data is complete and correct.',
				'dirty'     => 'character(s) need attention.',
				'done'      => 'Character check complete.',
			),
			'shows'      => array(
				'transient' => Shows::TRANSIENT_PROBLEMS,
				'status'    => 'show_problems',
				'scanner'   => array( Shows::class, 'find_shows_problems' ),
				'running'   => 'Running show data completeness check...',
				'clean'     => 'Excellent! All show data is complete and correct.',
				'dirty'     => 'show(s) need attention.',
				'done'      => 'Show check complete.',
			),
			'actor_imdb' => array(
				'transient' => Actors::TRANSIENT_IMDB,
				'status'    => 'actor_imdb',
				'scanner'   => array( Actors::class, 'find_actors_no_imdb' ),
				'running'   => 'Running actor IMDb check...',
				'clean'     => 'Excellent! All actors have IMDb data.',
				'dirty'     => 'actor(s) missing IMDb data.',
				'done'      => 'Actor IMDb check complete.',
			),
			'show_imdb'  => array(
				'transient' => Shows::TRANSIENT_IMDB,
				'status'    => 'show_imdb',
				'scanner'   => array( Shows::class, 'find_shows_no_imdb' ),
				'running'   => 'Running show IMDb check...',
				'clean'     => 'Excellent! All shows have IMDb data.',
				'dirty'     => 'show(s) missing IMDb data.',
				'done'      => 'Show IMDb check complete.',
			),
			'show_urls'  => array(
				'transient' => Shows::TRANSIENT_URL,
				'status'    => 'show_url',
				'scanner'   => array( Shows::class, 'find_shows_bad_url' ),
				'running'   => 'Running show URLs check...',
				'clean'     => 'Excellent! All show URLs are valid.',
				'dirty'     => 'show(s) have URL issues.',
				'done'      => 'Show URLs check complete.',
				'slow'      => true,
			),
			'on_air'     => array(
				'transient' => OnAir::TRANSIENT_PROBLEMS,
				'status'    => 'onair_problems',
				'scanner'   => array( OnAir::class, 'find_on_air_problems' ),
				'fixer'     => array( OnAir::class, 'fix_on_air_status' ),
				'running'   => 'Running on air check...',
				'clean'     => 'All shows have the correct on air status.',
				'dirty'     => 'show(s) have incorrect on-air status. Use --fix-it to attempt to fix these issues.',
				'done'      => 'On air check complete.',
			),
		);
	}

	/**
	 * Debug validation checks for LezWatch.TV content.
	 *
	 * ## OPTIONS
	 *
	 * <debug_type>
	 * : Type of debug check to run. Available types:
	 *   - queers: Check queer character/actor consistency
	 *   - dupes: Find duplicate actors and shows
	 *   - byq: Check BYQ (Bury Your Queers) data consistency
	 *   - actors: Check actor data completeness
	 *   - chars: Check character data completeness
	 *   - shows: Check show data completeness
	 *   - actor_imdb: Find actors without IMDb data
	 *   - show_imdb: Find shows without IMDb data
	 *   - show_urls: Check show URL validity (slow - makes a remote request per URL)
	 *   - on_air: Check on air status of shows
	 *
	 * [--fix-it]
	 * : Attempt to fix issues (not available for all checks).
	 * default: false
	 *
	 * [--force]
	 * : Ignore any cached results and run a fresh scan.
	 * default: false
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 * wp lwtv debug queers
	 * wp lwtv debug byq --format=json
	 * wp lwtv debug actors --force
	 * wp lwtv debug on_air --fix-it
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function __invoke( $args, $assoc_args = array() ) {

		$this->format     = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$this->fix_it     = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'fix-it', false );
		$this->force      = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false );
		$this->debug_type = $args[0] ?? '';

		try {
			$this->run_debug_check( $this->debug_type, $this->fix_it );
		} catch ( \Exception $exception ) {
			// No second argument: this must exit non-zero so cron can tell a
			// crashed check from a clean one.
			\WP_CLI::error( $exception->getMessage() );
		}
	}

	/**
	 * Run the appropriate debug check based on type.
	 *
	 * @param string $debug_type The type of debug check to run.
	 * @param bool   $fix_it     Whether to attempt to fix issues.
	 */
	public function run_debug_check( $debug_type, $fix_it ) {
		$checks = $this->get_checks();

		if ( ! isset( $checks[ $debug_type ] ) ) {
			\WP_CLI::error( 'Invalid debug type. Available types: ' . implode( ', ', array_keys( $checks ) ) );
		}

		$this->run_check( $checks[ $debug_type ], $fix_it );
	}

	/**
	 * Run a single registered check.
	 *
	 * @param array $check  Check definition from get_checks().
	 * @param bool  $fix_it Whether to attempt repairs.
	 *
	 * @return void
	 */
	private function run_check( array $check, bool $fix_it ): void {
		\WP_CLI::log( $check['running'] );

		if ( $fix_it && ! isset( $check['fixer'] ) ) {
			\WP_CLI::warning( '--fix-it is not available for this check; reporting only.' );
		}

		$items      = $this->force ? false : lwtv_plugin()->get_transient( $check['transient'] );
		$from_cache = false !== $items;

		if ( $from_cache ) {
			$this->report_cache_age( $check['status'] );
		} else {
			if ( ! empty( $check['slow'] ) ) {
				\WP_CLI::log( 'Note: this check makes a remote request per URL and can take a long time.' );
			}
			$items = $this->run_scanner( $check['scanner'] );
		}

		if ( empty( $items ) || ! is_array( $items ) ) {
			\WP_CLI::success( $check['clean'] );
			return;
		}

		if ( $fix_it && isset( $check['fixer'] ) ) {
			$this->apply_fixes( $check, $items );
			return;
		}

		\WP_CLI::log( count( $items ) . ' ' . $check['dirty'] );
		\WP_CLI\Utils\format_items( $this->format, $items, array( 'url', 'id', 'problem' ) );
		\WP_CLI::success( $check['done'] );
	}

	/**
	 * Instantiate and call a scanner.
	 *
	 * @param array $scanner array( class, method ).
	 *
	 * @return mixed
	 */
	private function run_scanner( array $scanner ) {
		list( $class, $method ) = $scanner;
		return ( new $class() )->$method();
	}

	/**
	 * Apply the registered fixer to every finding.
	 *
	 * @param array $check Check definition.
	 * @param array $items Findings.
	 *
	 * @return void
	 */
	private function apply_fixes( array $check, array $items ): void {
		list( $class, $method ) = $check['fixer'];

		\WP_CLI::log( 'Attempting to fix issues for ' . count( $items ) . ' item(s)...' );
		$progress = \WP_CLI\Utils\make_progress_bar( 'Fixing', count( $items ) );
		$fixer    = new $class();
		$fixed    = 0;
		$failed   = 0;

		foreach ( $items as $item ) {
			$progress->tick();
			if ( empty( $item['id'] ) ) {
				++$failed;
				continue;
			}
			if ( $fixer->$method( (int) $item['id'] ) ) {
				++$fixed;
			} else {
				++$failed;
			}
		}

		$progress->finish();

		// The findings we just repaired are now stale.
		lwtv_plugin()->delete_transient( $check['transient'] );

		if ( $failed ) {
			\WP_CLI::warning( $failed . ' item(s) could not be fixed automatically.' );
		}
		\WP_CLI::success( $fixed . ' item(s) fixed. Re-run the check to confirm.' );
	}

	/**
	 * Log how old the cached findings are.
	 *
	 * @param string $status_key Key inside the debugger status option.
	 *
	 * @return void
	 */
	private function report_cache_age( string $status_key ): void {
		$last = Status::last_run( $status_key );

		if ( ! $last ) {
			\WP_CLI::log( 'Using cached results (age unknown). Use --force for a fresh scan.' );
			return;
		}

		\WP_CLI::log(
			sprintf(
				'Using cached results from %s ago. Use --force for a fresh scan.',
				human_time_diff( $last )
			)
		);
	}
}

\WP_CLI::add_command( 'lwtv debug', 'WP_CLI_LWTV_Debug' );
