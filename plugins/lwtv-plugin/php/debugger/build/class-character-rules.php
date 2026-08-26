<?php
/**
 * Every rule that decides whether a character has a problem.
 *
 * PURE. Takes the plain array Collect\Character_Collector assembles and returns
 * findings. Same split as Build\Show_Rules, and the same reason: these rules
 * cross-reference clichés against death dates and show rows against roles, and
 * none of that could be tested while it sat inside a loop of `get_field()` and
 * `has_term()` calls.
 *
 * The data contract, as produced by the collector:
 *
 *     array(
 *         'post_id'    => int,
 *         'cliches'    => array<string>,   // term slugs, empty when none
 *         'last_death' => mixed,           // lezchars_last_death
 *         'has_actors' => bool,
 *         'shows'      => array<int, array{
 *             show_id:   int,              // 0 when the row names no show
 *             title:     string,
 *             has_years: bool,
 *             has_role:  bool,
 *         }>,
 *     )
 *
 * @package LWTV
 */

namespace LWTV\Debugger\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Character_Rules {

	/**
	 * Post type these findings are about.
	 */
	const POST_TYPE = 'post_type_characters';

	/**
	 * Taxonomy holding character clichés.
	 */
	const CLICHES = 'lez_cliches';

	/**
	 * Cliché that marks a character as dead.
	 */
	const DEAD = 'dead';

	/**
	 * Meta key holding the most recent death date.
	 */
	const META_DEATH = 'lezchars_last_death';

	/**
	 * Every finding for one character.
	 *
	 * @param  array $character Collected character data.
	 * @return array<int, array<string, mixed>>
	 */
	public static function evaluate( array $character ): array {
		if ( ! (int) ( $character['post_id'] ?? 0 ) ) {
			return array();
		}

		return array_merge(
			self::cliches( $character ),
			self::death( $character ),
			self::shows( $character ),
			self::actors( $character )
		);
	}

	/**
	 * A character carrying no cliché terms at all.
	 *
	 * Detect only -- Debugger\Characters::add_none_cliche() repairs it under
	 * --fix-it or from the admin.
	 *
	 * @param  array $character Collected character data.
	 * @return array
	 */
	public static function cliches( array $character ): array {
		if ( ! empty( $character['cliches'] ) ) {
			return array();
		}

		return array( Findings::make( (int) $character['post_id'], self::POST_TYPE, 'char-missing-cliche' ) );
	}

	/**
	 * Dead, but with no date recorded.
	 *
	 * This one matters beyond tidiness: the death statistics and Bury Your Queers
	 * both key off the cliché, so a dead character with no date is counted in one
	 * place and missing from another.
	 *
	 * @param  array $character Collected character data.
	 * @return array
	 */
	public static function death( array $character ): array {
		$cliches = (array) ( $character['cliches'] ?? array() );

		if ( ! in_array( self::DEAD, $cliches, true ) ) {
			return array();
		}

		if ( ! empty( $character['last_death'] ) ) {
			return array();
		}

		return array( Findings::make( (int) $character['post_id'], self::POST_TYPE, 'char-dead-no-date' ) );
	}

	/**
	 * Problems with the character's show rows.
	 *
	 * A character with no shows at all is one finding. A character with shows is
	 * checked row by row, and the show's title rides along in the message,
	 * because "no role set" is only actionable if you know which show.
	 *
	 * @param  array $character Collected character data.
	 * @return array
	 */
	public static function shows( array $character ): array {
		$post_id = (int) $character['post_id'];
		$shows   = (array) ( $character['shows'] ?? array() );

		if ( empty( $shows ) ) {
			return array( Findings::make( $post_id, self::POST_TYPE, 'char-no-shows' ) );
		}

		$findings = array();

		foreach ( $shows as $show ) {
			$title = (string) ( $show['title'] ?? '' );

			if ( empty( $show['has_years'] ) ) {
				$findings[] = Findings::make( $post_id, self::POST_TYPE, 'char-no-years', self::about( 'No years on air set', $title ) );
			}

			if ( empty( $show['has_role'] ) ) {
				$findings[] = Findings::make( $post_id, self::POST_TYPE, 'char-no-role', self::about( 'No role set', $title ) );
			}

			if ( ! (int) ( $show['show_id'] ?? 0 ) ) {
				$findings[] = Findings::make( $post_id, self::POST_TYPE, 'char-no-show-name' );
			}
		}

		return $findings;
	}

	/**
	 * A character with nobody playing them.
	 *
	 * @param  array $character Collected character data.
	 * @return array
	 */
	public static function actors( array $character ): array {
		if ( ! empty( $character['has_actors'] ) ) {
			return array();
		}

		return array( Findings::make( (int) $character['post_id'], self::POST_TYPE, 'char-no-actors' ) );
	}

	/**
	 * "<problem> for <show>." -- or just "<problem>." when the row names no show.
	 *
	 * The old copy appended the title unconditionally, so a show row with no
	 * show produced "No role set for ." Naming nothing reads better than naming
	 * an empty string.
	 *
	 * @param  string $problem Problem phrase, no trailing punctuation.
	 * @param  string $title   Show title, possibly empty.
	 * @return string
	 */
	private static function about( string $problem, string $title ): string {
		return ( '' === trim( $title ) ) ? $problem . '.' : $problem . ' for ' . $title . '.';
	}

	/**
	 * Meta keys the rules need.
	 *
	 * @return array<string>
	 */
	public static function meta_keys(): array {
		return array( self::META_DEATH );
	}

	/**
	 * Taxonomies the rules need.
	 *
	 * @return array<string>
	 */
	public static function taxonomies(): array {
		return array( self::CLICHES );
	}
}
