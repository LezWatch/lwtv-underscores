<?php
/**
 * Name: Search Request
 * Description: Builds the search results page's /multi-search body.
 *
 * Pure. One query per index, in tab order, each asking only for `id`
 * (WordPress renders the rows). The selected tab gets one page of IDs; the
 * others one hit each, enough for their counts. With no tab selected every
 * index gets a first page, so one request picks the tab and fills it.
 *
 * The search term goes in `q` as data, never into a filter string.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Search_Request {

	/**
	 * Results per page.
	 */
	const PER_PAGE = 20;

	/**
	 * The multi-search body.
	 *
	 * @param string                $q     Search term.
	 * @param string|null           $type  Selected tab, or null to pick the strongest match.
	 * @param int                   $paged Page of the selected tab (ignored with no tab; capped at Search_Params::MAX_PAGE).
	 * @param array<string, string> $uids  Index uid per type, in tab order.
	 * @return array{queries: array<int, array<string, mixed>>}
	 */
	public static function body( string $q, ?string $type, int $paged, array $uids ): array {
		$queries = array();

		foreach ( $uids as $tab => $uid ) {
			if ( null === $type ) {
				$queries[] = self::query( (string) $uid, $q, self::PER_PAGE, 0 );
			} elseif ( $tab === $type ) {
				$page      = min( max( 1, $paged ), Search_Params::MAX_PAGE );
				$queries[] = self::query( (string) $uid, $q, self::PER_PAGE, ( $page - 1 ) * self::PER_PAGE );
			} else {
				$queries[] = self::query( (string) $uid, $q, 1, 0 );
			}
		}

		return array( 'queries' => $queries );
	}

	/**
	 * A one-query body: the selected tab again, at another offset. Used when
	 * the requested page was past the end.
	 *
	 * @param string $q      Search term.
	 * @param string $uid    Index uid.
	 * @param int    $offset Offset.
	 * @return array{queries: array<int, array<string, mixed>>}
	 */
	public static function retry_body( string $q, string $uid, int $offset ): array {
		return array( 'queries' => array( self::query( $uid, $q, self::PER_PAGE, max( 0, $offset ) ) ) );
	}

	/**
	 * One query.
	 *
	 * @param string $uid    Index uid.
	 * @param string $q      Search term.
	 * @param int    $limit  Hits.
	 * @param int    $offset Offset.
	 * @return array<string, mixed>
	 */
	private static function query( string $uid, string $q, int $limit, int $offset ): array {
		return array(
			'indexUid'             => $uid,
			'q'                    => $q,
			'matchingStrategy'     => 'frequency',
			'showRankingScore'     => true,
			'attributesToRetrieve' => array( 'id' ),
			'limit'                => $limit,
			'offset'               => $offset,
		);
	}
}
