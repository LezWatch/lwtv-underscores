<?php
/**
 * Unit tests for the Site Pulse dashboard builder.
 *
 * Pins which forms count as messages (Slack applications and mailing-list
 * sign-ups don't), where a week starts, and which API routes are flagged.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Admin_Menu;

use PHPUnit\Framework\TestCase;
use LWTV\Admin_Menu\Build\Site_Pulse;

class SitePulseTest extends TestCase {

	private static function form( int $id, string $title, int $unread, bool $active = true ): array {
		return array(
			'id'        => $id,
			'title'     => $title,
			'unread'    => $unread,
			'is_active' => $active,
		);
	}

	private static function route( string $route, int $s5xx, int $s404 = 0, string $last_5xx = '' ): array {
		return array(
			'route'    => $route,
			's5xx'     => $s5xx,
			's404'     => $s404,
			'last_5xx' => $last_5xx,
		);
	}

	public function test_inbox_excludes_approval_and_signup_forms(): void {
		$inbox = Site_Pulse::inbox(
			array(
				self::form( 1, 'Suggest an Edit', 1 ),
				self::form( 3, 'Article Submissions', 0 ),
				self::form( 4, 'Slack Registration', 1 ),
				self::form( 7, 'Mailing List', 2 ),
				self::form( 9, 'Old Form', 3, false ),
			),
			array(
				1 => array( 'gravityformsslack' ),
				4 => array( 'gravityformsapprovals', 'gravityformsslack' ),
				7 => array( 'gravityformsbrevo' ),
			)
		);

		$this->assertSame( array( 1, 3 ), array_column( $inbox['rows'], 'id' ) );
		$this->assertSame( 1, $inbox['total'] );
	}

	public function test_inbox_excludes_lwtv_approvals_feed(): void {
		$inbox = Site_Pulse::inbox( array( self::form( 4, 'Slack', 1 ) ), array( 4 => array( 'lwtv-gf-approvals' ) ) );
		$this->assertSame( array(), $inbox['rows'] );
		$this->assertSame( 0, $inbox['total'] );
	}

	public function test_inbox_sorts_unread_then_title(): void {
		$inbox = Site_Pulse::inbox(
			array( self::form( 1, 'B', 2 ), self::form( 2, 'A', 2 ), self::form( 3, 'C', 5 ) ),
			array()
		);
		$this->assertSame( array( 'C', 'A', 'B' ), array_column( $inbox['rows'], 'title' ) );
	}

	public function test_week_bounds_monday_start(): void {
		$this->assertSame(
			array(
				'this_start' => '2026-11-16',
				'last_start' => '2026-11-09',
			),
			Site_Pulse::week_bounds( '2026-11-20', 1 )
		);
	}

	public function test_week_bounds_on_start_day(): void {
		$this->assertSame(
			array(
				'this_start' => '2026-11-16',
				'last_start' => '2026-11-09',
			),
			Site_Pulse::week_bounds( '2026-11-16', 1 )
		);
	}

	public function test_week_bounds_sunday_start(): void {
		$this->assertSame(
			array(
				'this_start' => '2026-11-15',
				'last_start' => '2026-11-08',
			),
			Site_Pulse::week_bounds( '2026-11-20', 0 )
		);
	}

	public function test_added_orders_and_fills_missing(): void {
		$this->assertSame(
			array(
				array(
					'type' => 'shows',
					'this' => 2,
					'last' => 5,
				),
				array(
					'type' => 'characters',
					'this' => 0,
					'last' => 0,
				),
				array(
					'type' => 'actors',
					'this' => 4,
					'last' => 0,
				),
			),
			Site_Pulse::added(
				array(
					'actors' => 4,
					'shows'  => 2,
				),
				array( 'shows' => 5 )
			)
		);
	}

	public function test_api_top_5xx_then_404_threshold(): void {
		$rows = Site_Pulse::api(
			array(
				self::route( 'c', 3 ),
				self::route( 'a', 9, 0, '2026-11-19' ),
				self::route( 'f', 0, 9 ),
				self::route( 'd', 1 ),
				self::route( 'e', 0, 10 ),
				self::route( 'b', 5 ),
			)
		);

		$this->assertSame( array( 'a', 'b', 'c', 'e' ), array_column( $rows, 'route' ) );
		$this->assertSame( array( '5xx', '5xx', '5xx', '404' ), array_column( $rows, 'reason' ) );
		$this->assertSame( '2026-11-19', $rows[0]['last_5xx'] );
	}

	public function test_api_404s_sorted_by_count(): void {
		$rows = Site_Pulse::api( array( self::route( 'x', 0, 12 ), self::route( 'y', 0, 40 ) ) );
		$this->assertSame( array( 'y', 'x' ), array_column( $rows, 'route' ) );
	}

	public function test_api_route_listed_once(): void {
		$rows = Site_Pulse::api( array( self::route( 'a', 2, 50 ) ) );
		$this->assertCount( 1, $rows );
		$this->assertSame( '5xx', $rows[0]['reason'] );
		$this->assertSame( 50, $rows[0]['s404'] );
	}

	public function test_api_ignores_healthy_routes(): void {
		$this->assertSame( array(), Site_Pulse::api( array( self::route( 'a', 0, 0 ) ) ) );
	}
}
