<?php
/**
 * Donut Segments Build Class
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure transform: ranks a flat [{'name','count'}] list, ramps the top N,
 * folds the remainder into "Other", and optionally pulls one matching item
 * out into its own forced-grey slot first (e.g. "cisgender" on Gender
 * views).
 *
 * Lifted verbatim from the identical closure that used to be duplicated at
 * the top of both nations/single.php and stations/single.php — same ramp,
 * same grey-match-first behavior, same shape — so both pages (and any
 * future taxonomy-profile caller) share one implementation instead of two
 * copies that could silently drift apart.
 */
class Donut_Segments {

	/**
	 * @param array  $items      Ordered [ { 'name' => string, 'count' => int }, ... ].
	 * @param int    $topn       Max ramped segments before folding the rest into "Other".
	 * @param string $grey_match Lowercase name to pull out into its own forced-grey
	 *                           segment before ramping (e.g. 'cisgender'), or '' to skip.
	 * @return array [ $segments, $total ] — $segments is the donut-ready list,
	 *               $total is the sum of every item's count (including the
	 *               grey-matched one).
	 */
	public static function build( array $items, int $topn, string $grey_match = '' ): array {
		$total = 0;
		foreach ( $items as $it ) {
			$total += (int) $it['count'];
		}

		$ramp     = array( 'dkpink', 'pink', 'mid', 'mid2', 'ltpink' );
		$segments = array();
		$grey_val = 0;

		// Pull the grey-matched item (e.g. cisgender) out first, if present.
		if ( '' !== $grey_match ) {
			foreach ( $items as $k => $it ) {
				if ( strtolower( $it['name'] ) === $grey_match ) {
					$grey_val = (int) $it['count'];
					unset( $items[ $k ] );
					break;
				}
			}
		}

		uasort( $items, fn( $a, $b ) => (int) $b['count'] <=> (int) $a['count'] );

		if ( '' !== $grey_match ) {
			$segments[] = array(
				'label' => ucfirst( $grey_match ),
				'count' => $grey_val,
				'pct'   => ( $total > 0 ) ? round( ( $grey_val / $total ) * 100, 1 ) : 0,
				'class' => 'grey',
			);
		}

		$i     = 0;
		$named = $grey_val;
		foreach ( $items as $it ) {
			if ( $i >= $topn || (int) $it['count'] <= 0 ) {
				break;
			}
			$c          = (int) $it['count'];
			$named     += $c;
			$segments[] = array(
				'label' => $it['name'],
				'count' => $c,
				'pct'   => ( $total > 0 ) ? round( ( $c / $total ) * 100, 1 ) : 0,
				'class' => $ramp[ $i ],
			);
			++$i;
		}

		$other = max( 0, $total - $named );
		if ( $other > 0 ) {
			$segments[] = array(
				'label' => __( 'Other', 'lwtv' ),
				'count' => $other,
				'pct'   => ( $total > 0 ) ? round( ( $other / $total ) * 100, 1 ) : 0,
				'class' => 'grey',
			);
		}

		return array( $segments, $total );
	}
}
