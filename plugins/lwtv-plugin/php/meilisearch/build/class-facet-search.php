<?php
/**
 * Name: Facet Search
 * Description: Request and response helpers for the FacetWP keyword facets.
 *
 * Pure. One query against one index, every word required (a filter box
 * should narrow, not list every partial match), IDs only. The hits are then
 * limited to the posts the archive already lists, in Meilisearch's order, so
 * FacetWP's other facets keep narrowing and "order by relevance" works.
 *
 * A response that isn't shaped as expected throws, and the caller falls
 * back to SearchWP.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Facet_Search {

	/**
	 * Most hits asked for: Meilisearch's default maxTotalHits.
	 */
	const LIMIT = 1000;

	/**
	 * The /multi-search body for one keyword.
	 *
	 * @param string $q   Keyword.
	 * @param string $uid Index uid.
	 * @return array{queries: array<int, array<string, mixed>>}
	 */
	public static function body( string $q, string $uid ): array {
		return array(
			'queries' => array(
				array(
					'indexUid'             => $uid,
					'q'                    => $q,
					'matchingStrategy'     => 'all',
					'attributesToRetrieve' => array( 'id' ),
					'limit'                => self::LIMIT,
				),
			),
		);
	}

	/**
	 * Hit IDs, in order, without duplicates or invalid entries.
	 *
	 * @param array $response Decoded /multi-search response.
	 * @return int[]
	 * @throws \UnexpectedValueException When the response has the wrong shape.
	 */
	public static function ids( array $response ): array {
		$result = $response['results'][0] ?? null;
		if ( ! is_array( $result ) || ! is_array( $result['hits'] ?? null ) ) {
			throw new \UnexpectedValueException( 'Unexpected multi-search response.' );
		}

		$ids = array();
		foreach ( $result['hits'] as $hit ) {
			$id = is_array( $hit ) && is_numeric( $hit['id'] ?? null ) ? (int) $hit['id'] : 0;
			if ( $id > 0 ) {
				$ids[ $id ] = $id;
			}
		}

		return array_values( $ids );
	}

	/**
	 * Hit IDs the archive lists, in hit order. No list means no limit.
	 *
	 * @param int[]        $ids     Hit IDs, in order.
	 * @param array<mixed> $allowed The archive's post IDs (FWP()->unfiltered_post_ids).
	 * @return int[]
	 */
	public static function allowed( array $ids, array $allowed ): array {
		if ( empty( $allowed ) ) {
			return array_values( $ids );
		}

		$keep = array_flip( array_map( 'intval', $allowed ) );

		return array_values( array_filter( $ids, static fn( $id ) => isset( $keep[ (int) $id ] ) ) );
	}
}
