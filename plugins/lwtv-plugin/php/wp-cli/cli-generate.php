<?php
/*
 * WP CLI Commands for LezWatch.TV
 *
 * These commands are 'generation' tools.
 */

use LWTV\_Components\Calendar;
use LWTV\_Components\Of_The_Day;
use LWTV\Debugger\Actors as Actors_Debugger;
use LWTV\Debugger\Characters as Characters_Debugger;
use LWTV\Debugger\Shows as Shows_Debugger;
use LWTV\Debugger\Dupes as Dupes_Debugger;
use LWTV\Debugger\Queers as Queers_Debugger;
use LWTV\Debugger\Watch_Host_Collisions;
use LWTV\Debugger\OnAir as OnAir_Debugger;
use LWTV\Debugger\Watch_URLs as Watch_URLs_Debugger;
use LWTV\Debugger\Log;
use LWTV\Features\Missed_Schedule;
use LWTV\Rest_API\BYQ;
use LWTV\CPTs\Shows as CPT_Shows;
use LWTV\CPTs\Actors as CPT_Actors;
use LWTV\Schedulers\Statistics_Cache_Warming;

// Bail if directly accessed
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	die();
}

/**
 * LezWatch.TV commands to regenerate content.
 */
class WP_CLI_LWTV_Generate {

	/**
	 * @var string
	 */
	public $format;

	/**
	 * @var string
	 */
	public $gen_type;

	/**
	 * @var string
	 */
	public $second;

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
	 * Generate files or abnormal code settings.
	 *
	 * ## OPTIONS
	 *
	 * <type>
	 * : Type to content to generate (i.e. 'TVmaze').
	 * options:
	 * - tvmaze
	 * - otd
	 * - lists
	 * - debug
	 * - cron
	 * ---
	 *
	 * [<second>]
	 * : Optional. Secondary data. OTD uses [show|character], debug uses [mon|tue|wed|thu|fri|sat|sun], cron uses [daily|hourly].
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Generate TV Maze
	 *     $ wp lwtv generate tvmaze
	 *     Success: TVMaze updated successfully.
	 *
	 *     # Generate OTD for shows
	 *     $ wp lwtv generate otd show
	 *     Success: The show "Of the Day" has been set.
	 *
	 *     # Generate lists
	 *     $ wp lwtv generate lists
	 *     Success: The lists have been updated.
	 *
	 *     # Generate debug for Monday
	 *     $ wp lwtv generate debug mon
	 *     Success: Debug checker ran successfully. Day: Mon
	 *
	 *     # Generate cron daily
	 *     $ wp lwtv generate cron daily
	 *     Success: Cron jobs triggered successfully.
	 *
	 *     # Check missed schedule status
	 *     $ wp lwtv generate missed-schedule status
	 *     Success: Missed schedule status retrieved.
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function __invoke( array $args, array $assoc_args = array() ) {

		$this->format   = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$this->gen_type = $args[0];
		$this->second   = ( isset( $args[1] ) ) ? $args[1] : null;

		try {
			$this->run_generator( $this->gen_type, $this->second );
		} catch ( Exception $exception ) {
			\WP_CLI::error( $exception->getMessage(), false );
		}
	}

	/**
	 * Build it!
	 *
	 * @param string $type   Type of content to generate
	 * @param string $second Secondary data (may not be used)
	 */
	public function run_generator( $type, $second ) {
		// Determine the appropriate checker:
		$build_it = match ( $type ) {
			'tvmaze' => $this->run_tvmaze(),
			'otd'    => $this->run_otd( $second ),
			'lists'  => $this->run_update_lists(),
			'debug'  => $this->run_debug_checker( $second ),
			'cron'   => $this->run_cron_jobs( $second ),
			default  => 'none',
		};

		if ( 'none' === $build_it ) {
			\WP_CLI::error( 'You picked an invalid tool to generate. ' . $type . ' does not exist.' );
		}

		if ( false === $build_it ) {
			\WP_CLI::error( 'There was an error running the ' . $type . ' generator.' );
		}

		\WP_CLI::success( 'The ' . $type . ' generator ran successfully.' );
	}

	/**
	 * Run the cron jobs.
	 */
	public function run_cron_jobs( $second = null ) {

		switch ( $second ) {
			case 'daily':
				\WP_CLI::log( 'Prepping DAILY cron.' );
				$this->run_cron_daily();
				break;
			case 'hourly':
			default:
				\WP_CLI::log( 'Prepping HOURLY cron.' );
				$this->run_cron_hourly();
				break;
		}

		\WP_CLI::success( 'Cron jobs triggered successfully.' );
	}

	/**
	 * Run the hourly cron jobs.
	 */
	public function run_cron_hourly() {
		// Check missed schedule:
		\WP_CLI::log( 'Attempting to publish all posts that have missed schedule.' );
		$missed_schedule = new Missed_Schedule();

		// Show scheduler status
		$status = $missed_schedule->get_scheduler_status();
		\WP_CLI::log( 'Missed Schedule Status: ' . $status['current_method'] );

		if ( $status['action_scheduler_available'] && $status['next_scheduled_check'] ) {
			\WP_CLI::log( 'Next scheduled check: ' . gmdate( 'Y-m-d H:i:s', $status['next_scheduled_check'] ) );
		}

		$result = $missed_schedule->missed_schedule();
		if ( ! empty( $result ) ) {
			\WP_CLI::log( $result );
		}
	}

