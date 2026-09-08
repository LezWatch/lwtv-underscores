<?php
/**
 * Name: Host Name
 * Description: Derive a display name from a hostname.
 *
 * Be aware of the ceiling. This gets you from wrong to recognisable, not to
 * right. Which label carries the brand is a semantic question:
 *
 *   netflix.com        -> the registrable label IS the brand.      "Netflix"
 *   abc.go.com         -> the brand is the SUBDOMAIN; go.com is
 *                         Disney's registrable domain.             "GO"
 *   gem.cbc.ca         -> both matter; the product is "CBC Gem".   "CBC"
 *   onemorelesbian.com -> unsplittable without a dictionary.       "Onemorelesbian"
 *
 * Which is fine, because a lez_watch_urls term overrides all of this, and
 * host_candidates() orders matches so a term on 'abc.go.com' beats one on
 * 'go.com'. Give a host a term when the guess isn't good enough; that is what
 * the taxonomy is for, and no parser will resolve these.
 *
 * @package LWTV
 */

namespace LWTV\CPTs\Shows\Watching;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Host_Name {

	/**
	 * Subdomain prefixes that never carry the brand, so they're safe to drop.
	 */
	const GENERIC_SUBDOMAINS = array(
		'www.',
		'watch.',
		'play.',
		'stream.',
		'video.',
		'premium.',
		'app.',
		'm.',
	);

	/**
	 * Two-label public suffixes. When a host ends in one of these, the name is
	 * the third label from the right, not the second.
	 */
	const COMPOUND_SUFFIXES = array(
		// UK
		'co.uk',
		'org.uk',
		'ac.uk',
		'gov.uk',
		'me.uk',
		'net.uk',
		'ltd.uk',
		'plc.uk',
		// Australia / NZ
		'com.au',
		'net.au',
		'org.au',
		'id.au',
		'co.nz',
		'net.nz',
		'org.nz',
		'ac.nz',
		// Americas
		'com.br',
		'com.mx',
		'com.ar',
		'com.co',
		'com.pe',
		'com.uy',
		'com.ve',
		'com.ec',
		// Asia
		'co.jp',
		'ne.jp',
		'or.jp',
		'co.kr',
		'co.in',
		'com.cn',
		'com.hk',
		'com.tw',
		'com.sg',
		'com.my',
		'com.ph',
		'com.vn',
		'co.th',
		'com.tr',
		// Europe / other
		'com.es',
		'com.pl',
		'com.ua',
		'com.pt',
		'co.il',
		'co.za',
	);

	/**
	 * Normalise a hostname for use as a cache key.
	 *
	 * Only 'www.' is dropped, because that is the one prefix guaranteed to mean
	 * the same site. 'abc.go.com' must stay distinct from 'go.com'.
	 *
	 * @param string $host Raw hostname.
	 * @return string Lowercased host without a leading 'www.'.
	 */
	public static function normalise( string $host ): string {
		$host = strtolower( trim( $host ) );
		$host = trim( $host, '.' );

		if ( str_starts_with( $host, 'www.' ) ) {
			$host = substr( $host, 4 );
		}

		return $host;
	}

	/**
	 * The label that carries the name.
	 *
	 * @param string $host Hostname.
	 * @return string One label, or '' when there's nothing usable.
	 */
	public static function registrable_label( string $host ): string {
		$host = self::normalise( $host );

		if ( '' === $host ) {
			return '';
		}

		$floor = self::registrable_floor( explode( '.', $host ) );

		// Drop generic subdomains, repeatedly: 'watch.play.foo.com' happens.
		$changed = true;
		while ( $changed ) {
			$changed = false;

			foreach ( self::GENERIC_SUBDOMAINS as $prefix ) {
				if ( ! str_starts_with( $host, $prefix ) ) {
					continue;
				}

				$candidate = substr( $host, strlen( $prefix ) );

				// Never strip past the registrable domain. Without this,
				// 'go.com' loses its own name to the 'go.' prefix and comes
				// back as 'com'.
				if ( '' === $candidate || count( explode( '.', $candidate ) ) < $floor ) {
					continue;
				}

				$host    = $candidate;
				$changed = true;
				break;
			}
		}

		$labels = explode( '.', $host );
		$count  = count( $labels );

		// Bare hostname, no dots.
		if ( $count < 2 ) {
			return $labels[0];
		}

		// A two-label public suffix pushes the name one place further left.
		if ( $count >= 3 && in_array( $labels[ $count - 2 ] . '.' . $labels[ $count - 1 ], self::COMPOUND_SUFFIXES, true ) ) {
			return $labels[ $count - 3 ];
		}

		return $labels[ $count - 2 ];
	}

