<?php
/**
 * Splits term findings into the ones worth working and the ones worth retiring.
 *
 * A broken provider URL on a term no published show reaches is not the same
 * finding as a broken URL a reader can hit, and it does not have the same fix.
 * Re-checking the URL cannot help: nothing is pointing at it either way. The
 * fix is to decide whether the term should still exist.
 *
 * @package LWTV
 */

namespace LWTV\Debugger\Format;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Triage {

	/**
	 * Split display rows on whether any published show reaches the term.
	 *
	 * Order within each group is the order it came in, so a caller that has
	 * already sorted by severity keeps that sort in both halves.
	 *
	 * @param  array $rows Display rows from Rows::from_term_findings().
	 * @return array{affecting: array<int, array<string, mixed>>, unused: array<int, array<string, mixed>>}
	 */
	public static function by_impact( array $rows ): array {
		$affecting = array();
		$unused    = array();

		foreach ( $rows as $row ) {
			if ( self::is_unused( (array) $row ) ) {
				$unused[] = $row;
				continue;
			}

			$affecting[] = $row;
		}

		return array(
			'affecting' => $affecting,
			'unused'    => $unused,
		);
	}

	/**
	 * Does no published show reach this term?
	 *
	 * Anything that is not a readable number is treated as unknown, and unknown
	 * is not zero: a finding cached before the count existed, or one carrying
	 * junk, is something to look at rather than something to file away.
	 *
	 * @param  array $row One display row.
	 * @return bool
	 */
	public static function is_unused( array $row ): bool {
		$shows = $row['shows'] ?? null;

		if ( ! is_numeric( $shows ) ) {
			return false;
		}

		return (int) $shows <= 0;
	}
}
