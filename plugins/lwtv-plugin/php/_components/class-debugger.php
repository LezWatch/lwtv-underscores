<?php
/**
 * LWTV\_Components\Debugger class.
 *
 * @package LWTV
 */

namespace LWTV\_Components;

/**
 * Class for debugging. This includes the feature methods:
 * is_dev_site(), is_debug_mode(), and log()
 *
 */
class Debugger implements Component, Templater {

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
			'is_dev_site'   => array( $this, 'is_dev_site' ),
			'is_debug_mode' => array( $this, 'is_debug_mode' ),
			'error_log'     => array( $this, 'error_log' ),
		);
	}

	/**
	 * Sanitize social media handles
	 * @param  string $usename Username
	 * @param  string $social  Social Media Type
	 * @return string          sanitized username
	 */
	public function sanitize_social( $usename, $social ): string {

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
	public function format_wikidate( $date ): string {
		$clean = trim( substr( $date, 0, strpos( $date, 'T' ) ), '+' );
		return $clean;
	}

	/**
	 * Validate IMDB
	 * @param  string  $imdb IMDB ID
	 * @return boolean         true/false
	 */
	public function validate_imdb( $imdb, $type = 'show' ): bool {

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
	public function validate_wikidata_id( $wiki_id ): bool {
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
	 * @return bool
	 */
	public function is_debug_mode(): bool {
		return defined( 'WP_DEBUG' ) && WP_DEBUG;
	}

	/**
	 * Log a message.
	 *
	 * @param string $type    The type of log message.
	 * @param string $message The message to log.
	 *
	 * @return void
	 */
	public function error_log( $type = 'debug', $message = '' ): void {
		if ( ! $this->is_debug_mode() || empty( $message ) ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[' . ucwords( $type ) . '] ' . $message );
	}
}
