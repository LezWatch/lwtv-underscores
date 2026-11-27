<?php
/**
 * Name: App Availability
 * Description: The body of lwtv/v2/app/availability.
 *
 * Compacts each show's stored TMDB watch providers into ids, with shared
 * provider, service and region dictionaries. Pure: no WordPress calls.
 *
 * @package LWTV
 */

namespace LWTV\Rest_API\Build;

use LWTV\_Helpers\Tmdb_Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class App_Availability {

	/**
	 * Credit required by JustWatch, who supply the data through TMDB.
	 */
	const ATTRIBUTION = array(
		'text' => 'Streaming data from JustWatch.',
		'url'  => 'https://www.justwatch.com/',
	);

	/**
	 * Build the response body.
	 *
	 * @param array<int, mixed>                             $stored       Show id => Tmdb_Providers::normalise() output.
	 * @param array<int, array{slug: string, name: string}> $overrides    Provider id => the service an editor assigned.
	 * @param array<string, string>                         $region_names Region code => name.
	 *
	 * @return array{attribution: array<string, string>, services: object, providers: object, regions: object, shows: object}
	 */
	public static function build( array $stored, array $overrides, array $region_names ): array {
		ksort( $stored );

		$map    = Service_Map::build( self::provider_index( $stored ), $overrides );
		$shows  = array();
		$region = array(); // Region code => service slug => show ids.

		foreach ( $stored as $show_id => $countries ) {
			if ( ! is_array( $countries ) ) {
				continue;
			}

			$show = array();

			foreach ( $countries as $code => $block ) {
				$code = (string) $code;
				if ( 1 !== preg_match( '/^[A-Z]{2}$/', $code ) || ! is_array( $block ) ) {
					continue;
				}

				$entry = array();

				foreach ( Tmdb_Providers::TYPES as $type ) {
					$ids = self::ids( $block[ $type ] ?? null );
					if ( empty( $ids ) ) {
						continue;
					}

					$entry[ $type ] = $ids;

					if ( in_array( $type, Tmdb_Providers::DISPLAY_TYPES, true ) ) {
						foreach ( $ids as $id ) {
							$slug = $map['providers'][ $id ]['service'] ?? null;
							if ( null !== $slug ) {
								$region[ $code ][ $slug ][ (int) $show_id ] = true;
							}
						}
					}
				}

				if ( ! empty( $entry ) ) {
					$show[ $code ]   = $entry;
					$region[ $code ] = $region[ $code ] ?? array();
				}
			}

			if ( ! empty( $show ) ) {
				ksort( $show, SORT_STRING );
				$shows[ (int) $show_id ] = $show;
			}
		}

		ksort( $region, SORT_STRING );

		$regions = array();
		foreach ( $region as $code => $by_service ) {
			$counts = array_map( 'count', $by_service );
			uksort(
				$counts,
				static function ( $a, $b ) use ( $counts ) {
					return array( $counts[ $b ], $a ) <=> array( $counts[ $a ], $b );
				}
			);

			$regions[ $code ] = array(
				'name'     => (string) ( $region_names[ $code ] ?? $code ),
				'services' => array_map( 'strval', array_keys( $counts ) ),
			);
		}

		return array(
			'attribution' => self::ATTRIBUTION,
			'services'    => App_Envelope::dict( $map['services'] ),
			'providers'   => App_Envelope::dict( $map['providers'] ),
			'regions'     => App_Envelope::dict( $regions ),
			'shows'       => App_Envelope::dict( $shows ),
		);
	}

	/**
	 * The first seen name and logo for each provider id used by any stored show.
	 *
	 * @param array<int, mixed> $stored Show id => Tmdb_Providers::normalise() output.
	 *
	 * @return array<int, array{name: string, logo: string}> Keyed by provider id, ascending.
	 */
	public static function provider_index( array $stored ): array {
		ksort( $stored );

		$index = array();

		foreach ( $stored as $countries ) {
			if ( ! is_array( $countries ) ) {
				continue;
			}

			foreach ( $countries as $code => $block ) {
				if ( 1 !== preg_match( '/^[A-Z]{2}$/', (string) $code ) || ! is_array( $block ) ) {
					continue;
				}

				foreach ( Tmdb_Providers::TYPES as $type ) {
					foreach ( is_array( $block[ $type ] ?? null ) ? $block[ $type ] : array() as $provider ) {
						$id = is_array( $provider ) ? (int) ( $provider['id'] ?? 0 ) : 0;
						if ( $id > 0 && ! isset( $index[ $id ] ) ) {
							$index[ $id ] = array(
								'name' => (string) ( $provider['name'] ?? '' ),
								'logo' => (string) ( $provider['logo'] ?? '' ),
							);
						}
					}
				}
			}
		}

		ksort( $index );

		return $index;
	}

	/**
	 * Provider ids from one stored type list: positive, unique, in stored order.
	 *
	 * @param mixed $types Stored list of provider arrays.
	 *
	 * @return array<int, int>
	 */
	private static function ids( $types ): array {
		$ids = array();

		foreach ( is_array( $types ) ? $types : array() as $provider ) {
			$id = is_array( $provider ) ? (int) ( $provider['id'] ?? 0 ) : 0;
			if ( $id > 0 ) {
				$ids[ $id ] = $id;
			}
		}

		return array_values( $ids );
	}
}
