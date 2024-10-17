<?php
/*
 * WP CLI Commands for LezWatch.TV
 *
 * These commands are 'generation' tools.
 */

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
		// Run the appropriate checker:
		switch ( $type ) {
			case 'tvmaze':
				$buildit = $this->run_tvmaze();
				break;
			case 'otd':
				$buildit = $this->run_otd( $second );
				break;
			case 'lists':
				$buildit = $this->run_update_lists();
				break;
			case 'debug':
				$buildit = $this->run_debug_checker( $second );
				break;
			case 'cron':
				$buildit = $this->run_cron_jobs( $second );
				break;
			default:
				$buildit = 'none';
		}

		if ( 'none' === $buildit ) {
			\WP_CLI::error( 'You picked an invalid tool to generate. ' . $type . ' does not exist.' );
		}

		if ( false === $buildit ) {
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
				\WP_CLI::line( 'Running DAILY cron.' );
				$this->run_cron_hourly();
				$this->run_cron_daily();
				break;
			case 'hourly':
			default:
				\WP_CLI::line( 'Running HOURLY cron.' );
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
		\WP_CLI::line( 'Attempting to publish all posts that have missed schedule.' );
		$missed_schedule = lwtv_plugin()->check_missed_schedule();
		if ( ! empty( $missed_schedule ) ) {
			\WP_CLI::line( $missed_schedule );
		}

		// Build tv maze:
		\WP_CLI::line( 'Downloading the TV Maze ICS.' );
		$this->run_tvmaze();
	}

	/**
	 * Run the daily cron jobs.
	 */
	public function run_cron_daily() {
		// Run the update lists
		\WP_CLI::line( 'Updating the lists...' );
		$this->run_update_lists();

		// run OTD
		\WP_CLI::line( 'Setting the "Of the Day"...' );
		$this->run_otd();

		// Run the debug of the day:
		$day = gmdate( 'D' );
		\WP_CLI::line( sprintf( 'Running the debug checker. Day: %s ...', $day ) );
		$this->run_debug_checker( $day );

		// Run the indexer
		\WP_CLI::line( 'Running the FacetWP indexer. Please be patient, this takes time...' );
		\FWP()->indexer->index();
	}

	/**
	 * Run a different debug checker based on what day it is.
	 *
	 * @param array $day Which 'day' are we running?
	 */
	public function run_debug_checker( $day = null ) {
		// If we got here without a Day, it's today.
		$day = ( isset( $day ) ) ? $day : gmdate( 'D' );

		// Run a different check each day.
		switch ( strtolower( $day ) ) {
			case 'mon':
				lwtv_plugin()->find_actors_problems();
				break;
			case 'tue':
				lwtv_plugin()->find_actors_no_imdb();
				break;
			case 'wed':
				lwtv_plugin()->find_duplicates();
				break;
			case 'thu':
				lwtv_plugin()->find_queer_chars();
				break;
			case 'fri':
				lwtv_plugin()->find_characters_problems();
				break;
			case 'sat':
				lwtv_plugin()->find_shows_problems();
				break;
			case 'sun':
				lwtv_plugin()->find_shows_no_imdb();
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
		lwtv_plugin()->download_tvmaze();

		$ics_file = lwtv_plugin()->get_tvmaze_ics();

		if ( false === $ics_file ) {
			\WP_CLI::warning( 'The TVMaze file is missing.' );
		}

		$file_time = filemtime( $ics_file );

		if ( file_exists( $ics_file ) && $file_time <= strtotime( '+1 sec' ) ) {
			\WP_CLI::success( 'TVMaze updated successfully.' );
		} else {
			\WP_CLI::warning( 'TVMaze is not able to be updated.' );
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
			lwtv_plugin()->set_of_the_day( $otd );
			\WP_CLI::success( 'The ' . $otd . ' "Of the Day" has been set.' );
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
			$count_shows = wp_count_posts( 'post_type_shows' )->publish;
			set_transient( 'lwtv_count_shows', $count_shows, 24 * HOUR_IN_SECONDS );
		}

		\WP_CLI::success( 'Updated the show count -- ' . $count_shows . ' shows.' );

		$count_actors = lwtv_plugin()->get_transient( 'lwtv_count_actors' );
		if ( false === $count_actors ) {
			$count_actors = wp_count_posts( 'post_type_actors' )->publish;
			set_transient( 'lwtv_count_actors', $count_actors, 24 * HOUR_IN_SECONDS );
		}

		\WP_CLI::success( 'Updated the actor count -- ' . $count_actors . ' actors.' );
	}
}

\WP_CLI::add_command( 'lwtv generate', 'WP_CLI_LWTV_Generate' );
