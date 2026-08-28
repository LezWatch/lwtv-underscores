<?php
/**
 * LWTV\_Components\Debugger class.
 *
 * @package LWTV
 */

namespace LWTV\_Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Admin_Menu\Debugging;
use LWTV\Debugger\Build\Log_Rules;
use LWTV\Debugger\Log;

/**
 * Class for debugging. This includes the feature methods:
 * is_dev_site(), is_debug_mode(), and log()
 *
 * The validators and formatters (validate_imdb, validate_wikidata_id,
 * sanitize_social, format_wikidate) are static: they hold no state and are
 * called per-post inside scan loops, where instantiating for each call was
 * pure waste. The rest stay instance methods because get_template_tags()
 * binds them with array( $this, ... ).
 */
class Debugger implements Component, Templater {

	/**
	 * Memoised debug-mode answer for this request, or null before it is known.
	 *
	 * debug_log() is called from 306 sites -- `statistics` alone from 104, many
	 * inside cache-warming loops -- and each call used to run get_field() twice.
	 *
	 * Only ever set once the answer is *authoritative*. See is_debug_mode().
	 *
	 * @var bool|null
	 */
	private static ?bool $debug_mode = null;

	/**
	 * Memoised list of enabled topics, or null before it is known.
	 *
	 * @var array<string>|null
	 */
	private static ?array $enabled_topics = null;

	/**
	 * Unknown topics already reported this request, as a set.
	 *
	 * @var array<string, bool>
	 */
	private static array $reported = array();

	/**
	 * Init the component. Hooks go in here.
	 *
	 * @return void
	 */
	public function init(): void {
		// Void
	}

	/**
	 * Get the template tags.
	 *
	 * @return array
	 */
	public function get_template_tags(): array {
		return array(
			'is_dev_site'      => array( $this, 'is_dev_site' ),
			'is_debug_mode'    => array( $this, 'is_debug_mode' ),
			'is_topic_enabled' => array( $this, 'is_topic_enabled' ),
			'debug_log'        => array( $this, 'debug_log' ),
			'error_log'        => array( $this, 'error_log' ),
		);
	}

	/**
	 * Sanitize social media handles
	 * @param  string $usename Username
	 * @param  string $social  Social Media Type
	 * @return string          sanitized username
	 */
	public static function sanitize_social( $usename, $social ): string {

		// Defaults.
		$trim  = 10;
		$regex = '/[^a-zA-Z_.0-9]/';

		switch ( $social ) {
			case 'instagram': // ex: https://instagram.com/lezwatchtv
				$usename = str_replace( 'https://instagram.com/', '', $usename );
				$trim    = 30;
				break;
			case 'twitter': // ex: https://twitter.com/lezwatchtv OR https://x.com/lezwatchtv
				$usename = str_replace( 'https://twitter.com/', '', $usename );
				$usename = str_replace( 'https://x.com/', '', $usename );
				$trim    = 15;
				break;
			case 'mastodon': // ex: https://mstdn.social/@lezwatchtv
				$regex = '/[^a-zA-Z_.0-9:\/@]/';
				$trim  = 2000;
				break;
		}

		// Remove all illegal characters.
		$clean = preg_replace( $regex, '', trim( $usename ) );

		$clean = substr( $clean, 0, $trim );

		return $clean;
	}

	/**
	 * Clean up the WikiDate
	 * @param  string $date Wiki formatted date: +1968-07-07T00:00:00Z
	 * @return string      LezWatch formatted date: 1968-07-07
	 */
	public static function format_wikidate( $date ): string {
		$clean = trim( substr( $date, 0, strpos( $date, 'T' ) ), '+' );
		return $clean;
	}

