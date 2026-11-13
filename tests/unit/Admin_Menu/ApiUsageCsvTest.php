<?php
/**
 * Unit tests for the API usage CSV parser.
 *
 * The CSV is written by cron/api-roundup.sh and every field in it comes from
 * request headers somebody else controls. The parser's job is to be strict:
 * anything that doesn't look like a rollup row is dropped rather than guessed
 * at, and repeated keys (the file is concatenated across runs) are summed.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Admin_Menu;

use PHPUnit\Framework\TestCase;
use LWTV\Admin_Menu\Build\Api_Usage_Csv;

class ApiUsageCsvTest extends TestCase {

	public function test_header_is_skipped(): void {
		$this->assertSame( array(), Api_Usage_Csv::parse( array( 'date,endpoint,consumer,status,hits,bytes' ) ) );
	}

	public function test_row_is_typed(): void {
		$rows = Api_Usage_Csv::parse( array( '2026-11-06,/wp-json/lwtv/v1/last-death,uptime-kuma,200,96,12345' ) );

		$this->assertSame(
			array(
				array(
					'date'     => '2026-11-06',
					'endpoint' => '/wp-json/lwtv/v1/last-death',
					'consumer' => 'uptime-kuma',
					'status'   => '200',
					'hits'     => 96,
					'bytes'    => 12345,
				),
			),
			$rows
		);
	}

	public function test_duplicate_keys_are_summed(): void {
		$rows = Api_Usage_Csv::parse(
			array(
				'2026-11-06,/wp-json/lwtv/v1/last-death,uptime-kuma,200,2,100',
				'2026-11-06,/wp-json/lwtv/v1/last-death,uptime-kuma,200,3,50',
			)
		);

		$this->assertCount( 1, $rows );
		$this->assertSame( 5, $rows[0]['hits'] );
		$this->assertSame( 150, $rows[0]['bytes'] );
	}

	public function test_malformed_lines_are_skipped(): void {
		$rows = Api_Usage_Csv::parse(
			array(
				'',
				'2026-11-06,/x',
				'bad-date,/wp-json/lwtv/v1/x,c,200,1,1',
				'2026-11-06,/wp-json/lwtv/v1/x,c,2xx,1,1',
				'2026-11-06,/wp-json/lwtv/v1/x,c,200,-1,1',
				'2026-11-06,wp-json/lwtv/v1/x,c,200,1,1',
				'2026-11-06,/wp-json/lwtv/v1/x,c,200,1,1,extra',
			)
		);

		$this->assertSame( array(), $rows );
	}

	public function test_empty_consumer_is_kept_under_a_visible_label(): void {
		// api-roundup.sh's fallback strips everything after the first `/` or
		// space, so a UA starting with either becomes ''. Those hits are real.
		$rows = Api_Usage_Csv::parse( array( '2026-11-12,/wp-json/lwtv/v1/stats,,200,3,10' ) );

		$this->assertCount( 1, $rows );
		$this->assertSame( Api_Usage_Csv::EMPTY_CONSUMER, $rows[0]['consumer'] );
		$this->assertSame( 3, $rows[0]['hits'] );
	}

	public function test_trailing_newline_and_crlf_are_tolerated(): void {
		$rows = Api_Usage_Csv::parse( array( "2026-11-06,/wp-json/lwtv/v1/x,c,200,1,10\r\n" ) );

		$this->assertCount( 1, $rows );
		$this->assertSame( 10, $rows[0]['bytes'] );
	}

	public function test_newest_date_ignores_order(): void {
		$rows = Api_Usage_Csv::parse(
			array(
				'2026-11-08,/wp-json/lwtv/v1/x,c,200,1,1',
				'2026-11-12,/wp-json/lwtv/v1/x,c,200,1,1',
				'2026-11-06,/wp-json/lwtv/v1/x,c,200,1,1',
			)
		);

		$this->assertSame( '2026-11-12', Api_Usage_Csv::newest_date( $rows ) );
		$this->assertNull( Api_Usage_Csv::newest_date( array() ) );
	}
}