	/**
	 * Run the daily cron jobs.
	 */
	public function run_cron_daily() {
		// Run the update lists
		\WP_CLI::log( 'Updating the lists...' );
		$this->run_update_lists();

		// Refresh BYQ (Bury Your Queers) caches daily to ensure fresh death data
		\WP_CLI::log( 'Refreshing BYQ death caches...' );
		$byq_refresh = ( new BYQ() )->daily_cache_refresh();
		if ( $byq_refresh ) {
			\WP_CLI::success( 'BYQ caches refreshed successfully.' );
		} else {
			\WP_CLI::warning( 'BYQ cache refresh may have had issues - check logs.' );
		}

		// Build tv maze:
		\WP_CLI::log( 'Downloading the TV Maze ICS.' );
		$this->run_tvmaze();

		/*
		 * Rotate the debug log before the day's check adds to it.
		 *
		 * Size-based, not daily: a 4KB log rotated every night just buries the
		 * useful history under a pile of near-empty files. Log::append() has its
		 * own mid-request backstop at a higher threshold for runaway loops
		 * between cron runs. See DEBUGGER-REVIEW.md 6.
		 */
		$rotated = Log::rotate();
		if ( '' !== $rotated ) {
			\WP_CLI::log( sprintf( 'Rotated the debug log to %s.', basename( $rotated ) ) );
		}

		// Run the debug of the day:
		$day = gmdate( 'D' );
		\WP_CLI::log( sprintf( 'Running the debug checker. Day: %s ...', $day ) );
		$this->run_debug_checker( $day );

		// Warm the statistics caches so they're never stale for long with no edits.
		\WP_CLI::log( 'Warming statistics caches...' );
		( new Statistics_Cache_Warming() )->warm_all();
		\WP_CLI::success( 'Statistics caches warmed.' );
	}

	/**
	 * Run a different debug checker based on what day it is.
	 *
	 * @param array $day Which 'day' are we running?
	 */
	public function run_debug_checker( $day = null ) {
		// If we got here without a Day, it's today.
		$day = ( ! isset( $day ) || is_null( $day ) ) ? gmdate( 'D' ) : $day;

		// Run a different check each day.
		switch ( strtolower( $day ) ) {
			case 'mon':
				\WP_CLI::log( 'Debugger: Checking queer characters...' );
				( new Queers_Debugger() )->find_queer_chars();
				break;
			case 'tue':
				\WP_CLI::log( 'Debugger: Checking for BYQ issues...' );
				( new Characters_Debugger() )->find_byq_problems();
				break;
			case 'wed':
				\WP_CLI::log( 'Debugger: Checking dupes...' );
				( new Dupes_Debugger() )->find_duplicates();
				\WP_CLI::log( 'Debugger: Checking on air status...' );
				( new OnAir_Debugger() )->find_on_air_problems();

				/*
				 * Give any newly-seen watch provider host a real display name.
				 *
				 * Wednesday because its other two checks are plain SQL, and
				 * because Sunday's time budget belongs to find_bad_watch_urls().
				 * Keeping the only two HTTP jobs on separate days means a cron
				 * timeout still tells you which one caused it.
				 *
				 * Safe to repeat weekly: enrich skips hosts that already have a
				 * term and hosts it has already asked about, so once the backlog
				 * is named a run does nothing at all. The default --limit of 25
				 * means the initial backlog is worked through over several weeks
				 * rather than in one long run. Unreachable hosts are deliberately
				 * not recorded, so a blip retries next Wednesday instead of
				 * becoming permanent.
				 *
				 * Routed through __invoke() rather than the private run_enrich()
				 * so cron takes exactly the path a human does.
				 */
				\WP_CLI::log( 'Ways to Watch: Naming any new provider hosts...' );
				( new \WP_CLI_LWTV_WaysToWatch() )->__invoke( array( 'enrich' ), array() );

				/*
				 * Two queries, no requests, so it rides along with the day's
				 * other plain-SQL checks. Should find nothing almost always --
				 * it exists to notice the day someone points a second provider
				 * term at a host that already has one, which host matching has
				 * to resolve by name order.
				 */
				\WP_CLI::log( 'Debugger: Checking for contested watch hosts...' );
				( new Watch_Host_Collisions() )->find_host_collisions();
				break;
			case 'thu':
				\WP_CLI::log( 'Debugger: Checking all actors...' );
				( new Actors_Debugger() )->find_actors_problems();
				\WP_CLI::log( 'Debugger: Checking actors for iMDB...' );
				( new Actors_Debugger() )->find_actors_no_imdb();
				\WP_CLI::log( 'Debugger: Checking actors for photos and bios...' );
				( new Actors_Debugger() )->find_actors_incomplete();
				break;
			case 'fri':
				\WP_CLI::log( 'Debugger: Checking all characters...' );
				( new Characters_Debugger() )->find_characters_problems();
				break;
			case 'sat':
				\WP_CLI::log( 'Debugger: Checking all shows...' );
				( new Shows_Debugger() )->find_shows_problems();
				\WP_CLI::log( 'Debugger: Checking shows for iMDB...' );
				( new Shows_Debugger() )->find_shows_no_imdb();
				break;
			case 'sun':
				\WP_CLI::log( 'Debugger: Force re-indexing FacetWP...' );
				if ( function_exists( 'FWP' ) ) {
					FWP()->indexer->index( true );
					\WP_CLI::log( 'FacetWP re-index complete.' );
				} else {
					\WP_CLI::warning( 'FacetWP is not active; skipping reindex.' );
				}

				// Sunday takes the slow one, on the quietest day, and it goes
				// last: this is one HTTP request per provider URL with a rate
				// limit between them (Watch_URLs::SLEEP_US), so it is the only
				// thing here that could plausibly hit a cron wrapper's timeout.
				// If it does, everything above it has already finished.
				\WP_CLI::log( 'Debugger: Checking watch provider URLs...' );
				( new Watch_URLs_Debugger() )->find_bad_watch_urls();
				break;
			default:
				\WP_CLI::warning( 'You must provide a valid day of the week. Use the THREE letter version (Mon, Tue, etc)' );
		}

		\WP_CLI::success( 'Debug checker ran successfully.' );
	}

