<?php
/**
 * Bury Your Queers: does a dead character's paperwork line up.
 *
 * PURE. Two questions, and the second one is the reason this class exists:
 *
 * 1. Does a character marked dead have a death year recorded?
 * 2. Is the death recorded on the right show?
 *
 * The second cannot be judged one show at a time. A character is killed on one
 * show and may have been on others that never killed her; those shows correctly
 * carry no BYQ trope. So the invariant is on the count of shows that *do* carry
 * it — exactly one — and that gate had a bug in it for as long as it was tangled
 * up with the WordPress reads. See DEBUGGER-REVIEW.md 1.9c.
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

		/*
		 * No shows at all is a bigger problem than any of this, and the Characters
		 * check reports it as `char-no-shows`. Reporting a missing death year for a
		 * character who is not recorded as being in anything would be noise on top
		 * of a more basic gap.
		 */
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
	 * Reported on its own terms. It used to be counted into the trope gate below,
	 * where it could cancel itself out: a dead character with no death year whose
	 * shows were otherwise correct was dropped from this report entirely.
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
	 * Exactly one of a dead character's shows should carry the trope: the one she
	 * was killed on.
	 *
	 * - One: correct, report nothing.
	 * - None: nobody recorded the death on the show it happened on, so every show
	 *   missing the trope is reported and one of them is the right one to fix.
	 * - More than one: two of her shows claim a queer death. That can be
	 *   legitimate — a show can kill someone else — but it is worth an eyeball.
	 *   It also reports nothing when no show is missing the trope, so this gate
	 *   can only ever suppress findings, never invent them.
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

			/*
			 * The edit link lives inside the message. That is markup in data,
			 * which the typed shape is otherwise trying to get away from — but the
			 * admin table has always rendered it, dropping it would cost editors a
			 * click, and Findings::plain() strips it for the CLI. The show ID is in
			 * context so a renderer can build the link properly later.
			 */
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
