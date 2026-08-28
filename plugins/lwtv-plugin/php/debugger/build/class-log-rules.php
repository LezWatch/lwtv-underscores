<?php
/**
 * Pure decisions for the LWTV debug log.
 *
 * No WordPress, no filesystem: every method here takes values and returns
 * values, so the parts that are easy to get subtly wrong -- which topics are
 * enabled, when to rotate, how many files to keep, how a line parses back into
 * a topic -- are unit-testable. The file handling itself lives in
 * Debugger\Log, and the option reads in _Components\Debugger.
 *
 * See DEBUGGER-REVIEW.md 6.
 *
 * @package LWTV
 */

namespace LWTV\Debugger\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Log_Rules {

	/**
	 * Rotate during cron once the log passes this size.
	 *
	 * Size-based rather than daily: rotating a 4KB file every night just buries
	 * the useful history in a pile of near-empty files.
	 */
	const ROTATE_AT = 1048576;

	/**
	 * Hard cap enforced mid-request, between cron runs.
	 *
	 * `statistics` alone has 104 call sites, many inside cache-warming loops, so
	 * a single bad afternoon can outrun a daily rotation. This is the backstop.
	 */
	const MAX_BYTES = 10485760;

	/**
	 * How many rotated files to keep.
	 */
	const KEEP = 5;

	/**
	 * Format one log line.
	 *
	 * The format is deliberately unchanged -- `ucwords()` and all -- because
	 * existing log files have to keep parsing. Note that `ucwords()` splits on
	 * spaces, not hyphens, so `shadow-taxonomy` becomes `Shadow-taxonomy`;
	 * topic_from_line() lowercases to compensate rather than "fixing" it here.
	 *
	 * @param  string $topic     Log topic.
	 * @param  string $message   The message.
	 * @param  string $timestamp Pre-formatted UTC timestamp (Y-m-d H:i:s).
	 * @return string
	 */
	public static function line( string $topic, string $message, string $timestamp ): string {
		return '[' . $timestamp . '] [' . ucwords( $topic ) . '] ' . $message . "\n";
	}

	/**
	 * Read the topic back out of a formatted line.
	 *
	 * @param  string $line One log line.
	 * @return string Lowercased topic, or '' when the line is not in our format.
	 */
	public static function topic_from_line( string $line ): string {
		if ( 1 !== preg_match( '/^\[[^\]]*\]\s\[([^\]]+)\]/', $line, $found ) ) {
			return '';
		}

		return strtolower( trim( $found[1] ) );
	}

	/**
	 * Is this topic one we know about?
	 *
	 * @param  string        $topic Topic to test.
	 * @param  array<string> $valid The declared vocabulary.
	 * @return bool
	 */
	public static function is_known_topic( string $topic, array $valid ): bool {
		return in_array( $topic, $valid, true );
	}

	/**
	 * Should this topic be written?
	 *
	 * Fail *closed*, which is a deliberate reversal: an empty selection used to
	 * mean "log everything", so unticking every box in a UI that plainly implies
	 * silence produced the loudest possible setting. Empty now means silence.
	 *
	 * An unknown topic is also refused. That makes a typo'd topic silent rather
	 * than unstoppable, which is the better failure of the two but still a
	 * failure -- Log::write() reports unknown topics to PHP's error log so the
	 * typo is discoverable.
	 *
	 * @param  string        $topic   Topic being logged.
	 * @param  array<string> $enabled Topics the editor has ticked.
	 * @param  array<string> $valid   The declared vocabulary.
	 * @return bool
	 */
	public static function topic_enabled( string $topic, array $enabled, array $valid ): bool {
		if ( '' === $topic || ! self::is_known_topic( $topic, $valid ) ) {
			return false;
		}

		if ( empty( $enabled ) ) {
			return false;
		}

		return in_array( $topic, $enabled, true );
	}

	/**
	 * Has the log outgrown a threshold?
	 *
	 * @param  int $bytes     Current size.
	 * @param  int $threshold Size to compare against.
	 * @return bool
	 */
	public static function should_rotate( int $bytes, int $threshold ): bool {
		return $threshold > 0 && $bytes >= $threshold;
	}

	/**
	 * Name for a rotated copy.
	 *
	 * Timestamped rather than numbered: numbering means renaming every kept file
	 * on every rotation, and a timestamp sorts into the right order for free.
	 *
	 * @param  string $basename Log file name, e.g. 'debug-lwtv.log'.
	 * @param  string $stamp    Pre-formatted stamp, e.g. '20260827-141500'.
	 * @return string
	 */
	public static function rotated_name( string $basename, string $stamp ): string {
		$dot = strrpos( $basename, '.' );

		if ( false === $dot ) {
			return $basename . '-' . $stamp;
		}

		return substr( $basename, 0, $dot ) . '-' . $stamp . substr( $basename, $dot );
	}

	/**
	 * Which rotated files are surplus to requirements?
	 *
	 * Sorted by name, which is chronological given rotated_name(). Newest KEEP
	 * survive; everything older is returned for deletion.
	 *
	 * @param  array<string> $files Rotated file names or paths.
	 * @param  int           $keep  How many to keep.
	 * @return array<string> The ones to delete.
	 */
	public static function prunable( array $files, int $keep ): array {
		$files = array_values( $files );
		sort( $files, SORT_STRING );

		$keep = max( 0, $keep );

		if ( count( $files ) <= $keep ) {
			return array();
		}

		return array_slice( $files, 0, count( $files ) - $keep );
	}

	/**
	 * Filter log lines for the tail command.
	 *
	 * @param  array<string> $lines  Lines, oldest first.
	 * @param  string        $topic  Only lines with this topic, or '' for all.
	 * @param  string        $search Only lines containing this text, or ''.
	 * @return array<string>
	 */
	public static function filter_lines( array $lines, string $topic = '', string $search = '' ): array {
		$topic  = strtolower( trim( $topic ) );
		$search = trim( $search );
		$kept   = array();

		foreach ( $lines as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}

			if ( '' !== $topic && self::topic_from_line( $line ) !== $topic ) {
				continue;
			}

			if ( '' !== $search && false === stripos( $line, $search ) ) {
				continue;
			}

			$kept[] = $line;
		}

		return $kept;
	}

	/**
	 * The last N entries of a filtered set.
	 *
	 * @param  array<string> $lines Filtered lines, oldest first.
	 * @param  int           $count How many to return. 0 or less means all.
	 * @return array<string>
	 */
	public static function tail( array $lines, int $count ): array {
		if ( $count <= 0 || count( $lines ) <= $count ) {
			return array_values( $lines );
		}

		return array_slice( $lines, -$count );
	}
}
