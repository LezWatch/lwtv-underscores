<?php
/**
 * Fetches what the actor rules need.
 *
 * The thinnest of the three collectors, because every actor check is a meta
 * read: one `get_post_meta( $id )` per actor returns the lot, and priming the
 * cache for the batch first means that call is served from memory.
 *
 * @package LWTV
 */

namespace LWTV\Debugger\Collect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Debugger\Build\Actor_Rules;

class Actor_Collector {

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
				'post_id' => $actor_id,
				'meta'    => $this->meta_for( $actor_id ),
			);
		}

		return $collected;
	}

	/**
	 * The meta the rules need, for one actor.
	 *
	 * Only the declared keys, so the collected array stays honest about what the
	 * rules are allowed to look at.
	 *
	 * @param  int $actor_id Actor post ID.
	 * @return array<string, mixed>
	 */
	private function meta_for( int $actor_id ): array {
		$meta = array();

		foreach ( Actor_Rules::meta_keys() as $key ) {
			$meta[ $key ] = get_post_meta( $actor_id, $key, true );
		}

		return $meta;
	}
}
