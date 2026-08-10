<?php
/**
 * Most-Resurrected Characters Query Class
 *
 * Ranks published characters by how many recorded deaths they have — the
 * lezchars_death_year repeater tracks soap-opera-style fake-deaths, so a
 * character can carry more than one dated death row.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Character_Death_Leaders {

	/**
	 * Hard limit on how many characters to show by default.
	 *
	 * @var int
	 */
	const TOP_LIMIT = 25;

	/**
	 * Generate the most-resurrected-characters leaderboard.
	 *
	 * Returns the top $limit characters with 2+ recorded deaths (defaults
	 * to TOP_LIMIT), ordered highest count first. A character with exactly
	 * one death has died, not been resurrected — those never qualify, so
	 * this board can legitimately return fewer than $limit rows (or none)
	 * if the data doesn't have that many repeat-deaths on record.
	 *
	 * @param int $limit How many characters to return.
	 * @return array [ int $char_id => [ 'name' => string, 'count' => int, 'url' => string ] ]
	 */
	public function generate( int $limit = self::TOP_LIMIT ) {
		$limit     = max( 1, $limit );
		$transient = 'character_death_leaders_top' . $limit;
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
	 * Build the leaderboard by counting death-date rows per character.
	 *
	 * lezchars_death_year is an ACF repeater; each row's `date` sub-field is
	 * stored as its own postmeta row (lezchars_death_year_{n}_date) — the
	 * same key pattern Dead::get_death_date_rows() already scans. That
	 * class's own comment is the reason this joins wp_posts and restricts
	 * post_type/post_status there rather than scanning wp_postmeta bare:
	 * ACF copies death-date meta onto revision posts too, so an unrestricted
	 * scan would double- (or triple-, or...) count every revised character.
	 *
	 * @param int $limit How many characters to return.
	 * @return array Character leaderboard data.
	 */
	public function build_leaders_data( int $limit = self::TOP_LIMIT ) {
		global $wpdb;

		// No user input: post_type/meta_key pattern are hardcoded literals
		// and the limit is cast to int.
		// phpcs:disable
		$query = "SELECT chars.ID as id, chars.post_title as name, COUNT(*) as death_count
			FROM {$wpdb->posts} chars
			INNER JOIN {$wpdb->postmeta} deaths ON deaths.post_id = chars.ID
				AND deaths.meta_key LIKE 'lezchars_death_year_%_date'
				AND deaths.meta_value != ''
			WHERE chars.post_type = 'post_type_characters'
			AND chars.post_status = 'publish'
			GROUP BY chars.ID
			HAVING death_count > 1
			ORDER BY death_count DESC, chars.post_date DESC
			LIMIT " . (int) $limit;
		// phpcs:enable

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input; all values are hardcoded literals or cast to int.
		$results = $wpdb->get_results( $query, ARRAY_A );

		if ( empty( $results ) ) {
			lwtv_plugin()->debug_log( 'statistics', 'Character death leaders query returned no rows (none, or none with 2+ deaths): ' . $wpdb->last_error );
			return array();
		}

		$leaders = array();
		foreach ( $results as $row ) {
			$id             = (int) $row['id'];
			$leaders[ $id ] = array(
				'name'  => $row['name'],
				'count' => (int) $row['death_count'],
				'url'   => get_permalink( $id ),
			);
		}

		return $leaders;
	}
}
