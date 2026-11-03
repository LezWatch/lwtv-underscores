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
	 * The only host a stored link may point at.
	 */
	const LINK_HOSTS = array( 'www.themoviedb.org', 'themoviedb.org' );

	/**
	 * What a logo_path looks like: one path segment and an image extension.
	 */
	const LOGO_PATTERN = '~^/[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|svg)$~';

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

		return array_values( $found );
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
	 * @return array{providers: array<int, array{name: string, logo: string}>, countries: array<string, array{link: string, ids: array<int, int>}>}
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
				'link' => (string) $data[ $code ]['link'],
				'ids'  => $ids,
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
	 * @param mixed $list Raw list.
	 *
	 * @return array<int, array{id: int, name: string, logo: string}>
	 */
	private static function providers( $provider_list ): array {
		if ( ! is_array( $provider_list ) ) {
			return array();
		}

		$found = array();

		foreach ( $provider_list as $provider ) {
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
