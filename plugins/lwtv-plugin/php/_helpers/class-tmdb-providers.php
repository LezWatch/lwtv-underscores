<?php
/**
 * TMDB watch providers.
 *
 * Reduces a /tv/{id}/watch/providers response to what we store and render.
 * Pure: the response is third-party input that ends up in post meta and in an
 * href, so this keeps only the fields we use and refuses anything that could not
 * be rendered safely. See docs/plans/tmdb-watch-providers.md.
 *
 * @package lwtv-plugin
 */

namespace LWTV\_Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tmdb_Providers {

	/**
	 * TMDB's availability types, in the order we list them.
	 *
	 * flatrate is subscription streaming; ads and free are what they say. rent and
	 * buy are stored but not shown by default.
	 */
	const TYPES = array( 'flatrate', 'free', 'ads', 'rent', 'buy' );

	/**
	 * The types shown on a show page.
	 */
	const DISPLAY_TYPES = array( 'flatrate', 'free', 'ads' );

	/**
	 * How the strip groups the displayed types, in drawing order. A service in
	 * two groups is drawn in the first.
	 */
	const GROUPS = array(
		'stream' => array( 'flatrate' ),
		'free'   => array( 'free', 'ads' ),
	);

	/**
	 * The only host a stored link may point at.
	 */
	const LINK_HOSTS = array( 'www.themoviedb.org', 'themoviedb.org' );

	/**
	 * What a logo_path looks like: one path segment and an image extension.
	 */
	const LOGO_PATTERN = '~^/[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|svg)$~';

	/**
	 * Where logos are served from: TMDB's image CDN at 92 px, twice the drawn
	 * size so they stay sharp on high-density screens.
	 */
	const LOGO_BASE = 'https://image.tmdb.org/t/p/w92';

	/**
	 * A reseller suffix on a provider name: another company's storefront selling
	 * the service. Only these three; "The Roku Channel" and "Plex Channel" are
	 * services of their own.
	 */
	const RESELLER_PATTERN = '/\s+(?:amazon|apple\s+tv|roku\s+premium)\s+channel$/iu';

	/**
	 * Reduce a decoded response to country => { link, <type> => [ {id, name, logo} ] }.
	 *
	 * @param mixed $response A decoded TMDB response, or anything else.
	 *
	 * @return array<string, array<string, mixed>> Empty when the response is not an answer.
	 */
	public static function normalise( $response ): array {
		if ( ! is_array( $response ) || isset( $response['status_message'] ) ) {
			return array();
		}

		if ( ! isset( $response['results'] ) || ! is_array( $response['results'] ) ) {
			return array();
		}

		$out = array();

		foreach ( $response['results'] as $code => $block ) {
			$code = strtoupper( (string) $code );

			if ( 1 !== preg_match( '/^[A-Z]{2}$/', $code ) || ! is_array( $block ) ) {
				continue;
			}

			$link = self::link( $block['link'] ?? null );

			if ( '' === $link ) {
				continue;
			}

			$country = array( 'link' => $link );
			$any     = false;

			foreach ( self::TYPES as $type ) {
				$country[ $type ] = self::providers( $block[ $type ] ?? null );
				$any              = $any || ! empty( $country[ $type ] );
			}

			if ( $any ) {
				$out[ $code ] = $country;
			}
		}

		ksort( $out );

		return $out;
	}

	/**
	 * One country's providers for the displayed types, one entry per service.
	 *
	 * Types are read in TYPES order (streaming first), and a service that is in
	 * two types appears once.
	 *
	 * @param array<string, array<string, mixed>> $data    normalise() output.
	 * @param string                              $country Country code, any case.
	 * @param array<int, string>                  $types   Types to include.
	 *
	 * @return array<int, array{id: int, name: string, logo: string}>
	 */
	public static function for_country( array $data, string $country, array $types ): array {
		$country = strtoupper( $country );
		$types   = array_values( array_intersect( self::TYPES, $types ) );
		$found   = array();

		foreach ( $types as $type ) {
			foreach ( $data[ $country ][ $type ] ?? array() as $provider ) {
				if ( ! isset( $found[ $provider['id'] ] ) ) {
					$found[ $provider['id'] ] = $provider;
				}
			}
		}

		return self::drop_variants( array_values( $found ) );
	}

