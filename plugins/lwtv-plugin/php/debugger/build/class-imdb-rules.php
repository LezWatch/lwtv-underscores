<?php
/**
 * Is a post's IMDb ID missing, malformed, or out of date?
 *
 * The data contract, as produced by Collect\Imdb_Collector:
 *
 *     array(
 *         'post_id'   => int,
 *         'imdb'      => string,   // what we hold
 *         'canonical' => string,   // what the oracle last told us
 *         'exempt'    => bool,     // a missing ID is fine for this post
 *         'no_oracle' => bool,     // the oracle has no entry for this post, so
 *                                  // there is no canonical to compare against
 *     )
 *
 * @package LWTV
 */

namespace LWTV\Debugger\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\_Components\Debugger as Debug_Tool;
use LWTV\_Helpers\Imdb_Canonical;

class Imdb_Rules {

	/**
	 * The show check.
	 */
	const SHOW = 'show';

	/**
	 * The actor check.
	 */
	const ACTOR = 'actor';

	/**
	 * Everything that differs between the two checks.
	 *
	 * - post_type: what the finding is about.
	 * - validate:  the type Debug_Tool::validate_imdb() expects ('tt' vs 'nm').
	 * - example:   a well-formed ID, for the error message.
	 * - oracle:    the third party whose disagreement we are reporting.
	 * - advice:    what to do about a disagreement. Shows have an override to
	 *              tick; actors do not.
	 * - issues:    the three issue types.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	const LEVELS = array(
		self::SHOW  => array(
			'post_type' => 'post_type_shows',
			'validate'  => 'show',
			'example'   => 'tt12345',
			'oracle'    => 'TVMaze',
			'advice'    => 'check which is right, then correct it or tick "Ignore TVMaze Match" on the show.',
			'issues'    => array(
				'not_set'    => 'show-imdb-not-set',
				'invalid'    => 'show-imdb-invalid',
				'url_pasted' => 'show-imdb-url-pasted',
				'stale'      => 'show-imdb-stale',
			),
		),
		self::ACTOR => array(
			'post_type' => 'post_type_actors',
			'validate'  => 'actor',
			'example'   => 'nm12345',
			'oracle'    => 'TMDB',
			'advice'    => 'check which is right before correcting it.',
			'issues'    => array(
				'not_set'    => 'actor-imdb-not-set',
				'invalid'    => 'actor-imdb-invalid',
				'url_pasted' => 'actor-imdb-url-pasted',
				'stale'      => 'actor-imdb-stale',
			),
		),
	);

	/**
	 * Every finding for one post.
	 *
	 * @param  string $level self::SHOW or self::ACTOR.
	 * @param  array  $item  Collected post data.
	 * @return array<int, array<string, mixed>>
	 */
	public static function evaluate( string $level, array $item ): array {
		$config  = self::LEVELS[ $level ] ?? array();
		$post_id = (int) ( $item['post_id'] ?? 0 );

		if ( empty( $config ) || ! $post_id ) {
			return array();
		}

		$imdb = trim( (string) ( $item['imdb'] ?? '' ) );

		if ( '' === $imdb ) {
			// Some shows do not have IMDb records (web series)
			if ( ! empty( $item['exempt'] ) ) {
				return array();
			}

			return array( Findings::make( $post_id, $config['post_type'], $config['issues']['not_set'] ) );
		}

		if ( false === Debug_Tool::validate_imdb( $imdb, $config['validate'] ) ) {
			// Check if someone put in IMDb as a URL and not the key.
			$extracted = self::id_from_url( $imdb, $level );

			if ( '' !== $extracted ) {
				return array(
					Findings::make(
						$post_id,
						$config['post_type'],
						$config['issues']['url_pasted'],
						'The IMDb field holds a URL, not an ID -- ' . $imdb . ' (the ID in it is ' . $extracted . ').',
						array(
							'imdb'      => $imdb,
							'extracted' => $extracted,
						)
					),
				);
			}

			return array(
				Findings::make(
					$post_id,
					$config['post_type'],
					$config['issues']['invalid'],
					'IMDb ID is invalid (ex: ' . $config['example'] . ') -- ' . $imdb,
					array( 'imdb' => $imdb )
				),
			);
		}

		return self::stale( $level, $item, $imdb );
	}

	/**
	 * The IMDb ID inside a pasted IMDb URL.
	 *
	 * Someone pasting `https://www.imdb.com/fr/name/nm10688602/` into the ID
	 * field is the common way this goes wrong, and unlike most bad values it is
	 * not ambiguous: the canonical ID is right there in a known position. That is
	 * what makes the repair safe to run in bulk.
	 *
	 * Deliberately strict about two things:
	 *
	 * - It must actually be an imdb.com URL. A bare `nm10688602` is already valid
	 *   and never reaches here; some other site's URL that happens to contain
	 *   something ID-shaped is not evidence of anything.
	 * - The prefix must match the level. A title ID in an actor's field is a
	 *   different mistake — probably the wrong record entirely — and quietly
	 *   "fixing" it into a valid-looking wrong answer would be worse than leaving
	 *   it visible.
	 *
	 * @param  string $value The stored value.
	 * @param  string $level self::SHOW or self::ACTOR.
	 * @return string The ID, or '' when there is nothing safe to extract.
	 */
	public static function id_from_url( string $value, string $level ): string {
		$config = self::LEVELS[ $level ] ?? array();

		if ( empty( $config ) ) {
			return '';
		}

		$value = trim( $value );

		// Must be an IMDb URL. Subdomains and language paths (/fr/) are fine.
		if ( ! preg_match( '#^https?://([a-z0-9-]+\.)*imdb\.com/#i', $value ) ) {
			return '';
		}

		$prefix = ( self::ACTOR === $level ) ? 'nm' : 'tt';

		if ( ! preg_match( '#/(' . $prefix . '[0-9]+)(?:/|\?|\#|$)#', $value, $matches ) ) {
			return '';
		}

		$id = $matches[1];

		// Run the extracted value through the same validator the finding
		// used, so a repair can never write something the check would
		// immediately flag again.
		return ( false === Debug_Tool::validate_imdb( $id, $config['validate'] ) ) ? '' : $id;
	}

	/**
	 * An ID that looks fine but the oracle disagrees with.
	 *
	 * IMDb reassigns title IDs and leaves the old one redirecting, so a stale ID
	 * still opens the right page in a browser while breaking every exact-match
	 * API lookup keyed on it. Nothing about the value looks wrong, which is why
	 *
	 * @param  string $level self::SHOW or self::ACTOR.
	 * @param  array  $item  Collected post data.
	 * @param  string $imdb  The ID we hold, trimmed.
	 * @return array
	 */
	public static function stale( string $level, array $item, string $imdb ): array {
		$config = self::LEVELS[ $level ] ?? array();

		if ( empty( $config ) || ! empty( $item['no_oracle'] ) ) {
			return array();
		}

		$canonical = (string) ( $item['canonical'] ?? '' );

		if ( ! Imdb_Canonical::is_stale( $imdb, $canonical ) ) {
			return array();
		}

		return array(
			Findings::make(
				(int) $item['post_id'],
				$config['post_type'],
				$config['issues']['stale'],
				'IMDb ID disagrees with ' . $config['oracle'] . ' -- ours is ' . $imdb
					. ', ' . $config['oracle'] . ' has ' . $canonical
					. '. Ours has probably gone stale; ' . $config['advice'],
				array(
					'imdb'      => $imdb,
					'canonical' => $canonical,
				)
			),
		);
	}
}