	/**
	 * Regenerate the TV Maze ICS file.
	 */
	public function run_tvmaze() {
		// Download the TV Maze ICS file.
		( new Calendar() )->download_tvmaze();

		$ics_file = ( new Calendar() )->get_tvmaze_ics();

		if ( false === $ics_file ) {
			\WP_CLI::warning( 'The TVMaze file is missing.' );
		}

		$file_time = filemtime( $ics_file );

		if ( file_exists( $ics_file ) && $file_time <= strtotime( '+1 sec' ) ) {
			\WP_CLI::success( 'TVMaze updated successfully.' );
		} else {
			\WP_CLI::warning( 'TVMaze was not able to be updated.' );
		}
	}

	/**
	 * Set "Of the Day" for the day.
	 *
	 * @param array $otd Which 'of the day' are we making.
	 */
	public function run_otd( $otd = null ) {
		// Valid things to find...
		$valid_otd = array( 'character', 'show' );

		// Check for valid arguments and post types
		if ( ! empty( $otd ) && ! in_array( $otd, $valid_otd, true ) ) {
			\WP_CLI::warning( 'You must provide a valid type of item to set for "Of the Day": ' . implode( ', ', $valid_otd ) );
		}

		if ( empty( $otd ) ) {
			$to_do = $valid_otd;
		} else {
			$to_do = array( $otd );
		}

		// Set it!
		foreach ( $to_do as $otd ) {
			\WP_CLI::log( 'Setting the ' . $otd . ' "Of the Day"...' );
			$new_otd = ( new Of_The_Day() )->set_of_the_day( $otd );

			if ( null === $new_otd || is_wp_error( $new_otd ) ) {
				\WP_CLI::error( 'There was an error setting the ' . $otd . ' "Of the Day": ' . wp_json_encode( $new_otd ) );
			}

			$post_id = $new_otd['pid'] ?? $new_otd['id'] ?? null;

			if ( empty( $post_id ) ) {
				\WP_CLI::error( 'The ' . $otd . ' "Of the Day" has no post ID and cannot be set: ' . wp_json_encode( $new_otd ) );
			}

			\WP_CLI::success( 'The ' . $otd . ' "Of the Day" has been set: ' . get_the_title( $post_id ) . ' (' . $post_id . ')' );
		}
	}

	/**
	 * Update Lists
	 *
	 * Update lists of shows and actors as transients to speed up queeries
	 * and make them cacheable.
	 *
	 * @access public
	 * @return void
	 */
	public function run_update_lists() {
		$count_shows = lwtv_plugin()->get_transient( 'lwtv_count_shows' );
		if ( false === $count_shows ) {
			$count_shows = wp_count_posts( CPT_Shows::SLUG )->publish;
			lwtv_plugin()->set_transient( 'lwtv_count_shows', $count_shows, 24 * HOUR_IN_SECONDS );
		}

		\WP_CLI::success( 'Updated the show count -- ' . $count_shows . ' shows.' );

		$count_actors = lwtv_plugin()->get_transient( 'lwtv_count_actors' );
		if ( false === $count_actors ) {
			$count_actors = wp_count_posts( CPT_Actors::SLUG )->publish;
			lwtv_plugin()->set_transient( 'lwtv_count_actors', $count_actors, 24 * HOUR_IN_SECONDS );
		}

		\WP_CLI::success( 'Updated the actor count -- ' . $count_actors . ' actors.' );
	}
}

\WP_CLI::add_command( 'lwtv generate', 'WP_CLI_LWTV_Generate' );
