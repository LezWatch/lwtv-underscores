<?php
/**
 * Dead Characters (By Date) view transforms for This Year.
 *
 * Pure array-in / array-out helpers that shape the death-date data into the
 * deaths-by-month graph model, the longest-stretch fact, and the ordered
 * timeline sequence. No WordPress runtime dependency — locale, i18n and
 * home_url() stay in the template.
 *
 * @package LezWatch.TV
 */

namespace LWTV\This_Year\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shapes death-date data for the Dead Characters By Date view.
 */
class Dead_Characters {

	/**
	 * Normalize a death-date key. A few legacy rows are stored dashless (Ymd);
	 * everything else is already Y-m-d.
	 *
	 * @param string $key The raw date key.
	 * @return string A Y-m-d string (best effort; unrecognized input trimmed only).
	 */
	public static function normalize_date_key( string $key ): string {
		$key = trim( $key );
		if ( false === strpos( $key, '-' ) && 8 === strlen( $key ) ) {
			return substr( $key, 0, 4 ) . '-' . substr( $key, 4, 2 ) . '-' . substr( $key, 6, 2 );
		}
		return $key;
	}

	/**
	 * The deaths-by-month graph model (Jan→Dec).
	 *
	 * For a year still in progress the caller passes the current month as
	 * $through_month so months that haven't happened yet are omitted entirely
	 * (rather than shown as empty). Past years pass null for the full calendar.
	 * Peak/empty flags are computed only over the months actually returned.
	 *
	 * @param array    $dead_by_date  Keyed by death-date string → list of characters.
	 * @param int|null $through_month Last month (1-12) to include; null for all 12.
	 * @return array Ordered list of { num, count, peak, empty }.
	 */
	public static function months( array $dead_by_date, ?int $through_month = null ): array {
		$through = ( null === $through_month ) ? 12 : max( 1, min( 12, $through_month ) );

		$counts = array_fill( 1, 12, 0 );
		foreach ( $dead_by_date as $date_key => $chars ) {
			$ts = strtotime( self::normalize_date_key( (string) $date_key ) );
			if ( ! $ts ) {
				continue;
			}
			$counts[ (int) gmdate( 'n', $ts ) ] += count( (array) $chars );
		}

		$max = 0;
		for ( $n = 1; $n <= $through; $n++ ) {
			$max = max( $max, $counts[ $n ] );
		}

		$months = array();
		for ( $n = 1; $n <= $through; $n++ ) {
			$months[] = array(
				'num'   => $n,
				'count' => $counts[ $n ],
				'peak'  => ( $max > 0 && $counts[ $n ] === $max ),
				'empty' => ( 0 === $counts[ $n ] ),
			);
		}
		return $months;
	}

	/**
	 * The longest gap, in days, between two consecutive death dates.
	 *
	 * @param array $dead_by_date Keyed by death-date string → list of characters.
	 * @return array|null { days, from, to } (ISO dates), or null if under two distinct dates.
	 */
	public static function longest_stretch( array $dead_by_date ): ?array {
		$dates = array();
		foreach ( array_keys( $dead_by_date ) as $key ) {
			$norm = self::normalize_date_key( (string) $key );
			$ts   = strtotime( $norm );
			if ( $ts ) {
				$dates[ $norm ] = $ts;
			}
		}
		if ( count( $dates ) < 2 ) {
			return null;
		}

		ksort( $dates );
		$keys = array_keys( $dates );

		$best = null;
		for ( $i = 1, $n = count( $keys ); $i < $n; $i++ ) {
			$days = (int) round( ( $dates[ $keys[ $i ] ] - $dates[ $keys[ $i - 1 ] ] ) / 86400 );
			if ( null === $best || $days > $best['days'] ) {
				$best = array(
					'days' => $days,
					'from' => $keys[ $i - 1 ],
					'to'   => $keys[ $i ],
				);
			}
		}
		return $best;
	}

	/**
	 * The ordered timeline render sequence: month waypoints, per-death rows,
	 * dashed gap markers for empty months between deaths, and a tail total.
	 *
	 * For a year still in progress the caller passes the current month as
	 * $through_month so the tail's empty-month tally excludes months that
	 * haven't happened yet. Past years pass null for the full calendar.
	 *
	 * @param array    $dead_by_date  Keyed by death-date string → list of characters.
	 * @param int|null $through_month Last month (1-12) to count; null for all 12.
	 * @return array Ordered list of typed items (waypoint|gap|death|tail).
	 */
	public static function timeline( array $dead_by_date, ?int $through_month = null ): array {
		$rows = array();
		foreach ( $dead_by_date as $key => $chars ) {
			$ts = strtotime( self::normalize_date_key( (string) $key ) );
			if ( ! $ts ) {
				continue;
			}
			$rows[] = array(
				'ts'    => $ts,
				'date'  => gmdate( 'Y-m-d', $ts ),
				'month' => (int) gmdate( 'n', $ts ),
				'chars' => array_values( (array) $chars ),
			);
		}
		usort( $rows, static fn( $a, $b ) => $a['ts'] <=> $b['ts'] );

		$month_counts = array();
		$total        = 0;
		foreach ( $rows as $row ) {
			$count                         = count( $row['chars'] );
			$month_counts[ $row['month'] ] = ( $month_counts[ $row['month'] ] ?? 0 ) + $count;
			$total                        += $count;
		}
		$through           = ( null === $through_month ) ? 12 : max( 1, min( 12, $through_month ) );
		$empty_month_count = max( 0, $through - count( $month_counts ) );

		$items      = array();
		$prev_month = null;
		foreach ( $rows as $row ) {
			if ( $row['month'] !== $prev_month ) {
				if ( null !== $prev_month && $row['month'] - $prev_month > 1 ) {
					$items[] = array(
						'type'   => 'gap',
						'months' => range( $prev_month + 1, $row['month'] - 1 ),
					);
				}
				$items[]    = array(
					'type'  => 'waypoint',
					'month' => $row['month'],
					'count' => $month_counts[ $row['month'] ],
				);
				$prev_month = $row['month'];
			}

			foreach ( $row['chars'] as $char ) {
				$shows   = array_values( $char['shows'] ?? array() );
				$items[] = array(
					'type'  => 'death',
					'date'  => $row['date'],
					'slug'  => (string) ( $char['slug'] ?? '' ),
					'name'  => (string) ( $char['name'] ?? '' ),
					'shows' => $shows,
					'role'  => $shows[0]['type'] ?? '',
				);
			}
		}

		$items[] = array(
			'type'              => 'tail',
			'total'             => $total,
			'empty_month_count' => $empty_month_count,
		);
		return $items;
	}
}
