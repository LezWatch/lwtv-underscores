<?php
/**
 * Every rule that decides whether an actor has a problem.
 *
 * PURE. Takes the plain array Collect\Actor_Collector assembles and returns
 * findings. This was the closest of the three to pure already -- the checks are
 * meta reads plus `Debug_Tool`'s stateless validators -- which is why the
 * collector here is thin.
 *
 * Messages carry raw values, not escaped ones. Escaping is the renderer's job
 * (`Validation::problem_cell()` runs `wp_kses_post()`, the CLI writes to a
 * terminal), and pre-escaping meant the admin escaped an already-escaped string
 * and showed the entities.
 *
 * The data contract, as produced by the collector:
 *
 *     array(
 *         'post_id' => int,
 *         'meta'    => array<string, string>,   // by meta key, '' when unset
 *     )
 *
 * @package LWTV
 */

namespace LWTV\Debugger\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\_Components\Debugger as Debug_Tool;

class Actor_Rules {

	/**
	 * Post type these findings are about.
	 */
	const POST_TYPE = 'post_type_actors';

	/**
	 * How many characters an actor is linked to.
	 */
	const META_CHARS = 'lezactors_char_count';

	/**
	 * Date of birth.
	 */
	const META_BIRTH = 'lezactors_birth';

	/**
	 * Date of death.
	 */
	const META_DEATH = 'lezactors_death';

	/**
	 * Wikipedia URL.
	 */
	const META_WIKI = 'lezactors_wikipedia';

	/**
	 * Homepage URL.
	 */
	const META_HOME = 'lezactors_homepage';

	/**
	 * Instagram handle.
	 */
	const META_INSTAGRAM = 'lezactors_instagram';

	/**
	 * Twitter handle.
	 */
	const META_TWITTER = 'lezactors_twitter';

	/**
	 * Fragment every Wikipedia URL contains, whatever the language subdomain.
	 */
	const WIKIPEDIA = 'wikipedia.org/';

	/**
	 * Shortest numeric part we will accept as an IMDb person ID.
	 *
	 * Real IMDb person IDs run 7-8 digits. `Debug_Tool::validate_imdb()` accepts
	 * `nm` plus *any* digits, which is right for validating the IMDb field but
	 * too loose for deciding a social handle is misfiled: 'nm2020' is a
	 * plausible Instagram handle, and the repair for this finding deletes the
	 * value.
	 */
	const IMDB_MIN_DIGITS = 6;

	/**
	 * Every finding for one actor.
	 *
	 * @param  array $actor Collected actor data.
	 * @return array<int, array<string, mixed>>
	 */
	public static function evaluate( array $actor ): array {
		if ( ! (int) ( $actor['post_id'] ?? 0 ) ) {
			return array();
		}

		return array_merge(
			self::characters( $actor ),
			self::wikipedia( $actor ),
			self::social( $actor, 'instagram' ),
			self::social( $actor, 'twitter' ),
			self::homepage( $actor )
		);
	}

	/**
	 * An actor linked to nobody.
	 *
	 * @param  array $actor Collected actor data.
	 * @return array
	 */
	public static function characters( array $actor ): array {
		if ( ! empty( self::meta( $actor, self::META_CHARS ) ) ) {
			return array();
		}

		return array( Findings::make( (int) $actor['post_id'], self::POST_TYPE, 'actor-no-characters' ) );
	}

	/**
	 * A Wikipedia field that does not hold a Wikipedia URL.
	 *
	 * @param  array $actor Collected actor data.
	 * @return array
	 */
	public static function wikipedia( array $actor ): array {
		$wiki = self::meta( $actor, self::META_WIKI );

		if ( '' === $wiki || str_contains( $wiki, self::WIKIPEDIA ) ) {
			return array();
		}

		return array( Findings::make( (int) $actor['post_id'], self::POST_TYPE, 'actor-wikipedia-invalid' ) );
	}

