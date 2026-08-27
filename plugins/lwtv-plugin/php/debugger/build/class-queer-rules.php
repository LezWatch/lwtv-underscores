<?php
/**
 * Does a character's Queer IRL cliché agree with who plays them?
 *
 * PURE. "Queer IRL" says the actor is queer in real life, so it is a claim about
 * the actor, checked against the actors actually linked to the character. Either
 * direction of disagreement is worth reporting, and they are different problems:
 * a missing tag is a gap in our data, while a tag with no queer actor behind it
 * is a claim we cannot support.
 *
 * The data contract, as produced by Collect\Queer_Collector:
 *
 *     array(
 *         'post_id'       => int,
 *         'has_actors'    => bool,
 *         'flagged_queer' => bool,   // carries the queer-irl cliché
 *         'actor_queer'   => bool,   // at least one linked actor is queer
 *     )
 *
 * @package LWTV
 */

namespace LWTV\Debugger\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Queer_Rules {

	/**
	 * Post type these findings are about.
	 */
	const POST_TYPE = 'post_type_characters';

	/**
	 * Cliché claiming the actor is queer in real life.
	 */
	const CLICHE = 'queer-irl';

	/**
	 * Every finding for one character.
	 *
	 * @param  array $character Collected character data.
	 * @return array<int, array<string, mixed>>
	 */
	public static function evaluate( array $character ): array {
		$post_id = (int) ( $character['post_id'] ?? 0 );

		if ( ! $post_id ) {
			return array();
		}

		/*
		 * No actors at all, so there is nothing to compare the cliché against.
		 * Reported instead of skipped, because a character nobody plays is itself
		 * a gap -- and the alternative would be silently passing every untagged
		 * character with no actors.
		 */
		if ( empty( $character['has_actors'] ) ) {
			return array( Findings::make( $post_id, self::POST_TYPE, 'char-no-actors-listed' ) );
		}

		$flagged = ! empty( $character['flagged_queer'] );
		$queer   = ! empty( $character['actor_queer'] );

		if ( $queer && ! $flagged ) {
			return array( Findings::make( $post_id, self::POST_TYPE, 'char-missing-queer-irl' ) );
		}

		if ( ! $queer && $flagged ) {
			return array( Findings::make( $post_id, self::POST_TYPE, 'char-no-queer-actor' ) );
		}

		return array();
	}
}