	/**
	 * The registrable domain: the shortest form that still identifies an owner.
	 *
	 * @param string $host Hostname.
	 * @return string Registrable domain, or '' when there's nothing usable.
	 */
	public static function registrable_domain( string $host ): string {
		$host = self::normalise( $host );

		if ( '' === $host ) {
			return '';
		}

		$labels = explode( '.', $host );
		$count  = count( $labels );
		$floor  = self::registrable_floor( $labels );

		// A bare hostname, or one already at or below the floor, is its own
		// registrable domain. 'localhost' and 'globo.com' both land here.
		if ( $count <= $floor ) {
			return $host;
		}

		return implode( '.', array_slice( $labels, $count - $floor ) );
	}

	/**
	 * How many labels the registrable domain needs.
	 *
	 * Three when the suffix is compound ('abc.net.au'), otherwise two
	 * ('globo.com').
	 *
	 * @param array<string> $labels Host split on dots.
	 * @return int
	 */
	private static function registrable_floor( array $labels ): int {
		$count = count( $labels );

		if ( $count >= 3 && in_array( $labels[ $count - 2 ] . '.' . $labels[ $count - 1 ], self::COMPOUND_SUFFIXES, true ) ) {
			return 3;
		}

		return 2;
	}

	/**
	 * Best-effort display name for a hostname.
	 *
	 * Short labels are almost always acronyms (abc, cbs, hbo, ifc), so those are
	 * upper-cased; anything longer gets its first letter capitalised.
	 *
	 * @param string $host Hostname.
	 * @return string Never empty.
	 */
	public static function guess( string $host ): string {
		$label = self::registrable_label( $host );

		if ( '' === $label ) {
			return 'Watch Online';
		}

		return ( strlen( $label ) <= 3 ) ? strtoupper( $label ) : ucfirst( $label );
	}

	/**
	 * Host forms worth trying when looking for a matching term, most specific
	 * first.
	 *
	 * Built by dropping leading labels one at a time rather than matching a list
	 * of known prefixes, so 'gshow.globo.com' offers 'globo.com' without anyone
	 * having to have listed 'gshow.' anywhere. Stops at the registrable domain
	 * so it never degrades to a bare public suffix like 'co.uk'.
	 *
	 * Order matters: callers should prefer the earliest match, so a term on
	 * 'abc.go.com' wins over one on 'go.com'.
	 *
	 * @param string $host Hostname.
	 * @return array<string> Unique hosts, most specific first.
	 */
	public static function host_candidates( string $host ): array {
		$host = self::normalise( $host );

		if ( '' === $host ) {
			return array();
		}

		$labels = explode( '.', $host );
		$count  = count( $labels );

		// Smallest meaningful form: the registrable domain.
		$floor = self::registrable_floor( $labels );

		$candidates = array();
		for ( $i = 0; $i <= $count - $floor; $i++ ) {
			$candidates[] = implode( '.', array_slice( $labels, $i ) );
		}

		// Editors are inconsistent about www, so offer it both ways.
		$with_www = array();
		foreach ( $candidates as $candidate ) {
			$with_www[] = $candidate;
			$with_www[] = 'www.' . $candidate;
		}

		return array_values( array_unique( $with_www ) );
	}
}