	/**
	 * Drop resold copies of a service that is already in the list.
	 *
	 * TMDB lists a service once directly and again per reseller, for example
	 * "HBO Max" and "HBO Max Amazon Channel" in the US, and ranks the reseller
	 * first. On a strip of logos that reads as the same service twice. A variant
	 * that is the only way to a service ("HBO Max on U-Next" in Japan) stays.
	 *
	 * Display only: normalise() stores every entry, so this can change without a
	 * refetch.
	 *
	 * @param array<int, array{id: int, name: string, logo: string}> $providers for_country() list, in order.
	 *
	 * @return array<int, array{id: int, name: string, logo: string}>
	 */
	private static function drop_variants( array $providers ): array {
		$names = array_map(
			static function ( $provider ) {
				return self::spaced_name( $provider['name'] );
			},
			$providers
		);

		// 1. A variant is named "<listed service> <anything>": a whole-word prefix,
		// so "Netflix basic with Ads" goes when "Netflix" is listed and "maxdome
		// Store" does not go for "Max".
		$kept = array_filter(
			$providers,
			static function ( $provider ) use ( $names ) {
				$name = self::spaced_name( $provider['name'] );

				foreach ( $names as $base ) {
					if ( str_starts_with( $name, $base . ' ' ) ) {
						return false;
					}
				}

				return true;
			}
		);

		// 2. Resellers and spellings of one service ("AMC+ Amazon Channel", "AMC
		// Plus Apple TV channel", "AMC+") share a key. One entry per key: the
		// direct service if it is listed, else the first (TMDB's priority).
		$winner = array();

		foreach ( $kept as $index => $provider ) {
			$key    = self::service_key( $provider['name'] );
			$direct = 1 !== preg_match( self::RESELLER_PATTERN, $provider['name'] );

			if ( ! isset( $winner[ $key ] ) || ( $direct && ! $winner[ $key ]['direct'] ) ) {
				$winner[ $key ] = array(
					'index'  => $index,
					'direct' => $direct,
				);
			}
		}

		$keep = array_flip( array_column( $winner, 'index' ) );

		return array_values( array_intersect_key( $kept, $keep ) );
	}

	/**
	 * A name for prefix matching: lower case, "+" read as " plus", single spaces.
	 *
	 * @param string $name Provider name.
	 *
	 * @return string
	 */
	private static function spaced_name( string $name ): string {
		return trim( (string) preg_replace( '/\s+/u', ' ', str_replace( '+', ' plus ', mb_strtolower( $name ) ) ) );
	}

	/**
	 * A service's identity: the name without a reseller suffix, letters and digits
	 * only, so "AcornTV" and "Acorn TV", "AMC+" and "AMC Plus" match.
	 *
	 * @param string $name Provider name.
	 *
	 * @return string
	 */
	private static function service_key( string $name ): string {
		$base = (string) preg_replace( self::RESELLER_PATTERN, '', $name );

		return (string) preg_replace( '/[^\p{L}\p{N}]+/u', '', str_replace( '+', 'plus', mb_strtolower( $base ) ) );
	}

	/**
	 * One country's strip: its TMDB link, the logos to draw, and how many more
	 * the TMDB page has. The browser script applies the same cap.
	 *
	 * @param array<string, array<string, mixed>> $data    normalise() output.
	 * @param array<int, string>                  $types   Types to include.
	 * @param string                              $country Country code, any case.
	 * @param int                                 $cap     Most logos to draw.
	 *
	 * @return array{country: string, link: string, shown: array<int, array{id: int, name: string, logo: string}>, groups: array<string, array<int, int>>, more: int}|array{} Empty when the country has nothing to show.
	 */
	public static function strip( array $data, array $types, string $country, int $cap ): array {
		$country   = strtoupper( $country );
		$providers = self::for_country( $data, $country, $types );

		if ( empty( $providers ) ) {
			return array();
		}

		$shown = array_slice( $providers, 0, max( 1, $cap ) );

		return array(
			'country' => $country,
			'link'    => (string) $data[ $country ]['link'],
			'shown'   => $shown,
			'groups'  => self::group_ids( $data, $country, array_column( $shown, 'id' ) ),
			'more'    => max( 0, count( $providers ) - max( 1, $cap ) ),
		);
	}

	/**
	 * Sort provider IDs into GROUPS for one country, keeping their order.
	 *
	 * Each ID goes to the first group with a type that lists it. IDs the country
	 * does not list, and empty groups, are left out.
	 *
	 * @param array<string, array<string, mixed>> $data    normalise() output.
	 * @param string                              $country Country code, any case.
	 * @param array<int, int>                     $ids     Provider IDs, in display order.
	 *
	 * @return array<string, array<int, int>> Group key => IDs.
	 */
	public static function group_ids( array $data, string $country, array $ids ): array {
		$country = strtoupper( $country );
		$listed  = array();

		foreach ( self::GROUPS as $group => $types ) {
			foreach ( $types as $type ) {
				foreach ( $data[ $country ][ $type ] ?? array() as $provider ) {
					$listed[ $provider['id'] ] ??= $group;
				}
			}
		}

		$out = array_fill_keys( array_keys( self::GROUPS ), array() );

		foreach ( $ids as $id ) {
			if ( isset( $listed[ $id ] ) ) {
				$out[ $listed[ $id ] ][] = $id;
			}
		}

		return array_filter( $out );
	}

	/**
	 * A logo's full URL, or '' for a provider without one.
	 *
	 * @param string $logo A normalised logo path.
	 *
	 * @return string
	 */
	public static function logo_url( string $logo ): string {
		return '' === $logo ? '' : self::LOGO_BASE . $logo;
	}

