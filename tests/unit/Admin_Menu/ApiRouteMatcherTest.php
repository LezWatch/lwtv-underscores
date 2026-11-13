<?php
/**
 * Unit tests for mapping logged paths onto registered REST routes.
 *
 * The fixture keys are copied verbatim from rest_get_server()->get_routes(
 * 'lwtv/v1' ) on a real install, because the whole point is that the regexes
 * WordPress gives us are what we match against. The test-only literal
 * `/lwtv/v1/stats/shows` exists to prove a fixed route beats a pattern.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Admin_Menu;

use PHPUnit\Framework\TestCase;
use LWTV\Admin_Menu\Build\Api_Route_Matcher;

class ApiRouteMatcherTest extends TestCase {

	const STATS_ONE   = '/lwtv/v1/stats/(?P<type>[a-zA-Z.\-]+)';
	const STATS_THREE = '/lwtv/v1/stats/(?P<type>[a-zA-Z.\-]+)/(?P<format>[a-zA-Z\-]+)/(?P<page>[a-zA-Z0-9\-]+)';
	const TY_TYPE     = '/lwtv/v1/this-year/(?P<type>[a-zA-Z.\-]+)';
	const TY_YEAR     = '/lwtv/v1/this-year/(?P<year>[\d]+)';

	private function matcher(): Api_Route_Matcher {
		return new Api_Route_Matcher(
			array(
				'/lwtv/v1',
				'/lwtv/v1/last-death',
				self::STATS_ONE,
				self::STATS_THREE,
				self::TY_TYPE,
				self::TY_YEAR,
				'/lwtv/v1/what-happened/(?P<date>[\d]{4}-[\d]{2}-[\d]{2})',
				'/lwtv/v1/export/(?P<type>[a-zA-Z0-9-]+)/(?P<item>[a-zA-Z0-9-]+)',
				'/lwtv/v1/whats-on/(?P<when>[a-zA-Z0-9-]+)',
				'/lwtv/v1/stats/shows',
			)
		);
	}

	public function test_two_ids_group_under_one_route(): void {
		$m = $this->matcher();

		$this->assertSame( self::STATS_THREE, $m->match( '/wp-json/lwtv/v1/stats/shows/name/all-my-children' ) );
		$this->assertSame( self::STATS_THREE, $m->match( '/wp-json/lwtv/v1/stats/shows/name/the-l-word' ) );
	}

	public function test_prefix_and_trailing_slash_are_stripped(): void {
		$m = $this->matcher();

		$this->assertSame( '/lwtv/v1/last-death', $m->match( '/wp-json/lwtv/v1/last-death/' ) );
		$this->assertSame( '/lwtv/v1', $m->match( '/wp-json/lwtv/v1' ) );
	}

	public function test_regex_params_are_respected(): void {
		$m = $this->matcher();

		$this->assertSame( self::TY_YEAR, $m->match( '/wp-json/lwtv/v1/this-year/2024' ) );
		$this->assertSame( self::TY_TYPE, $m->match( '/wp-json/lwtv/v1/this-year/shows' ) );
		$this->assertSame( Api_Route_Matcher::UNMATCHED, $m->match( '/wp-json/lwtv/v1/what-happened/2024-13' ) );
	}

	public function test_most_literal_route_wins(): void {
		$this->assertSame( '/lwtv/v1/stats/shows', $this->matcher()->match( '/wp-json/lwtv/v1/stats/shows' ) );
	}

	public function test_match_is_case_insensitive_like_wp(): void {
		$this->assertSame( '/lwtv/v1/last-death', $this->matcher()->match( '/wp-json/lwtv/v1/Last-Death' ) );
	}

	public function test_encoded_path_decodes_then_fails_cleanly(): void {
		$this->assertSame( Api_Route_Matcher::UNMATCHED, $this->matcher()->match( '/wp-json/lwtv/v1/whats-on/a%2Cb' ) );
	}

	public function test_foreign_path_is_unmatched(): void {
		$this->assertSame( Api_Route_Matcher::UNMATCHED, $this->matcher()->match( '/wp-json/wp/v2/posts' ) );
	}

	public function test_prefix_is_optional(): void {
		$this->assertSame( '/lwtv/v1/last-death', $this->matcher()->match( '/lwtv/v1/last-death' ) );
	}

	public function test_label_shows_params_as_names(): void {
		$this->assertSame( '/lwtv/v1/stats/{type}/{format}/{page}', Api_Route_Matcher::label( self::STATS_THREE ) );
		$this->assertSame( '/lwtv/v1/what-happened/{date}', Api_Route_Matcher::label( '/lwtv/v1/what-happened/(?P<date>[\d]{4}-[\d]{2}-[\d]{2})' ) );
		$this->assertSame( Api_Route_Matcher::UNMATCHED, Api_Route_Matcher::label( Api_Route_Matcher::UNMATCHED ) );
	}

	public function test_routes_keep_registration_order(): void {
		$routes = $this->matcher()->routes();

		$this->assertSame( '/lwtv/v1', $routes[0] );
		$this->assertSame( '/lwtv/v1/stats/shows', $routes[9] );
	}
}
