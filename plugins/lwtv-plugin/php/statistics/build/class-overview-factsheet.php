<?php
/**
 * Overview fact-sheet view transforms for the single-nation and
 * single-station statistics pages.
 *
 * Pure array-in / array-out helpers. No WordPress runtime dependency — every
 * query, meta read, permalink, and i18n string stays in the template.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shapes the fact-sheet model shared by nations/single.php and stations/single.php.
 */
class Overview_Factsheet {

	/**
	 * Fold a labelled count list into the top N segments plus an optional grey tail.
	 *
	 * @param array $items     [ ['label'=>string,'count'=>int], … ] in any order.
	 * @param int   $take      Number of ramped segments to keep before the tail.
	 * @param bool  $with_tail Emit the leftover as a grey tail segment (only when > 0).
	 * @return array [ 'segments'=>[['label','count','pct'], …], 'tail'=>['count','pct']|null, 'total'=>int ]
	 */
	public static function fold_top( array $items, int $take = 4, bool $with_tail = false ): array {
		$total = 0;
		foreach ( $items as $it ) {
			$total += (int) $it['count'];
		}

		usort( $items, static fn( $a, $b ) => (int) $b['count'] <=> (int) $a['count'] );

		$segments = array();
		$taken    = 0;
		foreach ( $items as $it ) {
			if ( count( $segments ) >= $take || (int) $it['count'] <= 0 ) {
				break;
			}
			$count      = (int) $it['count'];
			$taken     += $count;
			$segments[] = array(
				'label' => (string) $it['label'],
				'count' => $count,
				'pct'   => ( $total > 0 ) ? round( $count / $total * 100, 1 ) : 0.0,
			);
		}

		$tail      = null;
		$remainder = $total - $taken;
		if ( $with_tail && $remainder > 0 ) {
			$tail = array(
				'count' => $remainder,
				'pct'   => ( $total > 0 ) ? round( $remainder / $total * 100, 1 ) : 0.0,
			);
		}

		return array(
			'segments' => $segments,
			'tail'     => $tail,
			'total'    => $total,
		);
	}

	/**
	 * Decide whether a bar renders as a track or a text fallback.
	 *
	 * A track needs at least two non-zero segments; anything less would be a
	 * single 100% bar, which is visually useless. $force_text carries the
	 * external thin-data rule (too few characters or shows).
	 *
	 * @param array $counts     Flat list of segment counts.
	 * @param bool  $force_text Force the text fallback regardless of counts.
	 * @return string 'track' or 'text'.
	 */
	public static function finalize_bar( array $counts, bool $force_text = false ): string {
		if ( $force_text ) {
			return 'text';
		}
		$nonzero = 0;
		foreach ( $counts as $count ) {
			if ( (int) $count > 0 ) {
				++$nonzero;
			}
		}
		return ( $nonzero >= 2 ) ? 'track' : 'text';
	}

	/**
	 * Character composition bars (sexuality, gender, alive/dead) collapse below 5 characters.
	 *
	 * @param int $chars Character count.
	 * @return bool
	 */
	public static function collapse_for_chars( int $chars ): bool {
		return $chars < 5;
	}

	/**
	 * Show composition bars (format, on-air vs total) collapse below 3 shows.
	 *
	 * @param int $shows Show count.
	 * @return bool
	 */
	public static function collapse_for_shows( int $shows ): bool {
		return $shows < 3;
	}

	/**
	 * Build the masthead narrative descriptor. The template turns this into a
	 * translated sentence — keeping the words out of the transform.
	 *
	 * @param int|null $rank       1-based rank among entities with shows, or null.
	 * @param int|null $first_year Earliest tracked show year, or null.
	 * @param int      $shows      Total shows for this entity.
	 * @return array Descriptor keyed by 'mode'.
	 */
	public static function narrative( ?int $rank, ?int $first_year, int $shows ): array {
		if ( null === $first_year ) {
			return array(
				'mode'  => 'bare',
				'shows' => $shows,
			);
		}
		if ( null !== $rank && $shows >= 3 ) {
			return array(
				'mode'       => 'ranked',
				'rank'       => $rank,
				'first_year' => $first_year,
			);
		}
		return array(
			'mode'       => 'since',
			'shows'      => $shows,
			'first_year' => $first_year,
		);
	}

	/**
	 * English ordinal suffix for a positive integer (site is en_US).
	 *
	 * @param int $n Number.
	 * @return string e.g. '3rd'.
	 */
	public static function ordinal( int $n ): string {
		$mod100 = $n % 100;
		if ( $mod100 >= 11 && $mod100 <= 13 ) {
			return $n . 'th';
		}
		switch ( $n % 10 ) {
			case 1:
				return $n . 'st';
			case 2:
				return $n . 'nd';
			case 3:
				return $n . 'rd';
			default:
				return $n . 'th';
		}
	}

	/**
	 * One-decimal ratio, or null when the denominator is not positive.
	 *
	 * @param int $numerator   Top of the ratio.
	 * @param int $denominator Bottom of the ratio.
	 * @return float|null
	 */
	public static function ratio( int $numerator, int $denominator ): ?float {
		if ( $denominator <= 0 ) {
			return null;
		}
		return round( $numerator / $denominator, 1 );
	}

	/**
	 * Percentage of characters that are dead, one decimal, or null when there
	 * are no characters.
	 *
	 * @param int $dead  Dead characters.
	 * @param int $chars Total characters.
	 * @return float|null
	 */
	public static function death_rate( int $dead, int $chars ): ?float {
		if ( $chars <= 0 ) {
			return null;
		}
		return round( $dead / $chars * 100, 1 );
	}

	/**
	 * Peak of a per-year on-air series. Most recent year wins a tie.
	 *
	 * @param array $points [ ['year'=>int,'count'=>int], … ] ascending by year.
	 * @return array|null ['year'=>int,'count'=>int] or null for an empty list.
	 */
	public static function best_year( array $points ): ?array {
		$best = null;
		foreach ( $points as $point ) {
			$count = (int) $point['count'];
			// >= lets a later equal year overwrite an earlier one (points ascend).
			if ( null === $best || $count >= $best['count'] ) {
				$best = array(
					'year'  => (int) $point['year'],
					'count' => $count,
				);
			}
		}
		return $best;
	}
}
