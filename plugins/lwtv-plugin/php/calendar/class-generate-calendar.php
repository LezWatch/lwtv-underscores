<?php
/**
 * Name: Calendar
 * Description: Code to generate the calendar
 * Version: 1.0
 */

namespace LWTV\Calendar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Generate_Calendar {

	/**
	 * Generate what's on for a specific date.
	 *
	 * @param  string $tvmaze_url
	 * @param  string $when
	 * @param  string $date
	 */
	public function make( $tvmaze_url, $when = 'week', $date = false ): array {

		$by_day_array   = array();
		$lwtv_tz        = new \DateTimeZone( LWTV_TIMEZONE );
		$tvmaze_tz      = new \DateTimeZone( 'UTC' );
		$episodes_array = ( new ICS_Parser() )->generate_by_date( $tvmaze_url, $when, $date );

		if ( empty( $episodes_array ) ) {
			$return['none'] = 'Nothing queer is on TV that week. We\'re pretty shocked too!';
		} else {
			foreach ( $episodes_array as $episode ) {

				$showtime = new \DateTime( $episode->dtstart, $tvmaze_tz );
				$offset   = $lwtv_tz->getOffset( $showtime );
				$interval = \DateInterval::createFromDateString( (string) $offset . 'seconds' );
				$showtime->add( $interval );

				// Reformat the show name and episode name
				$episode_number = trim( substr( strrchr( $episode->summary, ':' ), 1 ) );
				$show_name      = substr( trim( str_replace( $episode_number, '', $episode->summary ) ), 0, -1 );
				$airdate        = $showtime->format( 'Y-m-d' );

				// Only list a show once, trying to compensate for The Binge.
				if ( isset( $by_day_array[ $airdate ] ) && array_key_exists( $show_name, $by_day_array[ $airdate ] ) ) {
					if ( $by_day_array[ $airdate ][ $show_name ]['timestamp'] === $showtime->getTimestamp() ) {
						// If they have the same timestamp, they're a binge so list all episodes for the show under one entry.
						$by_day_array[ $airdate ][ $show_name ]['title'] = $this->binge_it( $by_day_array[ $airdate ][ $show_name ]['title'], $episode->description, $episode_number );
					} elseif ( isset( $by_day_array[ $airdate ][ $show_name . '.lwtv-' . $airdate ] ) ) {
						// If there is already a show with the same name, but a different timestamp, we need to make a new entry.
						$by_day_array[ $airdate ][ $show_name . '.lwtv-' . $airdate ]['title'] = $this->binge_it( $by_day_array[ $airdate ][ $show_name . '.lwtv-' . $airdate ]['title'], $episode->description, $episode_number );
					} else {
						$by_day_array[ $airdate ][ $show_name . '.lwtv-' . $airdate ] = array(
							'show_name' => $show_name,
							'title'     => $episode->description . ' (' . $episode_number . ')',
							'timestamp' => $showtime->getTimestamp(),
						);
					}
				} else {
					$by_day_array[ $airdate ][ $show_name ] = array(
						'show_name' => $show_name,
						'title'     => $episode->description . ' (' . $episode_number . ')',
						'timestamp' => $showtime->getTimestamp(),
					);
				}
			}
		}

		return $by_day_array;
	}

	/**
	 * Rebuild the list if a bunch of episodes drop at once.
	 *
	 * @param  array|string  $show_title_array
	 * @param  string        $description
	 * @param  string        $number
	 * @return array
	 */
	private function binge_it( mixed $show_title_array, string $description, string $number ): array {
		if ( is_array( $show_title_array ) ) {
			$show_title_array[] = $description . ' (' . $number . ')';
		} else {
			$first = $show_title_array;
			$newer = $description . ' (' . $number . ')';

			// Now Make it.
			$show_title_array = array( $first, $newer );
		}

		return $show_title_array;
	}
}
