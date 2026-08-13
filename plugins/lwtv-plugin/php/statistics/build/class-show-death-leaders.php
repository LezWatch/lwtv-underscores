<?php
/**
 * Show Death Leaders Query Class
 *
 * Ranks published shows by their canonical lezshows_char_count /
 * lezshows_dead_count postmeta — the same two fields
 * Dead::generate_shows_by_characters() already reads for the Deaths → Shows
 * donut, just queried across every show rather than only the ones tagged
 * with the 'dead-queers' trope. That trope is a curator's manual tag, not a
 * live count, so a show can have recorded deaths without ever being tagged
 * with it — reading the raw meta directly here means a highlight like "Most
 * Lethal Show" can't be silently wrong just because a tag was missed.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Show_Death_Leaders {

	/**
	 * A death rate only counts toward "Highest Death Rate" once a show's
	 * cast is at least this large — otherwise a one-character show that
	 * kills its only character reads as a meaningless "100% lethal".
	 *
	 * @var int
	 */
	const MIN_CAST_FOR_RATE = 5;

	/**
	 * Generate the show-death summary: totals + the two leaderboard picks.
	 *
	 * @return array {
	 *     @type int        $total_shows      All published shows.
	 *     @type int        $shows_with_death Shows with a recorded death.
	 *     @type array|null $most_lethal      { 'id', 'name', 'count', 'char_count', 'url' } or null.
	 *     @type array|null $highest_rate     { 'id', 'name', 'count', 'char_count', 'pct', 'url' } or null.
	 * }
	 */
	public function generate(): array {
		$transient = 'show_death_leaders';
		$data      = lwtv_plugin()->get_transient( $transient );

		if ( false === $data || ! is_array( $data ) ) {
			$data = $this->build();

			if ( ! empty( $data ) ) {
				lwtv_plugin()->set_transient( $transient, $data, DAY_IN_SECONDS );
			}
		}

		return $data;
	}

	/**
	 * Query every published show's char/dead counts and fold them into the
	 * summary shape generate() returns.
	 *
	 * @return array See generate()'s return shape.
	 */
	private function build(): array {
		global $wpdb;

		// No user input: post_type/meta_key values are hardcoded literals.
		// phpcs:disable
		$query = "SELECT
				p.ID as id,
				p.post_title as name,
				COALESCE(cc.meta_value, 0) as char_count,
				COALESCE(dc.meta_value, 0) as dead_count
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} cc ON cc.post_id = p.ID AND cc.meta_key = 'lezshows_char_count'
			LEFT JOIN {$wpdb->postmeta} dc ON dc.post_id = p.ID AND dc.meta_key = 'lezshows_dead_count'
			WHERE p.post_type = 'post_type_shows'
			AND p.post_status = 'publish'";
		// phpcs:enable

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input; all values are hardcoded literals.
		$results = $wpdb->get_results( $query, ARRAY_A );

		if ( ! is_array( $results ) ) {
			lwtv_plugin()->debug_log( 'statistics', 'Show death leaders query failed: ' . $wpdb->last_error );
			return array();
		}

		$shows_with_death = 0;
		$most_lethal      = null;
		$highest_rate     = null;

		foreach ( $results as $row ) {
			$char_count = (int) $row['char_count'];
			$dead_count = (int) $row['dead_count'];

			if ( $dead_count <= 0 ) {
				continue;
			}

			++$shows_with_death;

			$id   = (int) $row['id'];
			$name = (string) $row['name'];

			// Most Lethal Show: raw body count wins; ties go to the larger cast.
			if ( null === $most_lethal
				|| $dead_count > $most_lethal['count']
				|| ( $dead_count === $most_lethal['count'] && $char_count > $most_lethal['char_count'] )
			) {
				$most_lethal = array(
					'id'         => $id,
					'name'       => $name,
					'count'      => $dead_count,
					'char_count' => $char_count,
					'url'        => get_permalink( $id ),
				);
			}

			// Highest Death Rate: only shows with a real cast qualify.
			if ( $char_count >= self::MIN_CAST_FOR_RATE ) {
				$pct = round( ( $dead_count / $char_count ) * 100, 1 );

				if ( null === $highest_rate
					|| $pct > $highest_rate['pct']
					|| ( $pct === $highest_rate['pct'] && $char_count > $highest_rate['char_count'] )
				) {
					$highest_rate = array(
						'id'         => $id,
						'name'       => $name,
						'count'      => $dead_count,
						'char_count' => $char_count,
						'pct'        => $pct,
						'url'        => get_permalink( $id ),
					);
				}
			}
		}

		return array(
			'total_shows'      => (int) lwtv_plugin()->generate_total_counts( 'shows' ),
			'shows_with_death' => $shows_with_death,
			'most_lethal'      => $most_lethal,
			'highest_rate'     => $highest_rate,
		);
	}
}
