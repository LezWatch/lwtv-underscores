<?php
/**
 * Calendar Agenda
 *
 * Pure transform for the airdate calendar: takes the processed calendar array
 * and a reference date, and returns an ordered list of day-groups ready to
 * render. No WordPress globals, no queries, no output.
 *
 * The one subtlety here is the airtime. `Generate_Calendar` stores a timestamp
 * that is NOT the real instant the episode airs: it takes the UTC time from
 * TVMaze and adds the US/Eastern offset, so that formatting the value as UTC
 * yields the Eastern wall-clock time. That is fine for display, but it means
 * the raw timestamp is several hours off the true moment. Anything that
 * compares an airtime against "now" - notably the client-side aired-state dot
 * logic - has to rebuild the real instant first. `airtime()` does that, and
 * derives the UTC offset from the date so it stays correct across DST.
 *
 * @package lwtv-plugin
 */

namespace LWTV\Calendar\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Agenda
 */
class Agenda {

	/**
	 * Timezone the calendar is presented in.
	 *
	 * @var string
	 */
	private $timezone;

	/**
	 * Constructor.
	 *
	 * @param string $timezone Timezone identifier, e.g. 'America/New_York'.
	 */
	public function __construct( string $timezone ) {
		$this->timezone = $timezone;
	}

	/**
	 * Build the ordered day-groups for the agenda.
	 *
	 * Days with nothing airing are omitted entirely - the day-of-week strip in
	 * the header is what tells a visitor a quiet day was quiet, so an empty row
	 * per day would just be noise across a three week range.
	 *
	 * @param  array  $calendar Processed calendar, keyed by Y-m-d.
	 * @param  string $today    Today's date as Y-m-d.
	 * @param  array  $range    Ordered list of Y-m-d dates to consider.
	 * @return array            Ordered day-groups.
	 */
	public function build( array $calendar, string $today, array $range ): array {
		$groups = array();

		foreach ( $range as $date ) {
			if ( empty( $calendar[ $date ] ) || ! is_array( $calendar[ $date ] ) ) {
				continue;
			}

			$state = $this->day_state( $date, $today );

			$episodes = array();
			foreach ( $calendar[ $date ] as $show ) {
				$episodes[] = $this->episode( $show, $state );
			}

			$groups[] = array(
				'date'     => $date,
				'label'    => $this->day_label( $date ),
				'state'    => $state,
				'episodes' => $episodes,
			);
		}

		return $groups;
	}

	/**
	 * Build the seven day-of-week markers for the week containing $today.
	 *
	 * @param  array  $calendar Processed calendar, keyed by Y-m-d.
	 * @param  string $today    Today's date as Y-m-d.
	 * @param  array  $week     Ordered list of seven Y-m-d dates, Sunday first.
	 * @return array            One entry per day.
	 */
	public function week_strip( array $calendar, string $today, array $week ): array {
		$strip = array();

		foreach ( $week as $date ) {
			$strip[] = array(
				'date'      => $date,
				'label'     => $this->weekday_abbreviation( $date ),
				'state'     => $this->day_state( $date, $today ),
				'has_shows' => ! empty( $calendar[ $date ] ) && is_array( $calendar[ $date ] ),
			);
		}

		return $strip;
	}

	/**
	 * Rebuild the true airtime for an episode.
	 *
	 * See the class docblock: the stored timestamp is the real instant shifted
	 * by the Eastern offset, so reading it back as UTC gives us the wall-clock
	 * time. We then reinterpret that wall clock in the real timezone, which
	 * produces the correct instant and the correct offset for that date.
	 *
	 * @param  int $timestamp Stored (offset-shifted) timestamp.
	 * @return \DateTimeImmutable
	 */
	public function airtime( int $timestamp ): \DateTimeImmutable {
		$wall_clock = ( new \DateTimeImmutable( '@' . $timestamp ) )->format( 'Y-m-d H:i:s' );

		return new \DateTimeImmutable( $wall_clock, new \DateTimeZone( $this->timezone ) );
	}

	/**
	 * Where a date sits relative to today.
	 *
	 * @param  string $date  Y-m-d.
	 * @param  string $today Y-m-d.
	 * @return string        past, today or future.
	 */
	public function day_state( string $date, string $today ): string {
		if ( $date === $today ) {
			return 'today';
		}

		return ( $date < $today ) ? 'past' : 'future';
	}

	/**
	 * Build one episode entry.
	 *
	 * `dot_state` is the no-JavaScript fallback, derived from the day alone.
	 * The client refines it per episode against the visitor's clock, which is
	 * the only way to know whether something airing later today has aired yet -
	 * the processed calendar is cached for a day, so the server cannot answer
	 * that question reliably.
	 *
	 * @param  array  $show      One processed show.
	 * @param  string $day_state past, today or future.
	 * @return array
	 */
	private function episode( array $show, string $day_state ): array {
		$airtime = $this->airtime( (int) $show['timestamp'] );

		$dot_state = match ( $day_state ) {
			'past'  => 'aired',
			'today' => 'today',
			default => 'upcoming',
		};

		return array(
			'time_label'  => $airtime->format( 'g:i A' ),
			'iso_airtime' => $airtime->format( 'c' ),
			'dot_state'   => $dot_state,
			'show_link'   => $show['show_link'] ?? '',
			'title'       => $show['title'] ?? '',
			'badge'       => $show['episode_badge'] ?? '',
		);
	}

	/**
	 * Human label for a day-group header, e.g. "Wednesday, Aug 19".
	 *
	 * @param  string $date Y-m-d.
	 * @return string
	 */
	private function day_label( string $date ): string {
		return $this->date_object( $date )->format( 'l, M j' );
	}

	/**
	 * Three letter weekday abbreviation, e.g. "Wed".
	 *
	 * @param  string $date Y-m-d.
	 * @return string
	 */
	private function weekday_abbreviation( string $date ): string {
		return $this->date_object( $date )->format( 'D' );
	}

	/**
	 * Build a date object for a Y-m-d string in the calendar's timezone.
	 *
	 * @param  string $date Y-m-d.
	 * @return \DateTimeImmutable
	 */
	private function date_object( string $date ): \DateTimeImmutable {
		return new \DateTimeImmutable( $date, new \DateTimeZone( $this->timezone ) );
	}
}
