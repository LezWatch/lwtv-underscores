<?php
/**
 * Shows block view transforms for This Year.
 *
 * Pure array-in / array-out helpers that build the jump-bar model for the
 * Shows On Air / New Shows / Canceled Shows panes. No WordPress runtime
 * dependency — all i18n and WP calls stay in the template.
 *
 * @package LezWatch.TV
 */

namespace LWTV\This_Year\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shapes the jump-bar model for the shared Shows block.
 */
class Shows_Block {

	/**
	 * The uppercase Latin initial of a group key, or null.
	 *
	 * @param string $key Group key (a letter marker, a country, or a format).
	 * @return string|null One of A–Z, or null for numeric / punctuation / non-Latin leads.
	 */
	public static function initial_of( string $key ): ?string {
		$first = mb_strtoupper( mb_substr( trim( $key ), 0, 1 ) );
		return ( 1 === preg_match( '/^[A-Z]$/', $first ) ) ? $first : null;
	}

	/**
	 * Ordered jump-bar chips for a pane.
	 *
	 * `target` is the zero-based index of the group to anchor to — the same
	 * order the template iterates the groups — or null when the chip is inert.
	 * Do not slugify keys into ids: '#' and '-' both sanitise to empty and
	 * collide, so the template anchors on this index (`g<target>`) instead.
	 *
	 * @param array  $group_keys Ordered pane group keys, exactly as rendered.
	 * @param string $mode       'name' | 'country' | 'format'.
	 * @param array  $counts     [ key => int ] show counts; used in 'format' mode only.
	 * @return array Ordered list of [ label, target, struck, count ].
	 */
	public static function jump_bar( array $group_keys, string $mode, array $counts = array() ): array {
		$keys = array_values( $group_keys );

		// Format: one chip per group, in the given (size) order, carrying its count.
		if ( 'format' === $mode ) {
			$chips = array();
			foreach ( $keys as $i => $key ) {
				$chips[] = array(
					'label'  => (string) $key,
					'target' => $i,
					'struck' => false,
					'count'  => (int) ( $counts[ (string) $key ] ?? 0 ),
				);
			}
			return $chips;
		}

		// Name / Country: marker chips (name only) then A–Z. First group with a
		// given initial wins the letter, since keys are pre-sorted alphabetically.
		$marker_chips = array();
		$letter_index = array();
		foreach ( $keys as $i => $key ) {
			$initial = self::initial_of( (string) $key );
			if ( null === $initial ) {
				if ( 'name' === $mode ) {
					$marker_chips[] = array(
						'label'  => (string) $key,
						'target' => $i,
						'struck' => false,
						'count'  => null,
					);
				}
				continue;
			}
			if ( ! isset( $letter_index[ $initial ] ) ) {
				$letter_index[ $initial ] = $i;
			}
		}

		$az = array();
		foreach ( range( 'A', 'Z' ) as $letter ) {
			$present = isset( $letter_index[ $letter ] );
			$az[]    = array(
				'label'  => $letter,
				'target' => $present ? $letter_index[ $letter ] : null,
				'struck' => ! $present,
				'count'  => null,
			);
		}

		return array_merge( $marker_chips, $az );
	}
}
