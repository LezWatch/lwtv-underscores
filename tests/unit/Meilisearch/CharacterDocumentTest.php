<?php
/**
 * Unit tests for building a character's Meilisearch document.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Meilisearch;

use PHPUnit\Framework\TestCase;
use LWTV\Meilisearch\Build\Character_Document;

class CharacterDocumentTest extends TestCase {

	/**
	 * Lookup entry.
	 *
	 * @param string $title   Title.
	 * @param bool   $visible Visible on the public site.
	 * @return array
	 */
	private function entry( string $title, bool $visible = true ): array {
		return array(
			'title'   => $title,
			'visible' => $visible,
		);
	}

	/**
	 * Build with defaults for url and thumb.
	 *
	 * @param mixed  $show_group Show rows.
	 * @param mixed  $actors     Actor IDs.
	 * @param array  $lookup     Lookup map.
	 * @param string $title      Character title.
	 * @return array
	 */
	private function build( $show_group, $actors, array $lookup, string $title = 'Mel' ): array {
		return Character_Document::build( 1, $title, $show_group, $actors, $lookup, '/character/mel/', '/thumb.jpg' );
	}

	public function test_full_document_shape(): void {
		$this->assertSame(
			array(
				'id'        => 1,
				'name'      => 'Mel',
				'shows'     => array( 'Slo-Pitch' ),
				'actors'    => array( 'Actor Seven' ),
				'role'      => 'regular',
				'role_rank' => 3,
				'url'       => '/character/mel/',
				'thumb'     => '/thumb.jpg',
			),
			$this->build(
				array(
					array(
						'show' => 5,
						'type' => 'regular',
					),
				),
				array( '7' ),
				array(
					5 => $this->entry( 'Slo-Pitch' ),
					7 => $this->entry( 'Actor Seven' ),
				)
			)
		);
	}

	public function test_best_role_across_shows(): void {
		$doc = $this->build(
			array(
				array(
					'show' => 5,
					'type' => 'guest',
				),
				array(
					'show' => 6,
					'type' => 'recurring',
				),
			),
			array(),
			array(
				5 => $this->entry( 'Five' ),
				6 => $this->entry( 'Six' ),
			)
		);

		$this->assertSame( 'recurring', $doc['role'] );
		$this->assertSame( 2, $doc['role_rank'] );
	}

	public function test_hidden_show_ignored_for_titles_and_role(): void {
		$doc = $this->build(
			array(
				array(
					'show' => 5,
					'type' => 'regular',
				),
				array(
					'show' => 6,
					'type' => 'guest',
				),
			),
			array(),
			array(
				5 => $this->entry( 'Five', false ),
				6 => $this->entry( 'Six' ),
			)
		);

		$this->assertSame( array( 'Six' ), $doc['shows'] );
		$this->assertSame( 'guest', $doc['role'] );
		$this->assertSame( 1, $doc['role_rank'] );
	}

	public function test_hidden_actor_left_out(): void {
		$doc = $this->build(
			array(),
			array( 7, 8 ),
			array(
				7 => $this->entry( 'Seven' ),
				8 => $this->entry( 'Eight', false ),
			)
		);

		$this->assertSame( array( 'Seven' ), $doc['actors'] );
	}

	public function test_missing_lookup_entry_skipped(): void {
		$doc = $this->build(
			array(
				array(
					'show' => 99,
					'type' => 'regular',
				),
			),
			array( 98 ),
			array()
		);

		$this->assertSame( array(), $doc['shows'] );
		$this->assertSame( array(), $doc['actors'] );
		$this->assertSame( 0, $doc['role_rank'] );
	}

	public function test_duplicate_show_listed_once(): void {
		$doc = $this->build(
			array(
				array(
					'show' => 5,
					'type' => 'guest',
				),
				array(
					'show' => '5',
					'type' => 'regular',
				),
			),
			array(),
			array( 5 => $this->entry( 'Five' ) )
		);

		$this->assertSame( array( 'Five' ), $doc['shows'] );
		$this->assertSame( 'regular', $doc['role'] );
	}

	public function test_entities_decoded(): void {
		$doc = $this->build(
			array(
				array(
					'show' => 5,
					'type' => 'regular',
				),
			),
			array(),
			array( 5 => $this->entry( 'Grey&#8217;s Anatomy' ) ),
			'Kate &amp; Allie'
		);

		$this->assertSame( 'Kate & Allie', $doc['name'] );
		$this->assertSame( array( 'Grey’s Anatomy' ), $doc['shows'] );
	}

	public function test_no_shows_no_actors(): void {
		$doc = $this->build( false, '', array() );

		$this->assertSame( array(), $doc['shows'] );
		$this->assertSame( array(), $doc['actors'] );
		$this->assertSame( '', $doc['role'] );
		$this->assertSame( 0, $doc['role_rank'] );
	}

	public function test_unknown_role_type(): void {
		$doc = $this->build(
			array(
				array(
					'show' => 5,
					'type' => 'cameo',
				),
			),
			array(),
			array( 5 => $this->entry( 'Five' ) )
		);

		$this->assertSame( array( 'Five' ), $doc['shows'] );
		$this->assertSame( '', $doc['role'] );
		$this->assertSame( 0, $doc['role_rank'] );
	}
}
