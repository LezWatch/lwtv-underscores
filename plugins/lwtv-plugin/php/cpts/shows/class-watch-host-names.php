<?php
/**
 * Name: Watch Host Names
 * Description: Cache of provider names discovered from a host's own metadata.
 *
 * A lez_watch_urls term always wins. This is for the hosts that don't have one
 * and probably never will -- the long tail of web series on their own domains.
 * Host_Name can only ever guess from the hostname ("Tubitv", "Onemorelesbian");
 * asking the site what it calls itself does better, and it only has to be asked
 * once per host.
 *
 * Populated by `wp lwtv waystowatch enrich`. Never fetches anything on a page
 * request -- rendering only ever reads this option.
 *
 * @package LWTV
 */

namespace LWTV\CPTs\Shows;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Watch_Host_Names {

	/**
	 * Option holding the host => record map. Not autoloaded.
	 */
	const OPTION = 'lwtv_watch_host_names';

	/**
	 * Where a name came from. Anything other than 'none' is usable.
	 */
	const SOURCE_OG_SITE_NAME = 'og:site_name';
	const SOURCE_APP_NAME     = 'application-name';
	const SOURCE_NONE         = 'none';

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
	 * Lets the enrich command skip hosts it has already asked about, while
	 * keeping "asked, found nothing" distinct from "never asked" -- the same
	 * distinction the TMDB backfill needs, for the same reason.
	 *
	 * @param string $host Hostname.
	 * @return bool
	 */
	public static function is_checked( string $host ): bool {
		return isset( self::all()[ Host_Name::normalise( $host ) ] );
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

		$map          = self::all();
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
	 * og:site_name is usually clean, but not always -- sites put taglines,
	 * full sentences and even URLs in it. Reject the obvious rubbish rather
	 * than putting it on a button.
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
