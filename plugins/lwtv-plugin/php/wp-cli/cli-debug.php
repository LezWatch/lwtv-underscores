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
use LWTV\Debugger\Baseline_Store;
use LWTV\Debugger\Build\Baseline;
use LWTV\Debugger\Build\Findings;
use LWTV\Debugger\Build\Issue_Registry;
use LWTV\Debugger\Characters;
use LWTV\Debugger\Dupes;
use LWTV\Debugger\Queers;
use LWTV\Debugger\Shows;
use LWTV\Debugger\OnAir;
use LWTV\Debugger\Status;
use LWTV\Debugger\Watch_URLs;

/**
 * LezWatch.TV commands to debug and validate content.
 */
class WP_CLI_LWTV_Debug {

	/**
	 * Output columns for checks that don't name their own.
	 */
	const DEFAULT_COLUMNS = array( 'url', 'id', 'problem' );

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
	 * Repair class instances, reused across a run.
	 *
	 * @var array<string, object>
	 */
	private $fixers = array();

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
	 * - columns:   optional output columns. Defaults to DEFAULT_COLUMNS, which
	 *              suits the post-based checks; term-based findings carry
	 *              different keys and a post permalink they do not have.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_checks(): array {
		return array(
			'queers'      => array(
				'transient' => Queers::TRANSIENT_QUEERCHECK,
				'status'    => 'queercheck',
				'scanner'   => array( Queers::class, 'find_queer_chars' ),
				'running'   => 'Running queer consistency check...',
				'clean'     => 'Excellent! All queer character/actor relationships are consistent.',
				'dirty'     => 'character(s) need attention for queer consistency.',
				'done'      => 'Queer consistency check complete.',
			),
			'dupes'       => array(
				'transient' => Dupes::TRANSIENT_DUPES,
				'status'    => 'duplicates',
				'scanner'   => array( Dupes::class, 'find_duplicates' ),
				'running'   => 'Running duplicate check...',
				'clean'     => 'Excellent! No duplicate actors or shows found.',
				'dirty'     => 'duplicate(s) found.',
				'done'      => 'Duplicate check complete.',
			),
			'byq'         => array(
				'transient' => Characters::TRANSIENT_BYQ,
				'status'    => 'byq_problems',
				'scanner'   => array( Characters::class, 'find_byq_problems' ),
				'running'   => 'Running BYQ (Bury Your Queers) check...',
				'clean'     => 'Excellent! All death data looks good and consistent.',
				'dirty'     => 'character(s) have BYQ-related issues.',
				'done'      => 'BYQ check complete.',
			),
			'actors'      => array(
				'transient' => Actors::TRANSIENT_PROBLEMS,
				'status'    => 'actor_problems',
				'scanner'   => array( Actors::class, 'find_actors_problems' ),
				'fixer'     => array( Actors::class, 'fix_actor_data' ),
				'running'   => 'Running actor data completeness check...',
				'clean'     => 'Excellent! All actor data is complete and correct.',
				'dirty'     => 'actor(s) need attention. Use --fix-it to repair the ones marked fixable.',
				'done'      => 'Actor check complete.',
			),
			'chars'       => array(
				'transient' => Characters::TRANSIENT_PROBLEMS,
				'status'    => 'character_problems',
				'scanner'   => array( Characters::class, 'find_characters_problems' ),
				'fixer'     => array( Characters::class, 'fix_character_data' ),
				'running'   => 'Running character data completeness check...',
				'clean'     => 'Excellent! All character data is complete and correct.',
				'dirty'     => 'character(s) need attention. Use --fix-it to repair the ones marked fixable.',
				'done'      => 'Character check complete.',
			),
			'shows'       => array(
				'transient' => Shows::TRANSIENT_PROBLEMS,
				'status'    => 'show_problems',
				'scanner'   => array( Shows::class, 'find_shows_problems' ),
				'fixer'     => array( Shows::class, 'fix_show_data' ),
				'running'   => 'Running show data completeness check...',
				'clean'     => 'Excellent! All show data is complete and correct.',
				'dirty'     => 'show(s) need attention. Use --fix-it to repair the ones marked fixable.',
				'done'      => 'Show check complete.',
			),
			'actor_empty' => array(
				'transient' => Actors::TRANSIENT_EMPTY,
				'status'    => 'actor_empty',
				'scanner'   => array( Actors::class, 'find_actors_incomplete' ),
				'running'   => 'Running actor completeness check...',
				'clean'     => 'Excellent! Every actor has a photo and a biography.',
				'dirty'     => 'actor(s) missing a photo or a biography.',
				'done'      => 'Actor completeness check complete.',
			),
			'actor_imdb'  => array(
				'transient' => Actors::TRANSIENT_IMDB,
				'status'    => 'actor_imdb',
				'scanner'   => array( Actors::class, 'find_actors_no_imdb' ),
				'running'   => 'Running actor IMDb check...',
				'clean'     => 'Excellent! All actors have IMDb data.',
				'dirty'     => 'actor(s) missing IMDb data.',
				'done'      => 'Actor IMDb check complete.',
			),
			'show_imdb'   => array(
				'transient' => Shows::TRANSIENT_IMDB,
				'status'    => 'show_imdb',
				'scanner'   => array( Shows::class, 'find_shows_no_imdb' ),
				'running'   => 'Running show IMDb check...',
				'clean'     => 'Excellent! All shows have IMDb data.',
				'dirty'     => 'show(s) missing IMDb data.',
				'done'      => 'Show IMDb check complete.',
			),
			'watchurls'   => array(
				'transient' => Watch_URLs::TRANSIENT_PROBLEMS,
				'status'    => Watch_URLs::STATUS_KEY,
				'scanner'   => array( Watch_URLs::class, 'find_bad_watch_urls' ),
				'columns'   => array( 'term', 'url', 'status', 'shows', 'problem' ),
				'running'   => 'Checking every URL on every watch provider term...',
				'clean'     => 'Excellent! Every watch provider URL answered and still looks like its provider.',
				'dirty'     => 'provider URL(s) need attention.',
				'done'      => 'Watch provider URL check complete.',
				'slow'      => true,
			),
			'on_air'      => array(
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
	 *   - actor_empty: Find actors with no photo or no biography
 *   - actor_imdb: Find actors without IMDb data
	 *   - show_imdb: Find shows without IMDb data
	 *   - watchurls: Check every URL on every watch provider term still works and
	 *     still belongs to that provider (slow - one remote request per URL)
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
	 * [--reset-baseline]
	 * : Forget what the last run found, so the next run treats everything as
	 * long-standing rather than new. Use after a deliberate mass change, or if a
	 * baseline was recorded from a run you don't trust.
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
	 * wp lwtv debug shows --fix-it
	 * wp lwtv debug chars --fix-it
	 * wp lwtv debug actors --fix-it
	 * wp lwtv debug watchurls --force
	 * wp lwtv debug shows --reset-baseline
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function __invoke( $args, $assoc_args = array() ) {

		$this->format     = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$this->fix_it     = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'fix-it', false );
		$this->force      = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false );
		$this->debug_type = $args[0] ?? '';

		if ( (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'reset-baseline', false ) ) {
			$this->reset_baseline( $this->debug_type );
			return;
		}

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
		\WP_CLI\Utils\format_items( $this->format, $this->for_display( $items ), $check['columns'] ?? self::DEFAULT_COLUMNS );
		$this->report_baseline( $check['status'] );
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
		\WP_CLI::log( 'Attempting to fix issues for ' . count( $items ) . ' item(s)...' );
		$progress = \WP_CLI\Utils\make_progress_bar( 'Fixing', count( $items ) );
		$fixed    = 0;
		$failed   = 0;

		foreach ( $items as $item ) {
			$progress->tick();
			if ( empty( $item['id'] ) ) {
				++$failed;
				continue;
			}

			if ( $this->fix_item( $check, $item ) ) {
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
	 * Repair one finding row.
	 *
	 * Prefers the per-issue repairs the row names in `fixable`, which is what
	 * makes the fix specific: only the issues actually found get repaired, and
	 * one row can carry several. Rows written before findings were typed -- the
	 * transients last a week -- have no `fixable` key, so those fall back to the
	 * check-level fixer. A check with neither is simply not repairable.
	 *
	 * @param  array $check Check definition.
	 * @param  array $item  One finding row.
	 * @return bool  True when at least one repair was applied.
	 */
	private function fix_item( array $check, array $item ): bool {
		$post_id = (int) $item['id'];

		/*
		 * Manual repairs are excluded here, not filtered out of the finding.
		 * They are judgement calls -- "this show really has no characters" --
		 * and applying one across a whole report would be making that judgement
		 * on somebody's behalf. They stay available per finding in wp-admin.
		 */
		$issues = array_values(
			array_filter(
				Findings::fixable_issues( $item ),
				static fn ( $issue_type ) => ! Issue_Registry::is_manual( $issue_type )
			)
		);

		if ( ! empty( $issues ) ) {
			$repaired = false;

			foreach ( $issues as $issue_type ) {
				list( $class, $method ) = Issue_Registry::fix_callable( $issue_type );

				// Deliberately not short-circuiting: a row can need all of them.
				if ( $this->fixer( $class )->$method( $post_id ) ) {
					$repaired = true;
				}
			}

			return $repaired;
		}

		if ( ! isset( $check['fixer'] ) ) {
			return false;
		}

		list( $class, $method ) = $check['fixer'];

		return (bool) $this->fixer( $class )->$method( $post_id );
	}

	/**
	 * One instance per repair class, reused across a run.
	 *
	 * @param  string $class_name Fully qualified class name.
	 * @return object
	 */
	private function fixer( string $class_name ): object {
		$key = ltrim( $class_name, '\\' );

		if ( ! isset( $this->fixers[ $key ] ) ) {
			$this->fixers[ $key ] = new $key();
		}

		return $this->fixers[ $key ];
	}

	/**
	 * Rows with their problems flattened to plain text.
	 *
	 * A copy: the transient keeps the admin-shaped `problem`, because the admin
	 * table wants the markup this strips. Applied for every output format, not
	 * just `table` -- nothing consuming the JSON or CSV wants `</br>` either.
	 *
	 * @param  array $items Finding rows.
	 * @return array
	 */
	private function for_display( array $items ): array {
		foreach ( $items as $index => $item ) {
			if ( isset( $item['problem'] ) ) {
				$items[ $index ]['problem'] = Findings::plain( $item );
			}
		}

		return $items;
	}

	/**
	 * Forget one check's baseline.
	 *
	 * @param  string $debug_type Check to reset.
	 * @return void
	 */
	private function reset_baseline( string $debug_type ): void {
		$checks = $this->get_checks();

		if ( ! isset( $checks[ $debug_type ] ) ) {
			\WP_CLI::error( 'Invalid debug type. Available types: ' . implode( ', ', array_keys( $checks ) ) );
		}

		$scope = $checks[ $debug_type ]['status'];

		if ( ! Baseline_Store::exists( $scope ) ) {
			\WP_CLI::warning( 'That check has no baseline to reset.' );
			return;
		}

		Baseline_Store::reset( $scope );

		\WP_CLI::success( 'Baseline cleared. The next run will report everything as long-standing rather than new.' );
	}

	/**
	 * Log the new/open/resolved breakdown, when the check records one.
	 *
	 * Only the converted checks do. The rest report a bare count, and saying
	 * nothing is better than implying a comparison that did not happen.
	 *
	 * @param  string $status_key Key inside the debugger status option.
	 * @return void
	 */
	private function report_baseline( string $status_key ): void {
		$summary = Status::all()[ $status_key ]['summary'] ?? array();

		if ( ! is_array( $summary ) || empty( $summary ) ) {
			return;
		}

		\WP_CLI::log( Baseline::describe_summary( $summary ) );
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
