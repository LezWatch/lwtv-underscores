<?php
/**
 * Unit tests for which indexes a post change queues.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Meilisearch;

use PHPUnit\Framework\TestCase;
use LWTV\Meilisearch\Build\Sync_Routes;

class SyncRoutesTest extends TestCase {

	const CHARACTER = 'post_type_characters';
	const SHOW      = 'post_type_shows';
	const ACTOR     = 'post_type_actors';
	const POST      = 'post';
	const PAGE      = 'page';

	public function test_acf_save(): void {
		$this->assertSame( array( 'characters', 'shows', 'actors' ), Sync_Routes::for_event( 'acf_save', self::CHARACTER ) );
		// A routine show save re-syncs the show only: no character or actor fan-out.
		$this->assertSame( array( 'shows' ), Sync_Routes::for_event( 'acf_save', self::SHOW ) );
		$this->assertSame( array( 'characters', 'shows', 'actors' ), Sync_Routes::for_event( 'acf_save', self::ACTOR ) );
		// Posts and pages aren't ACF-driven: their own save event covers them.
		$this->assertSame( array(), Sync_Routes::for_event( 'acf_save', self::POST ) );
	}

	public function test_save(): void {
		// Every edit matters to content: the whole body is indexed.
		$this->assertSame( array( 'content' ), Sync_Routes::for_event( 'save', self::POST ) );
		$this->assertSame( array( 'content' ), Sync_Routes::for_event( 'save', self::PAGE ) );
		// CPTs keep their ACF route; a plain save_post queues nothing for them.
		foreach ( array( self::CHARACTER, self::SHOW, self::ACTOR, 'attachment', 'revision' ) as $type ) {
			$this->assertSame( array(), Sync_Routes::for_event( 'save', $type ), $type );
		}
	}

	public function test_changed(): void {
		foreach ( array( self::CHARACTER, self::SHOW, self::ACTOR ) as $type ) {
			$this->assertSame( array( 'characters', 'shows', 'actors' ), Sync_Routes::for_event( 'changed', $type ), $type );
		}
		// Content documents quote no other post: a change only touches its own.
		$this->assertSame( array( 'content' ), Sync_Routes::for_event( 'changed', self::POST ) );
		$this->assertSame( array( 'content' ), Sync_Routes::for_event( 'changed', self::PAGE ) );
	}

	public function test_delete(): void {
		// A deleted character's shows are queued separately, before its links go.
		$this->assertSame( array( 'characters' ), Sync_Routes::for_event( 'delete', self::CHARACTER ) );
		$this->assertSame( array( 'shows' ), Sync_Routes::for_event( 'delete', self::SHOW ) );
		$this->assertSame( array( 'actors' ), Sync_Routes::for_event( 'delete', self::ACTOR ) );
		$this->assertSame( array( 'content' ), Sync_Routes::for_event( 'delete', self::POST ) );
		$this->assertSame( array( 'content' ), Sync_Routes::for_event( 'delete', self::PAGE ) );
	}

	public function test_score(): void {
		$this->assertSame( array( 'shows' ), Sync_Routes::for_event( 'score', self::SHOW ) );
		$this->assertSame( array(), Sync_Routes::for_event( 'score', self::CHARACTER ) );
	}

	public function test_calculated(): void {
		// Actor calculations write the queer flag and character count, the tie-breakers.
		$this->assertSame( array( 'actors' ), Sync_Routes::for_event( 'calculated', self::ACTOR ) );
		$this->assertSame( array(), Sync_Routes::for_event( 'calculated', self::CHARACTER ) );
	}

	public function test_every_route_is_a_registry_type(): void {
		foreach ( Sync_Routes::ROUTES as $event => $routes ) {
			$this->assertContains( $event, Sync_Routes::EVENTS );
			foreach ( $routes as $post_type => $types ) {
				foreach ( $types as $type ) {
					$this->assertContains( $type, array( 'characters', 'shows', 'actors', 'content' ), "$event / $post_type" );
				}
			}
		}
	}

	public function test_unknown_inputs(): void {
		$this->assertSame( array(), Sync_Routes::for_event( 'acf_save', 'attachment' ) );
		$this->assertSame( array(), Sync_Routes::for_event( 'bogus', self::CHARACTER ) );
	}

	public function test_events_listed(): void {
		$this->assertSame( array( 'acf_save', 'save', 'changed', 'delete', 'score', 'calculated' ), Sync_Routes::EVENTS );
	}
}