	/**
	 * Countries with at least one provider in the displayed types, sorted.
	 *
	 * @param array<string, array<string, mixed>> $data  normalise() output.
	 * @param array<int, string>                  $types Types to include.
	 *
	 * @return array<int, string>
	 */
	public static function countries( array $data, array $types ): array {
		$codes = array();

		foreach ( array_keys( $data ) as $code ) {
			if ( ! empty( self::for_country( $data, (string) $code, $types ) ) ) {
				$codes[] = (string) $code;
			}
		}

		sort( $codes );

		return $codes;
	}

	/**
	 * The country to show first.
	 *
	 * The visitor's own when we have a list for it, else the US, else the first
	 * country with one. The browser script applies the same order, so the
	 * server-rendered list and the script's pick agree when JavaScript is off or
	 * has no better guess.
	 *
	 * @param array<string, array<string, mixed>> $data     normalise() output.
	 * @param array<int, string>                  $types    Types to include.
	 * @param string                              $detected A guessed country code, or ''.
	 *
	 * @return string A country code, or '' when there is nothing to show.
	 */
	public static function default_country( array $data, array $types, string $detected = '' ): string {
		$available = self::countries( $data, $types );
		$detected  = strtoupper( $detected );

		if ( empty( $available ) ) {
			return '';
		}

		foreach ( array( $detected, 'US' ) as $candidate ) {
			if ( in_array( $candidate, $available, true ) ) {
				return $candidate;
			}
		}

		return $available[0];
	}

	/**
	 * What the browser script needs to switch country: each provider once, and per
	 * country its TMDB link and the provider IDs, in display order.
	 *
	 * Providers are held once because the same service is in most countries.
	 *
	 * @param array<string, array<string, mixed>> $data  normalise() output.
	 * @param array<int, string>                  $types Types to include.
	 *
	 * @return array{providers: array<int, array{name: string, logo: string}>, countries: array<string, array{link: string, ids: array<int, int>, groups: array<string, array<int, int>>}>}
	 */
	public static function payload( array $data, array $types ): array {
		$payload = array(
			'providers' => array(),
			'countries' => array(),
		);

		foreach ( self::countries( $data, $types ) as $code ) {
			$ids = array();

			foreach ( self::for_country( $data, $code, $types ) as $provider ) {
				$ids[] = $provider['id'];

				$payload['providers'][ $provider['id'] ] = array(
					'name' => $provider['name'],
					'logo' => $provider['logo'],
				);
			}

			$payload['countries'][ $code ] = array(
				'link'   => (string) $data[ $code ]['link'],
				'ids'    => $ids,
				'groups' => self::group_ids( $data, $code, $ids ),
			);
		}

		return $payload;
	}

	/**
	 * A TMDB link, or '' when it is not an https themoviedb.org URL.
	 *
	 * @param mixed $value Raw link.
	 *
	 * @return string
	 */
	private static function link( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = trim( $value );
		$parts = wp_parse_url( $value );

		if ( ! is_array( $parts ) || 'https' !== ( $parts['scheme'] ?? '' ) ) {
			return '';
		}

		return in_array( $parts['host'] ?? '', self::LINK_HOSTS, true ) ? $value : '';
	}

	/**
	 * One type's provider list, usable ones only, in priority order.
	 *
	 * @param mixed $raw Raw list.
	 *
	 * @return array<int, array{id: int, name: string, logo: string}>
	 */
	private static function providers( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$found = array();

		foreach ( $raw as $provider ) {
			if ( ! is_array( $provider ) ) {
				continue;
			}

			$id   = isset( $provider['provider_id'] ) && is_numeric( $provider['provider_id'] ) ? (int) $provider['provider_id'] : 0;
			$name = isset( $provider['provider_name'] ) && is_string( $provider['provider_name'] ) ? trim( $provider['provider_name'] ) : '';

			if ( $id < 1 || '' === $name || isset( $found[ $id ] ) ) {
				continue;
			}

			$logo = $provider['logo_path'] ?? '';

			$found[ $id ] = array(
				'priority' => isset( $provider['display_priority'] ) && is_numeric( $provider['display_priority'] ) ? (int) $provider['display_priority'] : PHP_INT_MAX,
				'id'       => $id,
				'name'     => $name,
				'logo'     => ( is_string( $logo ) && 1 === preg_match( self::LOGO_PATTERN, $logo ) ) ? $logo : '',
			);
		}

		// usort() is stable from PHP 8.0, so equal priorities keep TMDB's order.
		$found = array_values( $found );
		usort(
			$found,
			static function ( $a, $b ) {
				return $a['priority'] <=> $b['priority'];
			}
		);

		return array_map(
			static function ( $provider ) {
				unset( $provider['priority'] );
				return $provider;
			},
			$found
		);
	}
}
