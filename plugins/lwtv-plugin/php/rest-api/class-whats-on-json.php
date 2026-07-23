<?php
/**
 * Description: REST-API: What's On?
 *
 * The code that runs the What's On TV API service
 * - What's On: Outputs what's on TV today
 */

namespace LWTV\Rest_API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\_Components\Calendar;
use LWTV\Calendar\{ ICS_Parser, TVMaze };

class Whats_On_JSON {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
	}

	/**
	 * Rest API init
	 *
	 * Creates callbacks
	 *   - /lwtv/v1/whats-on/
	 *   - /lwtv/v1/whats-on/[today|tomorrow|week|month|year]
	 *   - YYYY-MM-DD
	 */
	public function rest_api_init() {
		register_rest_route(
			'lwtv/v1',
			'/whats-on/',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'lwtv/v1',
			'/whats-on/(?P<when>[a-zA-Z0-9-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'lwtv/v1',
			'/whats-on/(?P<when>[a-zA-Z0-9-]+)/(?P<name>[a-zA-Z0-9-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Rest API Callback
	 */
	public function rest_api_callback( $data ) {
		$params = $data->get_params();
		$when   = ( isset( $params['when'] ) && '' !== $params['when'] ) ? sanitize_title_for_query( $params['when'] ) : 'today';
		$show   = ( isset( $params['name'] ) && '' !== $params['name'] ) ? sanitize_title_for_query( $params['name'] ) : false;
		$date   = false;

		// Check if there's a valid date
		if ( isset( $params['name'] ) && preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $params['name'] ) ) {
			$date = $params['name'];
		}

		// Sometimes when comes in as a date, and if so, it's a date call.
		if ( preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $when ) ) {
			$date = $params['when'];
			$when = 'date';
		}

		// Figure out what we're running...
		switch ( $when ) {
			case 'date':
				$response = $this->whats_on_date( $date );
				break;
			case 'show':
				$response = $this->whats_on_show( $show );
				break;
			case 'today':
			case 'tomorrow':
			case 'tonight':
			case 'now':
				$response = self::whats_on_dayname( $when );
				break;
			case 'week':
				$response = $this->whats_on_week( $date );
				break;
			default:
				$response = array( '404' => 'No valid calendar found.' );
				break;
		}

		return $response;
	}

	/*
	 * What's On DayName
	 *
	 * This is good for named days (today, tomorrow, etc)
	 */
	public function whats_on_dayname( $when = 'today' ) {

		$when_today = array( 'today', 'now', 'tonight' );
		$when       = ( in_array( $when, $when_today, true ) ) ? 'today' : $when;
		$when       = ( ! in_array( $when, array( 'today', 'tomorrow' ), true ) ) ? 'today' : $when;
		$lwtv_tz    = new \DateTimeZone( LWTV_TIMEZONE );

		lwtv_plugin()->get_transient( 'lwtv_missed_schedule' );

		$tvmaze_url = ( new Calendar() )->get_tvmaze_ics();
		if ( false === $tvmaze_url ) {
			$return['none'] = 'The Calendar is down for maintenance. Come back soon.';
			return $return;
		}

		$calendar = ( new ICS_Parser() )->generate_by_date( $tvmaze_url, $when, false );
		$whats_on = $calendar;

		if ( empty( $whats_on ) ) {
			$datetime       = new \DateTime( $when, $lwtv_tz );
			$when_day       = $datetime->format( 'l' );
			$return['none'] = 'Nothing queer is on TV on ' . $when_day . '.';
		} else {
			$return = self::parse_calendar( $whats_on );
		}

		return $return;
	}

	/*
	 * What's On Date
	 *
	 * This is good for dates (eg 2019-11-11)
	 */
	public function whats_on_date( $date ) {

		$lwtv_tz    = new \DateTimeZone( LWTV_TIMEZONE );
		$tvmaze_url = ( new Calendar() )->get_tvmaze_ics();
		if ( false === $tvmaze_url ) {
			$return['none'] = 'The Calendar is down for maintenance. Come back soon.';
			return $return;
		}

		$calendar = ( new ICS_Parser() )->generate_by_date( $tvmaze_url, 'date', $date );
		$whats_on = $calendar;

		if ( empty( $whats_on ) ) {
			$datetime = new \DateTime( $date, $lwtv_tz );
			$when_day = $datetime->format( 'l' );

			$return['none'] = 'Nothing is on TV ' . $when_day . '.';
		} else {
			$return = self::parse_calendar( $whats_on );
		}

		return $return;
	}

	/*
	 * What's On Week
	 *
	 * This is good for whole weeks (eg 2019-11-11)
	 */
	public function whats_on_week( $when = 'now' ) {
		$tvmaze_url = ( new Calendar() )->get_tvmaze_ics();
		if ( false === $tvmaze_url ) {
			$return['none'] = 'The Calendar is down for maintenance. Come back soon.';
			return $return;
		}

		if ( 'now' === $when ) {
			$calendar = ( new ICS_Parser() )->generate_by_date( $tvmaze_url, 'week' );
		} else {
			$calendar = ( new ICS_Parser() )->generate_by_date( $tvmaze_url, 'week', $when );
		}

		$whats_on = $calendar;

		if ( empty( $whats_on ) ) {
			$return['none'] = 'Nothing is on TV that week. We\'re pretty shocked too!';
		} else {
			$return = self::parse_calendar( $whats_on );
		}

		return $return;
	}

	/*
	 * WHEN is a show on?
	 *
	 * This is good for finding out when a show is on.
	 *
	 * @param string $show
	 * @return array
	 */
	public function whats_on_show( $show = 'unknown' ) {

		$return = array(
			'pretty' => 'Our show robots were unable to find a television show with that name.',
		);

		// If someone put in an invalid entry
		if ( false === $show || 'unknown' === $show ) {
			$return['pretty'] = 'No name for a TV show was entered. You have to tell us what show you want airdates for.';
		} else {
			// Default reply
			$return['pretty'] = 'There is no upcoming airing of this show in the next 30 days.';

			// If we passed a show ID, try to flip it to a post-slug
			// pretty much so we can be lazy in other places.
			if ( is_numeric( $show ) ) {
				$show_id   = $show;
				$show_name = get_the_title( $show );
			} else {
				// Get the show object:
				$show_obj = get_page_by_path( $show, OBJECT, 'post_type_shows' );

				if ( $show_obj ) {
					// Show ID:
					$show_id = $show_obj->ID;
				}
			}

			// Only published shows are exposed via this endpoint.
			if ( empty( $show_id ) || 'post_type_shows' !== get_post_type( $show_id ) || 'publish' !== get_post_status( $show_id ) ) {
				return $return;
			}

			// Get the on-air status.
			$on_air = get_post_meta( $show_id, 'lezshows_on_air', true );

			// We only want to do the rest of this if the show is on air.
			if ( 'yes' === $on_air ) {
				// Remove everything after a space-and parenthesis to compensate for
				// 'charmed (2018)' situations but NOT 'thirtysomething(else)'
				// can shows PLEASE stop being so clever? UGH.
				// Also remove accents, which TVMaze is having ish with.
				$show_name = remove_accents( trim( current( explode( ' (', get_the_title( $show_id ) ) ) ) );

				// Get the details based on Show ID!
				$details = self::get_show_details( $show_id, $show_name );

				if ( ! is_wp_error( $details ) && is_array( $details ) && isset( $details['next'] ) ) {
					// Return details:
					$return = array(
						'pretty'       => 'The next episode of "' . $show_name . '" is ' . $details['next'] . '.',
						'next'         => $details['next'],
						'next_summary' => $details['next_summary'],
						'tvmaze'       => $details['tvmaze'],
					);
				}
			}
		}

		return $return;
	}

	public function get_show_details( $show_id, $show_name ) {

		$on_air = get_post_meta( $show_id, 'lezshows_on_air', true );
		$array  = $on_air;

		// If the show isn't on-air, we short circuit and stop.
		if ( 'yes' === $on_air ) {

			// Get the TV Maze data
			$array = get_post_meta( $show_id, 'lezshows_tvmaze', true );

			// If the array isn't an array, time isn't set, or time is MORE than the
			// 'next check', we check. Otherwise we can reuse what we have and save
			// CPU.
			if ( ! is_array( $array ) || ! isset( $array['time'] ) || time() >= $array['time'] ) {

				$show_info  = ( new TVMaze() )->get_tvmaze_info_show( $show_id, $show_name );
				$show_array = $this->make_show_array( $show_id, $show_name, $show_info );

				$array = $show_array;
			}
		}

		return $array;
	}

	/**
	 * Fetch a TVMaze episode link only if it points at the TVMaze API host.
	 *
	 * The href comes from a TVMaze API response; validate it before following
	 * to prevent SSRF via a tampered response.
	 *
	 * @param string|false $href The _links href from the show response.
	 * @return array|false wp_remote_get result, or false.
	 */
	private function fetch_tvmaze_link( $href ) {
		if ( empty( $href ) ) {
			return false;
		}
		$parts = wp_parse_url( $href );
		if ( ! is_array( $parts ) ) {
			return false;
		}
		if ( empty( $parts['host'] ) || 'https' !== ( $parts['scheme'] ?? '' ) || 'api.tvmaze.com' !== strtolower( $parts['host'] ) ) {
			return false;
		}
		return wp_remote_get( $href );
	}

	/**
	 * Make the show array
	 *
	 * @param  int    $show_id   The show ID
	 * @param  string $show_name The show name
	 * @param  array  $show_array The show info
	 * @return array            The show array
	 */
	public function make_show_array( $show_id, $show_name, $show_array ) {
		// Set default
		$array = array(
			'time' => strtotime( 'tomorrow 01:00' ),
			'name' => $show_name,
			'url'  => get_the_permalink( $show_id ),
		);

		// If the array is false, we return the default array.
		if ( false === $show_array ) {
			return $array;
		}
		// Default Episode Arrays
		$episodes_array = array();

		// Get the previous episode, if it exists:
		$previous_episode = ( isset( $show_array['_links']['previousepisode']['href'] ) ) ? $this->fetch_tvmaze_link( $show_array['_links']['previousepisode']['href'] ) : false;

		if ( ! is_wp_error( $previous_episode ) ) {
			// If the previous episode URL has data, we use it as an array
			$episodes_array['previous'] = ( false !== $previous_episode && isset( $previous_episode['body'] ) ) ? json_decode( $previous_episode['body'], true ) : false;

			// Get the next episode if it exists.
			$next_episode = ( isset( $show_array['_links']['nextepisode']['href'] ) ) ? $this->fetch_tvmaze_link( $show_array['_links']['nextepisode']['href'] ) : false;

			// If the next episode URL has data, we use it as an array
			$episodes_array['next'] = ( ! is_wp_error( $next_episode ) && false !== $next_episode && isset( $next_episode['body'] ) ) ? json_decode( $next_episode['body'], true ) : false;
		}

		// Build out next episode:
		// If there's a next episode and it has a title, we go!
		// There are rare cases where episodes have no titles, and those tend to be errors.
		if ( ! empty( $episodes_array ) && false !== $episodes_array['next'] && isset( $episodes_array['next']['name'] ) ) {
			$next  = '"' . $episodes_array['next']['name'] . '"';
			$next .= ( isset( $episodes_array['next']['season'] ) && isset( $episodes_array['next']['number'] ) ) ? ' (' . $episodes_array['next']['season'] . 'x' . $episodes_array['next']['number'] . ')' : '';
			$next .= ( isset( $episodes_array['next']['airstamp'] ) ) ? ' on ' . self::convert_time( $episodes_array['next']['airstamp'] ) : '';

			// set final array
			$array['next']         = $next;
			$array['next_summary'] = ( isset( $episodes_array['next']['summary'] ) ) ? wp_filter_nohtml_kses( $episodes_array['next']['summary'] ) : 'TBD';
		}

		// Build out Previous episode:
		// Same logic, no title, no listing.
		if ( ! empty( $episodes_array ) && false !== $episodes_array['previous'] && isset( $episodes_array['previous']['name'] ) ) {
			$previous  = '"' . $episodes_array['previous']['name'] . '"';
			$previous .= ( isset( $episodes_array['previous']['season'] ) && isset( $episodes_array['previous']['number'] ) ) ? ' (' . $episodes_array['previous']['season'] . 'x' . $episodes_array['previous']['number'] . ')' : '';
			$previous .= ( isset( $episodes_array['previous']['airstamp'] ) ) ? ' on ' . self::convert_time( $episodes_array['previous']['airstamp'] ) : '';

			// set final array
			$array['previous'] = $previous;
		}

		// Add in the TV maze link.
		$array['tvmaze'] = ( isset( $show_array['url'] ) ) ? $show_array['url'] : 'https://tvmaze.com/';

		return $array;
	}

	/**
	 * Convert time to local time
	 *
	 * @param  string $date The date
	 * @return string       The local time
	 */
	public function convert_time( $date ) {
		$lwtv_tz   = new \DateTimeZone( LWTV_TIMEZONE );
		$tvmaze_tz = new \DateTimeZone( 'UTC' );
		$dt        = new \DateTime( $date, $tvmaze_tz );
		$dt->setTimezone( $lwtv_tz );
		$airtime = $dt->format( 'l j F, Y \a\t g:i A T' );

		return $airtime;
	}

	/**
	 * [generate_tvshow_calendar description]
	 * @param  [type] $date [description]
	 * @return [type]       [description]
	 */
	public function generate_tvshow_calendar( $date ) {
		return ( new Calendar() )->generate_tvmaze_calendar( $date );
	}

	/**
	 * Parse Calendar and clean up
	 * @param  array $whats_on calendar output
	 * @return array           cleaned up content
	 */
	public function parse_calendar( $whats_on ) {

		$lwtv_tz   = new \DateTimeZone( LWTV_TIMEZONE );
		$tvmaze_tz = new \DateTimeZone( 'UTC' );
		$on_array  = array();

		if ( empty( $whats_on ) || ! is_array( $whats_on ) ) {
			$return = 'Error: The calendar is empty.';
		} else {
			foreach ( $whats_on as $episode ) {
				$showtime = new \DateTime( $episode->dtstart, $tvmaze_tz );
				$offset   = $lwtv_tz->getOffset( $showtime );
				$interval = \DateInterval::createFromDateString( (string) $offset . 'seconds' );
				$showtime->add( $interval );

				// Reformat the show name and episode name
				$colon_count = substr_count( $episode->summary, ':' );
				if ( 1 === $colon_count ) {
					$show_name      = substr( $episode->summary, 0, strpos( $episode->summary, ':' ) );
					$episode_number = trim( substr( $episode->summary, strpos( $episode->summary, ':' ) + 1 ) );
				} else {
					$show_name      = substr( $episode->summary, 0, strrpos( $episode->summary, ':' ) );
					$episode_number = trim( substr( $episode->summary, strrpos( $episode->summary, ':' ) + 1 ) );
				}

				if ( array_key_exists( $show_name, $on_array ) ) {
					if ( ! is_array( $on_array[ $show_name ]['title'] ) ) {
						$on_array[ $show_name ]['title'] = array( $on_array[ $show_name ]['title'] );
					}

					$on_array[ $show_name ]['count'] = (int) $on_array[ $show_name ]['count'] + 1;
					$on_array[ $show_name ]['show']  = $show_name;
					$on_array[ $show_name ]['title'] = $on_array[ $show_name ]['count'] . ' episodes';
				} else {
					$on_array[ $show_name ] = array(
						'show'    => $show_name,
						'title'   => $episode->description . ' (' . $episode_number . ')',
						'airdate' => $showtime->format( 'F d, Y' ),
						'airtime' => $showtime->format( 'g:i A' ),
						'nextep'  => '"' . $episode->description . '" (' . $episode_number . ') on ' . $showtime->format( 'l F d, Y' ) . ' at ' . $showtime->format( 'g:i A' ) . ' US/Eastern.',
						'count'   => 1,
					);
				}
			}

			foreach ( $on_array as $is_on ) {
				$return[] = $is_on;
			}
		}

		return $return;
	}
}
