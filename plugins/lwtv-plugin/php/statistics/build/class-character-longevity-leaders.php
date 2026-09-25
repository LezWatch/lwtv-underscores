<?php
/**
 * Longest-Running Characters Query Class
 *
 * Ranks published characters by distinct credited `appears` years (not the
 * first-to-last span); min/max are returned for display.
 * See docs/statistics/data-model.md#anchoring-characters-to-a-year.
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
	 * Returns the top $limit characters by distinct on-screen years
	 * (defaults to TOP_LIMIT) — count() of the unique years credited, not
	 * (latest - earliest). Ties on count are broken by the most recent
	 * latest-year first (a character still active, or active more
	 * recently, ranks above one whose run ended longer ago). Keyed by
	 * character ID, ordered most distinct years first.
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
	 * Build the leaderboard by finding each character's set of distinct
	 * on-screen years.
	 *
	 * One query for every `appears` row, then a per-character year set in
	 * PHP (a year credited via two shows counts once).
	 * See docs/statistics/data-model.md#repeaters.
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

			$id = (int) $row['id'];

			if ( ! isset( $spans[ $id ] ) ) {
				$spans[ $id ] = array(
					'name'      => $row['name'],
					'post_date' => $row['post_date'],
					'years'     => array(),
				);
			}

			// Keyed by year so a character with several show-group rows
			// (recurring across multiple shows, or multiple stints on the
			// same one) doesn't get a year counted twice just because two
			// rows both mention it.
			foreach ( $years as $year ) {
				$spans[ $id ]['years'][ $year ] = true;
			}
		}

		if ( empty( $spans ) ) {
			return array();
		}

		$counted = array();
		foreach ( $spans as $id => $span ) {
			$distinct_years = array_keys( $span['years'] );
			if ( empty( $distinct_years ) ) {
				continue;
			}

			$counted[] = array(
				'id'        => $id,
				'name'      => $span['name'],
				// Distinct years actually credited, not (max - min + 1) —
				// a character with gaps in the middle of a long run
				// shouldn't get credit for years they weren't on screen
				// just because they bookend a wide span.
				'count'     => count( $distinct_years ),
				'min'       => min( $distinct_years ),
				'max'       => max( $distinct_years ),
				'post_date' => $span['post_date'],
			);
		}

		if ( empty( $counted ) ) {
			return array();
		}

		// Ties on distinct-year count broken by the more recent latest-year
		// first (active more recently ranks above a run that ended longer
		// ago), then most-recently-added character — same final tie-break
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
