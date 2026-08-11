<?php
/**
 * Longest-Running Characters Query Class
 *
 * Ranks published characters by the span between their earliest and latest
 * on-screen year, using the years recorded in the lezchars_show_group
 * repeater's `appears` sub-field — no join against show airdates needed,
 * the character's own appearance years are already tracked directly.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Character_Longevity_Leaders {

	/**
	 * Hard limit on how many characters to show by default.
	 *
	 * @var int
	 */
	const TOP_LIMIT = 25;

	/**
	 * Generate the longest-running-characters leaderboard.
	 *
	 * Returns the top $limit characters by on-screen year span (defaults to
	 * TOP_LIMIT) — latest appearance year minus earliest, inclusive. Ties
	 * on span are broken by the most recent latest-year first (a character
	 * still active, or active more recently, ranks above one whose run
	 * ended longer ago). Keyed by character ID, ordered widest span first.
	 *
	 * @param int $limit How many characters to return.
	 * @return array [ int $char_id => [ 'name' => string, 'count' => int, 'url' => string, 'min' => int, 'max' => int ] ]
	 */
	public function generate( int $limit = self::TOP_LIMIT ) {
		$limit     = max( 1, $limit );
		$transient = 'character_longevity_leaders_top' . $limit;
		$array     = lwtv_plugin()->get_transient( $transient );

		if ( false === $array ) {
			$array = $this->build_leaders_data( $limit );

			// Cache for 7 days since character data is relatively stable —
			// same cadence Cliche_Leaders uses.
			if ( ! empty( $array ) ) {
				lwtv_plugin()->set_transient( $transient, $array, WEEK_IN_SECONDS );
			}
		}

		return $array;
	}

	/**
	 * Build the leaderboard by finding each character's earliest/latest
	 * appearance year.
	 *
	 * The `appears` sub-field is a multi-value select, one serialized array
	 * per lezchars_show_group row (unlike that repeater's `show` sub-field,
	 * which is one scalar ID per row) — there's no per-year meta row to
	 * COUNT/MIN/MAX in SQL directly. This pulls every (post_id, meta_value)
	 * row for that sub-field across all published characters in one query,
	 * then folds each row's unserialized year list into a running min/max
	 * per character in PHP — one query total, not a get_field() call per
	 * character, same shape Character_Actor_Leaders uses for lezchars_actor.
	 *
	 * @param int $limit How many characters to return.
	 * @return array Character leaderboard data.
	 */
	public function build_leaders_data( int $limit = self::TOP_LIMIT ) {
		global $wpdb;

		// No user input: post_type/meta_key pattern are hardcoded literals.
		// phpcs:disable
		$query = "SELECT chars.ID as id, chars.post_title as name, chars.post_date as post_date, appears.meta_value as years
			FROM {$wpdb->posts} chars
			INNER JOIN {$wpdb->postmeta} appears ON appears.post_id = chars.ID
				AND appears.meta_key LIKE 'lezchars_show_group_%_appears'
				AND appears.meta_value != ''
			WHERE chars.post_type = 'post_type_characters'
			AND chars.post_status = 'publish'";
		// phpcs:enable

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input; all values are hardcoded literals.
		$results = $wpdb->get_results( $query, ARRAY_A );

		if ( empty( $results ) ) {
			lwtv_plugin()->debug_log( 'statistics', 'Character longevity leaders query returned no results: ' . $wpdb->last_error );
			return array();
		}

		$spans = array();
		foreach ( $results as $row ) {
			$years = maybe_unserialize( $row['years'] );
			// A single-selection "appears" row can come back as a plain
			// scalar rather than a one-item array — normalize both shapes
			// the same defensive way Character_Actor_Leaders normalizes
			// lezchars_actor.
			if ( is_array( $years ) ) {
				$years = array_values( array_filter( array_map( 'intval', $years ) ) );
			} elseif ( is_numeric( $years ) ) {
				$years = array( (int) $years );
			} else {
				$years = array();
			}

			if ( empty( $years ) ) {
				continue;
			}

			$id      = (int) $row['id'];
			$row_min = min( $years );
			$row_max = max( $years );

			if ( ! isset( $spans[ $id ] ) ) {
				$spans[ $id ] = array(
					'name'      => $row['name'],
					'post_date' => $row['post_date'],
					'min'       => $row_min,
					'max'       => $row_max,
				);
			} else {
				$spans[ $id ]['min'] = min( $spans[ $id ]['min'], $row_min );
				$spans[ $id ]['max'] = max( $spans[ $id ]['max'], $row_max );
			}
		}

		if ( empty( $spans ) ) {
			return array();
		}

		$counted = array();
		foreach ( $spans as $id => $span ) {
			$years_active = ( $span['max'] - $span['min'] ) + 1;
			if ( $years_active < 1 ) {
				continue; // Malformed range (max < min) — skip rather than show a negative span.
			}

			$counted[] = array(
				'id'        => $id,
				'name'      => $span['name'],
				'count'     => $years_active,
				'min'       => $span['min'],
				'max'       => $span['max'],
				'post_date' => $span['post_date'],
			);
		}

		if ( empty( $counted ) ) {
			return array();
		}

		// Ties on span broken by the more recent latest-year first (active
		// more recently ranks above a run that ended longer ago), then
		// most-recently-added character — same final tie-break
		// Cliche_Leaders/Character_Actor_Leaders use.
		usort(
			$counted,
			static function ( $a, $b ) {
				if ( $a['count'] !== $b['count'] ) {
					return $b['count'] <=> $a['count'];
				}
				if ( $a['max'] !== $b['max'] ) {
					return $b['max'] <=> $a['max'];
				}
				return strcmp( $b['post_date'], $a['post_date'] );
			}
		);

		$counted = array_slice( $counted, 0, max( 1, $limit ) );

		// Name and year-range are kept separate rather than fused into one
		// string — callers that want a combined display (e.g. "50 yrs
		// (1977–2026)") build that themselves from count/min/max, so the
		// plain name is still usable on its own (link text, spotlight cards).
		$leaders = array();
		foreach ( $counted as $row ) {
			$leaders[ $row['id'] ] = array(
				'name'  => $row['name'],
				'count' => $row['count'],
				'url'   => get_permalink( $row['id'] ),
				'min'   => $row['min'],
				'max'   => $row['max'],
			);
		}

		return $leaders;
	}
}
