<?php

namespace LWTV\Statistics\Build;

class Dead_Role {

	/**
	 * Statistics Array for DEAD by ROLE - Optimized with single query
	 *
	 * Generate array to parse content for death by character role
	 * using optimized single query instead of N+1 pattern
	 *
	 * @return array
	 */
	public function make() {
		try {
			$transient = 'dead_role_stats';
			$array     = lwtv_plugin()->get_transient( $transient );

			if ( false === $array ) {
				$array = $this->build_dead_role_optimized();

				// save array as transient for a reason.
				if ( ! empty( $array ) ) {
					lwtv_plugin()->set_transient( $transient, $array, DAY_IN_SECONDS );
				}
			}

			return $array;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'dead-role-error', 'Error building dead role statistics: ' . $e->getMessage() );
			return array(
				'regular'   => array(
					'count' => 0,
					'name'  => 'Regular',
					'url'   => home_url( '/characters/?fwp_char_roles=regular' ),
				),
				'guest'     => array(
					'count' => 0,
					'name'  => 'Guest',
					'url'   => home_url( '/characters/?fwp_char_roles=guest' ),
				),
				'recurring' => array(
					'count' => 0,
					'name'  => 'Recurring',
					'url'   => home_url( '/characters/?fwp_char_roles=recurring' ),
				),
			);
		}
	}

	/**
	 * Build dead role statistics using optimized single query
	 *
	 * @return array
	 */
	private function build_dead_role_optimized() {
		global $wpdb;

		try {
			// Single optimized query to get dead character role counts
			$queery = "SELECT
				COUNT(DISTINCT CASE WHEN JSON_EXTRACT(show_meta.meta_value, '$[*].type') LIKE '%regular%' THEN chars.ID END) as regular_count,
				COUNT(DISTINCT CASE WHEN JSON_EXTRACT(show_meta.meta_value, '$[*].type') LIKE '%guest%' THEN chars.ID END) as guest_count,
				COUNT(DISTINCT CASE WHEN JSON_EXTRACT(show_meta.meta_value, '$[*].type') LIKE '%recurring%' THEN chars.ID END) as recurring_count
			FROM {$wpdb->posts} chars
			INNER JOIN {$wpdb->term_relationships} dead_rel ON chars.ID = dead_rel.object_id
			INNER JOIN {$wpdb->term_taxonomy} dead_tax ON dead_rel.term_taxonomy_id = dead_tax.term_taxonomy_id
			INNER JOIN {$wpdb->terms} dead_term ON dead_tax.term_id = dead_term.term_id
			INNER JOIN {$wpdb->postmeta} show_meta ON chars.ID = show_meta.post_id AND show_meta.meta_key = 'lezchars_show_group'
			WHERE chars.post_type = 'post_type_characters'
			AND chars.post_status = 'publish'
			AND dead_tax.taxonomy = 'lez_cliches'
			AND dead_term.slug = 'dead'
			AND show_meta.meta_value IS NOT NULL
			AND show_meta.meta_value != ''";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- There's no need to prepare this query
			$result = $wpdb->get_row( $queery, ARRAY_A );

			$regular_count   = (int) ( $result['regular_count'] ?? 0 );
			$guest_count     = (int) ( $result['guest_count'] ?? 0 );
			$recurring_count = (int) ( $result['recurring_count'] ?? 0 );

			return array(
				'regular'   => array(
					'count' => $regular_count,
					'name'  => 'Regular',
					'url'   => home_url( '/characters/?fwp_char_roles=regular' ),
				),
				'guest'     => array(
					'count' => $guest_count,
					'name'  => 'Guest',
					'url'   => home_url( '/characters/?fwp_char_roles=guest' ),
				),
				'recurring' => array(
					'count' => $recurring_count,
					'name'  => 'Recurring',
					'url'   => home_url( '/characters/?fwp_char_roles=recurring' ),
				),
			);

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'dead-role-error', 'Error building dead role statistics: ' . $e->getMessage() );
			return array(
				'regular'   => array(
					'count' => 0,
					'name'  => 'Regular',
					'url'   => home_url( '/characters/?fwp_char_roles=regular' ),
				),
				'guest'     => array(
					'count' => 0,
					'name'  => 'Guest',
					'url'   => home_url( '/characters/?fwp_char_roles=guest' ),
				),
				'recurring' => array(
					'count' => 0,
					'name'  => 'Recurring',
					'url'   => home_url( '/characters/?fwp_char_roles=recurring' ),
				),
			);
		}
	}
}
