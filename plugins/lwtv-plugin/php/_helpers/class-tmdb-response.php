<?php
/**
 * TMDB response shapes.
 *
 * _Components\CPTs::get_tmdb_info() returns one of two incompatible shapes, and
 * which one you get depends on our own post meta rather than on anything the
 * caller asked for:
 *
 *   lez{shows,actors}_tmdb_id set -> /3/{tv,person}/{id} -> a detail object,
 *                                    with `id` at the top level
 *   only lez{shows,actors}_imdb   -> /3/find/{imdb_id}   -> a find envelope,
 *                                    with `tv_results` / `person_results` arrays
 *
 * Nothing in the returned array says which one arrived, and a post silently
 * changes shape the moment a backfill writes its TMDB ID -- `tv_results` stops
 * existing on the day the show gets an ID. This class is the single copy of the
 * detail-then-envelope handling, so callers never read one shape directly.
 *
 * Pure: takes decoded arrays, returns scalars, touches no WordPress. The HTTP and
 * the endpoint choice stay in _Components\CPTs.
 *
 * @package lwtv-plugin
 */

namespace LWTV\_Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tmdb_Response {

	/**
	 * The find-envelope bucket each of our post types lands in.
	 *
	 * TMDB files TV movies under `movie_results` rather than `tv_results`, which
	 * is a real case in this corpus but a different question -- see
	 * CLI\TMDB::look_up(), which reports it as `wrong_kind` instead of storing it.
	 */
	const RESULT_KEYS = array(
		'post_type_shows'  => 'tv_results',
		'post_type_actors' => 'person_results',
	);

	/**
	 * The find-envelope key for a post type.
	 *
	 * @param string $post_type A post type slug.
	 *
	 * @return string The results key, or '' for a post type TMDB knows nothing about.
	 */
	public static function result_key( string $post_type ): string {
		return self::RESULT_KEYS[ $post_type ] ?? '';
	}

	/**
	 * The TMDB ID out of either shape.
	 *
	 * Detail first, because that is the shape a post gets once it has an ID, and
	 * the envelope's first result second. A find response is keyed by the IMDb ID
	 * we sent, so result [0] is the only candidate there is.
	 *
	 * @param mixed  $data      A decoded TMDB response, or anything else.
	 * @param string $post_type The post type the request was made for.
	 *
	 * @return string The ID, or '' when the response holds none for this type.
	 */
	public static function id( $data, string $post_type ): string {
		if ( ! is_array( $data ) ) {
			return '';
		}

		// Detail: /tv/{id} and /person/{id} both put it at the top level.
		if ( isset( $data['id'] ) && is_scalar( $data['id'] ) ) {
			return (string) $data['id'];
		}

		$key = self::result_key( $post_type );

		if ( '' === $key || ! isset( $data[ $key ][0]['id'] ) || ! is_scalar( $data[ $key ][0]['id'] ) ) {
			return '';
		}

		return (string) $data[ $key ][0]['id'];
	}

	/**
	 * TMDB's own 0.5-10 vote average, out of either shape.
	 *
	 * Returned unscaled, in TMDB's own units, so that the ×10 conversion to the
	 * 0-100 scale lezshows_3rd_scores holds lives in exactly one place --
	 * Grading\TMDB::update_scores() -- rather than once per branch.
	 *
	 * @param mixed  $data      A decoded TMDB response, or anything else.
	 * @param string $post_type The post type the request was made for.
	 *
	 * @return float|null The average, or null when TMDB has no rating to give.
	 */
	public static function vote_average( $data, string $post_type ): ?float {
		if ( ! is_array( $data ) ) {
			return null;
		}

		// A detail object answers for itself; there is no envelope behind it.
		if ( isset( $data['vote_average'] ) ) {
			return self::rating( $data );
		}

		$key = self::result_key( $post_type );

		if ( '' === $key || ! isset( $data[ $key ][0] ) || ! is_array( $data[ $key ][0] ) ) {
			return null;
		}

		return self::rating( $data[ $key ][0] );
	}

	/**
	 * One rated entity's average, or null if nobody has rated it.
	 *
	 * TMDB's user scale runs 0.5 to 10, so it has no way to express "rated zero"
	 * and sends a bare 0 for anything unrated instead. The payload says so
	 * plainly: an unaired episode arrives as vote_average 0.0 alongside
	 * vote_count 0. Passing that through as a score would put a hard 0 on the
	 * show -- indistinguishable from a real drubbing, and cached for a day --
	 * where 'TBD' lets the daily recheck pick the rating up once votes exist.
	 *
	 * @param array $source A detail object or one find result.
	 *
	 * @return float|null
	 */
	private static function rating( array $source ): ?float {
		if ( ! isset( $source['vote_average'] ) || ! is_numeric( $source['vote_average'] ) ) {
			return null;
		}

		// Where TMDB sends a count it is the authoritative answer to "has anyone
		// rated this", so it overrides whatever the average happens to say.
		if ( isset( $source['vote_count'] ) && is_numeric( $source['vote_count'] ) && (int) $source['vote_count'] < 1 ) {
			return null;
		}

		$rating = (float) $source['vote_average'];

		return ( $rating > 0.0 ) ? $rating : null;
	}

	/**
	 * The IMDb ID TMDB holds, distinguishing "no link" from "not in this shape".
	 *
	 * The distinction is the whole point, because the two mean opposite things to
	 * Imdb_Canonical::verdict(): '' is a real answer that clears a stale flag,
	 * while null must leave our stored value untouched. Only /person/{id} carries
	 * `imdb_id` at the top level. /tv/{id} does not carry one at all -- it needs
	 * `append_to_response=external_ids` -- and the find envelope's results carry
	 * none either. Reading a missing key as '' would turn both of those into
	 * "TMDB has no IMDb link", which invents a verdict out of the wrong shape.
	 *
	 * @param mixed $data A decoded TMDB response, or anything else.
	 *
	 * @return string|null The ID, '' when TMDB has the record and no link, or null
	 *                     when this shape cannot answer the question.
	 */
	public static function imdb_id( $data ): ?string {
		if ( ! is_array( $data ) ) {
			return null;
		}

		// append_to_response=external_ids nests it, which is the only way a /tv/
		// response can answer at all.
		if ( isset( $data['external_ids'] ) && is_array( $data['external_ids'] ) && array_key_exists( 'imdb_id', $data['external_ids'] ) ) {
			return self::as_id( $data['external_ids']['imdb_id'] );
		}

		if ( array_key_exists( 'imdb_id', $data ) ) {
			return self::as_id( $data['imdb_id'] );
		}

		return null;
	}

	/**
	 * Present-but-empty collapses to ''. TMDB sends null for a person it holds
	 * with no IMDb link, which is an answer rather than a failure.
	 *
	 * @param mixed $value The raw value.
	 *
	 * @return string
	 */
	private static function as_id( $value ): string {
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}
}
