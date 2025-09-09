<?php

namespace LWTV\Statistics\Build;

class Show_Roles {

	/**
	 * Statistics Roles on Shows - Optimized with single query
	 *
	 * @param string $type (default: 'dead')
	 * @return array
	 */
	public function make( $type = 'dead' ) {
		try {
			$transient = 'show_roles_' . $type;
			$array     = lwtv_plugin()->get_transient( $transient );

			if ( false === $array ) {
				$array = $this->build_show_roles_optimized( $type );

				// save array as transient.
				if ( ! empty( $array ) ) {
					lwtv_plugin()->set_transient( $transient, $array, DAY_IN_SECONDS );
				}
			}

			return $array;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'show-roles-error', 'Error building show roles statistics: ' . $e->getMessage() );
			return array(
				'guest'     => array(
					'name'  => 'Only Guests',
					'count' => 0,
					'url'   => home_url( '/characters/?fwp_char_roles=guest' ),
				),
				'main'      => array(
					'name'  => 'Only Main',
					'count' => 0,
					'url'   => home_url( '/characters/?fwp_char_roles=regular' ),
				),
				'recurring' => array(
					'name'  => 'Only Recurring',
					'count' => 0,
					'url'   => home_url( '/characters/?fwp_char_roles=recurring' ),
				),
			);
		}
	}

	/**
	 * Build show roles statistics using optimized single query
	 *
	 * @param string $type Type of statistics (dead/alive)
	 * @return array
	 */
	private function build_show_roles_optimized( $type ) {
		global $wpdb;

		try {
			// Single optimized query to get all show role statistics
			// phpcs:disable
			$queery = $wpdb->prepare(
				"SELECT
					shows.ID as show_id,
					shows.post_title,
					shows.post_name,
					shows.post_status,
					COUNT(DISTINCT CASE WHEN JSON_EXTRACT(show_meta.meta_value, '$[*].type') LIKE '%guest%' AND dead_term.term_id IS NULL THEN chars.ID END) as guest_alive,
					COUNT(DISTINCT CASE WHEN JSON_EXTRACT(show_meta.meta_value, '$[*].type') LIKE '%guest%' AND dead_term.term_id IS NOT NULL THEN chars.ID END) as guest_dead,
					COUNT(DISTINCT CASE WHEN JSON_EXTRACT(show_meta.meta_value, '$[*].type') LIKE '%regular%' AND dead_term.term_id IS NULL THEN chars.ID END) as regular_alive,
					COUNT(DISTINCT CASE WHEN JSON_EXTRACT(show_meta.meta_value, '$[*].type') LIKE '%regular%' AND dead_term.term_id IS NOT NULL THEN chars.ID END) as regular_dead,
					COUNT(DISTINCT CASE WHEN JSON_EXTRACT(show_meta.meta_value, '$[*].type') LIKE '%recurring%' AND dead_term.term_id IS NULL THEN chars.ID END) as recurring_alive,
					COUNT(DISTINCT CASE WHEN JSON_EXTRACT(show_meta.meta_value, '$[*].type') LIKE '%recurring%' AND dead_term.term_id IS NOT NULL THEN chars.ID END) as recurring_dead
				FROM {$wpdb->posts} shows
				INNER JOIN {$wpdb->posts} chars ON chars.post_type = 'post_type_characters' AND chars.post_status = 'publish'
				INNER JOIN {$wpdb->postmeta} show_meta ON chars.ID = show_meta.post_id AND show_meta.meta_key = 'lezchars_show_group'
				LEFT JOIN {$wpdb->term_relationships} dead_rel ON chars.ID = dead_rel.object_id
				LEFT JOIN {$wpdb->term_taxonomy} dead_tax ON dead_rel.term_taxonomy_id = dead_tax.term_taxonomy_id AND dead_tax.taxonomy = 'lez_cliches'
				LEFT JOIN {$wpdb->terms} dead_term ON dead_tax.term_id = dead_term.term_id AND dead_term.slug = 'dead'
				WHERE shows.post_type = 'post_type_shows'
				AND shows.post_status = 'publish'
				AND show_meta.meta_value IS NOT NULL
				AND show_meta.meta_value != ''
				AND JSON_EXTRACT(show_meta.meta_value, '$[*].show') LIKE CONCAT('%', shows.ID, '%')
				GROUP BY shows.ID, shows.post_title, shows.post_name, shows.post_status",
				$type
			);
			// phpcs:enable

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$results = $wpdb->get_results( $queery, ARRAY_A );

			// Process results into role categories
			$guest_alive_array     = array();
			$recurring_alive_array = array();
			$main_alive_array      = array();
			$guest_dead_array      = array();
			$recurring_dead_array  = array();
			$main_dead_array       = array();

			foreach ( $results as $row ) {
				$show_id   = (int) $row['show_id'];
				$show_name = preg_replace( '/\s*/', '', $row['post_title'] );
				$show_name = strtolower( $show_name );
				$show_data = array(
					'url'    => home_url( '/' . $row['post_name'] . '/' ),
					'name'   => $row['post_title'],
					'status' => $row['post_status'],
				);

				$guest_alive     = (int) $row['guest_alive'];
				$guest_dead      = (int) $row['guest_dead'];
				$regular_alive   = (int) $row['regular_alive'];
				$regular_dead    = (int) $row['regular_dead'];
				$recurring_alive = (int) $row['recurring_alive'];
				$recurring_dead  = (int) $row['recurring_dead'];

				// Categorize shows based on role patterns
				if ( 0 === $regular_alive && 0 !== $recurring_alive && 0 === $guest_alive ) {
					$recurring_alive_array[ $show_name ] = $show_data;
				}
				if ( 0 === $regular_alive && 0 === $recurring_alive && 0 !== $guest_alive ) {
					$guest_alive_array[ $show_name ] = $show_data;
				}
				if ( 0 !== $regular_alive && 0 === $guest_alive && 0 === $recurring_alive ) {
					$main_alive_array[ $show_name ] = $show_data;
				}

				if ( 0 === $regular_dead && 0 !== $recurring_dead && 0 === $guest_dead ) {
					$recurring_dead_array[ $show_name ] = $show_data;
				}
				if ( 0 === $regular_dead && 0 === $recurring_dead && 0 !== $guest_dead ) {
					$guest_dead_array[ $show_name ] = $show_data;
				}
				if ( 0 !== $regular_dead && 0 === $guest_dead && 0 === $recurring_dead ) {
					$main_dead_array[ $show_name ] = $show_data;
				}
			}

			// Build final arrays
			$alive_array = array(
				'guest'     => array(
					'name'  => 'Only Guests',
					'count' => count( $guest_alive_array ),
					'url'   => home_url( '/characters/?fwp_char_roles=guest' ),
				),
				'main'      => array(
					'name'  => 'Only Main',
					'count' => count( $main_alive_array ),
					'url'   => home_url( '/characters/?fwp_char_roles=regular' ),
				),
				'recurring' => array(
					'name'  => 'Only Recurring',
					'count' => count( $recurring_alive_array ),
					'url'   => home_url( '/characters/?fwp_char_roles=recurring' ),
				),
			);

			$dead_array = array(
				'guest'     => array(
					'name'  => 'Only Guests',
					'count' => count( $guest_dead_array ),
					'url'   => home_url( '/characters/?fwp_char_roles=guest' ),
				),
				'main'      => array(
					'name'  => 'Only Main',
					'count' => count( $main_dead_array ),
					'url'   => home_url( '/characters/?fwp_char_roles=regular' ),
				),
				'recurring' => array(
					'name'  => 'Only Recurring',
					'count' => count( $recurring_dead_array ),
					'url'   => home_url( '/characters/?fwp_char_roles=recurring' ),
				),
			);

			return 'dead' === $type ? $dead_array : $alive_array;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'show-roles-error', 'Error building show roles statistics: ' . $e->getMessage() );
			return array(
				'guest'     => array(
					'name'  => 'Only Guests',
					'count' => 0,
					'url'   => home_url( '/characters/?fwp_char_roles=guest' ),
				),
				'main'      => array(
					'name'  => 'Only Main',
					'count' => 0,
					'url'   => home_url( '/characters/?fwp_char_roles=regular' ),
				),
				'recurring' => array(
					'name'  => 'Only Recurring',
					'count' => 0,
					'url'   => home_url( '/characters/?fwp_char_roles=recurring' ),
				),
			);
		}
	}
}
