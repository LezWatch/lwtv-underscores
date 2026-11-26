<?php
/**
 * Unit tests for reading the search results page's /multi-search response.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Meilisearch;

use PHPUnit\Framework\TestCase;
use LWTV\Meilisearch\Build\Search_Results;

class SearchResultsTest extends TestCase {

	const TYPES = array( 'shows', 'characters', 'actors', 'content' );

	/**
	 * One index's result.
	 *
	 * @param int   $total  estimatedTotalHits.
	 * @param array $hits   id => _rankingScore, in order.
	 * @return array
	 */
	private function index_result( int $total, array $hits = array() ): array {
		$rows = array();
		foreach ( $hits as $id => $score ) {
			$rows[] = array(
				'id'            => $id,
				'_rankingScore' => $score,
			);
		}
		return array(
			'hits'               => $rows,
			'estimatedTotalHits' => $total,
		);
	}

	/**
	 * A response, one result per type in TYPES order.
	 *
	 * @param array ...$results Results.
	 * @return array
	 */
	private function response( array ...$results ): array {
		return array( 'results' => $results );
	}

	public function test_strongest_match_picks_the_highest_top_score(): void {
		$parsed = Search_Results::parse(
			$this->response(
				$this->index_result( 3, array( 1 => 0.7 ) ),
				$this->index_result( 5, array( 2 => 0.95 ) ),
				$this->index_result( 1, array( 3 => 0.8 ) ),
				$this->index_result( 9, array( 4 => 0.5 ) )
			),
			self::TYPES,
			null,
			1
		);

		$this->assertSame( 'characters', $parsed['type'] );
		$this->assertSame( array( 2 ), $parsed['ids'] );
	}

	public function test_a_tie_goes_in_tab_order(): void {
		$parsed = Search_Results::parse(
			$this->response(
				$this->index_result( 3, array( 1 => 0.9 ) ),
				$this->index_result( 5, array( 2 => 0.9 ) ),
				$this->index_result( 0 ),
				$this->index_result( 0 )
			),
			self::TYPES,
			null,
			1
		);

		$this->assertSame( 'shows', $parsed['type'] );
	}

	public function test_zero_hit_tabs_are_skipped(): void {
		$parsed = Search_Results::parse(
			$this->response(
				$this->index_result( 0 ),
				$this->index_result( 0 ),
				$this->index_result( 2, array( 7 => 0.2 ) ),
				$this->index_result( 0 )
			),
			self::TYPES,
			null,
			1
		);

		$this->assertSame( 'actors', $parsed['type'] );
	}

	public function test_all_zeros_give_shows(): void {
		$parsed = Search_Results::parse(
			$this->response( $this->index_result( 0 ), $this->index_result( 0 ), $this->index_result( 0 ), $this->index_result( 0 ) ),
			self::TYPES,
			null,
			1
		);

		$this->assertSame( 'shows', $parsed['type'] );
		$this->assertSame( array(), $parsed['ids'] );
		$this->assertSame( 1, $parsed['page'] );
		$this->assertSame( 1, $parsed['pages'] );
	}

	public function test_counts_come_from_estimated_total_hits(): void {
		$parsed = Search_Results::parse(
			$this->response(
				$this->index_result( 221, array( 1 => 0.9 ) ),
				$this->index_result( 14, array( 2 => 0.5 ) ),
				$this->index_result( 0 ),
				$this->index_result( 35, array( 4 => 0.3 ) )
			),
			self::TYPES,
			'characters',
			1
		);

		$this->assertSame(
			array(
				'shows'      => 221,
				'characters' => 14,
				'actors'     => 0,
				'content'    => 35,
			),
			$parsed['counts']
		);
		$this->assertSame( 'characters', $parsed['type'] );
	}

	public function test_paged_past_the_end_asks_for_the_last_page(): void {
		$parsed = Search_Results::parse(
			$this->response( $this->index_result( 25 ), $this->index_result( 1, array( 2 => 0.1 ) ), $this->index_result( 0 ), $this->index_result( 0 ) ),
			self::TYPES,
			'shows',
			9
		);

		$this->assertSame( 2, $parsed['page'] );
		$this->assertSame( 2, $parsed['pages'] );
		$this->assertSame( 20, $parsed['retry_offset'] );
		$this->assertSame( array(), $parsed['ids'] );
	}

	public function test_a_page_in_range_keeps_hit_order(): void {
		$parsed = Search_Results::parse(
			$this->response(
				$this->index_result(
					25,
					array(
						50 => 0.4,
						10 => 0.3,
						40 => 0.3,
						20 => 0.2,
						30 => 0.1,
					)
				),
				$this->index_result( 0 ),
				$this->index_result( 0 ),
				$this->index_result( 0 )
			),
			self::TYPES,
			'shows',
			2
		);

		$this->assertNull( $parsed['retry_offset'] );
		$this->assertSame( array( 50, 10, 40, 20, 30 ), $parsed['ids'] );
		$this->assertSame( 2, $parsed['page'] );
		$this->assertSame( 2, $parsed['pages'] );
	}

	public function test_no_type_is_always_page_one(): void {
		$parsed = Search_Results::parse(
			$this->response( $this->index_result( 90, array( 1 => 0.9 ) ), $this->index_result( 0 ), $this->index_result( 0 ), $this->index_result( 0 ) ),
			self::TYPES,
			null,
			4
		);

		$this->assertSame( 1, $parsed['page'] );
		$this->assertSame( 5, $parsed['pages'] );
		$this->assertNull( $parsed['retry_offset'] );
	}

	public function test_an_empty_tab_has_one_page_and_no_retry(): void {
		$parsed = Search_Results::parse(
			$this->response( $this->index_result( 4, array( 1 => 0.9 ) ), $this->index_result( 0 ), $this->index_result( 0 ), $this->index_result( 0 ) ),
			self::TYPES,
			'actors',
			3
		);

		$this->assertSame( 'actors', $parsed['type'] );
		$this->assertSame( 1, $parsed['page'] );
		$this->assertSame( 1, $parsed['pages'] );
		$this->assertNull( $parsed['retry_offset'] );
	}

	public function test_missing_results_throws(): void {
		$this->expectException( \UnexpectedValueException::class );
		Search_Results::parse( array( 'message' => 'nope' ), self::TYPES, null, 1 );
	}

	public function test_wrong_number_of_results_throws(): void {
		$this->expectException( \UnexpectedValueException::class );
		Search_Results::parse( $this->response( $this->index_result( 1, array( 1 => 0.1 ) ) ), self::TYPES, null, 1 );
	}

	public function test_retry_ids_reads_the_one_result(): void {
		$this->assertSame(
			array( 21, 22 ),
			Search_Results::retry_ids(
				$this->response(
					$this->index_result(
						22,
						array(
							21 => 0.2,
							22 => 0.1,
						)
					)
				)
			)
		);
	}

	public function test_retry_ids_throws_on_a_bad_response(): void {
		$this->expectException( \UnexpectedValueException::class );
		Search_Results::retry_ids( array() );
	}
}
