<?php
/**
 * Unit tests for building a post's or page's Meilisearch document.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Meilisearch;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use LWTV\Meilisearch\Build\Content_Document;

class ContentDocumentTest extends TestCase {

	/**
	 * Build an otherwise-empty post, overriding some inputs.
	 *
	 * @param array $overrides Input name => value.
	 * @return array
	 */
	private function build( array $overrides = array() ): array {
		$in = array_merge(
			array(
				'post_type'  => 'post',
				'title'      => 'Foo',
				'excerpt'    => '',
				'body'       => '',
				'categories' => array(),
				'tags'       => array(),
				'date'       => 1700000000,
			),
			$overrides
		);

		return Content_Document::build(
			123,
			$in['post_type'],
			$in['title'],
			$in['excerpt'],
			$in['body'],
			$in['categories'],
			$in['tags'],
			$in['date'],
			'https://lezwatchtv.com/foo/',
			'https://lezwatchtv.com/foo.jpg'
		);
	}

	public function test_shape(): void {
		$doc = $this->build(
			array(
				'excerpt'    => 'Short.',
				'body'       => 'Long body.',
				'categories' => array( 'News' ),
				'tags'       => array( 'Batwoman' ),
			)
		);

		$this->assertSame(
			array(
				'id'         => 123,
				'type'       => 'post',
				'type_rank'  => 0,
				'title'      => 'Foo',
				'excerpt'    => 'Short.',
				'body'       => 'Long body.',
				'categories' => array( 'News' ),
				'tags'       => array( 'Batwoman' ),
				'date'       => 1700000000,
				'url'        => 'https://lezwatchtv.com/foo/',
				'thumb'      => 'https://lezwatchtv.com/foo.jpg',
			),
			$doc
		);
	}

	public function test_pages_outrank_posts_on_ties(): void {
		// Mika, 2026-11-25: evergreen pages win ties, then newer articles.
		$this->assertSame( 1, $this->build( array( 'post_type' => 'page' ) )['type_rank'] );
		$this->assertSame( 0, $this->build( array( 'post_type' => 'post' ) )['type_rank'] );
		$this->assertSame( 'page', $this->build( array( 'post_type' => 'page' ) )['type'] );
	}

	public function test_text_is_decoded_and_collapsed(): void {
		$doc = $this->build(
			array(
				'title' => "  Queer&nbsp;Beats:\n Tom &amp; Jerry &#8217;s  ",
				'body'  => "One\t\ttwo\n\nthree",
			)
		);

		$this->assertSame( 'Queer Beats: Tom & Jerry ’s', $doc['title'] );
		$this->assertSame( 'One two three', $doc['body'] );
	}

	public function test_full_body_is_kept(): void {
		// The whole body is searchable; displayedAttributes keep it out of results.
		$body = str_repeat( 'word ', 10000 );

		$this->assertSame( trim( $body ), $this->build( array( 'body' => $body ) )['body'] );
	}

	public function test_written_excerpt_wins(): void {
		$doc = $this->build(
			array(
				'excerpt' => 'Hand &amp; written.',
				'body'    => 'Body text.',
			)
		);

		$this->assertSame( 'Hand & written.', $doc['excerpt'] );
	}

	public function test_missing_excerpt_falls_back_to_body_words(): void {
		$words = array();
		for ( $i = 1; $i <= 80; $i++ ) {
			$words[] = 'w' . $i;
		}

		$doc = $this->build( array( 'body' => implode( ' ', $words ) ) );

		$this->assertSame( implode( ' ', array_slice( $words, 0, Content_Document::EXCERPT_WORDS ) ) . '…', $doc['excerpt'] );
	}

	public function test_short_body_excerpt_has_no_ellipsis(): void {
		$this->assertSame( 'Just a few words.', $this->build( array( 'body' => 'Just a few words.' ) )['excerpt'] );
	}

	public function test_terms_are_cleaned_and_unique(): void {
		$doc = $this->build(
			array(
				'tags'       => array( 'Batwoman', ' Batwoman ', '', 'Grey&#8217;s Anatomy', null, array( 'x' ) ),
				'categories' => array( 'News', 'News' ),
			)
		);

		$this->assertSame( array( 'Batwoman', 'Grey’s Anatomy' ), $doc['tags'] );
		$this->assertSame( array( 'News' ), $doc['categories'] );
	}

	public function test_negative_date_is_zero(): void {
		$this->assertSame( 0, $this->build( array( 'date' => -5 ) )['date'] );
	}

	/**
	 * @return array<string, array{string, string, string, bool}>
	 */
	public static function eligibility(): array {
		return array(
			'published post'     => array( 'post', 'publish', '', true ),
			'published page'     => array( 'page', 'publish', '', true ),
			'draft'              => array( 'post', 'draft', '', false ),
			'private'            => array( 'page', 'private', '', false ),
			'scheduled'          => array( 'post', 'future', '', false ),
			'trashed'            => array( 'post', 'trash', '', false ),
			'password-protected' => array( 'post', 'publish', 'secret', false ),
			'other post type'    => array( 'post_type_shows', 'publish', '', false ),
			'attachment'         => array( 'attachment', 'inherit', '', false ),
		);
	}

	#[DataProvider( 'eligibility' )]
	public function test_eligible( string $post_type, string $status, string $password, bool $expected ): void {
		$this->assertSame( $expected, Content_Document::eligible( $post_type, $status, $password ) );
	}

	public function test_post_types(): void {
		$this->assertSame( array( 'post', 'page' ), Content_Document::POST_TYPES );
	}
}
