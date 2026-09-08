<?php
/**
 * Name: Watch Host Map
 * Description: Which lez_watch_urls term owns which host.
 *
 * @package LWTV
 */

namespace LWTV\CPTs\Shows\Watching;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Watch_Host_Map {

	/**
	 * Reduce stored term URLs to a host => term_id map.
	 *
	 * @param array<int, array{term_id: int, name: string, url: string}> $term_urls Rows as Watch_Hosts::term_urls() returns them.
	 * @return array{map: array<string, int>, collisions: array<string, array<int, string>>}
	 */
	public static function build( array $term_urls ): array {
		$map       = array();
		$claimants = array();

		foreach ( $term_urls as $row ) {
			$term_id = (int) ( $row['term_id'] ?? 0 );
			$host    = self::host_of( (string) ( $row['url'] ?? '' ) );

			if ( ! $term_id || '' === $host ) {
				continue;
			}

			// Track every claimant before deciding, so a collision is visible
			// even though only the first claim is honoured.
			$claimants[ $host ][ $term_id ] = (string) ( $row['name'] ?? '' );

			if ( ! isset( $map[ $host ] ) ) {
				$map[ $host ] = $term_id;
			}
		}

		$collisions = array();
		foreach ( $claimants as $host => $terms ) {
			if ( count( $terms ) > 1 ) {
				$collisions[ $host ] = $terms;
			}
		}

		return array(
			'map'        => $map,
			'collisions' => $collisions,
		);
	}

	/**
	 * The term that owns a host, or 0.
	 *
	 * @param array<string, int> $map  Map from build().
	 * @param string             $host Hostname, any form.
	 * @return int Term ID, or 0 when nothing owns it.
	 */
	public static function resolve( array $map, string $host ): int {
		$host = Host_Name::normalise( $host );

		if ( '' === $host || empty( $map ) ) {
			return 0;
		}

		foreach ( Host_Name::host_candidates( $host ) as $candidate ) {
			if ( isset( $map[ $candidate ] ) ) {
				return (int) $map[ $candidate ];
			}
		}

		return 0;
	}

	/**
	 * Which shows reach each provider term, by way of the hosts they link to.
	 *
	 * @param array<string, array<int|string>> $ids_by_host host => post IDs, as Watch_Hosts::show_ids_by_host() returns it.
	 * @param array<string, int>               $map         Map from build().
	 * @return array<int, array<int, int>> term_id => post IDs, first-seen order.
	 */
	public static function ids_per_term( array $ids_by_host, array $map ): array {
		$seen = array();

		foreach ( $ids_by_host as $host => $post_ids ) {
			$term_id = self::resolve( $map, (string) $host );

			if ( ! $term_id ) {
				continue;
			}

			foreach ( (array) $post_ids as $post_id ) {
				$seen[ $term_id ][ (int) $post_id ] = true;
			}
		}

		return array_map( 'array_keys', $seen );
	}

	/**
	 * The normalised host a stored URL points at.
	 *
	 * @param string $url Stored URL.
	 * @return string Normalised host, or '' when there isn't one.
	 */
	private static function host_of( string $url ): string {
		$url = trim( $url );

		if ( '' === $url ) {
			return '';
		}

		$parsed = wp_parse_url( $url );

		if ( ! is_array( $parsed ) || empty( $parsed['host'] ) ) {
			if ( str_contains( $url, '://' ) ) {
				return '';
			}

			$parsed = wp_parse_url( 'https://' . ltrim( $url, '/' ) );

			if ( ! is_array( $parsed ) || empty( $parsed['host'] ) ) {
				return '';
			}
		}

		return Host_Name::normalise( (string) $parsed['host'] );
	}
}
