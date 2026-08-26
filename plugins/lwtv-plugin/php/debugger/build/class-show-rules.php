<?php
/**
 * Every rule that decides whether a show has a problem.
 *
 * PURE. Takes the plain array Collect\Show_Collector assembles and returns
 * findings. No queries, no meta reads, no globals -- which is the whole point:
 * this is the layer where the bugs have historically lived (the airdate check
 * reading a legacy key, the duplicate matcher treating two missing IMDb IDs as
 * a match) and it could not be tested while it was interleaved with WordPress.
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
		/*
		 * A show with no characters recorded, flagged deliberately and forever.
		 *
		 * These are real: some shows we simply do not have the character data for
		 * yet, and others only ever had background or unnamed queer characters. It
		 * is not a bug to be suppressed -- it is a documentation gap we want to
		 * keep seeing until somebody fills it in, which is precisely what this
		 * report is for. It also matters more than it used to: with the character
		 * score now weighted by screen time, a show with no characters has no
		 * character component at all, so it is scored on three of four parts.
		 *
		 * No `empty_ok`, on purpose. lezshows_char_count comes back as the string
		 * '0' for a genuinely characterless show, and empty( '0' ) is TRUE in PHP,
		 * so the standard check below flags it. That reads like an accident and
		 * is not -- it also catches a missing key, which means the show has never
		 * been calculated, and that is worth surfacing too.
		 *
		 * `acknowledged_by` is how a show leaves this report without the gap being
		 * filled: an editor toggles "No Known Characters", which is a statement
		 * that we looked and there is nothing findable. That flag also changes
		 * what the show page says, so it is real data rather than a way of
		 * silencing the debugger.
		 */
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
		/*
		 * Reported, not silently backfilled. This used to be written to 'TBD'
		 * mid-scan, which is why it carried `skip` -- the scan repaired it and
		 * so never had anything to report. The repair now lives in
		 * Shows::set_thumb_tbd() behind --fix-it, so the finding has to be
		 * visible or there is nothing for the fixer to act on.
		 *
		 * Worth knowing before deciding this is cosmetic: class-scores.php and
		 * class-of-the-day.php INNER JOIN on lezshows_worthit_rating, so a show
		 * with no row at all drops out of those queries entirely.
		 */
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
		/*
		 * Collected but not reported here: the dedicated `show_imdb` check owns
		 * that report, and the duplicate rule below needs the value.
		 */
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
		// Same story as 'thumb' above: repaired by Shows::add_none_trope() under
		// --fix-it, so the finding is reported rather than skipped.
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
	 * Each problem is its own issue type now, rather than four different strings
	 * under one `show-airdate`, so they can be counted and repaired separately.
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
	 * WordPress appends `-2`, `-3` to a duplicate slug, so a numeric tail is the
	 * signal that a show may have been entered twice. Some shows are genuinely
	 * number-named (90210), which is why finding a suffix only starts the check
	 * rather than finishing it.
	 *
	 * Public because the collector uses it to decide whether a lookup is even
	 * worth doing -- most slugs have no suffix and cost nothing.
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
	 * Matching needs both IMDb IDs present and equal. Two shows both *missing*
	 * an IMDb ID is not evidence of anything -- the old `isset()` test was
	 * always true, so every numerically-suffixed show with no IMDb ID matched
	 * any same-named show that also had none.
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
	 * `disabled_character` is null when the collector had no reason to look --
	 * the show does not claim that intersection -- and that is not the same as
	 * false, so it must not report.
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
	 * Includes the intersections taxonomy, which has no CHECKS entry because an
	 * empty one is not a problem -- it is only read to decide whether the
	 * cross-check below applies.
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
