<?php

namespace LWTV\Statistics\Build;

class Actor_Char_Role {

	/**
	 * Stats for character roles per actor - Optimized with single query
	 *
	 * @param string $type   Post Type
	 * @param string $the_id Post ID
	 *
	 * @return array
	 */
	public function make( $type, $the_id ) {
		$post_type = 'post_type_' . $type;
		if ( 'post_type_actors' === $post_type ) {
			return array();
		}

		$transient = 'actor_char_role_' . $the_id;
		$array     = lwtv_plugin()->get_transient( $transient );

		if ( false === $array || empty( $array ) ) {
			$array = $this->build_actor_char_role_optimized( $the_id );

			// save array as transient for a reason.
			if ( ! empty( $array ) ) {
				lwtv_plugin()->set_transient( $transient, $array, DAY_IN_SECONDS );
			}
		}

		return $array;
	}

	/**
	 * Build actor character role statistics using optimized single query
	 *
	 * @param string $actor_id Actor post ID
	 * @return array
	 */
	private function build_actor_char_role_optimized( $actor_id ) {
		global $wpdb;

		try {
			// Single optimized query to get role counts for actor
			// phpcs:disable
			$queery = $wpdb->prepare(
				"SELECT
					COUNT(DISTINCT CASE WHEN JSON_EXTRACT(show_meta.meta_value, '$[*].type') LIKE '%regular%' THEN chars.ID END) as regular_count,
					COUNT(DISTINCT CASE WHEN JSON_EXTRACT(show_meta.meta_value, '$[*].type') LIKE '%recurring%' THEN chars.ID END) as recurring_count,
					COUNT(DISTINCT CASE WHEN JSON_EXTRACT(show_meta.meta_value, '$[*].type') LIKE '%guest%' THEN chars.ID END) as guest_count
				FROM {$wpdb->posts} chars
				INNER JOIN {$wpdb->postmeta} actor_meta ON chars.ID = actor_meta.post_id AND actor_meta.meta_key = 'lezchars_actor'
				INNER JOIN {$wpdb->postmeta} show_meta ON chars.ID = show_meta.post_id AND show_meta.meta_key = 'lezchars_show_group'
				WHERE chars.post_type = 'post_type_characters'
				AND chars.post_status = 'publish'
				AND actor_meta.meta_value = %s
				AND show_meta.meta_value IS NOT NULL
				AND show_meta.meta_value != ''",
				$actor_id
			);
			// phpcs:enable

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$result = $wpdb->get_row( $queery, ARRAY_A );

			$regular_count   = (int) ( $result['regular_count'] ?? 0 );
			$recurring_count = (int) ( $result['recurring_count'] ?? 0 );
			$guest_count     = (int) ( $result['guest_count'] ?? 0 );

			return array(
				'regular'   => array(
					'count' => $regular_count,
					'name'  => 'regular',
					'url'   => '',
				),
				'recurring' => array(
					'count' => $recurring_count,
					'name'  => 'recurring',
					'url'   => '',
				),
				'guest'     => array(
					'count' => $guest_count,
					'name'  => 'guest',
					'url'   => '',
				),
			);

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'actor-char-role-error', 'Error building actor character role statistics: ' . $e->getMessage() );
			return array(
				'regular'   => array(
					'count' => 0,
					'name'  => 'regular',
					'url'   => '',
				),
				'recurring' => array(
					'count' => 0,
					'name'  => 'recurring',
					'url'   => '',
				),
				'guest'     => array(
					'count' => 0,
					'name'  => 'guest',
					'url'   => '',
				),
			);
		}
	}
}