	/**
	 * A social handle that is malformed, or is an IMDb ID in the wrong box.
	 *
	 * The two are mutually exclusive on purpose: a handle that fails
	 * sanitisation is reported as invalid and nothing else, because the repair
	 * for the IMDb case deletes the value and must not be offered for a handle
	 * we merely failed to parse.
	 *
	 * @param  array  $actor  Collected actor data.
	 * @param  string $social 'instagram' or 'twitter'.
	 * @return array
	 */
	public static function social( array $actor, string $social ): array {
		$meta_key = ( 'instagram' === $social ) ? self::META_INSTAGRAM : self::META_TWITTER;
		$value    = self::meta( $actor, $meta_key );

		if ( '' === $value ) {
			return array();
		}

		$post_id = (int) $actor['post_id'];
		$label   = ucfirst( $social );

		if ( Debug_Tool::sanitize_social( $value, $social ) !== $value ) {
			return array(
				Findings::make(
					$post_id,
					self::POST_TYPE,
					'actor-' . $social . '-invalid',
					$label . ' ID is invalid -- ' . $value,
					array( 'value' => $value )
				),
			);
		}

		if ( self::looks_like_actor_imdb( $value ) ) {
			return array(
				Findings::make(
					$post_id,
					self::POST_TYPE,
					'actor-' . $social . '-is-imdb',
					$label . ' ID is an IMDb ID: ' . $value,
					array( 'value' => $value )
				),
			);
		}

		return array();
	}

	/**
	 * A homepage that is really a Wikipedia URL.
	 *
	 * Three outcomes, two of them repairable: move it when the Wikipedia field
	 * is empty, drop it when it duplicates what is there, and report it when the
	 * two are different Wikipedia pages, which needs a human to pick.
	 *
	 * @param  array $actor Collected actor data.
	 * @return array
	 */
	public static function homepage( array $actor ): array {
		$home = self::meta( $actor, self::META_HOME );

		if ( '' === $home || ! str_contains( $home, self::WIKIPEDIA ) ) {
			return array();
		}

		$post_id = (int) $actor['post_id'];
		$wiki    = self::meta( $actor, self::META_WIKI );

		if ( '' === $wiki ) {
			return array( Findings::make( $post_id, self::POST_TYPE, 'actor-homepage-is-wikipedia' ) );
		}

		if ( $wiki === $home ) {
			return array( Findings::make( $post_id, self::POST_TYPE, 'actor-homepage-dupe-wiki' ) );
		}

		return array(
			Findings::make(
				$post_id,
				self::POST_TYPE,
				'actor-homepage-wikipedia',
				'Homepage points to Wikipedia - ' . $home,
				array( 'homepage' => $home )
			),
		);
	}

	/**
	 * Does a social handle actually look like an actor's IMDb ID?
	 *
	 * Public because Debugger\Actors::remove_imdb_from_social() re-checks it
	 * before deleting anything -- the repair must not trust that the finding it
	 * was called for is still true.
	 *
	 * @param  string $value Social handle as stored.
	 * @return bool
	 */
	public static function looks_like_actor_imdb( string $value ): bool {
		if ( ! Debug_Tool::validate_imdb( $value, 'actor' ) ) {
			return false;
		}

		return strlen( substr( $value, 2 ) ) >= self::IMDB_MIN_DIGITS;
	}

	/**
	 * One meta value as a trimmed string.
	 *
	 * @param  array  $actor Collected actor data.
	 * @param  string $key   Meta key.
	 * @return string
	 */
	private static function meta( array $actor, string $key ): string {
		$value = $actor['meta'][ $key ] ?? '';

		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	/**
	 * Meta keys the rules need.
	 *
	 * META_BIRTH and META_DEATH are collected but not yet judged: a death date
	 * with no date of birth was flagged into a `$warnings` array that nothing
	 * ever read, because plenty of people have no recorded DoB and nobody has
	 * decided whether that is worth reporting. Give it an issue type when it is
	 * decided either way; the data is already here.
	 *
	 * @return array<string>
	 */
	public static function meta_keys(): array {
		return array(
			self::META_CHARS,
			self::META_BIRTH,
			self::META_DEATH,
			self::META_WIKI,
			self::META_HOME,
			self::META_INSTAGRAM,
			self::META_TWITTER,
			'lezactors_imdb',
		);
	}
}
