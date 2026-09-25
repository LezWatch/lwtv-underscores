<?php
/**
 * Unit tests for the New Posts announcement text.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Postiz;

use PHPUnit\Framework\TestCase;
use LWTV\Postiz\Build\Post_Announcement;

class PostAnnouncementTest extends TestCase {

	const URL = 'https://lezwatchtv.com/2026/09/a-new-post/';

	public function test_title_excerpt_and_link_are_separated_by_blank_lines(): void {
		$this->assertSame(
			"A New Post\n\nSome words about it.\n\n" . self::URL,
			Post_Announcement::content( 'A New Post', 'Some words about it.', self::URL )
		);
	}

	public function test_empty_excerpt_gives_title_and_link(): void {
		$this->assertSame(
			"A New Post\n\n" . self::URL,
			Post_Announcement::content( 'A New Post', '   ', self::URL )
		);
	}

	public function test_excerpt_that_repeats_the_title_is_dropped(): void {
		$this->assertSame(
			"A New Post\n\n" . self::URL,
			Post_Announcement::content( 'A New Post', 'A New Post', self::URL )
		);
	}

	public function test_entities_are_decoded(): void {
		$this->assertSame(
			"Tom & Jerry’s Post\n\nIt’s “good”.\n\n" . self::URL,
			Post_Announcement::content( 'Tom &amp; Jerry&#8217;s Post', 'It&#8217;s &#8220;good&#8221;.', self::URL )
		);
	}

	public function test_whitespace_in_excerpt_is_collapsed(): void {
		$this->assertSame(
			"Title\n\nOne two three.\n\n" . self::URL,
			Post_Announcement::content( 'Title', "  One\n two\t\tthree.  ", self::URL )
		);
	}

	public function test_long_excerpt_is_trimmed_so_the_whole_post_fits(): void {
		$content = Post_Announcement::content( 'A New Post', str_repeat( 'word ', 200 ), self::URL );

		$this->assertLessThanOrEqual( Post_Announcement::LIMIT, mb_strlen( $content ) );
		$this->assertStringStartsWith( "A New Post\n\nword", $content );
		$this->assertStringEndsWith( "…\n\n" . self::URL, $content );
	}

	public function test_trimming_breaks_on_a_word_boundary(): void {
		$content = Post_Announcement::content( 'T', str_repeat( 'abcdefghij ', 40 ), self::URL );
		$parts   = explode( "\n\n", $content );

		$this->assertMatchesRegularExpression( '/^(abcdefghij )*abcdefghij…$/', $parts[1] );
	}

	public function test_multibyte_excerpt_is_counted_in_characters_not_bytes(): void {
		// 200 two-byte characters are 400 bytes but only 200 characters; all must be kept.
		$excerpt = str_repeat( 'é', 200 );
		$content = Post_Announcement::content( 'T', $excerpt, 'https://x.co/' );

		$this->assertSame( "T\n\n" . $excerpt . "\n\nhttps://x.co/", $content );
		$this->assertTrue( mb_check_encoding( $content, 'UTF-8' ) );
	}

	public function test_link_is_never_cut(): void {
		$content = Post_Announcement::content( str_repeat( 'Long title ', 40 ), 'Excerpt.', self::URL );

		$this->assertLessThanOrEqual( Post_Announcement::LIMIT, mb_strlen( $content ) );
		$this->assertStringEndsWith( "\n\n" . self::URL, $content );
		$this->assertStringNotContainsString( 'Excerpt.', $content );
	}

	public function test_excerpt_is_dropped_when_there_is_no_room_for_a_useful_amount(): void {
		$title   = str_repeat( 'x', Post_Announcement::LIMIT - mb_strlen( self::URL ) - 8 );
		$content = Post_Announcement::content( $title, 'An excerpt that will not fit.', self::URL );

		$this->assertSame( $title . "\n\n" . self::URL, $content );
	}
}
