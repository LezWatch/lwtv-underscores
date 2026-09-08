<?php
/**
 * Every rule that decides whether a show has a problem.
 *
 * The data contract, as produced by the collector:
 *
 *     array(
 *         'post_id'            => int,
 *         'slug'               => string,                     // post_name
 *         'meta'               => array<string, mixed>,       // by meta key
 *         'terms'              => array<string, array>,       // taxonomy => slugs
 *         'airdates'           => array{start: string, finish: string},
 *         'duplicate'          => array{}|array{id: int, imdb: string},
 *         'disabled_character' => bool|null,                  // null = not applicable
 *     )
 *
 * @package LWTV
 */

namespace LWTV\Debugger\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Shows\Airdates;

class Show_Rules {

	/**
	 * Post type these findings are about.
	 */
	const POST_TYPE = 'post_type_shows';

	/**
	 * Taxonomy holding intersectional representation terms.
	 */
	const INTERSECTIONS = 'lez_intersections';

	/**
	 * Editor flag: we have looked, and there are no findable characters.
	 */
	const META_NO_CHARS = 'lezshows_no_chars';

	/**
	 * The straightforward "is this field filled in" checks.
	 *
	 * Read by the rules to evaluate, and by the collector to know what to fetch,
	 * so the list is declared once.
	 *
	 * - issue:           key into Issue_Registry, which owns the human copy.
	 * - meta:            post meta key to test, or...
	 * - term:            taxonomy to test.
	 * - empty_ok:        empty is fine, do not report it.
	 * - skip:            do not report, but still collect the value -- other
	 *                    rules or repairs need it.
	 * - acknowledged_by: post meta key that, when truthy, means an editor has
	 *                    already looked at this and confirmed it is not a fault.
	 *                    Not the same as `empty_ok`: the field is still empty and
	 *                    still wrong in the abstract, but somebody has decided
	 *                    that is the truth about this show.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	const CHECKS = array(
		'score'      => array(
			'issue'    => 'show-no-score',
			'meta'     => 'lezshows_the_score',
			'empty_ok' => true,
		),
		'characters' => array(
			'issue'           => 'show-no-characters',
			'meta'            => 'lezshows_char_count',
			'acknowledged_by' => self::META_NO_CHARS,
		),
		'details'    => array(
			'issue'    => 'show-no-worthit-details',
			'meta'     => 'lezshows_worthit_details',
			'empty_ok' => true,
		),
		'thumb'      => array(
			'issue' => 'show-missing-thumb',
			'meta'  => 'lezshows_worthit_rating',
		),
		'realness'   => array(
			'issue'    => 'show-no-realness',
			'meta'     => 'lezshows_realness_rating',
			'empty_ok' => true,
		),
		'quality'    => array(
			'issue'    => 'show-no-quality',
			'meta'     => 'lezshows_quality_rating',
			'empty_ok' => true,
		),
		'screentime' => array(
			'issue'    => 'show-no-screentime',
			'meta'     => 'lezshows_screentime_rating',
			'empty_ok' => true,
		),
		'imdb'       => array(
			'issue' => 'show-no-imdb',
			'meta'  => 'lezshows_imdb',
			'skip'  => true,
		),
		'stations'   => array(
			'issue' => 'show-no-stations',
			'term'  => 'lez_stations',
		),
		'nations'    => array(
			'issue' => 'show-no-country',
			'term'  => 'lez_country',
		),
		'formats'    => array(
			'issue' => 'show-no-format',
			'term'  => 'lez_formats',
		),
		'genres'     => array(
			'issue' => 'show-no-genres',
			'term'  => 'lez_genres',
		),
		'tropes'     => array(
			'issue' => 'show-missing-trope',
			'term'  => 'lez_tropes',
		),
	);

	/**
	 * Every finding for one show.
	 *
	 * @param  array $show Collected show data.
	 * @return array<int, array<string, mixed>>
	 */
	public static function evaluate( array $show ): array {
		$post_id = (int) ( $show['post_id'] ?? 0 );

		if ( ! $post_id ) {
			return array();
		}

		return array_merge(
			self::missing_fields( $show ),
			self::airdates( $show ),
			self::duplicate( $show ),
			self::intersections( $show )
		);
	}

	/**
	 * Findings for every unset field in CHECKS.
	 *
	 * @param  array $show Collected show data.
	 * @return array
	 */
	public static function missing_fields( array $show ): array {
		$post_id  = (int) $show['post_id'];
		$meta     = (array) ( $show['meta'] ?? array() );
		$terms    = (array) ( $show['terms'] ?? array() );
		$findings = array();

		foreach ( self::CHECKS as $check ) {
			if ( ! empty( $check['empty_ok'] ) || ! empty( $check['skip'] ) ) {
				continue;
			}

			// Somebody has already looked at this one and said it is correct.
			if ( isset( $check['acknowledged_by'] ) && ! empty( $meta[ $check['acknowledged_by'] ] ) ) {
				continue;
			}

			if ( isset( $check['meta'] ) ) {
				$is_missing = empty( $meta[ $check['meta'] ] );
			} elseif ( isset( $check['term'] ) ) {
				$is_missing = empty( $terms[ $check['term'] ] );
			} else {
				continue;
			}

			if ( $is_missing ) {
				$findings[] = Findings::make( $post_id, self::POST_TYPE, $check['issue'] );
			}
		}

		return $findings;
	}

