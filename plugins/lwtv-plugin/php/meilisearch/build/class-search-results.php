<?php
/**
 * Name: Search Results
 * Description: Reads the search results page's /multi-search response.
 *
 * Pure. Turns the response into per-tab counts, the tab to show, that tab's
 * post IDs in Meilisearch's order, and the page numbers. The tab rule is the
 * modal's: the highest top-hit _rankingScore wins, ties go in tab order,
 * empty tabs are skipped, and with no hits anywhere it's the first tab.
 *
 * A response that isn't shaped as expected throws, and the caller falls
 * back to the standard search.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Search_Results {

	/**
	 * Parse a response.
	 *
	 * @param array       $response Decoded /multi-search response.
	 * @param string[]    $types    Tab types, in the order the queries were sent.
	 * @param string|null $type     Selected tab, or null to pick the strongest match.
	 * @param int         $paged    Requested page.
	 * @return array{counts: array<string, int>, type: string, ids: int[], page: int, pages: int, retry_offset: int|null}
	 * @throws \UnexpectedValueException When the response has the wrong shape.
	 */
	public static function parse( array $response, array $types, ?string $type, int $paged ): array {
		$results = $response['results'] ?? null;
		if ( ! is_array( $results ) || count( $results ) !== count( $types ) ) {
			throw new \UnexpectedValueException( 'Unexpected multi-search response.' );
		}

		$types  = array_values( $types );
		$counts = array();
		$hits   = array();
		$best   = null;
		$score  = -1.0;

		foreach ( array_values( $results ) as $i => $result ) {
			if ( ! is_array( $result ) ) {
				throw new \UnexpectedValueException( 'Unexpected multi-search result.' );
			}

			$tab            = (string) $types[ $i ];
			$counts[ $tab ] = max( 0, (int) ( $result['estimatedTotalHits'] ?? 0 ) );
			$hits[ $tab ]   = is_array( $result['hits'] ?? null ) ? $result['hits'] : array();

			$top = $hits[ $tab ][0]['_rankingScore'] ?? null;
			if ( $counts[ $tab ] > 0 && is_numeric( $top ) && (float) $top > $score ) {
				$score = (float) $top;
				$best  = $tab;
			}
		}

		$chosen = ( null !== $type && isset( $counts[ $type ] ) ) ? $type : ( $best ?? (string) $types[0] );
		$total  = $counts[ $chosen ];
		$pages  = max( 1, (int) ceil( $total / Search_Request::PER_PAGE ) );
		$page   = ( null === $type ) ? 1 : min( max( 1, $paged ), $pages );
		$ids    = self::ids( $hits[ $chosen ] );

		// Past the end: the request's offset found nothing, so ask for the last page.
		$retry = null;
		if ( null !== $type && array() === $ids && $total > 0 && $paged > $pages ) {
			$retry = ( $pages - 1 ) * Search_Request::PER_PAGE;
		}

		return array(
			'counts'       => $counts,
			'type'         => $chosen,
			'ids'          => $ids,
			'page'         => $page,
			'pages'        => $pages,
			'retry_offset' => $retry,
		);
	}

	/**
	 * The IDs from a one-query retry response.
	 *
	 * @param array $response Decoded /multi-search response.
	 * @return int[]
	 * @throws \UnexpectedValueException When the response has the wrong shape.
	 */
	public static function retry_ids( array $response ): array {
		$results = $response['results'] ?? null;
		if ( ! is_array( $results ) || 1 !== count( $results ) || ! is_array( $results[0] ?? null ) ) {
			throw new \UnexpectedValueException( 'Unexpected multi-search response.' );
		}

		return self::ids( is_array( $results[0]['hits'] ?? null ) ? $results[0]['hits'] : array() );
	}

	/**
	 * Post IDs from hits, in order.
	 *
	 * @param array $hits Hits.
	 * @return int[]
	 */
	private static function ids( array $hits ): array {
		$ids = array();
		foreach ( $hits as $hit ) {
			$id = is_array( $hit ) ? ( $hit['id'] ?? null ) : null;
			if ( is_numeric( $id ) && (int) $id > 0 ) {
				$ids[] = (int) $id;
			}
		}

		return $ids;
	}
}
