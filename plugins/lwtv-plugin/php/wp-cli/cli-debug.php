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
	 *   - show_urls: Check show URL validity
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
	 * wp lwtv debug actors
	 * wp lwtv debug chars
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function __invoke( $args, $assoc_args = array() ) {

		$this->format     = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$this->debug_type = $args[0];

		try {
			$this->run_debug_check( $this->debug_type );
		} catch ( \Exception $exception ) {
			\WP_CLI::error( $exception->getMessage(), false );
		}
	}

	/**
	 * Run the appropriate debug check based on type.
	 *
	 * @param string $debug_type The type of debug check to run
	 */
	public function run_debug_check( $debug_type ) {
		$valid_types = array(
			'queers',
			'dupes',
			'byq',
			'actors',
			'chars',
			'shows',
			'actor_imdb',
			'show_imdb',
			'show_urls',
		);

		if ( ! in_array( $debug_type, $valid_types, true ) ) {
			$display_types = implode( ', ', $valid_types );
			\WP_CLI::error( 'Invalid debug type. Available types: ' . $display_types );
		}

		// Run the appropriate debug check
		switch ( $debug_type ) {
			case 'queers':
				$this->run_queer_check();
				break;
			case 'dupes':
				$this->run_dupe_check();
				break;
			case 'byq':
				$this->run_byq_check();
				break;
			case 'actors':
				$this->run_actor_check();
				break;
			case 'chars':
				$this->run_character_check();
				break;
			case 'shows':
				$this->run_show_check();
				break;
			case 'actor_imdb':
				$this->run_actor_imdb_check();
				break;
			case 'show_imdb':
				$this->run_show_imdb_check();
				break;
			case 'show_urls':
				$this->run_show_urls_check();
				break;
		}
	}

	/**
	 * Run queer consistency check.
	 */
	private function run_queer_check() {
		\WP_CLI::log( 'Running queer consistency check...' );
		$items = lwtv_plugin()->get_transient( 'lwtv_debug_queercheck' );
		// Force fresh check if no cached data
		if ( false === $items ) {
			$items = ( new Queers() )->find_queer_chars();
		}

		if ( empty( $items ) || ! is_array( $items ) ) {
			\WP_CLI::success( 'Excellent! All queer character/actor relationships are consistent.' );
		} else {
			\WP_CLI::log( count( $items ) . ' character(s) need attention for queer consistency.' );
			\WP_CLI\Utils\format_items( $this->format, $items, array( 'url', 'id', 'problem' ) );
			\WP_CLI::success( 'Queer consistency check complete.' );
		}
	}

	/**
	 * Run duplicate check.
	 */
	private function run_dupe_check() {
		\WP_CLI::log( 'Running duplicate check...' );
		$items = lwtv_plugin()->get_transient( 'lwtv_debug_duplicates' );
		// Force fresh check if no cached data
		if ( false === $items ) {
			$items = ( new Dupes() )->find_duplicates();
		}

		if ( empty( $items ) || ! is_array( $items ) ) {
			\WP_CLI::success( 'Excellent! No duplicate actors or shows found.' );
		} else {
			\WP_CLI::log( count( $items ) . ' duplicate(s) found.' );
			\WP_CLI\Utils\format_items( $this->format, $items, array( 'url', 'id', 'problem' ) );
			\WP_CLI::success( 'Duplicate check complete.' );
		}
	}

	/**
	 * Run BYQ (Bury Your Queers) check.
	 */
	private function run_byq_check() {
		\WP_CLI::log( 'Running BYQ (Bury Your Queers) check...' );
		$items = lwtv_plugin()->get_transient( 'lwtv_debug_byq_problems' );
		// Force fresh check if no cached data
		if ( false === $items ) {
			$items = ( new Characters() )->find_byq_problems();
		}

		if ( empty( $items ) || ! is_array( $items ) ) {
			\WP_CLI::success( 'Excellent! All death data looks good and consistent.' );
		} else {
			\WP_CLI::log( count( $items ) . ' character(s) have BYQ-related issues.' );
			\WP_CLI\Utils\format_items( $this->format, $items, array( 'url', 'id', 'problem' ) );
			\WP_CLI::success( 'BYQ check complete.' );
		}
	}

	/**
	 * Run actor data check.
	 */
	private function run_actor_check() {
		\WP_CLI::log( 'Running actor data completeness check...' );
		$items = lwtv_plugin()->get_transient( 'lwtv_debug_actor_problems' );
		// Force fresh check if no cached data
		if ( false === $items ) {
			$items = ( new Actors() )->find_actors_problems();
		}

		if ( empty( $items ) || ! is_array( $items ) ) {
			\WP_CLI::success( 'Excellent! All actor data is complete and correct.' );
		} else {
			\WP_CLI::log( count( $items ) . ' actor(s) need attention.' );
			\WP_CLI\Utils\format_items( $this->format, $items, array( 'url', 'id', 'problem' ) );
			\WP_CLI::success( 'Actor check complete.' );
		}
	}

	/**
	 * Run character data check.
	 */
	private function run_character_check() {
		\WP_CLI::log( 'Running character data completeness check...' );
		$items = lwtv_plugin()->get_transient( 'lwtv_debug_character_problems' );
		// Force fresh check if no cached data
		if ( false === $items ) {
			$items = ( new Characters() )->find_characters_problems();
		}

		if ( empty( $items ) || ! is_array( $items ) ) {
			\WP_CLI::success( 'Excellent! All character data is complete and correct.' );
		} else {
			\WP_CLI::log( count( $items ) . ' character(s) need attention.' );
			\WP_CLI\Utils\format_items( $this->format, $items, array( 'url', 'id', 'problem' ) );
			\WP_CLI::success( 'Character check complete.' );
		}
	}

	/**
	 * Run show data check.
	 */
	private function run_show_check() {
		\WP_CLI::log( 'Running show data completeness check...' );
		$items = lwtv_plugin()->get_transient( 'lwtv_debug_show_problems' );
		// Force fresh check if no cached data
		if ( false === $items ) {
			$items = ( new Shows() )->find_shows_problems();
		}

		if ( empty( $items ) || ! is_array( $items ) ) {
			\WP_CLI::success( 'Excellent! All show data is complete and correct.' );
		} else {
			\WP_CLI::log( count( $items ) . ' show(s) need attention.' );
			\WP_CLI\Utils\format_items( $this->format, $items, array( 'url', 'id', 'problem' ) );
			\WP_CLI::success( 'Show check complete.' );
		}
	}

	/**
	 * Run actor IMDb check.
	 */
	private function run_actor_imdb_check() {
		\WP_CLI::log( 'Running actor IMDb check...' );
		$items = lwtv_plugin()->get_transient( 'lwtv_debug_actor_imdb' );
		// Force fresh check if no cached data
		if ( false === $items ) {
			$items = ( new Actors() )->find_actors_no_imdb();
		}

		if ( empty( $items ) || ! is_array( $items ) ) {
			\WP_CLI::success( 'Excellent! All actors have IMDb data.' );
		} else {
			\WP_CLI::log( count( $items ) . ' actor(s) missing IMDb data.' );
			\WP_CLI\Utils\format_items( $this->format, $items, array( 'url', 'id', 'problem' ) );
			\WP_CLI::success( 'Actor IMDb check complete.' );
		}
	}

	/**
	 * Run show IMDb check.
	 */
	private function run_show_imdb_check() {
		\WP_CLI::log( 'Running show IMDb check...' );
		$items = lwtv_plugin()->get_transient( 'lwtv_debug_show_imdb' );
		// Force fresh check if no cached data
		if ( false === $items ) {
			$items = ( new Shows() )->find_shows_no_imdb();
		}

		if ( empty( $items ) || ! is_array( $items ) ) {
			\WP_CLI::success( 'Excellent! All shows have IMDb data.' );
		} else {
			\WP_CLI::log( count( $items ) . ' show(s) missing IMDb data.' );
			\WP_CLI\Utils\format_items( $this->format, $items, array( 'url', 'id', 'problem' ) );
			\WP_CLI::success( 'Show IMDb check complete.' );
		}
	}

	/**
	 * Run show URLs check.
	 */
	private function run_show_urls_check() {
		\WP_CLI::log( 'Running show URLs check...' );
		$items = lwtv_plugin()->get_transient( 'lwtv_debug_show_urls' );
		// Force fresh check if no cached data
		if ( false === $items ) {
			$items = ( new Shows() )->find_shows_bad_url();
		}

		if ( empty( $items ) || ! is_array( $items ) ) {
			\WP_CLI::success( 'Excellent! All show URLs are valid.' );
		} else {
			\WP_CLI::log( count( $items ) . ' show(s) have URL issues.' );
			\WP_CLI\Utils\format_items( $this->format, $items, array( 'url', 'id', 'problem' ) );
			\WP_CLI::success( 'Show URLs check complete.' );
		}
	}
}

\WP_CLI::add_command( 'lwtv debug', 'WP_CLI_LWTV_Debug' );
