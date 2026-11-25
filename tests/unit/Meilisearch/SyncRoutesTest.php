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

	public function test_acf_save(): void {
		$this->assertSame( array( 'characters', 'shows' ), Sync_Routes::for_event( 'acf_save', self::CHARACTER ) );
		// A routine show save re-syncs the show only: no character fan-out.
		$this->assertSame( array( 'shows' ), Sync_Routes::for_event( 'acf_save', self::SHOW ) );
		$this->assertSame( array( 'characters', 'shows' ), Sync_Routes::for_event( 'acf_save', self::ACTOR ) );
	}

	public function test_changed(): void {
		foreach ( array( self::CHARACTER, self::SHOW, self::ACTOR ) as $type ) {
			$this->assertSame( array( 'characters', 'shows' ), Sync_Routes::for_event( 'changed', $type ), $type );
		}
	}

	public function test_delete(): void {
		// A deleted character's shows are queued separately, before its links go.
		$this->assertSame( array( 'characters' ), Sync_Routes::for_event( 'delete', self::CHARACTER ) );
		$this->assertSame( array( 'shows' ), Sync_Routes::for_event( 'delete', self::SHOW ) );
		$this->assertSame( array(), Sync_Routes::for_event( 'delete', self::ACTOR ) );
	}

	public function test_score(): void {
		$this->assertSame( array( 'shows' ), Sync_Routes::for_event( 'score', self::SHOW ) );
		$this->assertSame( array(), Sync_Routes::for_event( 'score', self::CHARACTER ) );
	}

	public function test_unknown_inputs(): void {
		$this->assertSame( array(), Sync_Routes::for_event( 'acf_save', 'post' ) );
		$this->assertSame( array(), Sync_Routes::for_event( 'bogus', self::CHARACTER ) );
	}

	public function test_events_listed(): void {
		$this->assertSame( array( 'acf_save', 'changed', 'delete', 'score' ), Sync_Routes::EVENTS );
	}
}
