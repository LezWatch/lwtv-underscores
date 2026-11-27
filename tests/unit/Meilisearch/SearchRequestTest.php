<?php
/**
 * Unit tests for the search results page's /multi-search body.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Meilisearch;

use PHPUnit\Framework\TestCase;
use LWTV\Meilisearch\Build\Search_Request;

class SearchRequestTest extends TestCase {

	const UIDS = array(
		'shows'      => 'local_shows',
		'characters' => 'local_characters',
		'actors'     => 'local_actors',
		'content'    => 'local_content',
	);

	/**
	 * limit/offset per indexUid.
	 *
	 * @param array $body Request body.
	 * @return array<string, array{int, int}>
	 */
	private function windows( array $body ): array {
		$out = array();
		foreach ( $body['queries'] as $query ) {
			$out[ $query['indexUid'] ] = array( $query['limit'], $query['offset'] );
		}
		return $out;
	}

	public function test_selected_tab_gets_the_page_and_others_one_hit(): void {
		$body = Search_Request::body( 'supergirl', 'characters', 3, self::UIDS );

		$this->assertSame(
			array(
				'local_shows'      => array( 1, 0 ),
				'local_characters' => array( 20, 40 ),
				'local_actors'     => array( 1, 0 ),
				'local_content'    => array( 1, 0 ),
			),
			$this->windows( $body )
		);
	}

	public function test_no_type_gets_the_first_page_everywhere(): void {
		$body = Search_Request::body( 'supergirl', null, 1, self::UIDS );

		$this->assertSame(
			array(
				'local_shows'      => array( 20, 0 ),
				'local_characters' => array( 20, 0 ),
				'local_actors'     => array( 20, 0 ),
				'local_content'    => array( 20, 0 ),
			),
			$this->windows( $body )
		);
	}

	public function test_no_type_ignores_paged(): void {
		$body = Search_Request::body( 'supergirl', null, 4, self::UIDS );

		$this->assertSame( array( 20, 0 ), $this->windows( $body )['local_shows'] );
	}

	public function test_huge_paged_never_overflows_the_offset(): void {
		$body = Search_Request::body( 'q', 'shows', PHP_INT_MAX, self::UIDS );

		$this->assertIsInt( $body['queries'][0]['offset'] );
	}

	public function test_index_order_follows_uids(): void {
		$uids = array(
			'content' => 'x_content',
			'shows'   => 'x_shows',
		);
		$body = Search_Request::body( 'q', null, 1, $uids );

		$this->assertSame( array( 'x_content', 'x_shows' ), array_column( $body['queries'], 'indexUid' ) );
	}

	public function test_every_query_asks_only_for_id(): void {
		$body = Search_Request::body( 'q', 'shows', 1, self::UIDS );

		foreach ( $body['queries'] as $query ) {
			$this->assertSame( array( 'id' ), $query['attributesToRetrieve'] );
		}
	}

	public function test_every_query_uses_frequency_and_ranking_score(): void {
		$body = Search_Request::body( 'alex danvers', 'shows', 1, self::UIDS );

		foreach ( $body['queries'] as $query ) {
			$this->assertSame( 'alex danvers', $query['q'] );
			$this->assertSame( 'frequency', $query['matchingStrategy'] );
			$this->assertTrue( $query['showRankingScore'] );
		}
	}

	public function test_json_keeps_limit_and_offset_as_ints(): void {
		$json = json_encode( Search_Request::body( 'q', 'shows', 2, self::UIDS ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- pure test.

		$this->assertStringContainsString( '"limit":20,"offset":20', $json );
		$this->assertStringContainsString( '"limit":1,"offset":0', $json );
	}

	public function test_retry_body_is_one_query_at_the_offset(): void {
		$body = Search_Request::retry_body( 'supergirl', 'local_shows', 20 );

		$this->assertSame(
			array(
				'queries' => array(
					array(
						'indexUid'             => 'local_shows',
						'q'                    => 'supergirl',
						'matchingStrategy'     => 'frequency',
						'showRankingScore'     => true,
						'attributesToRetrieve' => array( 'id' ),
						'limit'                => 20,
						'offset'               => 20,
					),
				),
			),
			$body
		);
	}
}
