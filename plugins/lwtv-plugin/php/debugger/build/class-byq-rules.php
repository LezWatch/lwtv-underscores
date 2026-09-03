<?php
/**
 * Bury Your Queers: does a dead character's paperwork line up.
 *
 * 1. Does a character marked dead have a death year recorded?
 * 2. Is the death recorded on the right show?
 *
 * The second cannot be judged one show at a time. A character is killed on one
 * show and may have been on others that never killed her; those shows correctly
 * carry no BYQ trope. So the invariant is on the count of shows that *do* carry
 * it.
 *
 * The data contract, as produced by Collect\Byq_Collector:
 *
 *     array(
 *         'post_id'    => int,
 *         'has_death_year' => bool,
 *         'shows'      => array<int, array{
 *             show_id:    int,
 *             title:      string,
 *             has_trope:  bool,   // the show carries dead-queers
 *         }>,
 *     )
 *
 * @package LWTV
 */

namespace LWTV\Debugger\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Byq_Rules {

	/**
	 * Post type these findings are about.
	 */
	const POST_TYPE = 'post_type_characters';

	/**
	 * Taxonomy and term marking a show as having buried a queer character.
	 */
	const TROPE_TAXONOMY = 'lez_tropes';
	const TROPE          = 'dead-queers';

	/**
	 * How many of a dead character's shows should carry the trope.
	 */
	const EXPECTED_WITH_TROPE = 1;

	/**
	 * Every finding for one dead character.
	 *
	 * @param  array $character Collected character data.
	 * @return array<int, array<string, mixed>>
	 */
	public static function evaluate( array $character ): array {
		$post_id = (int) ( $character['post_id'] ?? 0 );
		$shows   = (array) ( $character['shows'] ?? array() );

		if ( ! $post_id ) {
			return array();
		}

		// If a character has no shows, there's a bigger issue at hand.
		if ( empty( $shows ) ) {
			return array();
		}

		return array_merge(
			self::death_year( $character ),
			self::tropes( $post_id, $shows )
		);
	}

	/**
	 * Dead, with no death year recorded.
	 *
	 * @param  array $character Collected character data.
	 * @return array
	 */
	public static function death_year( array $character ): array {
		if ( ! empty( $character['has_death_year'] ) ) {
			return array();
		}

		return array( Findings::make( (int) $character['post_id'], self::POST_TYPE, 'char-no-death-year' ) );
	}

	/**
	 * Shows whose BYQ trope does not add up.
	 *
	 * Exactly one of a dead character's shows should carry the trope: the one they
	 * were killed on.
	 *
	 * - One: correct, report nothing.
	 * - None: nobody recorded the death on the show it happened on, so every show
	 *   missing the trope is reported and one of them is the right one to fix.
	 * - More than one: two shows claim a queer death. This is usually a Sara Lance
	 *   Paradigm, but needs eyes to verify.
	 *
	 * @param  int   $post_id Character post ID.
	 * @param  array $shows   Collected show rows.
	 * @return array
	 */
	public static function tropes( int $post_id, array $shows ): array {
		$missing = array();

		foreach ( $shows as $show ) {
			if ( ! empty( $show['has_trope'] ) ) {
				continue;
			}

			$show_id = (int) ( $show['show_id'] ?? 0 );
			$title   = (string) ( $show['title'] ?? '' );

			$missing[] = Findings::make(
				$post_id,
				self::POST_TYPE,
				'char-show-no-byq-trope',
				'There is no BYQ trope on the show <a href="/wp-admin/post.php?post=' . $show_id . '&action=edit">' . $title . '</a> (edit).',
				array( 'show_id' => $show_id )
			);
		}

		$with_trope = count( $shows ) - count( $missing );

		return ( self::EXPECTED_WITH_TROPE === $with_trope ) ? array() : $missing;
	}
}
