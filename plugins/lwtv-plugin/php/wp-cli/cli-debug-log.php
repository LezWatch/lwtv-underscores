<?php
/**
 * WP-CLI: read and maintain the LWTV debug log.
 *
 * The settings page toggles logging; this is how you read the result. Chosen
 * over an admin log viewer deliberately -- reading a log is an operator task,
 * and everyone who can turn logging on already has shell access.
 *
 * @package LWTV
 */

use LWTV\Admin_Menu\Debugging;
use LWTV\Debugger\Build\Log_Rules;
use LWTV\Debugger\Log;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	exit;
}

class WP_CLI_LWTV_Debug_Log {

	/**
	 * Read and maintain debug-lwtv.log.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : What to do.
	 * ---
	 * options:
	 *   - tail
	 *   - status
	 *   - topics
	 *   - rotate
	 *   - clear
	 * ---
	 *
	 * [--lines=<number>]
	 * : tail only. How many entries to show. Default 25. Use 0 for everything
	 * that was read.
	 *
	 * [--topic=<topic>]
	 * : tail only. Only entries for one topic. Case-insensitive.
	 *
	 * [--search=<text>]
	 * : tail only. Only entries containing this text. Case-insensitive.
	 *
	 * [--file=<path>]
	 * : tail only. Read a rotated log instead of the live one.
	 *
	 * [--force]
	 * : rotate only. Rotate regardless of size.
	 *
	 * ## EXAMPLES
	 *
	 *     # The last 25 entries.
	 *     $ wp lwtv debug-log tail
	 *
	 *     # What the statistics cache warming has been up to.
	 *     $ wp lwtv debug-log tail --topic=statistics --lines=100
	 *
	 *     # Find one show across every topic.
	 *     $ wp lwtv debug-log tail --search="Gentleman Jack" --lines=0
	 *
	 *     # Is logging even on, and how big is the file?
	 *     $ wp lwtv debug-log status
	 *
	 *     # Which topics are declared, enabled, and actually present?
	 *     $ wp lwtv debug-log topics
	 *
	 *     # Rotate now, whatever the size.
	 *     $ wp lwtv debug-log rotate --force
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Flags.
	 */
	public function __invoke( $args, $assoc_args = array() ) {
		switch ( $args[0] ?? '' ) {
			case 'tail':
				$this->run_tail( $assoc_args );
				break;
			case 'status':
				$this->run_status();
				break;
			case 'topics':
				$this->run_topics();
				break;
			case 'rotate':
				$this->run_rotate( $assoc_args );
				break;
			case 'clear':
				$this->run_clear();
				break;
			default:
				\WP_CLI::error( 'Invalid action. Use: tail, status, topics, rotate, clear' );
		}
	}

