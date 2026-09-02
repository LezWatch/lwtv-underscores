<?php
/**
 * Fetches what the actor completeness rules need.
 *
 * Two questions per actor, but across every published actor, so the batch's meta
 * cache is primed first (the featured image is a meta lookup).
 *
 * @package LWTV
 */

namespace LWTV\Debugger\Collect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Actor_Completeness_Collector {

	/**
	 * How many actors to gather per pass.
	 */
	const BATCH = 200;

	/**
	 * Collect one batch of actors.
	 *
	 * @param  array<int> $actor_ids Actor post IDs.
	 * @return array<int, array<string, mixed>>
	 */
	public function collect( array $actor_ids ): array {
		$actor_ids = array_values( array_unique( array_map( 'intval', $actor_ids ) ) );

		if ( empty( $actor_ids ) ) {
			return array();
		}

		update_postmeta_cache( $actor_ids );

		$collected = array();

		foreach ( $actor_ids as $actor_id ) {
			$collected[] = array(
				'post_id'   => $actor_id,
				'has_image' => has_post_thumbnail( $actor_id ),
				'has_bio'   => '' !== trim( (string) get_the_content( '', false, $actor_id ) ),
			);
		}

		return $collected;
	}
}
