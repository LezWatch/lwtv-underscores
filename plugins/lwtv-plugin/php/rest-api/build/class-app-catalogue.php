<?php
/**
 * Name: App Catalogue
 * Description: The catalogue body for lwtv/v2/app/catalogue: term dictionaries and show records.
 *
 * Pure: raw shows in, a body out. The envelope is added by the caller.
 *
 * @package LWTV
 */

namespace LWTV\Rest_API\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class App_Catalogue {

	/**
	 * Dictionary order in the response.
	 */
	const DICTIONARIES = array( 'tropes', 'genres', 'formats', 'countries', 'stations' );

	/**
	 * Build the catalogue body.
	 *
	 * @param array $raws Raw shows, as App_Show_Record::build() takes them.
	 *
	 * @return array{terms: array<string, object>, shows: array<int, array>}
	 */
	public static function build( array $raws ): array {
		$shows = array();
		$known = array();

		foreach ( $raws as $raw ) {
			$record                 = App_Show_Record::build( $raw );
			$shows[]                = $record;
			$known[ $record['id'] ] = true;
		}

		foreach ( $shows as $index => $record ) {
			$shows[ $index ]['similar'] = array_values(
				array_filter(
					$record['similar'],
					static fn( int $id ): bool => isset( $known[ $id ] )
				)
			);
		}

		usort( $shows, static fn( array $a, array $b ): int => $a['id'] <=> $b['id'] );

		return array(
			'terms' => self::dictionaries( $raws ),
			'shows' => $shows,
		);
	}

	/**
	 * Dictionaries of the terms the given shows use, keyed by term id.
	 *
	 * @param array $raws Raw shows.
	 *
	 * @return array<string, object>
	 */
	private static function dictionaries( array $raws ): array {
		$maps = array_fill_keys( self::DICTIONARIES, array() );

		foreach ( $raws as $raw ) {
			$terms = is_array( $raw['terms'] ?? null ) ? $raw['terms'] : array();

			foreach ( self::DICTIONARIES as $field ) {
				$rows = is_array( $terms[ $field ] ?? null ) ? $terms[ $field ] : array();

				foreach ( $rows as $term_id => $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}

					$maps[ $field ][ (int) $term_id ] = array(
						'slug' => (string) ( $row['slug'] ?? '' ),
						'name' => App_Envelope::plain_text( (string) ( $row['name'] ?? '' ) ),
					);
				}
			}
		}

		return array_map(
			static function ( array $map ): object {
				ksort( $map );

				return App_Envelope::dict( $map );
			},
			$maps
		);
	}
}
