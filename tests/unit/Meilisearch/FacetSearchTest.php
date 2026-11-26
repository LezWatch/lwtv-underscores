<?php
/**
 * Unit tests for the FacetWP keyword-facet search helpers.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Meilisearch;

use PHPUnit\Framework\TestCase;
use LWTV\Meilisearch\Build\Facet_Search;

class FacetSearchTest extends TestCase {

	public function test_body_requires_every_word(): void {
		$body  = Facet_Search::body( 'alex danvers', 'local_characters' );
		$query = $body['queries'][0];

		$this->assertCount( 1, $body['queries'] );
		$this->assertSame( 'local_characters', $query['indexUid'] );
		$this->assertSame( 'alex danvers', $query['q'] );
		$this->assertSame( 'all', $query['matchingStrategy'] );
		$this->assertSame( array( 'id' ), $query['attributesToRetrieve'] );
		$this->assertSame( 1000, $query['limit'] );
		$this->assertSame( 1000, json_decode( (string) json_encode( $body ), true )['queries'][0]['limit'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- pure test.
	}

	public function test_ids_in_hit_order(): void {
		$response = array( 'results' => array( array( 'hits' => array( array( 'id' => 30 ), array( 'id' => '10' ), array( 'id' => 20 ) ) ) ) );
		$this->assertSame( array( 30, 10, 20 ), Facet_Search::ids( $response ) );
	}

	public function test_ids_skip_bad_and_duplicate_hits(): void {
		$response = array( 'results' => array( array( 'hits' => array( array( 'id' => 5 ), array( 'nope' => 1 ), array( 'id' => 0 ), array( 'id' => 5 ), 'x' ) ) ) );
		$this->assertSame( array( 5 ), Facet_Search::ids( $response ) );
	}

	public function test_ids_with_no_hits(): void {
		$this->assertSame( array(), Facet_Search::ids( array( 'results' => array( array( 'hits' => array() ) ) ) ) );
	}

	public function test_malformed_response_throws(): void {
		$this->expectException( \UnexpectedValueException::class );
		Facet_Search::ids( array( 'hits' => array() ) );
	}

	public function test_allowed_keeps_meilisearch_order(): void {
		$this->assertSame( array( 30, 20 ), Facet_Search::allowed( array( 30, 10, 20 ), array( 20, 30, 40 ) ) );
	}

	public function test_allowed_with_no_archive_list_keeps_all(): void {
		$this->assertSame( array( 30, 10 ), Facet_Search::allowed( array( 30, 10 ), array() ) );
	}

	public function test_allowed_accepts_string_ids(): void {
		$this->assertSame( array( 10 ), Facet_Search::allowed( array( 10, 11 ), array( '10' ) ) );
	}
}
