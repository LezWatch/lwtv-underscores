<?php
/**
 * Unit tests for the Overview fact-sheet transform: composition segment
 * folding, the single-segment / thin-data collapse guards, the narrative
 * descriptor, ratio facts, and best-year selection.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Statistics;

use PHPUnit\Framework\TestCase;
use LWTV\Statistics\Build\Overview_Factsheet;

class OverviewFactsheetTest extends TestCase {

	private function items( array $pairs ): array {
		$out = array();
		foreach ( $pairs as $label => $count ) {
			$out[] = array(
				'label' => $label,
				'count' => $count,
			);
		}
		return $out;
	}

	public function test_fold_top_sorts_desc_and_computes_pct(): void {
		$in  = $this->items(
			array(
				'bi'      => 26,
				'lesbian' => 40,
				'gay'     => 14,
			)
		);
		$out = Overview_Factsheet::fold_top( $in, 4, false );

		$this->assertSame( 80, $out['total'] );
		$this->assertSame( 'lesbian', $out['segments'][0]['label'] );
		$this->assertSame( 40, $out['segments'][0]['count'] );
		$this->assertSame( 50.0, $out['segments'][0]['pct'] );
		$this->assertNull( $out['tail'] );
	}

	public function test_fold_top_emits_grey_tail_only_when_requested_and_nonzero(): void {
		$in = $this->items(
			array(
				'a' => 40,
				'b' => 26,
				'c' => 14,
				'd' => 8,  // smallest — the leftover folded into the grey tail
				'e' => 12,
			)
		);

		$with = Overview_Factsheet::fold_top( $in, 4, true );
		$this->assertCount( 4, $with['segments'] );
		$this->assertNotNull( $with['tail'] );
		$this->assertSame( 8, $with['tail']['count'] );

		$without = Overview_Factsheet::fold_top( $in, 4, false );
		$this->assertNull( $without['tail'] );

		// Tail requested but nothing left over -> still null.
		$exact = Overview_Factsheet::fold_top( $this->items( array( 'a' => 5, 'b' => 5 ) ), 4, true );
		$this->assertNull( $exact['tail'] );
	}

	public function test_fold_top_handles_zero_total(): void {
		$out = Overview_Factsheet::fold_top( $this->items( array( 'a' => 0 ) ), 4, true );
		$this->assertSame( 0, $out['total'] );
		$this->assertSame( array(), $out['segments'] );
		$this->assertNull( $out['tail'] );
	}

	public function test_finalize_bar_collapses_single_segment_to_text(): void {
		// Two non-zero counts -> track.
		$this->assertSame( 'track', Overview_Factsheet::finalize_bar( array( 183, 31 ) ) );
		// Only one non-zero count -> would be a single 100% segment -> text.
		$this->assertSame( 'text', Overview_Factsheet::finalize_bar( array( 183, 0 ) ) );
		// External thin-data override forces text even with two non-zero counts.
		$this->assertSame( 'text', Overview_Factsheet::finalize_bar( array( 4, 1 ), true ) );
	}

	public function test_collapse_thresholds(): void {
		$this->assertTrue( Overview_Factsheet::collapse_for_chars( 4 ) );
		$this->assertFalse( Overview_Factsheet::collapse_for_chars( 5 ) );
		$this->assertTrue( Overview_Factsheet::collapse_for_shows( 2 ) );
		$this->assertFalse( Overview_Factsheet::collapse_for_shows( 3 ) );
	}

	public function test_narrative_ranked_when_ranked_and_deep(): void {
		$out = Overview_Factsheet::narrative( 3, 1996, 68 );
		$this->assertSame( 'ranked', $out['mode'] );
		$this->assertSame( 3, $out['rank'] );
		$this->assertSame( 1996, $out['first_year'] );
	}

	public function test_narrative_since_when_unranked_or_thin(): void {
		$unranked = Overview_Factsheet::narrative( null, 2015, 40 );
		$this->assertSame( 'since', $unranked['mode'] );
		$this->assertSame( 2015, $unranked['first_year'] );

		$thin = Overview_Factsheet::narrative( 50, 2021, 2 );
		$this->assertSame( 'since', $thin['mode'] );
		$this->assertSame( 2, $thin['shows'] );
	}

	public function test_narrative_bare_when_no_year(): void {
		$out = Overview_Factsheet::narrative( 5, null, 4 );
		$this->assertSame( 'bare', $out['mode'] );
		$this->assertSame( 4, $out['shows'] );
	}

	public function test_ordinal(): void {
		$this->assertSame( '1st', Overview_Factsheet::ordinal( 1 ) );
		$this->assertSame( '2nd', Overview_Factsheet::ordinal( 2 ) );
		$this->assertSame( '3rd', Overview_Factsheet::ordinal( 3 ) );
		$this->assertSame( '4th', Overview_Factsheet::ordinal( 4 ) );
		$this->assertSame( '11th', Overview_Factsheet::ordinal( 11 ) );
		$this->assertSame( '12th', Overview_Factsheet::ordinal( 12 ) );
		$this->assertSame( '13th', Overview_Factsheet::ordinal( 13 ) );
		$this->assertSame( '21st', Overview_Factsheet::ordinal( 21 ) );
		$this->assertSame( '22nd', Overview_Factsheet::ordinal( 22 ) );
		$this->assertSame( '113th', Overview_Factsheet::ordinal( 113 ) );
	}

	public function test_ratio_rounds_and_guards_zero(): void {
		$this->assertSame( 3.1, Overview_Factsheet::ratio( 214, 68 ) );
		$this->assertSame( 2.6, Overview_Factsheet::ratio( 26, 10 ) );
		$this->assertNull( Overview_Factsheet::ratio( 10, 0 ) );
	}

	public function test_death_rate_rounds_and_guards_zero(): void {
		$this->assertSame( 14.5, Overview_Factsheet::death_rate( 31, 214 ) );
		$this->assertNull( Overview_Factsheet::death_rate( 0, 0 ) );
	}

	public function test_best_year_picks_peak_most_recent_on_tie(): void {
		$points = array(
			array(
				'year'  => 2018,
				'count' => 4,
			),
			array(
				'year'  => 2020,
				'count' => 6,
			),
			array(
				'year'  => 2022,
				'count' => 6, // ties 2020; most recent wins
			),
			array(
				'year'  => 2024,
				'count' => 3,
			),
		);
		$best = Overview_Factsheet::best_year( $points );
		$this->assertSame( 2022, $best['year'] );
		$this->assertSame( 6, $best['count'] );
	}

	public function test_best_year_null_when_empty(): void {
		$this->assertNull( Overview_Factsheet::best_year( array() ) );
	}
}
