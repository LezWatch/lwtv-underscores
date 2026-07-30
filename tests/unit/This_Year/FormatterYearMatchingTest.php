<?php
/**
 * Regression tests for the This Year formatters' year matching.
 *
 * The New / Canceled / Dead-by-date formatters filter rows by comparing a show's
 * start/finish year (or a character's death year) against the requested year.
 * That comparison used a strict `===`, so it silently produced EMPTY output
 * whenever the two sides were different types — e.g. a caller passing the year
 * as the int 2024 against a `'2024'` string pulled from meta. Every caller
 * happened to stringify the year, so the bug was latent, but it contradicted the
 * `@param int` signatures. The comparison now casts both sides to int; these
 * tests lock that in by asserting an int year and a string year yield identical,
 * non-empty results.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\This_Year;

use PHPUnit\Framework\TestCase;
use LWTV\This_Year\Format\New_Shows_Formatter;
use LWTV\This_Year\Format\Canceled_Shows_Formatter;
use LWTV\This_Year\Format\Dead_Characters_Formatter;

class FormatterYearMatchingTest extends TestCase {

	/**
	 * A "grouped by marker, then show" payload with two shows: one that starts
	 * in 2024 and stays on air, one that ran 2019 and finished in 2024.
	 *
	 * @return array
	 */
	private function shows_by_marker(): array {
		return array(
			'A' => array(
				array(
					'name'     => 'Alpha',
					'airdates' => array(
						'start'  => '2024',
						'finish' => 'current',
					),
				),
				array(
					'name'     => 'Beta',
					'airdates' => array(
						'start'  => '2019',
						'finish' => '2024',
					),
				),
			),
		);
	}

	// ---- New shows: matched on the START year. ----

	public function test_new_shows_match_start_year_for_int_and_string_year(): void {
		$data       = $this->shows_by_marker();
		$from_int    = ( new New_Shows_Formatter() )->format_by_name_for_year( 2024, $data );
		$from_string = ( new New_Shows_Formatter() )->format_by_name_for_year( '2024', $data );

		// Both spellings of the year must agree...
		$this->assertSame( $from_string, $from_int );
		// ...and actually match the 2024 debut (the bug made this empty).
		$this->assertArrayHasKey( 'Alpha', $from_int['A'] );
		$this->assertArrayNotHasKey( 'Beta', $from_int['A'] );
	}

	// ---- Canceled shows: matched on the FINISH year; 'current' never matches. ----

	public function test_canceled_shows_match_finish_year_and_exclude_current(): void {
		$data       = $this->shows_by_marker();
		$from_int    = ( new Canceled_Shows_Formatter() )->format_by_name_for_year( 2024, $data );
		$from_string = ( new Canceled_Shows_Formatter() )->format_by_name_for_year( '2024', $data );

		$this->assertSame( $from_string, $from_int );
		// Beta finished in 2024; Alpha ('current') is still airing and must be excluded.
		$this->assertArrayHasKey( 'Beta', $from_int['A'] );
		$this->assertArrayNotHasKey( 'Alpha', $from_int['A'] );
	}

	// ---- Dead characters by date: matched on the death YEAR (first 4 of Ymd). ----

	public function test_dead_by_date_matches_year_for_int_and_string_year(): void {
		$characters = array(
			array(
				'dead'        => true,
				'death_years' => array( '20240615' ),
			),
			array(
				'dead'        => true,
				'death_years' => array( '20190101' ),
			),
			array(
				'dead'        => false,
				'death_years' => array( '20240101' ),
			),
		);

		$from_int    = ( new Dead_Characters_Formatter() )->format_by_date_for_year( 2024, $characters );
		$from_string = ( new Dead_Characters_Formatter() )->format_by_date_for_year( '2024', $characters );

		$this->assertSame( $from_string, $from_int );
		// Exactly the one 2024 death, keyed by its normalized Y-m-d date.
		$this->assertSame( array( '2024-06-15' ), array_keys( $from_int ) );
		$this->assertCount( 1, $from_int['2024-06-15'] );
	}
}
