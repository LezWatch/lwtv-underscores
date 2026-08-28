<?php
/**
 * The debug log file itself: write, rotate, prune, read.
 *
 * Everything here touches the filesystem, which is why it is separate from
 * Build\Log_Rules -- that holds the decisions and is unit-tested; this holds the
 * side effects and is verified against a running site.
 *
 * The option reads that decide *whether* to log stay in _Components\Debugger,
 * which owns the template tags. This class is told what to write, not whether.
 *
 * See DEBUGGER-REVIEW.md 6.
 *
 * @package LWTV
 */

namespace LWTV\Debugger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Debugger\Build\Log_Rules;

class Log {

	/**
	 * The log file's name, inside WP_CONTENT_DIR.
	 *
	 * Unchanged from the original inline path so existing logs and any server
	 * housekeeping keep working.
	 */
	const FILENAME = 'debug-lwtv.log';

	/**
	 * How much of the file the reader will pull into memory, in bytes.
	 *
	 * Tailing does not need the whole file, and the hard cap allows 10MB.
	 */
	const READ_BYTES = 2097152;

	/**
	 * Size seen by this request's cap check, or null before it has run.
	 *
	 * One filesize() per request rather than one per debug_log() call. With 306
	 * call sites and `statistics` alone accounting for 104, per-call would be
	 * the expensive kind of safety.
	 *
	 * @var int|null
	 */
	private static ?int $guarded = null;

	/**
	 * Full path to the live log.
	 *
	 * @return string
	 */
	public static function path(): string {
		return WP_CONTENT_DIR . '/' . self::FILENAME;
	}

	/**
	 * Current size of the live log, in bytes.
	 *
	 * @return int
	 */
	public static function size(): int {
		if ( ! file_exists( self::path() ) ) {
			return 0;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_filesize -- Reading a local log file's size; WP_Filesystem is not loaded on the front end.
		$bytes = filesize( self::path() );

		return is_int( $bytes ) ? $bytes : 0;
	}

	/**
	 * Append one already-formatted line.
	 *
	 * @param  string $line Line to write, newline included.
	 * @return bool Whether the write was attempted.
	 */
	public static function append( string $line ): bool {
		if ( '' === $line ) {
			return false;
		}

		self::guard();

		// error_log()'s message_type 3 is an append-and-close, which is what the
		// original implementation used. Keeping it avoids holding a handle open
		// across a request that may write hundreds of lines.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		return error_log( $line, 3, self::path() );
	}

	/**
	 * Rotate mid-request if the log has run away between cron runs.
	 *
	 * Checked once per request, not once per line. A single request could in
	 * principle write a long way past the cap after the check passes, but a
	 * request writes kilobytes and cron rotates daily; this exists to stop a
	 * runaway loop filling a disk, not to enforce the cap to the byte.
	 *
	 * @return void
	 */
	private static function guard(): void {
		if ( null !== self::$guarded ) {
			return;
		}

		self::$guarded = self::size();

		if ( Log_Rules::should_rotate( self::$guarded, Log_Rules::MAX_BYTES ) ) {
			self::rotate( Log_Rules::MAX_BYTES );
			self::$guarded = 0;
		}
	}

	/**
	 * Rotate the log if it has passed a threshold.
	 *
	 * @param  int $threshold Size in bytes at which to rotate.
	 * @return string The rotated file's path, or '' when nothing was rotated.
	 */
	public static function rotate( int $threshold = Log_Rules::ROTATE_AT ): string {
		$path = self::path();

		if ( ! file_exists( $path ) || ! Log_Rules::should_rotate( self::size(), $threshold ) ) {
			return '';
		}

		$target = WP_CONTENT_DIR . '/' . Log_Rules::rotated_name( self::FILENAME, gmdate( 'Ymd-His' ) );

		// Two processes rotating at once means one of them renames a file that is
		// already gone. Nothing is lost either way, so a failed rename is not an
		// error worth reporting.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename, WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! @rename( $path, $target ) ) {
			return '';
		}

		self::$guarded = 0;

		self::prune();

		return $target;
	}

	/**
	 * Every rotated log, oldest first.
	 *
	 * @return array<string> Full paths.
	 */
	public static function rotated(): array {
		$pattern = WP_CONTENT_DIR . '/' . Log_Rules::rotated_name( self::FILENAME, '*' );
		$found   = glob( $pattern );

		if ( ! is_array( $found ) ) {
			return array();
		}

		sort( $found, SORT_STRING );

		return $found;
	}

	/**
	 * Delete rotated logs beyond the retention count.
	 *
	 * @param  int $keep How many to keep.
	 * @return array<string> Paths that were deleted.
	 */
	public static function prune( int $keep = Log_Rules::KEEP ): array {
		$deleted = array();

		foreach ( Log_Rules::prunable( self::rotated(), $keep ) as $path ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.PHP.NoSilencedErrors.Discouraged
			if ( @unlink( $path ) ) {
				$deleted[] = $path;
			}
		}

		return $deleted;
	}

	/**
	 * Read the tail of the log as lines, oldest first.
	 *
	 * Only the last READ_BYTES are read, and a partial first line is dropped so
	 * a truncated entry is never shown as though it were whole.
	 *
	 * @param  string $path Which file to read. Defaults to the live log.
	 * @return array<string>
	 */
	public static function lines( string $path = '' ): array {
		$path = ( '' === $path ) ? self::path() : $path;

		if ( ! file_exists( $path ) ) {
			return array();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_filesize -- Local log file.
		$bytes  = (int) filesize( $path );
		$offset = max( 0, $bytes - self::READ_BYTES );

		$body = file_get_contents( $path, false, null, $offset, self::READ_BYTES );

		if ( ! is_string( $body ) || '' === $body ) {
			return array();
		}

		$lines = explode( "\n", $body );

		// A non-zero offset almost certainly landed mid-line.
		if ( $offset > 0 ) {
			array_shift( $lines );
		}

		return $lines;
	}

	/**
	 * Empty the live log without rotating it.
	 *
	 * @return bool
	 */
	public static function clear(): bool {
		if ( ! file_exists( self::path() ) ) {
			return true;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$written = file_put_contents( self::path(), '' );

		self::$guarded = 0;

		return false !== $written;
	}
}