	/**
	 * Findings for a show's airdates.
	 *
	 * @param  array $show Collected show data.
	 * @return array
	 */
	public static function airdates( array $show ): array {
		$post_id  = (int) $show['post_id'];
		$airdates = (array) ( $show['airdates'] ?? array() );
		$start    = (string) ( $airdates['start'] ?? '' );
		$finish   = (string) ( $airdates['finish'] ?? '' );

		if ( '' === $start && '' === $finish ) {
			return array( Findings::make( $post_id, self::POST_TYPE, 'show-no-airdates' ) );
		}

		$findings = array();

		if ( '' === $start ) {
			$findings[] = Findings::make( $post_id, self::POST_TYPE, 'show-no-start-date' );
		}

		if ( '' === $finish ) {
			$findings[] = Findings::make( $post_id, self::POST_TYPE, 'show-no-end-date' );
			return $findings;
		}

		// 'current' means still airing, so there is nothing to compare against.
		if ( Airdates::is_still_airing( $finish ) ) {
			return $findings;
		}

		// Only compare when both sides are actually years.
		if ( is_numeric( $start ) && is_numeric( $finish ) && (int) $start > (int) $finish ) {
			$findings[] = Findings::make( $post_id, self::POST_TYPE, 'show-airdate-inverted' );
		}

		return $findings;
	}

	/**
	 * The numeric suffix on a slug, if it has one.
	 *
	 * @param  string $slug Post slug.
	 * @return string The suffix, or '' when there is none.
	 */
	public static function numeric_suffix( string $slug ): string {
		$parts = explode( '-', $slug );
		$last  = end( $parts );

		return is_numeric( $last ) ? (string) $last : '';
	}

	/**
	 * The slug a numerically-suffixed show would be a duplicate of.
	 *
	 * @param  string $slug Post slug.
	 * @return string The base slug, or '' when the slug has no numeric suffix.
	 */
	public static function base_slug( string $slug ): string {
		$suffix = self::numeric_suffix( $slug );

		return ( '' === $suffix ) ? '' : str_replace( '-' . $suffix, '', $slug );
	}

	/**
	 * Findings for a likely duplicate show.
	 *
	 * @param  array $show Collected show data.
	 * @return array
	 */
	public static function duplicate( array $show ): array {
		$post_id   = (int) $show['post_id'];
		$candidate = (array) ( $show['duplicate'] ?? array() );

		if ( empty( $candidate ) ) {
			return array();
		}

		// The 90210 loop: a number-named show finds itself. Not a duplicate.
		if ( (int) ( $candidate['id'] ?? 0 ) === $post_id ) {
			return array();
		}

		$ours   = (string) ( $show['meta']['lezshows_imdb'] ?? '' );
		$theirs = (string) ( $candidate['imdb'] ?? '' );

		if ( '' === $ours || '' === $theirs || $ours !== $theirs ) {
			return array();
		}

		return array( Findings::make( $post_id, self::POST_TYPE, 'show-duplicate' ) );
	}

	/**
	 * Findings where a claimed intersection is not backed by a character.
	 *
	 * Only the `disabled` intersection is checked, because it is the only one
	 * with a matching character-level term to check against.
	 *
	 * @param  array $show Collected show data.
	 * @return array
	 */
	public static function intersections( array $show ): array {
		$post_id       = (int) $show['post_id'];
		$intersections = (array) ( $show['terms'][ self::INTERSECTIONS ] ?? array() );

		if ( ! in_array( 'disabled', $intersections, true ) ) {
			return array();
		}

		if ( false !== ( $show['disabled_character'] ?? null ) ) {
			return array();
		}

		return array( Findings::make( $post_id, self::POST_TYPE, 'show-intersection' ) );
	}

	/**
	 * Meta keys the rules need.
	 *
	 * @return array<string>
	 */
	public static function meta_keys(): array {
		$keys = array();

		foreach ( self::CHECKS as $check ) {
			if ( isset( $check['meta'] ) ) {
				$keys[] = $check['meta'];
			}

			// The acknowledgement flag has to be collected too, or the rule
			// cannot see that somebody has already ruled on this.
			if ( isset( $check['acknowledged_by'] ) ) {
				$keys[] = $check['acknowledged_by'];
			}
		}

		return array_values( array_unique( $keys ) );
	}

	/**
	 * Taxonomies the rules need.
	 *
	 * @return array<string>
	 */
	public static function taxonomies(): array {
		$taxes = array( self::INTERSECTIONS );

		foreach ( self::CHECKS as $check ) {
			if ( isset( $check['term'] ) ) {
				$taxes[] = $check['term'];
			}
		}

		return $taxes;
	}
}
