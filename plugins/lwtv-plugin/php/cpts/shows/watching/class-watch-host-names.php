<?php
/**
 * Name: Watch Host Names
 * Description: Cache of provider names discovered from a host's own metadata.
 *
 * A lez_watch_urls term always wins. This is for the hosts that don't have one
 * and probably never will.
 *
 * Populated by `wp lwtv waystowatch enrich`. Never fetches anything on a page
 * request -- rendering only ever reads this option.
 *
 * @package LWTV
 */

namespace LWTV\CPTs\Shows\Watching;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Watch_Host_Names {

	/**
	 * Option holding the host => record map. Not autoloaded.
	 */
	const OPTION = 'lwtv_watch_host_names';

	/**
	 * Where a name came from. Anything other than 'none' or 'error' is usable.
	 */
	const SOURCE_OG_SITE_NAME = 'og:site_name';
	const SOURCE_APP_NAME     = 'application-name';
	const SOURCE_NONE         = 'none';
	const SOURCE_ERROR        = 'error';

	/**
	 * How many times to try a host that will not answer before giving up on it.
	 */
	const MAX_ATTEMPTS = 3;

	/**
	 * Longest name we'll accept. Anything past this is a tagline, not a name.
	 */
	const MAX_NAME_LENGTH = 40;

	/**
	 * Request-level memo so a page with several links reads the option once.
	 *
	 * @var array<string, array>|null
	 */
	private static $cache = null;

	/**
	 * The whole map: normalised host => record.
	 *
	 * Each record is array{ name: string, source: string, checked: int }.
	 *
	 * @return array<string, array>
	 */
	public static function all(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$stored      = get_option( self::OPTION );
		self::$cache = is_array( $stored ) ? $stored : array();

		return self::$cache;
	}

	/**
	 * The discovered name for a host, if we have a usable one.
	 *
	 * @param string $host Hostname, any form.
	 * @return string|null Null when unknown or known-unusable.
	 */
	public static function get( string $host ): ?string {
		$record = self::all()[ Host_Name::normalise( $host ) ] ?? null;

		if ( ! is_array( $record ) || empty( $record['name'] ) ) {
			return null;
		}

		return (string) $record['name'];
	}

	/**
	 * Has this host been looked at, regardless of outcome?
	 *
	 * @param string $host Hostname.
	 * @return bool
	 */
	public static function is_checked( string $host ): bool {
		return isset( self::all()[ Host_Name::normalise( $host ) ] );
	}

	/**
	 * Is it still worth asking this host what it calls itself?
	 *
	 * @param string $host Hostname.
	 * @return bool
	 */
	public static function should_ask( string $host ): bool {
		$record = self::all()[ Host_Name::normalise( $host ) ] ?? null;

		// Never looked at.
		if ( ! is_array( $record ) ) {
			return true;
		}

		// Asked and answered, one way or the other.
		if ( self::SOURCE_ERROR !== ( $record['source'] ?? '' ) ) {
			return false;
		}

		return (int) ( $record['attempts'] ?? 0 ) < self::MAX_ATTEMPTS;
	}

	/**
	 * How many times a host has failed to answer.
	 *
	 * @param string $host Hostname.
	 * @return int
	 */
	public static function attempts( string $host ): int {
		$record = self::all()[ Host_Name::normalise( $host ) ] ?? null;

		return is_array( $record ) ? (int) ( $record['attempts'] ?? 0 ) : 0;
	}

	/**
	 * Record that a host would not answer.
	 *
	 * @param string $host Hostname.
	 * @return void
	 */
	public static function fail( string $host ): void {
		$host = Host_Name::normalise( $host );

		if ( '' === $host ) {
			return;
		}

		$map          = self::all();
		$attempts     = (int) ( $map[ $host ]['attempts'] ?? 0 );
		$map[ $host ] = array(
			'name'     => '',
			'source'   => self::SOURCE_ERROR,
			'attempts' => $attempts + 1,
			'checked'  => time(),
		);

		self::$cache = $map;
		update_option( self::OPTION, $map, false );
	}

	/**
	 * Record the outcome of a lookup.
	 *
	 * @param string $host   Hostname.
	 * @param string $name   Discovered name, or '' when none was found.
	 * @param string $source One of the SOURCE_* constants.
	 * @return void
	 */
	public static function set( string $host, string $name, string $source ): void {
		$host = Host_Name::normalise( $host );

		if ( '' === $host ) {
			return;
		}

		$map = self::all();

		// No `attempts`: a host that answers has stopped failing, and leaving a
		// count behind would make a future failure look like the third strike
		// rather than the first.
		$map[ $host ] = array(
			'name'    => self::sanitize_name( $name ),
			'source'  => $source,
			'checked' => time(),
		);

		self::$cache = $map;
		update_option( self::OPTION, $map, false );
	}

	/**
	 * Forget one host, or everything.
	 *
	 * @param string $host Hostname, or '' for all.
	 * @return void
	 */
	public static function forget( string $host = '' ): void {
		if ( '' === $host ) {
			self::$cache = array();
			delete_option( self::OPTION );
			return;
		}

		$map = self::all();
		unset( $map[ Host_Name::normalise( $host ) ] );

		self::$cache = $map;
		update_option( self::OPTION, $map, false );
	}

	/**
	 * Is this string plausibly a site name?
	 *
	 * og:site_name is usually clean, but not always.
	 *
	 * @param string $name Candidate.
	 * @return bool
	 */
	public static function is_plausible_name( string $name ): bool {
		$name = self::sanitize_name( $name );

		if ( '' === $name || strlen( $name ) > self::MAX_NAME_LENGTH ) {
			return false;
		}

		// A URL is not a name.
		if ( preg_match( '#^(https?:)?//#i', $name ) || str_contains( $name, '://' ) ) {
			return false;
		}

		// Taglines and sentences, not names.
		if ( str_word_count( $name ) > 5 ) {
			return false;
		}

		// Needs at least one letter or digit somewhere.
		return (bool) preg_match( '/[\p{L}\p{N}]/u', $name );
	}

	/**
	 * Tidy a candidate name.
	 *
	 * @param string $name Raw value.
	 * @return string
	 */
	public static function sanitize_name( string $name ): string {
		$name = wp_strip_all_tags( $name );
		$name = html_entity_decode( $name, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$name = preg_replace( '/\s+/u', ' ', $name );

		return trim( (string) $name );
	}
}
