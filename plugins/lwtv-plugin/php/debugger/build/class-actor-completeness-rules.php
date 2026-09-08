<?php
/**
 * Does an actor have a photo and a biography?
 *
 * The data contract, as produced by Collect\Actor_Completeness_Collector:
 *
 *     array(
 *         'post_id'   => int,
 *         'has_image' => bool,
 *         'has_bio'   => bool,
 *     )
 *
 * @package LWTV
 */

namespace LWTV\Debugger\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Actor_Completeness_Rules {

	/**
	 * Post type these findings are about.
	 */
	const POST_TYPE = 'post_type_actors';

	/**
	 * Every finding for one actor.
	 *
	 * @param  array $actor Collected actor data.
	 * @return array<int, array<string, mixed>>
	 */
	public static function evaluate( array $actor ): array {
		$post_id = (int) ( $actor['post_id'] ?? 0 );

		if ( ! $post_id ) {
			return array();
		}

		$findings = array();

		if ( empty( $actor['has_image'] ) ) {
			$findings[] = Findings::make( $post_id, self::POST_TYPE, 'actor-no-image' );
		}

		if ( empty( $actor['has_bio'] ) ) {
			$findings[] = Findings::make( $post_id, self::POST_TYPE, 'actor-no-bio' );
		}

		return $findings;
	}
}
