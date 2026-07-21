<?php
/**
 * Most Clichéd Characters Query Class
 *
 * Ranks published characters by how many lez_cliches terms each one carries.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cliche_Leaders {

	/**
	 * Hard limit on how many characters to show.
	 *
	 * @var int
	 */
	const TOP_LIMIT = 25;

	/**
	 * Generate the most-clichéd-characters leaderboard.
	 *
	 * Returns the top TOP_LIMIT characters by cliché count. Ties on count are
	 * broken by most-recently-added character first. Keyed by character ID,
	 * ordered highest count first.
	 *
	 * @return array [ int $char_id => [ 'name' => string, 'count' => int, 'url' => string ] ]
	 */
	public function generate() {
		$transient = 'cliche_leaders_characters_top' . self::TOP_LIMIT;
		$array     = lwtv_plugin()->get_transient( $transient );

		if ( false === $array ) {
			$array = $this->build_leaders_data();

			// Cache for 7 days since character data is relatively stable.
			if ( ! empty( $array ) ) {
				lwtv_plugin()->set_transient( $transient, $array, WEEK_IN_SECONDS );
			}
		}

		return $array;
	}

	/**
	 * Build the leaderboard by counting lez_cliches terms per character.
	 *
	 * @return array Character leaderboard data.
	 */
	public function build_leaders_data() {
		global $wpdb;

		// Count how many lez_cliches terms each published character carries, then
		// take the top TOP_LIMIT. Ties on count are broken by most-recently-added
		// character first (post_date DESC). No user input: taxonomy and post_type
		// are hardcoded literals and the limit is an integer constant.
		// phpcs:disable
		$query = "SELECT chars.ID as id, chars.post_title as name, COUNT(tr.term_taxonomy_id) as cliche_count
			FROM {$wpdb->posts} chars
			INNER JOIN {$wpdb->term_relationships} tr ON chars.ID = tr.object_id
			INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			WHERE chars.post_type = 'post_type_characters'
			AND chars.post_status = 'publish'
			AND tt.taxonomy = 'lez_cliches'
			GROUP BY chars.ID
			ORDER BY cliche_count DESC, chars.post_date DESC
			LIMIT " . (int) self::TOP_LIMIT;
		// phpcs:enable

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input; all values are hardcoded literals or an integer constant.
		$results = $wpdb->get_results( $query, ARRAY_A );

		if ( empty( $results ) ) {
			lwtv_plugin()->debug_log( 'statistics', 'Cliché leaders query returned no results: ' . $wpdb->last_error );
			return array();
		}

		$leaders = array();
		foreach ( $results as $row ) {
			$id             = (int) $row['id'];
			$leaders[ $id ] = array(
				'name'  => $row['name'],
				'count' => (int) $row['cliche_count'],
				'url'   => get_permalink( $id ),
			);
		}

		return $leaders;
	}
}
