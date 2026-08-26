<?php
/**
 * Unit tests for the debugger's issue vocabulary: lookups, fallbacks for
 * unregistered types, and the rule that a repair exists if and only if the
 * entry declares one.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Debugger;

use PHPUnit\Framework\TestCase;
use LWTV\Debugger\Build\Issue_Registry;

class IssueRegistryTest extends TestCase {

	/*
	 * Lookups.
	 */

	public function test_exists_for_registered_and_unregistered(): void {
		$this->assertTrue( Issue_Registry::exists( 'show-missing-trope' ) );
		$this->assertFalse( Issue_Registry::exists( 'show-invented-nonsense' ) );
	}

	public function test_message_comes_from_the_registry(): void {
		$this->assertSame( 'No tropes set.', Issue_Registry::message( 'show-missing-trope' ) );
		$this->assertSame( 'Dead but missing date.', Issue_Registry::message( 'char-dead-no-date' ) );
	}

	public function test_unregistered_message_falls_back_to_the_key(): void {
		// A blank table cell would hide the bug; the key itself surfaces it.
		$this->assertSame( 'show-invented-nonsense', Issue_Registry::message( 'show-invented-nonsense' ) );
	}

	public function test_level_reports_the_cpt(): void {
		$this->assertSame( 'show', Issue_Registry::level( 'show-missing-trope' ) );
		$this->assertSame( 'character', Issue_Registry::level( 'char-missing-cliche' ) );
		$this->assertSame( 'actor', Issue_Registry::level( 'actor-twitter-is-imdb' ) );
		$this->assertSame( '', Issue_Registry::level( 'show-invented-nonsense' ) );
	}

	/*
	 * Fixability.
	 */

	public function test_is_fixable_tracks_the_fix_key(): void {
		$this->assertTrue( Issue_Registry::is_fixable( 'show-missing-trope' ) );
		$this->assertTrue( Issue_Registry::is_fixable( 'show-missing-thumb' ) );
		$this->assertFalse( Issue_Registry::is_fixable( 'show-no-characters' ) );
		$this->assertFalse( Issue_Registry::is_fixable( 'show-invented-nonsense' ) );
	}

	public function test_fix_label_is_empty_without_a_repair(): void {
		$this->assertSame( 'adds the "none" trope', Issue_Registry::fix_label( 'show-missing-trope' ) );
		$this->assertSame( '', Issue_Registry::fix_label( 'show-no-characters' ) );
	}

	public function test_fix_callable_is_a_class_method_pair(): void {
		$this->assertSame(
			array( '\LWTV\Debugger\Shows', 'add_none_trope' ),
			Issue_Registry::fix_callable( 'show-missing-trope' )
		);
		$this->assertSame( array(), Issue_Registry::fix_callable( 'show-no-characters' ) );
	}

	public function test_every_fixable_issue_declares_a_label_and_callable(): void {
		foreach ( Issue_Registry::fixable_types() as $issue_type ) {
			$fix = Issue_Registry::fix_callable( $issue_type );

			$this->assertCount( 2, $fix, $issue_type . ' needs a class/method pair' );
			$this->assertNotSame( '', Issue_Registry::fix_label( $issue_type ), $issue_type . ' needs a fix_label' );
		}
	}

	public function test_fixable_types_are_the_expected_set(): void {
		// Locks the list: adding a repair should be a deliberate edit here too.
		$this->assertSame(
			array(
				'show-missing-thumb',
				'show-missing-trope',
				'char-missing-cliche',
				'actor-instagram-is-imdb',
				'actor-twitter-is-imdb',
				'actor-homepage-is-wikipedia',
				'actor-homepage-dupe-wiki',
			),
			Issue_Registry::fixable_types()
		);
	}

	/*
	 * Structural guarantees the rest of the debugger relies on.
	 */

	public function test_every_issue_has_a_level_and_a_message(): void {
		foreach ( Issue_Registry::ISSUES as $issue_type => $issue ) {
			$this->assertArrayHasKey( 'level', $issue, $issue_type );
			$this->assertArrayHasKey( 'message', $issue, $issue_type );
			$this->assertContains( $issue['level'], array( 'show', 'character', 'actor' ), $issue_type );
			$this->assertNotSame( '', $issue['message'], $issue_type );
		}
	}

	public function test_for_level_partitions_the_vocabulary(): void {
		$shows  = Issue_Registry::for_level( 'show' );
		$chars  = Issue_Registry::for_level( 'character' );
		$actors = Issue_Registry::for_level( 'actor' );

		$this->assertSame(
			count( Issue_Registry::ISSUES ),
			count( $shows ) + count( $chars ) + count( $actors )
		);
		$this->assertContains( 'show-no-genres', $shows );
		$this->assertNotContains( 'show-no-genres', $chars );
		$this->assertSame( array(), Issue_Registry::for_level( 'nonsense' ) );
	}
}
