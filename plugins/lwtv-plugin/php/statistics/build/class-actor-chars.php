<?php

namespace LWTV\Statistics\Build;

class Actor_Chars {

	/**
	 * Statistics: Actors and Characters - Optimized with proper filtering
	 *
	 * @access public
	 * @static
	 * @param string $type (default: 'characters')
	 * @return void
	 */
	public function make( $type = 'characters' ) {

		if ( str_contains( $type, 'per-' ) ) {
			$type = ( 'per-char' === $type ) ? 'characters' : 'actors';
		}

		$transient = 'actor_chars_' . $type;
		$array     = lwtv_plugin()->get_transient( $transient );

		if ( false === $array ) {
			$array = $this->build_actor_chars_optimized( $type );

			// save array as transient for a reason.
			if ( ! empty( $array ) ) {
				lwtv_plugin()->set_transient( $transient, $array, DAY_IN_SECONDS );
			}
		}

		return $array;
	}

	/**
	 * Build actor-character statistics with proper filtering
	 *
	 * @param string $type Post type (characters or actors)
	 * @return array
	 */
	private function build_actor_chars_optimized( $type ) {
		global $wpdb;

		try {
			$results_array = array();

			if ( 'actors' === $type ) {
				// For actors: Only include actors who have characters
				// phpcs:disable
				$queery = "SELECT
					p.ID,
					COALESCE(char_count.meta_value, 0) as char_count
					FROM {$wpdb->posts} p
					LEFT JOIN {$wpdb->postmeta} char_count ON p.ID = char_count.post_id AND char_count.meta_key = 'lezactors_char_count'
					WHERE p.post_type = 'post_type_actors'
					AND p.post_status = 'publish'
					AND COALESCE(char_count.meta_value, 0) > 0
					ORDER BY CAST(char_count.meta_value AS UNSIGNED) DESC";
				// phpcs:enable

				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- There's no need to prepare this query
				$results = $wpdb->get_results( $queery, ARRAY_A );

				foreach ( $results as $row ) {
					$char_count = (int) $row['char_count'];

					if ( ! isset( $results_array[ $char_count ] ) ) {
						$results_array[ $char_count ] = array(
							'name'  => $char_count . ' characters',
							'count' => 0,
							'url'   => '',
						);
					}
					++$results_array[ $char_count ]['count'];
				}
			} else {
				// For characters: Include all characters (they can exist without actors)
				// Single optimized query to count actors per character
				// phpcs:disable
				$queery = "SELECT
					COUNT(DISTINCT actor_meta.meta_value) as actor_count,
					COUNT(*) as character_count
					FROM {$wpdb->posts} p
					LEFT JOIN {$wpdb->postmeta} actor_meta ON p.ID = actor_meta.post_id AND actor_meta.meta_key = 'lezchars_actor'
					WHERE p.post_type = 'post_type_characters'
					AND p.post_status = 'publish'
					GROUP BY p.ID
					ORDER BY actor_count DESC";
				// phpcs:enable

				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- There's no need to prepare this query
				$results = $wpdb->get_results( $queery, ARRAY_A );

				foreach ( $results as $row ) {
					$actor_count = (int) $row['actor_count'];

					if ( ! isset( $results_array[ $actor_count ] ) ) {
						$results_array[ $actor_count ] = array(
							'name'  => $actor_count . ' actors',
							'count' => 0,
							'url'   => '',
						);
					}
					++$results_array[ $actor_count ]['count'];
				}
			}

			ksort( $results_array );
			return $results_array;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'actor-chars-error', 'Error building actor-character statistics: ' . $e->getMessage() );
			return array();
		}
	}
}
