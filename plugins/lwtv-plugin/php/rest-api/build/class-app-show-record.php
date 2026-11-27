<?php
/**
 * Name: App Show Record
 * Description: One show, in the shape the lwtv/v2/app contract defines.
 *
 * Pure: raw values in, a record out. Gathering the values is the caller's job.
 *
 * @package LWTV
 */

namespace LWTV\Rest_API\Build;

use LWTV\CPTs\Shows\Airdates;
use LWTV\CPTs\Shows\Scoring\Trigger_Warning;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class App_Show_Record {

	/**
	 * Record field => taxonomy it is read from.
	 */
	const TAXONOMIES = array(
		'formats'   => 'lez_formats',
		'countries' => 'lez_country',
		'stations'  => 'lez_stations',
		'genres'    => 'lez_genres',
		'tropes'    => 'lez_tropes',
	);

	/**
	 * The trope slug that marks a show with a queer death.
	 */
	const DEATH_TROPE = 'dead-queers';

	/**
	 * Star tiers the app knows about.
	 */
	const STARS = array( 'gold', 'silver', 'bronze', 'anti' );

	/**
	 * Build one show record.
	 *
	 * @param array $raw Raw values: id, slug, title, url, start, finish, on_air,
	 *                   terms, trigger_term, trigger_meta, star, score, tmdb_id, similar.
	 *
	 * @return array The contract's show fields, in contract order.
	 */
	public static function build( array $raw ): array {
		$id    = (int) ( $raw['id'] ?? 0 );
		$terms = is_array( $raw['terms'] ?? null ) ? $raw['terms'] : array();

		$ids = array();
		foreach ( array_keys( self::TAXONOMIES ) as $field ) {
			$ids[ $field ] = self::term_ids( $terms[ $field ] ?? array() );
		}

		$on_air       = 'yes' === ( $raw['on_air'] ?? '' );
		$still_airing = $on_air || Airdates::is_still_airing( $raw['finish'] ?? '' );

		return array(
			'id'           => $id,
			'slug'         => (string) ( $raw['slug'] ?? '' ),
			'title'        => App_Envelope::plain_text( (string) ( $raw['title'] ?? '' ) ),
			'url'          => (string) ( $raw['url'] ?? '' ),
			'year_start'   => self::year( $raw['start'] ?? '' ),
			'year_end'     => $still_airing ? null : self::year( $raw['finish'] ?? '' ),
			'on_air'       => $on_air,
			'formats'      => $ids['formats'],
			'countries'    => $ids['countries'],
			'stations'     => $ids['stations'],
			'genres'       => $ids['genres'],
			'tropes'       => $ids['tropes'],
			'queer_death'  => self::has_slug( $terms['tropes'] ?? array(), self::DEATH_TROPE ),
			'trigger'      => self::trigger( (string) ( $raw['trigger_term'] ?? '' ), $raw['trigger_meta'] ?? '' ),
			'star'         => in_array( $raw['star'] ?? '', self::STARS, true ) ? $raw['star'] : null,
			'lwtv_score'   => self::score( $raw['score'] ?? '' ),
			'viewer_score' => null,
			'viewer_votes' => null,
			'tmdb_id'      => self::positive_int( $raw['tmdb_id'] ?? null ),
			'similar'      => self::similar( $raw['similar'] ?? array(), $id ),
		);
	}

	/**
	 * Term ids as ints, ascending.
	 *
	 * @param mixed $terms Term map keyed by term id.
	 *
	 * @return int[]
	 */
	private static function term_ids( $terms ): array {
		$ids = is_array( $terms ) ? array_map( 'intval', array_keys( $terms ) ) : array();
		sort( $ids );

		return $ids;
	}

	/**
	 * Does any term in the map carry this slug?
	 *
	 * @param mixed  $terms Term map.
	 * @param string $slug  Slug to look for.
	 *
	 * @return bool
	 */
	private static function has_slug( $terms, string $slug ): bool {
		if ( ! is_array( $terms ) ) {
			return false;
		}

		foreach ( $terms as $term ) {
			if ( is_array( $term ) && ( $term['slug'] ?? null ) === $slug ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The leading four-digit year of an airdate, or null.
	 *
	 * @param mixed $date Airdate string.
	 *
	 * @return int|null
	 */
	private static function year( $date ): ?int {
		return 1 === preg_match( '/^(\d{4})/', (string) $date, $match ) ? (int) $match[1] : null;
	}

	/**
	 * Trigger level: the term slug first, else the meta, normalised.
	 *
	 * @param string $term Lez_triggers slug, or ''.
	 * @param mixed  $meta Raw lezshows_triggerwarning meta.
	 *
	 * @return string|null 'low', 'med', 'high' or null.
	 */
	private static function trigger( string $term, $meta ): ?string {
		$level = Trigger_Warning::normalize( '' !== $term ? $term : (string) $meta );

		return 'none' === $level ? null : $level;
	}

	/**
	 * Score rounded to a whole number and clamped to 0-100, or null.
	 *
	 * @param mixed $score Raw score meta.
	 *
	 * @return int|null
	 */
	private static function score( $score ): ?int {
		if ( ! is_numeric( $score ) ) {
			return null;
		}

		return (int) max( 0, min( 100, round( (float) $score ) ) );
	}

	/**
	 * A positive integer, or null for blank, zero or non-numeric.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return int|null
	 */
	private static function positive_int( $value ): ?int {
		$int = is_numeric( $value ) ? (int) $value : 0;

		return $int > 0 ? $int : null;
	}

	/**
	 * Similar show ids: cast, de-duplicated, without the show itself.
	 *
	 * @param mixed $similar Raw similar-shows meta.
	 * @param int   $own_id The show's own id.
	 *
	 * @return int[]
	 */
	private static function similar( $similar, int $own_id ): array {
		if ( ! is_array( $similar ) ) {
			return array();
		}

		$ids = array();
		foreach ( $similar as $value ) {
			$id = self::positive_int( $value );
			if ( null !== $id && $id !== $own_id ) {
				$ids[ $id ] = $id;
			}
		}

		return array_values( $ids );
	}
}
