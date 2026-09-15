<?php
/**
 * Unit tests for Q-ID trust and candidate selection.
 *
 * The trust half matters more than it looks. is_trusted() is the single gate
 * between a fuzzy name match and the death audit acting on it, so the cases that
 * pin SOURCE_NAME and SOURCE_LEGACY as untrusted are load-bearing: make either
 * one pass and the audit starts asserting deaths on the strength of a name
 * collision.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Wikidata;

use PHPUnit\Framework\TestCase;
use LWTV\Wikidata\Build\Qid_Trust;

class QidTrustTest extends TestCase {

	/**
	 * An actor with no Q-ID and an IMDb ID to find one with -- the plain
	 * backfill candidate.
	 *
	 * @param  array $item Values to replace.
	 * @return array
	 */
	private function actor( array $item = array() ): array {
		return array_merge(
			array(
				'qid'          => '',
				'source'       => '',
				'ignored'      => false,
				'checked'      => 0,
				'imdb'         => 'nm0000123',
				'retry_missed' => false,
				'reverify'     => false,
			),
			$item
		);
	}

	/**
	 * @param  array $item Collected actor data.
	 * @return bool
	 */
	private function check( array $item ): bool {
		return Qid_Trust::should_check( $item )['check'];
	}

	/**
	 * @param  array $item Collected actor data.
	 * @return string
	 */
	private function reason( array $item ): string {
		return Qid_Trust::should_check( $item )['reason'];
	}

	/*
	 * is_trusted()
	 */

	public function test_a_hand_entered_qid_is_trusted(): void {
		$this->assertTrue( Qid_Trust::is_trusted( Qid_Trust::SOURCE_MANUAL ) );
	}

	public function test_an_imdb_resolved_qid_is_trusted(): void {
		$this->assertTrue( Qid_Trust::is_trusted( Qid_Trust::SOURCE_IMDB ) );
	}

	public function test_a_name_resolved_qid_is_never_trusted(): void {
		$this->assertFalse( Qid_Trust::is_trusted( Qid_Trust::SOURCE_NAME ) );
	}

	public function test_a_qid_predating_source_tracking_is_not_trusted(): void {
		// The existing column is a mix of hand-entered IDs and old fuzzy
		// first-hits. Trusting it wholesale is the laundering bug.
		$this->assertFalse( Qid_Trust::is_trusted( Qid_Trust::SOURCE_LEGACY ) );
		$this->assertFalse( Qid_Trust::is_trusted( '' ) );
	}

	public function test_an_unrecognised_source_is_not_trusted(): void {
		$this->assertFalse( Qid_Trust::is_trusted( 'sparql' ) );
		$this->assertFalse( Qid_Trust::is_trusted( 'MANUALLY_VERIFIED' ) );
	}

	public function test_a_known_source_survives_casing_and_whitespace(): void {
		$this->assertTrue( Qid_Trust::is_trusted( '  IMDb  ' ) );
		$this->assertSame( Qid_Trust::SOURCE_MANUAL, Qid_Trust::normalise_source( 'Manual' ) );
	}

	public function test_normalise_reads_anything_unknown_as_legacy(): void {
		$this->assertSame( Qid_Trust::SOURCE_LEGACY, Qid_Trust::normalise_source( '' ) );
		$this->assertSame( Qid_Trust::SOURCE_LEGACY, Qid_Trust::normalise_source( 'whatever' ) );
		$this->assertSame( Qid_Trust::SOURCE_NAME, Qid_Trust::normalise_source( 'name' ) );
	}

	/*
	 * should_check() -- the yes cases
	 */

	public function test_no_qid_with_an_imdb_id_is_a_candidate(): void {
		$this->assertTrue( $this->check( $this->actor() ) );
	}

	public function test_an_unverified_qid_is_a_candidate_under_reverify(): void {
		$this->assertTrue(
			$this->check(
				$this->actor(
					array(
						'qid'      => 'Q12345',
						'source'   => Qid_Trust::SOURCE_LEGACY,
						'reverify' => true,
					)
				)
			)
		);
	}

	public function test_a_name_resolved_qid_is_a_candidate_under_reverify(): void {
		$this->assertTrue(
			$this->check(
				$this->actor(
					array(
						'qid'      => 'Q12345',
						'source'   => Qid_Trust::SOURCE_NAME,
						'reverify' => true,
					)
				)
			)
		);
	}

	public function test_a_previous_no_match_is_a_candidate_under_retry_missed(): void {
		$this->assertTrue(
			$this->check(
				$this->actor(
					array(
						'checked'      => 1750000000,
						'retry_missed' => true,
					)
				)
			)
		);
	}

	/*
	 * should_check() -- the no cases
	 */

	public function test_an_ignored_actor_is_never_asked_about(): void {
		$this->assertFalse( $this->check( $this->actor( array( 'ignored' => true ) ) ) );
	}

	public function test_ignore_wins_over_every_other_signal(): void {
		// Reverify, retry-missed, an IMDb ID and no Q-ID would all say yes.
		$this->assertFalse(
			$this->check(
				$this->actor(
					array(
						'ignored'      => true,
						'reverify'     => true,
						'retry_missed' => true,
					)
				)
			)
		);
	}

	public function test_a_hand_set_qid_is_never_overwritten(): void {
		// There is one Q-ID field, so a hand-set value is not a separate key --
		// it is this key with source 'manual'. Being trusted is what protects it,
		// and --reverify only targets what we cannot vouch for, so even that
		// leaves it alone.
		$item = $this->actor(
			array(
				'qid'      => 'Q999',
				'source'   => Qid_Trust::SOURCE_MANUAL,
				'reverify' => true,
			)
		);

		$this->assertFalse( $this->check( $item ) );
		$this->assertSame( 'already resolved (manual)', $this->reason( $item ) );
	}

	public function test_a_write_locked_actor_is_never_asked_about(): void {
		// store_qid() would refuse the answer, so spending a request on it is
		// pure waste -- and --reverify must not override an editor's lock.
		$item = $this->actor(
			array(
				'qid'      => 'Q999',
				'source'   => Qid_Trust::SOURCE_LEGACY,
				'ignored'  => true,
				'reverify' => true,
			)
		);

		$this->assertFalse( $this->check( $item ) );
		$this->assertSame( 'write-locked by an editor', $this->reason( $item ) );
	}

	public function test_an_already_trusted_qid_is_not_re_asked(): void {
		$this->assertFalse(
			$this->check(
				$this->actor(
					array(
						'qid'    => 'Q12345',
						'source' => Qid_Trust::SOURCE_IMDB,
					)
				)
			)
		);
	}

	public function test_reverify_does_not_re_ask_about_a_trusted_qid(): void {
		// Nothing to gain: an exact IMDb match is what reverify would do again.
		$this->assertFalse(
			$this->check(
				$this->actor(
					array(
						'qid'      => 'Q12345',
						'source'   => Qid_Trust::SOURCE_IMDB,
						'reverify' => true,
					)
				)
			)
		);
	}

	public function test_no_imdb_id_means_nothing_we_can_safely_ask(): void {
		$item = $this->actor( array( 'imdb' => '' ) );

		$this->assertFalse( $this->check( $item ) );
		$this->assertSame( 'no IMDb ID to ask with', $this->reason( $item ) );
	}

	public function test_an_unverified_qid_with_no_imdb_id_cannot_be_verified(): void {
		$item = $this->actor(
			array(
				'qid'      => 'Q12345',
				'source'   => Qid_Trust::SOURCE_NAME,
				'imdb'     => '',
				'reverify' => true,
			)
		);

		$this->assertFalse( $this->check( $item ) );
		$this->assertStringContainsString( 'no IMDb ID', $this->reason( $item ) );
	}

	public function test_an_unverified_qid_is_left_alone_on_a_routine_run(): void {
		$item = $this->actor(
			array(
				'qid'    => 'Q12345',
				'source' => Qid_Trust::SOURCE_LEGACY,
			)
		);

		$this->assertFalse( $this->check( $item ) );
		$this->assertStringContainsString( '--reverify', $this->reason( $item ) );
	}

	public function test_a_previous_no_match_is_not_re_asked_by_default(): void {
		$item = $this->actor( array( 'checked' => 1750000000 ) );

		$this->assertFalse( $this->check( $item ) );
		$this->assertSame( 'checked before, no match', $this->reason( $item ) );
	}

	public function test_every_refusal_explains_itself(): void {
		$refusals = array(
			$this->actor( array( 'ignored' => true ) ),
			$this->actor(
				array(
					'qid'    => 'Q999',
					'source' => Qid_Trust::SOURCE_MANUAL,
				)
			),
			$this->actor(
				array(
					'qid'    => 'Q1',
					'source' => Qid_Trust::SOURCE_IMDB,
				)
			),
			$this->actor( array( 'imdb' => '' ) ),
			$this->actor(
				array(
					'qid'    => 'Q1',
					'source' => Qid_Trust::SOURCE_NAME,
				)
			),
			$this->actor( array( 'checked' => 1750000000 ) ),
		);

		foreach ( $refusals as $index => $item ) {
			$result = Qid_Trust::should_check( $item );

			$this->assertFalse( $result['check'], 'case ' . $index . ' should not be a candidate' );
			$this->assertNotSame( '', $result['reason'], 'case ' . $index . ' should give a reason' );
		}
	}

	public function test_a_missing_contract_key_does_not_fatal(): void {
		// The scheduler builds this array from meta reads that can all come back
		// empty; a partial array must degrade to "cannot ask", never to a crash.
		$result = Qid_Trust::should_check( array() );

		$this->assertFalse( $result['check'] );
		$this->assertSame( 'no IMDb ID to ask with', $result['reason'] );
	}
}
