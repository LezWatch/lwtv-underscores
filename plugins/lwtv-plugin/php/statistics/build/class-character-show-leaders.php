<?php
/**
 * Most-Crossover Characters Query Class
 *
 * Ranks published characters by how many distinct shows they've appeared in.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Character_Show_Leaders {

	/**
	 * Hard limit on how many characters to show by default.
	 *
	 * @var int
	 */
	const TOP_LIMIT = 25;

	/**
	 * Generate the most-crossover-characters leaderboard.
	 *
	 * Returns the top $limit characters by distinct-show count (defaults to
	 * TOP_LIMIT). Ties on count are broken by most-recently-added character
	 * first. Keyed by character ID, ordered highest count first.
	 *
	 * @param int $limit How many characters to return.
	 * @return array [ int $char_id => [ 'name' => string, 'count' => int, 'url' => string ] ]
	 */
	public function generate( int $limit = self::TOP_LIMIT ) {
		$limit     = max( 1, $limit );
		$transient = 'character_show_leaders_top' . $limit;
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
	 * Build the leaderboard by counting distinct shows per character.
	 *
	 * lezchars_show_group is an ACF repeater; each row's `show` sub-field is
	 * stored as its own postmeta row (lezchars_show_group_{n}_show), not a
	 * single serialized value under lezchars_show_group — the same
	 * sub-field-key join Taxonomy_Optimized::get_bulk_character_counts()
	 * already relies on. A character can carry two rows pointing at the
	 * same show (e.g. guest-then-regular across separate stints), so this
	 * counts DISTINCT show IDs, not raw rows — "how many different shows",
	 * not "how many appearance stints".
	 *
	 * @param int $limit How many characters to return.
	 * @return array Character leaderboard data.
	 */
	public function build_leaders_data( int $limit = self::TOP_LIMIT ) {
		global $wpdb;

		// No user input: post_type/meta_key pattern are hardcoded literals
		// and the limit is cast to int.
		// phpcs:disable
		$query = "SELECT chars.ID as id, chars.post_title as name, COUNT(DISTINCT char_shows.meta_value) as show_count
			FROM {$wpdb->posts} chars
			INNER JOIN {$wpdb->postmeta} char_shows ON char_shows.post_id = chars.ID
				AND char_shows.meta_key LIKE 'lezchars_show_group_%_show'
			WHERE chars.post_type = 'post_type_characters'
			AND chars.post_status = 'publish'
			GROUP BY chars.ID
			ORDER BY show_count DESC, chars.post_date DESC
			LIMIT " . (int) $limit;
		// phpcs:enable

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input; all values are hardcoded literals or cast to int.
		$results = $wpdb->get_results( $query, ARRAY_A );

		if ( empty( $results ) ) {
			lwtv_plugin()->debug_log( 'statistics', 'Character show leaders query returned no results: ' . $wpdb->last_error );
			return array();
		}

		$leaders = array();
		foreach ( $results as $row ) {
			$id             = (int) $row['id'];
			$leaders[ $id ] = array(
				'name'  => $row['name'],
				'count' => (int) $row['show_count'],
				'url'   => get_permalink( $id ),
			);
		}

		return $leaders;
	}
}
