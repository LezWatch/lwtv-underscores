<?php
/**
 * Unit tests for the API usage report aggregator.
 *
 * The report exists to answer "what is broken?" before "what is popular?", so
 * the status buckets and error rate are pinned exactly. 499 (client gave up)
 * is its own bucket and is not an error. Hits to a broken route are unmet
 * demand, which is why 5xx leads the default sort.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Admin_Menu;

use PHPUnit\Framework\TestCase;
use LWTV\Admin_Menu\Build\Api_Route_Matcher;
use LWTV\Admin_Menu\Build\Api_Usage_Report;

class ApiUsageReportTest extends TestCase {

	const DEATH = '/lwtv/v1/last-death';
	const STATS = '/lwtv/v1/stats/(?P<type>[a-zA-Z.\-]+)/(?P<format>[a-zA-Z\-]+)/(?P<page>[a-zA-Z0-9\-]+)';
	const LIST  = '/lwtv/v1/list/(?P<type>[a-zA-Z.\-]+)';

	private function matcher(): Api_Route_Matcher {
		return new Api_Route_Matcher( array( self::DEATH, self::STATS, self::LIST ) );
	}

	/**
	 * One parsed CSV row.
	 */
	private static function row( string $date, string $path, string $consumer, string $status, int $hits, int $bytes = 10 ): array {
		return array(
			'date'     => $date,
			'endpoint' => '/wp-json/lwtv/v1/' . $path,
			'consumer' => $consumer,
			'status'   => $status,
			'hits'     => $hits,
			'bytes'    => $bytes,
		);
	}

	private function build( array $rows, string $from = '2026-11-01', string $to = '2026-11-30', array $exclude = array(), string $new_since = '' ): array {
		return Api_Usage_Report::build( $rows, $this->matcher(), $from, $to, $exclude, $new_since );
	}

	private static function by_route( array $report, string $route ): array {
		foreach ( $report['routes'] as $r ) {
			if ( $route === $r['route'] ) {
				return $r;
			}
		}
		self::fail( 'route not in report: ' . $route );
	}

	public function test_status_classes_and_error_rate(): void {
		$report = $this->build(
			array(
				self::row( '2026-11-06', 'last-death', 'a', '200', 6 ),
				self::row( '2026-11-06', 'last-death', 'a', '404', 2 ),
				self::row( '2026-11-06', 'last-death', 'a', '499', 1 ),
				self::row( '2026-11-06', 'last-death', 'a', '500', 1 ),
			)
		);
		$death  = self::by_route( $report, self::DEATH );

		$this->assertSame( 10, $death['hits'] );
		$this->assertSame( 6, $death['s2xx'] );
		$this->assertSame( 2, $death['s4xx'] );
		$this->assertSame( 1, $death['s499'] );
		$this->assertSame( 1, $death['s5xx'] );
		$this->assertEqualsWithDelta( 0.3, $death['error_rate'], 0.0001 );
	}

	public function test_range_filters_rows(): void {
		$report = $this->build(
			array(
				self::row( '2026-11-05', 'last-death', 'a', '200', 4 ),
				self::row( '2026-11-07', 'last-death', 'a', '200', 1 ),
			),
			'2026-11-06',
			'2026-11-12'
		);
		$death  = self::by_route( $report, self::DEATH );

		$this->assertSame( 1, $death['hits'] );
		$this->assertSame( '2026-11-07', $death['last_seen'] );
	}

	public function test_excluded_consumers_are_dropped(): void {
		$report = $this->build(
			array(
				self::row( '2026-11-06', 'last-death', 'uptime-kuma', '200', 50, 500 ),
				self::row( '2026-11-06', 'last-death', 'someone', '200', 1, 7 ),
			),
			'2026-11-01',
			'2026-11-30',
			array( 'uptime-kuma' )
		);

		$this->assertSame( 1, self::by_route( $report, self::DEATH )['hits'] );
		$this->assertSame( array( 'someone' ), array_column( $report['consumers'], 'consumer' ) );
		$this->assertSame(
			array(
				'hits'  => 1,
				'bytes' => 7,
			),
			$report['totals']
		);
	}

	public function test_distinct_consumers_and_top_paths(): void {
		$report = $this->build(
			array(
				self::row( '2026-11-06', 'stats/shows/name/small', 'a', '200', 1 ),
				self::row( '2026-11-06', 'stats/shows/name/big', 'b', '200', 5 ),
				self::row( '2026-11-07', 'stats/shows/name/big', 'c', '200', 2 ),
			)
		);
		$stats  = self::by_route( $report, self::STATS );

		$this->assertSame( 3, $stats['consumers'] );
		$this->assertSame(
			array(
				array(
					'path' => '/wp-json/lwtv/v1/stats/shows/name/big',
					'hits' => 7,
				),
				array(
					'path' => '/wp-json/lwtv/v1/stats/shows/name/small',
					'hits' => 1,
				),
			),
			$stats['top_paths']
		);
	}

	public function test_top_paths_are_capped_at_five(): void {
		$rows = array();
		foreach ( range( 1, 7 ) as $i ) {
			$rows[] = self::row( '2026-11-06', 'stats/shows/name/s' . $i, 'a', '200', $i );
		}

		$this->assertCount( 5, self::by_route( $this->build( $rows ), self::STATS )['top_paths'] );
	}

	public function test_no_hits_lists_registered_routes_without_traffic(): void {
		$report = $this->build(
			array(
				self::row( '2026-11-06', 'last-death', 'a', '200', 1 ),
				self::row( '2026-11-06', 'nope', 'a', '404', 1 ),
			)
		);

		$this->assertSame( array( self::STATS, self::LIST ), $report['no_hits'] );
	}

	public function test_unmatched_paths_are_kept(): void {
		$report = $this->build( array( self::row( '2026-11-06', 'nope', 'a', '404', 3 ) ) );

		$this->assertSame( 3, self::by_route( $report, Api_Route_Matcher::UNMATCHED )['hits'] );
	}

	public function test_empty_range_is_safe(): void {
		$report = $this->build(
			array( self::row( '2026-10-01', 'last-death', 'a', '200', 1 ) ),
			'2026-11-01',
			'2026-11-30'
		);

		$this->assertSame( array(), $report['routes'] );
		$this->assertSame( array(), $report['consumers'] );
		$this->assertSame( array( self::DEATH, self::STATS, self::LIST ), $report['no_hits'] );
		$this->assertSame(
			array(
				'hits'  => 0,
				'bytes' => 0,
			),
			$report['totals']
		);
	}

	public function test_new_consumer_uses_all_data_first_seen(): void {
		$report = $this->build(
			array(
				self::row( '2026-11-01', 'last-death', 'old', '200', 1 ),
				self::row( '2026-11-11', 'last-death', 'old', '200', 1 ),
				self::row( '2026-11-11', 'last-death', 'fresh', '200', 1 ),
			),
			'2026-11-10',
			'2026-11-12',
			array(),
			'2026-11-05'
		);
		$by     = array_column( $report['consumers'], null, 'consumer' );

		$this->assertFalse( $by['old']['is_new'] );
		$this->assertSame( '2026-11-01', $by['old']['first_seen'] );
		$this->assertSame( '2026-11-11', $by['old']['last_seen'] );
		$this->assertTrue( $by['fresh']['is_new'] );
	}

	public function test_consumer_rows_list_routes_by_hits(): void {
		$report = $this->build(
			array(
				self::row( '2026-11-06', 'last-death', 'a', '500', 1 ),
				self::row( '2026-11-06', 'list/shows', 'a', '200', 3 ),
			)
		);
		$a      = $report['consumers'][0];

		$this->assertSame( 4, $a['hits'] );
		$this->assertSame( 1, $a['s5xx'] );
		$this->assertEqualsWithDelta( 0.25, $a['error_rate'], 0.0001 );
		$this->assertSame(
			array(
				array(
					'route' => self::LIST,
					'hits'  => 3,
				),
				array(
					'route' => self::DEATH,
					'hits'  => 1,
				),
			),
			$a['routes']
		);
	}

	public function test_default_sort_puts_5xx_first(): void {
		$report = $this->build(
			array(
				self::row( '2026-11-06', 'last-death', 'a', '200', 900 ),
				self::row( '2026-11-06', 'list/shows', 'a', '500', 2 ),
			)
		);

		$this->assertSame( self::LIST, $report['routes'][0]['route'] );
	}

	public function test_sort_rejects_unknown_key(): void {
		$rows = array(
			array(
				'route'      => 'a',
				's5xx'       => 0,
				'error_rate' => 0.0,
				'hits'       => 9,
			),
			array(
				'route'      => 'b',
				's5xx'       => 3,
				'error_rate' => 0.5,
				'hits'       => 1,
			),
		);

		$this->assertSame( 'b', Api_Usage_Report::sort( $rows, 'evil); drop' )[0]['route'] );
	}

	public function test_sort_ascending_by_name(): void {
		$rows = array(
			array(
				'route'      => 'b',
				's5xx'       => 0,
				'error_rate' => 0.0,
				'hits'       => 1,
			),
			array(
				'route'      => 'a',
				's5xx'       => 0,
				'error_rate' => 0.0,
				'hits'       => 1,
			),
		);

		$this->assertSame( array( 'a', 'b' ), array_column( Api_Usage_Report::sort( $rows, 'route', 'asc' ), 'route' ) );
	}
}
