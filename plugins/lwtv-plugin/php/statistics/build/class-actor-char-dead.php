<?php

namespace LWTV\Statistics\Build;

class Actor_Char_Dead {

	/**
	 * Stats for dead character per actor - Optimized with single query
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

		$transient = 'actor_char_dead_' . $the_id;
		$array     = lwtv_plugin()->get_transient( $transient );

		if ( false === $array || empty( $array ) ) {
			$array = $this->build_actor_char_dead_optimized( $the_id );

			// save array as transient for a reason.
			if ( ! empty( $array ) ) {
				lwtv_plugin()->set_transient( $transient, $array, DAY_IN_SECONDS );
			}
		}

		return $array;
	}

	/**
	 * Build actor character death statistics using optimized single query
	 *
	 * @param string $actor_id Actor post ID
	 * @return array
	 */
	private function build_actor_char_dead_optimized( $actor_id ) {
		global $wpdb;

		try {
			// Single optimized query to get dead/alive character counts for actor
			$queery = $wpdb->prepare(
				"SELECT
					COUNT(DISTINCT CASE WHEN dead_term.term_id IS NOT NULL THEN chars.ID END) as dead_count,
					COUNT(DISTINCT CASE WHEN dead_term.term_id IS NULL THEN chars.ID END) as alive_count
				FROM {$wpdb->posts} chars
				INNER JOIN {$wpdb->postmeta} actor_meta ON chars.ID = actor_meta.post_id AND actor_meta.meta_key = 'lezchars_actor'
				LEFT JOIN {$wpdb->term_relationships} dead_rel ON chars.ID = dead_rel.object_id
				LEFT JOIN {$wpdb->term_taxonomy} dead_tax ON dead_rel.term_taxonomy_id = dead_tax.term_taxonomy_id AND dead_tax.taxonomy = 'lez_cliches'
				LEFT JOIN {$wpdb->terms} dead_term ON dead_tax.term_id = dead_term.term_id AND dead_term.slug = 'dead'
				WHERE chars.post_type = 'post_type_characters'
				AND chars.post_status = 'publish'
				AND actor_meta.meta_value = %s",
				$actor_id
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$result = $wpdb->get_row( $queery, ARRAY_A );

			$dead_count  = (int) ( $result['dead_count'] ?? 0 );
			$alive_count = (int) ( $result['alive_count'] ?? 0 );

			return array(
				'alive' => array(
					'count' => $alive_count,
					'name'  => 'alive',
					'url'   => '',
				),
				'dead'  => array(
					'count' => $dead_count,
					'name'  => 'dead',
					'url'   => '',
				),
			);

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'actor-char-dead-error', 'Error building actor character death statistics: ' . $e->getMessage() );
			return array(
				'alive' => array(
					'count' => 0,
					'name'  => 'alive',
					'url'   => '',
				),
				'dead'  => array(
					'count' => 0,
					'name'  => 'dead',
					'url'   => '',
				),
			);
		}
	}
}
