<?php
/**
 * Name: Trigger Warning
 * Description: The single canonical mapping from a lez_triggers slug (or its
 * legacy alias) to a normalized level.
 *
 * @package LWTV
 */

namespace LWTV\CPTs\Shows\Scoring;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trigger_Warning {

	/**
	 * Legacy alias => canonical slug.
	 *
	 * 'on' and 'medium' are older spellings of 'high' and 'med' that still
	 * exist in stored data.
	 */
	const ALIASES = array(
		'on'     => 'high',
		'medium' => 'med',
	);

	/** Canonical levels a normalized value can resolve to. */
	const LEVELS = array( 'high', 'med', 'low' );

	/**
	 * Normalize a trigger-warning slug to a canonical level.
	 *
	 * Matching is deliberately exact-case (no strtolower/trim) because the
	 * show_score() code this replaced did an exact-case key_exists() lookup
	 * against a lowercase-only table; case-folding here would change stored
	 * scores for any show with legacy mixed-case trigger meta.
	 *
	 * @param string $slug Raw slug or meta value, e.g. 'on', 'medium', 'low'.
	 * @return string One of 'high', 'med', 'low', or 'none'.
	 */
	public static function normalize( string $slug ): string {
		$slug = self::ALIASES[ $slug ] ?? $slug;

		return in_array( $slug, self::LEVELS, true ) ? $slug : 'none';
	}
}
