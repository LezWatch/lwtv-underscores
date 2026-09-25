<?php
/**
 * Unit tests for the New Shows announcement rules.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Postiz;

use PHPUnit\Framework\TestCase;
use LWTV\Postiz\Build\Show_Announcement;

class ShowAnnouncementTest extends TestCase {

	const NOW = 1790000000;

	/**
	 * A published show with characters, published an hour ago, never announced.
	 *
	 * @param  array $item Values to replace.
	 * @return array
	 */
	private function show( array $item = array() ): array {
		return array_merge(
			array(
				'status'       => 'publish',
				'char_count'   => 3,
				'announced'    => false,
				'published_at' => self::NOW - 3600,
			),
			$item
		);
	}

	private function should( array $item ): bool {
		return Show_Announcement::should_announce( $this->show( $item ), self::NOW );
	}

	public function test_a_new_published_show_with_characters_is_announced(): void {
		$this->assertTrue( $this->should( array() ) );
	}

	public function test_a_show_with_no_characters_waits(): void {
		$this->assertFalse( $this->should( array( 'char_count' => 0 ) ) );
	}

	public function test_an_unpublished_show_is_not_announced(): void {
		foreach ( array( 'draft', 'pending', 'future', 'private' ) as $status ) {
			$this->assertFalse( $this->should( array( 'status' => $status ) ), $status );
		}
	}

	public function test_a_show_is_announced_only_once(): void {
		$this->assertFalse( $this->should( array( 'announced' => true ) ) );
	}

	public function test_characters_arriving_inside_the_window_still_announce(): void {
		$inside = self::NOW - ( Show_Announcement::WINDOW_DAYS * DAY_IN_SECONDS ) + 60;
		$this->assertTrue( $this->should( array( 'published_at' => $inside ) ) );
	}

	public function test_an_old_show_is_never_announced(): void {
		// Guards `wp lwtv calc --all`: rewriting every show's character count
		// must not announce the back catalogue.
		$outside = self::NOW - ( Show_Announcement::WINDOW_DAYS * DAY_IN_SECONDS ) - 60;
		$this->assertFalse( $this->should( array( 'published_at' => $outside ) ) );
	}

	public function test_an_unknown_publish_time_is_not_announced(): void {
		$this->assertFalse( $this->should( array( 'published_at' => 0 ) ) );
	}

	public function test_title_cleanup_matches_of_the_day(): void {
		$this->assertSame( 'Will and Grace', Show_Announcement::clean_title( 'Will & Grace (2017)' ) );
		$this->assertSame( 'tagged', Show_Announcement::clean_title( 't@gged' ) );
	}

	public function test_hashtag_is_camel_case_from_the_slug(): void {
		$this->assertSame( '#TheLWord', Show_Announcement::hashtag( 'the-l-word' ) );
		$this->assertSame( '', Show_Announcement::hashtag( '' ) );
	}

	public function test_content_with_station_and_year(): void {
		$this->assertSame(
			'New on LezWatch.TV: The L Word (Showtime, 2004) #TheLWord - https://lezwatchtv.com/show/the-l-word/',
			Show_Announcement::content( 'The L Word', array( 'Showtime' ), '2004', '#TheLWord', 'https://lezwatchtv.com/show/the-l-word/' )
		);
	}

	public function test_content_drops_missing_parts(): void {
		$this->assertSame(
			'New on LezWatch.TV: Vigil - https://example.com/vigil/',
			Show_Announcement::content( 'Vigil', array(), '', '', 'https://example.com/vigil/' )
		);
		$this->assertSame(
			'New on LezWatch.TV: Vigil (2021) - https://example.com/vigil/',
			Show_Announcement::content( 'Vigil', array(), '2021', '', 'https://example.com/vigil/' )
		);
	}

	public function test_content_lists_at_most_two_stations(): void {
		$this->assertSame(
			'New on LezWatch.TV: X (A, B, 2020) - u',
			Show_Announcement::content( 'X', array( 'A', 'B', 'C' ), '2020', '', 'u' )
		);
	}

	public function test_year_accepts_a_date_value(): void {
		$this->assertSame( '2004', Show_Announcement::year( '20040118' ) );
		$this->assertSame( '2004', Show_Announcement::year( '2004' ) );
		$this->assertSame( '', Show_Announcement::year( 'soon' ) );
	}
}
