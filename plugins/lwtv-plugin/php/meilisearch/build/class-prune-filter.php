<?php
/**
 * Name: Prune Filter
 * Description: The Meilisearch filter that selects documents no longer published.
 *
 * Pure. Used by `wp lwtv meili reindex` after re-sending every published
 * character, with POST /indexes/{uid}/documents/delete. Requires `id` in
 * filterableAttributes.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Prune_Filter {

	/**
	 * A filter matching every document whose id is not in the set.
	 *
	 * Returns null for an empty set: "not in nothing" would delete the
	 * whole index, which is never what a reindex means.
	 *
	 * @param array $ids Published post IDs.
	 * @return string|null
	 */
	public static function not_in( array $ids ): ?string {
		$clean = array();
		foreach ( $ids as $id ) {
			if ( is_numeric( $id ) && (int) $id > 0 ) {
				$clean[ (int) $id ] = (int) $id;
			}
		}

		if ( empty( $clean ) ) {
			return null;
		}

		sort( $clean );

		return 'id NOT IN [' . implode( ', ', $clean ) . ']';
	}
}
