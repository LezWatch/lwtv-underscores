<?php
/**
 * Name: Site Pulse
 * Description: Pure transforms behind the Site Pulse dashboard widget.
 *
 * The widget answers "what's happening?" (Data Health answers "what's broken?").
 * Everything here takes plain arrays and returns plain arrays so the rules can
 * be unit-tested; the component reads Gravity Forms, WP_Query and the API
 * usage report and hands the results in.
 *
 * - A form is a message form unless it feeds an approvals add-on (the Slack
 *   application form, which has its own widget) or only feeds a mailing-list
 *   add-on (sign-ups, not messages). Matching on feeds, not IDs, survives a
 *   form being recreated.
 * - Weeks start on the site's start_of_week option, so the count climbs
 *   through the week and resets.
 * - API: the worst few 5xx routes, plus any route with enough 404s to suggest
 *   a broken link somewhere. A route is listed once, under 5xx if it has both.
 *
 * @package LWTV
 */

namespace LWTV\Admin_Menu\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site_Pulse {

	/**
	 * Feed add-ons that mark a form as an application, not a message.
	 */
	const APPROVAL_SLUGS = array( 'lwtv-gf-approvals', 'gravityformsapprovals' );

	/**
	 * Feed add-ons that mark a form as a mailing-list sign-up.
	 */
	const SIGNUP_SLUGS = array( 'gravityformsbrevo' );

	/**
	 * Post types counted in "Added this week", in display order.
	 */
	const TYPES = array( 'shows', 'characters', 'actors' );

	/**
	 * Unread counts for the message forms.
	 *
	 * @param list<array{id:int,title:string,unread:int,is_active:bool}> $forms Forms with unread counts.
	 * @param array<int, list<string>>                                    $feeds Active feed add-on slugs per form ID.
	 * @return array{rows: list<array{id:int,title:string,unread:int}>, total:int}
	 */
	public static function inbox( array $forms, array $feeds ): array {
		$rows = array();

		foreach ( $forms as $form ) {
			if ( empty( $form['is_active'] ) ) {
				continue;
			}

			$slugs = $feeds[ (int) $form['id'] ] ?? array();
			if ( array_intersect( $slugs, self::APPROVAL_SLUGS ) ) {
				continue;
			}
			if ( $slugs && ! array_diff( $slugs, self::SIGNUP_SLUGS ) ) {
				continue;
			}

			$rows[] = array(
				'id'     => (int) $form['id'],
				'title'  => (string) $form['title'],
				'unread' => (int) $form['unread'],
			);
		}

		usort(
			$rows,
			static fn( $a, $b ) => ( $b['unread'] <=> $a['unread'] ) ?: strcasecmp( $a['title'], $b['title'] )
		);

		return array(
			'rows'  => $rows,
			'total' => array_sum( array_column( $rows, 'unread' ) ),
		);
	}

	/**
	 * First day of this week and of last week.
	 *
	 * @param string $today         Local date, Y-m-d.
	 * @param int    $start_of_week 0 (Sunday) to 6 (Saturday), as the WP option.
	 * @return array{this_start:string, last_start:string} Both Y-m-d.
	 */
	public static function week_bounds( string $today, int $start_of_week ): array {
		$day   = new \DateTimeImmutable( $today );
		$back  = ( (int) $day->format( 'w' ) - $start_of_week + 7 ) % 7;
		$start = $day->modify( '-' . $back . ' days' );

		return array(
			'this_start' => $start->format( 'Y-m-d' ),
			'last_start' => $start->modify( '-7 days' )->format( 'Y-m-d' ),
		);
	}

	/**
	 * This week's and last week's counts, per type, in display order.
	 *
	 * @param array<string,int> $this_week Counts keyed by type.
	 * @param array<string,int> $last_week Counts keyed by type.
	 * @return list<array{type:string,this:int,last:int}>
	 */
	public static function added( array $this_week, array $last_week ): array {
		$rows = array();
		foreach ( self::TYPES as $type ) {
			$rows[] = array(
				'type' => $type,
				'this' => (int) ( $this_week[ $type ] ?? 0 ),
				'last' => (int) ( $last_week[ $type ] ?? 0 ),
			);
		}
		return $rows;
	}

	/**
	 * Routes worth a look: the top 5xx routes, then any with many 404s.
	 *
	 * @param list<array<string,mixed>> $routes  Route rows from Api_Usage_Report.
	 * @param int                       $top     How many 5xx routes to keep.
	 * @param int                       $min_404 404 count at which a route is flagged.
	 * @return list<array{route:string,s5xx:int,s404:int,last_5xx:string,reason:string}>
	 */
	public static function api( array $routes, int $top = 3, int $min_404 = 10 ): array {
		$shape = static fn( array $r, string $reason ): array => array(
			'route'    => (string) $r['route'],
			's5xx'     => (int) ( $r['s5xx'] ?? 0 ),
			's404'     => (int) ( $r['s404'] ?? 0 ),
			'last_5xx' => (string) ( $r['last_5xx'] ?? '' ),
			'reason'   => $reason,
		);

		$failing = array_values( array_filter( $routes, static fn( $r ) => (int) ( $r['s5xx'] ?? 0 ) > 0 ) );
		usort( $failing, static fn( $a, $b ) => (int) $b['s5xx'] <=> (int) $a['s5xx'] );
		$failing = array_slice( $failing, 0, max( 0, $top ) );
		$listed  = array_column( $failing, 'route' );

		$missing = array_values(
			array_filter(
				$routes,
				static fn( $r ) => (int) ( $r['s404'] ?? 0 ) >= $min_404 && ! in_array( $r['route'], $listed, true )
			)
		);
		usort( $missing, static fn( $a, $b ) => (int) $b['s404'] <=> (int) $a['s404'] );

		return array_merge(
			array_map( static fn( $r ) => $shape( $r, '5xx' ), $failing ),
			array_map( static fn( $r ) => $shape( $r, '404' ), $missing )
		);
	}
}
