<?php
/**
 * Provider-to-service map for the companion app.
 *
 * TMDB lists "Netflix" and "Netflix Standard with Ads", or "AMC+" and
 * "AMC+ Amazon Channel", as separate providers. A person picks one service, so
 * this groups providers into services. Pure: no WordPress calls.
 *
 * @package lwtv-plugin
 */

namespace LWTV\Rest_API\Build;

use LWTV\_Helpers\Tmdb_Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Service_Map {

	/**
	 * What follows a base name for an ad tier: "standard with ads", "with ads".
	 * Only a known tier word may precede "with ads", so "Apple TV Plus Basic with
	 * Ads" does not fold into the "Apple TV" store.
	 */
	const AD_TIER = '/^(?:(?:standard|basic|essential|premium|ad[\s-]supported)\s+)?with\s+ads$/u';

	/**
	 * Latin diacritics to ASCII. Fixed and locale-independent, so a service's
	 * slug (the app's stable key) is the same on every server. Latin-1
	 * Supplement and Latin Extended-A; anything else is dropped by slug().
	 */
	const ASCII_FOLD = array(
		'À' => 'A',
		'Á' => 'A',
		'Â' => 'A',
		'Ã' => 'A',
		'Ä' => 'A',
		'Å' => 'A',
		'Æ' => 'AE',
		'Ç' => 'C',
		'È' => 'E',
		'É' => 'E',
		'Ê' => 'E',
		'Ë' => 'E',
		'Ì' => 'I',
		'Í' => 'I',
		'Î' => 'I',
		'Ï' => 'I',
		'Ð' => 'D',
		'Ñ' => 'N',
		'Ò' => 'O',
		'Ó' => 'O',
		'Ô' => 'O',
		'Õ' => 'O',
		'Ö' => 'O',
		'Ø' => 'O',
		'Ù' => 'U',
		'Ú' => 'U',
		'Û' => 'U',
		'Ü' => 'U',
		'Ý' => 'Y',
		'Þ' => 'TH',
		'ß' => 'ss',
		'à' => 'a',
		'á' => 'a',
		'â' => 'a',
		'ã' => 'a',
		'ä' => 'a',
		'å' => 'a',
		'æ' => 'ae',
		'ç' => 'c',
		'è' => 'e',
		'é' => 'e',
		'ê' => 'e',
		'ë' => 'e',
		'ì' => 'i',
		'í' => 'i',
		'î' => 'i',
		'ï' => 'i',
		'ð' => 'd',
		'ñ' => 'n',
		'ò' => 'o',
		'ó' => 'o',
		'ô' => 'o',
		'õ' => 'o',
		'ö' => 'o',
		'ø' => 'o',
		'ù' => 'u',
		'ú' => 'u',
		'û' => 'u',
		'ü' => 'u',
		'ý' => 'y',
		'þ' => 'th',
		'ÿ' => 'y',
		'Ā' => 'A',
		'ā' => 'a',
		'Ă' => 'A',
		'ă' => 'a',
		'Ą' => 'A',
		'ą' => 'a',
		'Ć' => 'C',
		'ć' => 'c',
		'Ĉ' => 'C',
		'ĉ' => 'c',
		'Ċ' => 'C',
		'ċ' => 'c',
		'Č' => 'C',
		'č' => 'c',
		'Ď' => 'D',
		'ď' => 'd',
		'Đ' => 'D',
		'đ' => 'd',
		'Ē' => 'E',
		'ē' => 'e',
		'Ĕ' => 'E',
		'ĕ' => 'e',
		'Ė' => 'E',
		'ė' => 'e',
		'Ę' => 'E',
		'ę' => 'e',
		'Ě' => 'E',
		'ě' => 'e',
		'Ĝ' => 'G',
		'ĝ' => 'g',
		'Ğ' => 'G',
		'ğ' => 'g',
		'Ġ' => 'G',
		'ġ' => 'g',
		'Ģ' => 'G',
		'ģ' => 'g',
		'Ĥ' => 'H',
		'ĥ' => 'h',
		'Ħ' => 'H',
		'ħ' => 'h',
		'Ĩ' => 'I',
		'ĩ' => 'i',
		'Ī' => 'I',
		'ī' => 'i',
		'Ĭ' => 'I',
		'ĭ' => 'i',
		'Į' => 'I',
		'į' => 'i',
		'İ' => 'I',
		'ı' => 'i',
		'Ĳ' => 'IJ',
		'ĳ' => 'ij',
		'Ĵ' => 'J',
		'ĵ' => 'j',
		'Ķ' => 'K',
		'ķ' => 'k',
		'ĸ' => 'k',
		'Ĺ' => 'L',
		'ĺ' => 'l',
		'Ļ' => 'L',
		'ļ' => 'l',
		'Ľ' => 'L',
		'ľ' => 'l',
		'Ŀ' => 'L',
		'ŀ' => 'l',
		'Ł' => 'L',
		'ł' => 'l',
		'Ń' => 'N',
		'ń' => 'n',
		'Ņ' => 'N',
		'ņ' => 'n',
		'Ň' => 'N',
		'ň' => 'n',
		'ŉ' => 'n',
		'Ŋ' => 'N',
		'ŋ' => 'n',
		'Ō' => 'O',
		'ō' => 'o',
		'Ŏ' => 'O',
		'ŏ' => 'o',
		'Ő' => 'O',
		'ő' => 'o',
		'Œ' => 'OE',
		'œ' => 'oe',
		'Ŕ' => 'R',
		'ŕ' => 'r',
		'Ŗ' => 'R',
		'ŗ' => 'r',
		'Ř' => 'R',
		'ř' => 'r',
		'Ś' => 'S',
		'ś' => 's',
		'Ŝ' => 'S',
		'ŝ' => 's',
		'Ş' => 'S',
		'ş' => 's',
		'Š' => 'S',
		'š' => 's',
		'Ţ' => 'T',
		'ţ' => 't',
		'Ť' => 'T',
		'ť' => 't',
		'Ŧ' => 'T',
		'ŧ' => 't',
		'Ũ' => 'U',
		'ũ' => 'u',
		'Ū' => 'U',
		'ū' => 'u',
		'Ŭ' => 'U',
		'ŭ' => 'u',
		'Ů' => 'U',
		'ů' => 'u',
		'Ű' => 'U',
		'ű' => 'u',
		'Ų' => 'U',
		'ų' => 'u',
		'Ŵ' => 'W',
		'ŵ' => 'w',
		'Ŷ' => 'Y',
		'ŷ' => 'y',
		'Ÿ' => 'Y',
		'Ź' => 'Z',
		'ź' => 'z',
		'Ż' => 'Z',
		'ż' => 'z',
		'Ž' => 'Z',
		'ž' => 'z',
		'ſ' => 's',
	);

	/**
	 * Positive integer ids from a comma or whitespace separated string.
	 *
	 * @param string $raw Raw list.
	 *
	 * @return array<int, int> Unique, ascending.
	 */
	public static function parse_ids( string $raw ): array {
		$ids = array();

		foreach ( (array) preg_split( '/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY ) as $part ) {
			if ( ctype_digit( $part ) && (int) $part > 0 ) {
				$ids[ (int) $part ] = (int) $part;
			}
		}

		sort( $ids );

		return $ids;
	}

	/**
	 * A URL-safe ASCII slug, only [a-z0-9-]: Latin diacritics folded through
	 * ASCII_FOLD, lower case, "+" as "-plus", other runs as "-". Letters outside
	 * the table (Cyrillic, CJK) drop out, so the result may be ''. An all-digit
	 * result gets a "service-" prefix, because clients decode JSON "10" keys to
	 * integers. Callers must never emit ''.
	 *
	 * @param string $name Display name.
	 *
	 * @return string
	 */
	public static function slug( string $name ): string {
		$slug = str_replace( '+', '-plus', strtolower( strtr( $name, self::ASCII_FOLD ) ) );
		$slug = trim( (string) preg_replace( '/[^a-z0-9]+/', '-', $slug ), '-' );

		return ctype_digit( $slug ) ? 'service-' . $slug : $slug;
	}

	/**
	 * The key a provider groups under: its service key, or its own id when the
	 * name has no letters or digits at all.
	 *
	 * @param string $name Provider name.
	 * @param int    $id   Provider id.
	 *
	 * @return string
	 */
	private static function group_key( string $name, int $id ): string {
		$key = Tmdb_Providers::service_key( $name );

		return '' === $key ? '#' . $id : $key;
	}

	/**
	 * Map every provider to a service.
	 *
	 * @param array<int, array{name: string, logo: string}>  $providers Provider id => name and stored logo path.
	 * @param array<int, array{slug: string, name: string}> $overrides Provider id => the service an editor assigned.
	 *
	 * @return array{services: array<string, array{name: string}>, providers: array<int, array{name: string, service: ?string, logo: ?string}>}
	 */
	public static function build( array $providers, array $overrides ): array {
		ksort( $providers );
		ksort( $overrides );

		$spaced = array();
		foreach ( $providers as $id => $provider ) {
			$spaced[ $id ] = Tmdb_Providers::spaced_name( (string) $provider['name'] );
		}

		// Ad-tier folds: provider id => the base provider id it folds into.
		$base_of = array();
		foreach ( $providers as $id => $provider ) {
			if ( isset( $overrides[ $id ] ) ) {
				continue;
			}

			$best = 0;
			foreach ( $providers as $other => $unused ) {
				if ( $other === $id || '' === $spaced[ $other ] || ! str_starts_with( $spaced[ $id ], $spaced[ $other ] . ' ' ) ) {
					continue;
				}

				$remainder = substr( $spaced[ $id ], strlen( $spaced[ $other ] ) + 1 );

				if ( 1 === preg_match( self::AD_TIER, $remainder ) && ( 0 === $best || strlen( $spaced[ $other ] ) > strlen( $spaced[ $best ] ) ) ) {
					$best = $other;
				}
			}

			if ( $best > 0 ) {
				$base_of[ $id ] = $best;
			}
		}

		// Automatic groups for everything that is neither overridden nor folded.
		$groups = array();
		foreach ( $providers as $id => $provider ) {
			if ( isset( $overrides[ $id ] ) || isset( $base_of[ $id ] ) ) {
				continue;
			}

			$groups[ self::group_key( (string) $provider['name'], (int) $id ) ][] = $id;
		}

		// One slug and name per group, from the direct provider, else the lowest id.
		$group_service = array();
		$services      = array();
		$claimed       = array();
		foreach ( $overrides as $id => $override ) {
			if ( isset( $providers[ $id ] ) && ! isset( $services[ $override['slug'] ] ) ) {
				$services[ $override['slug'] ] = array( 'name' => $override['name'] );
			}
		}
		ksort( $groups );
		uasort(
			$groups,
			static function ( $a, $b ) {
				return $a[0] <=> $b[0];
			}
		);
		foreach ( $groups as $key => $ids ) {
			$lead = $ids[0];
			foreach ( $ids as $id ) {
				if ( 1 !== preg_match( Tmdb_Providers::RESELLER_PATTERN, (string) $providers[ $id ]['name'] ) ) {
					$lead = $id;
					break;
				}
			}

			$name = trim( (string) preg_replace( Tmdb_Providers::RESELLER_PATTERN, '', (string) $providers[ $lead ]['name'] ) );
			$slug = self::slug( $name );

			// A name with nothing slug-able in it (a symbol, an unfolded script).
			if ( '' === $slug ) {
				$slug = 'service-' . $ids[0];
			}

			// Two groups that slug alike ("Café" and "Cafe" fold together): the
			// group with the lower id keeps the plain slug, the other gets "-<id>".
			if ( isset( $claimed[ $slug ] ) ) {
				$slug .= '-' . $ids[0];
			}

			$claimed[ $slug ]      = true;
			$group_service[ $key ] = $slug;

			if ( ! isset( $services[ $slug ] ) ) {
				$services[ $slug ] = array( 'name' => $name );
			}
		}

		$resolve = static function ( int $id ) use ( &$resolve, $overrides, $base_of, $providers, $group_service ): ?string {
			if ( isset( $overrides[ $id ] ) ) {
				return $overrides[ $id ]['slug'];
			}

			if ( isset( $base_of[ $id ] ) ) {
				return $resolve( $base_of[ $id ] );
			}

			return $group_service[ self::group_key( (string) $providers[ $id ]['name'], $id ) ] ?? null;
		};

		$out = array();
		foreach ( $providers as $id => $provider ) {
			$out[ $id ] = array(
				'name'    => (string) $provider['name'],
				'service' => $resolve( (int) $id ),
				'logo'    => '' === (string) $provider['logo'] ? null : (string) $provider['logo'],
			);
		}

		ksort( $services, SORT_STRING );

		return array(
			'services'  => $services,
			'providers' => $out,
		);
	}
}
