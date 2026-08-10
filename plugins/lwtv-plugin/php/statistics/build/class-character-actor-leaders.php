<?php
/**
 * Most-Recast Characters Query Class
 *
 * Ranks published characters by how many actors have played them.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Character_Actor_Leaders {

	/**
	 * Hard limit on how many characters to show by default.
	 *
	 * @var int
	 */
	const TOP_LIMIT = 25;

	/**
	 * Generate the most-recast-characters leaderboard.
	 *
	 * Returns the top $limit characters by actor/recast count (defaults to
	 * TOP_LIMIT). Ties on count are broken by most-recently-added character
	 * first. Keyed by character ID, ordered highest count first.
	 *
	 * @param int $limit How many characters to return.
	 * @return array [ int $char_id => [ 'name' => string, 'count' => int, 'url' => string ] ]
	 */
	public function generate( int $limit = self::TOP_LIMIT ) {
		$limit     = max( 1, $limit );
		$transient = 'character_actor_leaders_top' . $limit;
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
	 * Build the leaderboard by counting actors per character.
	 *
	 * lezchars_actor is an ACF relationship field stored as one serialized
	 * array per character — unlike lezchars_show_group's per-row sub-field
	 * keys, there's no per-actor meta row to COUNT(DISTINCT ...) against in
	 * SQL. This pulls one (post_id, meta_value) row per published character
	 * and counts each unserialized array in PHP — one query total, not a
	 * get_field() call per character — then sorts/slices to $limit here
	 * since the count isn't available to ORDER BY in the query itself.
	 *
	 * @param int $limit How many characters to return.
	 * @return array Character leaderboard data.
	 */
	public function build_leaders_data( int $limit = self::TOP_LIMIT ) {
		global $wpdb;

		// No user input: post_type/meta_key are hardcoded literals.
		// phpcs:disable
		$query = "SELECT chars.ID as id, chars.post_title as name, chars.post_date as post_date, pm.meta_value as actors
			FROM {$wpdb->posts} chars
			INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = chars.ID AND pm.meta_key = 'lezchars_actor'
			WHERE chars.post_type = 'post_type_characters'
			AND chars.post_status = 'publish'";
		// phpcs:enable

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input; all values are hardcoded literals.
		$results = $wpdb->get_results( $query, ARRAY_A );

		if ( empty( $results ) ) {
			lwtv_plugin()->debug_log( 'statistics', 'Character actor leaders query returned no results: ' . $wpdb->last_error );
			return array();
		}

		$counted = array();
		foreach ( $results as $row ) {
			$actors = maybe_unserialize( $row['actors'] );
			// Guard the same way the migration writer that cleaned this
			// field up does — filter to positive integer actor IDs only,
			// dropping stray zeros/non-numeric leftovers.
			$actors = is_array( $actors ) ? array_values( array_filter( array_map( 'absint', $actors ) ) ) : array();

			if ( empty( $actors ) ) {
				continue;
			}

			$counted[] = array(
				'id'        => (int) $row['id'],
				'name'      => $row['name'],
				'count'     => count( $actors ),
				'post_date' => $row['post_date'],
			);
		}

		if ( empty( $counted ) ) {
			return array();
		}

		// Ties on count broken by most-recently-added character first, same
		// tie-break Cliche_Leaders uses — done here in PHP since the count
		// wasn't available to ORDER BY in the query above. PHP 8+'s stable
		// sort keeps equal-post_date rows in their original (ID) order.
		usort(
			$counted,
			static function ( $a, $b ) {
				if ( $a['count'] !== $b['count'] ) {
					return $b['count'] <=> $a['count'];
				}
				return strcmp( $b['post_date'], $a['post_date'] );
			}
		);

		$counted = array_slice( $counted, 0, max( 1, $limit ) );

		$leaders = array();
		foreach ( $counted as $row ) {
			$leaders[ $row['id'] ] = array(
				'name'  => $row['name'],
				'count' => $row['count'],
				'url'   => get_permalink( $row['id'] ),
			);
		}

		return $leaders;
	}
}
