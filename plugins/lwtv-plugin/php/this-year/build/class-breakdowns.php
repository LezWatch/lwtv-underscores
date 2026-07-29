<?php
/**
 * This Year "Where it came from" Breakdowns
 *
 * Pure count transforms for the Overview Chapter 02 cards: show origin
 * (top-N countries + Other), format split, and per-relationship role split.
 *
 * @package lwtv-plugin
 */

namespace LWTV\This_Year\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Breakdowns
 */
class Breakdowns {

	/**
	 * Role types tracked for the role split, in display order. Mirrors the
	 * convention in Statistics\Build\Dead and \Actors.
	 *
	 * @var string[]
	 */
	private const ROLE_TYPES = array( 'regular', 'recurring', 'guest' );

	/**
	 * Top-N countries of origin, with the remainder aggregated into "Other".
	 *
	 * @param array $shows_by_nation [ country => [ show_name => show data ] ].
	 * @param int   $limit           Number of named countries to keep.
	 * @return array { @type array $top [ [ name, count ], ... ], @type int $other }
	 */
	public static function origin( array $shows_by_nation, int $limit = 5 ): array {
		$counts = array();
		foreach ( $shows_by_nation as $country => $shows ) {
			$counts[ $country ] = count( $shows );
		}

		// Stable in PHP 8+, so equal counts keep the builder's alphabetical order.
		arsort( $counts );

		$top   = array();
		$other = 0;
		$rank  = 0;
		foreach ( $counts as $country => $count ) {
			if ( $rank < $limit ) {
				$top[] = array(
					'name'  => $country,
					'count' => $count,
				);
			} else {
				$other += $count;
			}
			++$rank;
		}

		return array(
			'top'   => $top,
			'other' => $other,
		);
	}

	/**
	 * A count per format, sorted descending.
	 *
	 * @param array $shows_by_format [ format => [ show_name => show data ] ].
	 * @return array [ [ name, count ], ... ]
	 */
	public static function formats( array $shows_by_format ): array {
		$counts = array();
		foreach ( $shows_by_format as $format => $shows ) {
			$counts[ $format ] = count( $shows );
		}

		arsort( $counts );

		$out = array();
		foreach ( $counts as $format => $count ) {
			$out[] = array(
				'name'  => $format,
				'count' => $count,
			);
		}

		return $out;
	}

	/**
	 * Per-relationship tally of role types across the year's characters.
	 *
	 * Each character carries a `shows` list whose entries have a `type`; every
	 * relationship counts, so a character who is regular on one show and a guest
	 * on another contributes to both — matching the site's death/actor stats.
	 *
	 * @param array $characters_with_shows [ [ 'shows' => [ [ 'type' => ... ], ... ] ], ... ].
	 * @return array [ [ key, count ], ... ] in ROLE_TYPES order.
	 */
	public static function roles( array $characters_with_shows ): array {
		$counts = array_fill_keys( self::ROLE_TYPES, 0 );

		foreach ( $characters_with_shows as $character ) {
			if ( empty( $character['shows'] ) || ! is_array( $character['shows'] ) ) {
				continue;
			}
			foreach ( $character['shows'] as $show ) {
				$type = $show['type'] ?? '';
				if ( isset( $counts[ $type ] ) ) {
					++$counts[ $type ];
				}
			}
		}

		$out = array();
		foreach ( self::ROLE_TYPES as $type ) {
			$out[] = array(
				'key'   => $type,
				'count' => $counts[ $type ],
			);
		}

		return $out;
	}
}