	/**
	 * Show the tail of the log.
	 *
	 * @param  array $assoc_args Flags.
	 * @return void
	 */
	private function run_tail( array $assoc_args ): void {
		$lines  = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'lines', 25 );
		$topic  = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'topic', '' );
		$search = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'search', '' );
		$file   = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'file', '' );

		if ( '' !== $topic && ! Log_Rules::is_known_topic( strtolower( $topic ), Debugging::VALID_LOG_TOPICS ) ) {
			\WP_CLI::warning( sprintf( '"%s" is not a declared topic, so this will match nothing. Try: wp lwtv debug-log topics', $topic ) );
		}

		$raw = Log::lines( $file );

		if ( empty( $raw ) ) {
			\WP_CLI::success( '' === $file ? 'The log is empty.' : 'That file is empty or does not exist.' );
			return;
		}

		$found = Log_Rules::tail( Log_Rules::filter_lines( $raw, $topic, $search ), $lines );

		if ( empty( $found ) ) {
			\WP_CLI::success( 'Nothing matched.' );
			return;
		}

		foreach ( $found as $line ) {
			\WP_CLI::line( rtrim( $line, "\n" ) );
		}

		\WP_CLI::success( sprintf( '%d entr%s shown.', count( $found ), ( 1 === count( $found ) ) ? 'y' : 'ies' ) );
	}

	/**
	 * Report on the log's size, rotation state, and whether logging is even on.
	 *
	 * @return void
	 */
	private function run_status(): void {
		$bytes   = Log::size();
		$rotated = Log::rotated();

		$rows = array(
			array(
				'setting' => 'Logging enabled',
				'value'   => lwtv_plugin()->is_debug_mode() ? 'yes' : 'no',
			),
			array(
				'setting' => 'Log file',
				'value'   => Log::path(),
			),
			array(
				'setting' => 'Current size',
				'value'   => size_format( $bytes, 2 ) . ' (' . number_format_i18n( $bytes ) . ' bytes)',
			),
			array(
				'setting' => 'Rotates at',
				'value'   => size_format( Log_Rules::ROTATE_AT ) . ' during cron',
			),
			array(
				'setting' => 'Hard cap',
				'value'   => size_format( Log_Rules::MAX_BYTES ) . ' mid-request',
			),
			array(
				'setting' => 'Rotated files',
				'value'   => count( $rotated ) . ' of ' . Log_Rules::KEEP . ' kept',
			),
		);

		\WP_CLI\Utils\format_items( 'table', $rows, array( 'setting', 'value' ) );

		foreach ( $rotated as $path ) {
			\WP_CLI::line( '  ' . basename( $path ) );
		}
	}

	/**
	 * Cross-reference the declared, enabled, and present topics.
	 *
	 * Answers "why am I not seeing anything for X" in one screen, which is the
	 * question this whole section of the review started from.
	 *
	 * @return void
	 */
	private function run_topics(): void {
		$declared = Debugging::VALID_LOG_TOPICS;
		$present  = array();

		foreach ( Log::lines() as $line ) {
			$topic = Log_Rules::topic_from_line( $line );

			if ( '' !== $topic ) {
				$present[ $topic ] = ( $present[ $topic ] ?? 0 ) + 1;
			}
		}

		$rows = array();

		foreach ( $declared as $topic ) {
			$rows[] = array(
				'topic'   => $topic,
				'enabled' => lwtv_plugin()->is_topic_enabled( $topic ) ? 'yes' : 'no',
				'in log'  => (string) ( $present[ $topic ] ?? 0 ),
			);
		}

		\WP_CLI\Utils\format_items( 'table', $rows, array( 'topic', 'enabled', 'in log' ) );

		// A topic in the file but not in the vocabulary means the constant was
		// changed after the fact -- worth saying out loud rather than hiding.
		$stray = array_diff( array_keys( $present ), $declared );

		if ( ! empty( $stray ) ) {
			\WP_CLI::warning( sprintf( 'In the log but no longer declared: %s', implode( ', ', $stray ) ) );
		}

		if ( ! lwtv_plugin()->is_debug_mode() ) {
			\WP_CLI::warning( 'Debug mode is off, so nothing is being written whatever is ticked.' );
		}
	}

	/**
	 * Rotate the log.
	 *
	 * @param  array $assoc_args Flags.
	 * @return void
	 */
	private function run_rotate( array $assoc_args ): void {
		$force = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false );

		if ( 0 === Log::size() ) {
			\WP_CLI::success( 'Nothing to rotate; the log is empty.' );
			return;
		}

		// A threshold of 1 byte rotates anything non-empty.
		$rotated = Log::rotate( $force ? 1 : Log_Rules::ROTATE_AT );

		if ( '' === $rotated ) {
			\WP_CLI::success(
				sprintf(
					'Left alone: %s is below the %s rotation threshold. Use --force to rotate anyway.',
					size_format( Log::size(), 2 ),
					size_format( Log_Rules::ROTATE_AT )
				)
			);
			return;
		}

		\WP_CLI::success( sprintf( 'Rotated to %s.', basename( $rotated ) ) );
	}

	/**
	 * Empty the log without keeping a copy.
	 *
	 * @return void
	 */
	private function run_clear(): void {
		\WP_CLI::confirm( 'This throws the current log away without rotating it. Continue?' );

		if ( ! Log::clear() ) {
			\WP_CLI::error( 'Could not empty the log. Check file permissions.' );
		}

		\WP_CLI::success( 'Log emptied.' );
	}
}

\WP_CLI::add_command( 'lwtv debug-log', 'WP_CLI_LWTV_Debug_Log' );
