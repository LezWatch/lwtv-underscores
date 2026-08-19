<?php
/**
 * Unit tests for the calendar agenda transform.
 *
 * The airtime tests are the important ones. `Generate_Calendar` stores a
 * timestamp that has already had the US/Eastern offset added to it, so reading
 * it back as UTC gives the Eastern wall-clock time rather than the real
 * instant. Anything comparing an airtime to "now" has to undo that, and it has
 * to stay correct on both sides of a DST boundary.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Calendar;

use PHPUnit\Framework\TestCase;
use LWTV\Calendar\Build\Agenda;

class AgendaTest extends TestCase {

	private const TZ = 'America/New_York';

	/**
	 * Build the shifted timestamp the way Generate_Calendar does, so the tests
	 * exercise the same values production sees.
	 *
	 * @param  string $utc_airtime True airtime in UTC, 'Y-m-d H:i:s'.
	 * @return int                 Offset-shifted timestamp.
	 */
	private function stored_timestamp( string $utc_airtime ): int {
		$showtime = new \DateTime( $utc_airtime, new \DateTimeZone( 'UTC' ) );
		$offset   = ( new \DateTimeZone( self::TZ ) )->getOffset( $showtime );
		$showtime->add( \DateInterval::createFromDateString( (string) $offset . ' seconds' ) );

		return $showtime->getTimestamp();
	}

	private function agenda(): Agenda {
		return new Agenda( self::TZ );
	}

	// ---- airtime(): recovers the real instant from the shifted timestamp. ----

	public function test_airtime_recovers_the_true_instant_during_dst(): void {
		// 06:00 UTC on 19 Aug 2026 is 02:00 EDT (UTC-4).
		$stored  = $this->stored_timestamp( '2026-08-19 06:00:00' );
		$airtime = $this->agenda()->airtime( $stored );

		$this->assertSame( '2026-08-19T02:00:00-04:00', $airtime->format( 'c' ) );
		$this->assertSame( '2:00 AM', $airtime->format( 'g:i A' ) );

		// The recovered instant must equal the real UTC moment, not the shifted one.
		$this->assertSame(
			( new \DateTimeImmutable( '2026-08-19 06:00:00', new \DateTimeZone( 'UTC' ) ) )->getTimestamp(),
			$airtime->getTimestamp()
		);
	}

	public function test_airtime_uses_standard_offset_outside_dst(): void {
		// 07:00 UTC on 15 Jan 2026 is 02:00 EST (UTC-5).
		$stored  = $this->stored_timestamp( '2026-01-15 07:00:00' );
		$airtime = $this->agenda()->airtime( $stored );

		$this->assertSame( '2026-01-15T02:00:00-05:00', $airtime->format( 'c' ) );
		$this->assertSame(
			( new \DateTimeImmutable( '2026-01-15 07:00:00', new \DateTimeZone( 'UTC' ) ) )->getTimestamp(),
			$airtime->getTimestamp()
		);
	}

	public function test_airtime_does_not_drift_by_the_offset(): void {
		// Guards the specific bug: emitting the stored timestamp directly would
		// put the airtime four hours early, greying dots out before they air.
		$stored  = $this->stored_timestamp( '2026-08-19 06:00:00' );
		$airtime = $this->agenda()->airtime( $stored );

		$this->assertNotSame( $stored, $airtime->getTimestamp() );
		$this->assertSame( 4 * 3600, $airtime->getTimestamp() - $stored );
	}

	// ---- day_state(): past / today / future. ----

	public function test_day_state_classifies_relative_to_today(): void {
		$agenda = $this->agenda();

		$this->assertSame( 'past', $agenda->day_state( '2026-08-18', '2026-08-19' ) );
		$this->assertSame( 'today', $agenda->day_state( '2026-08-19', '2026-08-19' ) );
		$this->assertSame( 'future', $agenda->day_state( '2026-08-20', '2026-08-19' ) );
	}

	public function test_day_state_compares_across_month_and_year_boundaries(): void {
		$agenda = $this->agenda();

		$this->assertSame( 'past', $agenda->day_state( '2026-07-31', '2026-08-01' ) );
		$this->assertSame( 'future', $agenda->day_state( '2027-01-01', '2026-12-31' ) );
	}

	// ---- build(): grouping, ordering, omission of quiet days. ----

	public function test_build_omits_days_with_nothing_airing(): void {
		$calendar = array(
			'2026-08-19' => array( $this->show( 'Ted Lasso', '2026-08-19 12:00:00' ) ),
			'2026-08-21' => array( $this->show( 'Emmerdale', '2026-08-21 06:00:00' ) ),
		);
		$range    = array( '2026-08-19', '2026-08-20', '2026-08-21' );

		$groups = $this->agenda()->build( $calendar, '2026-08-19', $range );

		$this->assertCount( 2, $groups );
		$this->assertSame( array( '2026-08-19', '2026-08-21' ), array_column( $groups, 'date' ) );
	}

	public function test_build_follows_the_range_order_not_the_calendar_key_order(): void {
		// Output order comes from the range we are handed, never from the
		// calendar array's own key order.
		$calendar = array(
			'2026-08-19' => array( $this->show( 'Ted Lasso', '2026-08-19 12:00:00' ) ),
			'2026-08-26' => array( $this->show( 'Ted Lasso', '2026-08-26 12:00:00' ) ),
			'2026-08-12' => array( $this->show( 'Ted Lasso', '2026-08-12 12:00:00' ) ),
		);
		$range    = array( '2026-08-12', '2026-08-19', '2026-08-26' );

		$groups = $this->agenda()->build( $calendar, '2026-08-19', $range );

		$this->assertSame( array( '2026-08-12', '2026-08-19', '2026-08-26' ), array_column( $groups, 'date' ) );
	}

	public function test_build_labels_and_states_each_group(): void {
		$calendar = array(
			'2026-08-18' => array( $this->show( 'Emmerdale', '2026-08-18 06:00:00' ) ),
			'2026-08-19' => array( $this->show( 'Ted Lasso', '2026-08-19 12:00:00' ) ),
			'2026-08-20' => array( $this->show( 'Silo', '2026-08-20 12:00:00' ) ),
		);
		$range    = array( '2026-08-18', '2026-08-19', '2026-08-20' );

		$groups = $this->agenda()->build( $calendar, '2026-08-19', $range );

		$this->assertSame( 'Tuesday, Aug 18', $groups[0]['label'] );
		$this->assertSame( 'past', $groups[0]['state'] );
		$this->assertSame( 'Wednesday, Aug 19', $groups[1]['label'] );
		$this->assertSame( 'today', $groups[1]['state'] );
		$this->assertSame( 'Thursday, Aug 20', $groups[2]['label'] );
		$this->assertSame( 'future', $groups[2]['state'] );
	}

	public function test_build_derives_the_no_javascript_dot_state_from_the_day(): void {
		$calendar = array(
			'2026-08-18' => array( $this->show( 'Emmerdale', '2026-08-18 06:00:00' ) ),
			'2026-08-19' => array( $this->show( 'Ted Lasso', '2026-08-19 12:00:00' ) ),
			'2026-08-20' => array( $this->show( 'Silo', '2026-08-20 12:00:00' ) ),
		);
		$range    = array( '2026-08-18', '2026-08-19', '2026-08-20' );

		$groups = $this->agenda()->build( $calendar, '2026-08-19', $range );

		$this->assertSame( 'aired', $groups[0]['episodes'][0]['dot_state'] );
		$this->assertSame( 'today', $groups[1]['episodes'][0]['dot_state'] );
		$this->assertSame( 'upcoming', $groups[2]['episodes'][0]['dot_state'] );
	}

	public function test_build_keeps_multiple_episodes_in_calendar_order(): void {
		$calendar = array(
			'2026-08-17' => array(
				$this->show( 'Emmerdale', '2026-08-17 06:00:00' ),
				$this->show( 'All American', '2026-08-18 00:00:00' ),
			),
		);

		$groups = $this->agenda()->build( $calendar, '2026-08-19', array( '2026-08-17' ) );

		$this->assertCount( 2, $groups[0]['episodes'] );
		$this->assertSame( '2:00 AM', $groups[0]['episodes'][0]['time_label'] );
		$this->assertSame( '8:00 PM', $groups[0]['episodes'][1]['time_label'] );
	}

	public function test_build_passes_binge_titles_through_as_an_array(): void {
		$show          = $this->show( 'Lioness', '2026-08-16 12:00:00' );
		$show['title'] = array( 'Episode One (1x01)', 'Episode Two (1x02)' );

		$groups = $this->agenda()->build( array( '2026-08-16' => array( $show ) ), '2026-08-19', array( '2026-08-16' ) );

		$this->assertIsArray( $groups[0]['episodes'][0]['title'] );
		$this->assertCount( 2, $groups[0]['episodes'][0]['title'] );
	}

	public function test_build_returns_empty_when_nothing_airs_in_range(): void {
		$groups = $this->agenda()->build( array(), '2026-08-19', array( '2026-08-19', '2026-08-20' ) );

		$this->assertSame( array(), $groups );
	}

	// ---- week_strip(): seven markers with per-day state. ----

	public function test_week_strip_marks_state_and_whether_anything_airs(): void {
		$calendar = array(
			'2026-08-19' => array( $this->show( 'Ted Lasso', '2026-08-19 12:00:00' ) ),
		);
		$week     = array(
			'2026-08-16',
			'2026-08-17',
			'2026-08-18',
			'2026-08-19',
			'2026-08-20',
			'2026-08-21',
			'2026-08-22',
		);

		$strip = $this->agenda()->week_strip( $calendar, '2026-08-19', $week );

		$this->assertCount( 7, $strip );
		$this->assertSame( array( 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ), array_column( $strip, 'label' ) );
		$this->assertSame( 'past', $strip[0]['state'] );
		$this->assertSame( 'today', $strip[3]['state'] );
		$this->assertSame( 'future', $strip[4]['state'] );
		$this->assertTrue( $strip[3]['has_shows'] );
		$this->assertFalse( $strip[0]['has_shows'] );
	}

	/**
	 * Build a processed-show array shaped the way Data_Processor emits it.
	 *
	 * @param  string $name        Show name.
	 * @param  string $utc_airtime True airtime in UTC, 'Y-m-d H:i:s'.
	 * @return array
	 */
	private function show( string $name, string $utc_airtime ): array {
		return array(
			'show_name'     => $name,
			'show_link'     => '<a href="/show/' . strtolower( str_replace( ' ', '-', $name ) ) . '/">' . $name . '</a>',
			'show_id'       => 1,
			'title'         => $name . ' episode',
			'timestamp'     => $this->stored_timestamp( $utc_airtime ),
			'episode_badge' => '',
		);
	}
}