	/**
	 * Validate IMDB
	 * @param  string  $imdb IMDB ID
	 * @return boolean         true/false
	 */
	public static function validate_imdb( $imdb, $type = 'show' ): bool {

		// Defaults
		$type = ( ! in_array( $type, array( 'show', 'actor' ), true ) ) ? 'show' : $type;

		switch ( $type ) {
			case 'show':
				$substr = 'tt';
				break;
			case 'actor':
				$substr = 'nm';
				break;
			default:
				$substr = 'tt';
				break;
		}

		// IMDB looks like tt123456 or nm12356
		if ( substr( $imdb, 0, 2 ) !== $substr || ! is_numeric( substr( $imdb, 2 ) ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Validate WikiData ID.
	 *
	 * They're always Q and a number (i.e. Q12345)
	 *
	 * @param  string $wiki_id
	 * @return bool
	 */
	public static function validate_wikidata_id( $wiki_id ): bool {
		// If it doesn't start with a Q, fail.
		if ( ! str_starts_with( $wiki_id, 'Q' ) ) {
			return false;
		}

		// Remove the Q:
		$no_qid = (int) ltrim( $wiki_id, 'Q' );

		// If it doesn't end with a number, fail.
		if ( 0 === $no_qid ) {
			return false;
		}

		// Otherwise true.
		return true;
	}

	/**
	 * Check if the site is in dev mode.
	 *
	 * @return bool
	 */
	public function is_dev_site(): bool {
		return defined( 'LWTV_DEV_SITE' ) && LWTV_DEV_SITE;
	}

	/**
	 * Check if the site is in debug mode.
	 *
	 * Checks both WP_DEBUG constant and custom LWTV debug mode option.
	 *
	 * WP_DEBUG forcing this on is deliberate: a development environment should
	 * not also need the option ticked.
	 *
	 * @return bool
	 */
	public function is_debug_mode(): bool {
		if ( null !== self::$debug_mode ) {
			return self::$debug_mode;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			self::$debug_mode = true;
			return true;
		}

		/*
		 * Deliberately NOT memoised while ACF is unavailable. debug_log() is
		 * called during bootstrap, before acf/init, so caching "off" from an
		 * early call would silence the rest of the request.
		 */
		if ( ! function_exists( 'get_field' ) ) {
			return false;
		}

		self::$debug_mode = (bool) get_field( 'debug_mode', 'option' );

		return self::$debug_mode;
	}

	/**
	 * Check if a specific topic is enabled for logging.
	 *
	 * @param string $topic The topic to check.
	 *
	 * @return bool
	 */
	public function is_topic_enabled( string $topic ): bool {
		/*
		 * ACF is not loaded yet, so what is ticked cannot be known. Fail open
		 * here -- losing bootstrap-time logs is worse than a few extra lines --
		 * but only for topics in the vocabulary, so this is not a way around it.
		 *
		 * This is a separate case from an empty selection, which now means
		 * silence. See Log_Rules::topic_enabled().
		 */
		if ( ! function_exists( 'get_field' ) ) {
			return Log_Rules::is_known_topic( $topic, Debugging::VALID_LOG_TOPICS );
		}

		return Log_Rules::topic_enabled( $topic, $this->enabled_topics(), Debugging::VALID_LOG_TOPICS );
	}

	/**
	 * Topics the editor has ticked, memoised for the request.
	 *
	 * @return array<string>
	 */
	private function enabled_topics(): array {
		if ( null !== self::$enabled_topics ) {
			return self::$enabled_topics;
		}

		if ( ! function_exists( 'get_field' ) ) {
			return array();
		}

		$topics = get_field( 'log_topics', 'option' );

		self::$enabled_topics = is_array( $topics )
			? array_values( array_filter( array_map( 'strval', $topics ) ) )
			: array();

		return self::$enabled_topics;
	}

	/**
	 * Log a debug message to debug-lwtv.log.
	 *
	 * Only logs if debug mode is enabled AND the topic is both known and
	 * enabled. An unknown topic is refused rather than written, and reported
	 * once per request to PHP's error log so the mistake is discoverable --
	 * silence is a better failure than an unstoppable one, but it is still a
	 * failure, and this is how you find out.
	 *
	 * @param string $type    The type/topic of log message.
	 * @param string $message The message to log.
	 *
	 * @return void
	 */
	public function debug_log( $type = 'debug', $message = '' ): void {
		if ( ! $this->is_debug_mode() || empty( $message ) ) {
			return;
		}

		$topic = strtolower( trim( (string) $type ) );

		if ( ! Log_Rules::is_known_topic( $topic, Debugging::VALID_LOG_TOPICS ) ) {
			$this->report_unknown_topic( $topic );
			return;
		}

		if ( ! $this->is_topic_enabled( $topic ) ) {
			return;
		}

		Log::append( Log_Rules::line( $topic, (string) $message, gmdate( 'Y-m-d H:i:s' ) ) );
	}

	/**
	 * Complain about an undeclared topic, once per topic per request.
	 *
	 * Goes to PHP's error log rather than ours: the point is that this topic
	 * cannot be written to ours, and a message about a broken topic should not
	 * be filed under the broken topic.
	 *
	 * @param  string $topic The topic that is not in the vocabulary.
	 * @return void
	 */
	private function report_unknown_topic( string $topic ): void {
		if ( '' === $topic || isset( self::$reported[ $topic ] ) ) {
			return;
		}

		self::$reported[ $topic ] = true;

		$this->error_log(
			'lwtv',
			sprintf(
				'debug_log() was called with the undeclared topic "%s", so nothing was written. Add it to Admin_Menu\Debugging::VALID_LOG_TOPICS.',
				$topic
			)
		);
	}

	/**
	 * Log a message straight to PHP's error log.
	 *
	 * Ignores debug mode and topics entirely, which is the point: it is the
	 * escape hatch for "this needs saying regardless". Prefer debug_log() for
	 * anything that belongs to a topic.
	 *
	 * @param string $type    The type of log message.
	 * @param string $message The message to log.
	 *
	 * @return void
	 */
	public function error_log( $type = 'debug', $message = '' ): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[' . ucwords( $type ) . '] ' . $message );
	}
}
