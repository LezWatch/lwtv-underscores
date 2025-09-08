<?php

namespace LWTV\Statistics\Build;

class Dead_Shows {

	/*
	 * Statistics Death on Shows - Optimized with single query
	 *
	 * Death is insane. This is how to figure out who died on what show.
	 * We can use it to determine how many shows have ALL dead queers, etc.
	 * It's fucked up. I'm sorry.
	 *
	 * @param string $format The format of our output
	 *
	 * @return array
	 */
	public function make( $format ) {

		$transient = 'dead_shows_' . $format;
		$array     = lwtv_plugin()->get_transient( $transient );

		if ( false === $array ) {
			$array = $this->build_dead_shows_optimized( $format );

			// save array as transient for a reason.
			if ( ! empty( $array ) ) {
				lwtv_plugin()->set_transient( $transient, $array, DAY_IN_SECONDS );
			}
		}

		return $array;
	}

	/**
	 * Build dead shows statistics using optimized single query
	 *
	 * @param string $format Output format
	 * @return array
	 */
	private function build_dead_shows_optimized( $format ) {
		global $wpdb;

		// Single optimized query to get all show death data
		// phpcs:disable
		$queery = $wpdb->prepare(
			"SELECT
				p.ID,
				p.post_title,
				p.post_status,
				p.post_name,
				dead_count.meta_value as dead_count,
				char_count.meta_value as char_count,
				CASE
					WHEN dead_trope.term_taxonomy_id IS NOT NULL THEN 'has_dead'
					ELSE 'no_dead'
				END as death_status
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} dead_count ON p.ID = dead_count.post_id AND dead_count.meta_key = 'lezshows_dead_count'
			LEFT JOIN {$wpdb->postmeta} char_count ON p.ID = char_count.post_id AND char_count.meta_key = 'lezshows_char_count'
			LEFT JOIN {$wpdb->term_relationships} dead_trope ON p.ID = dead_trope.object_id
			LEFT JOIN {$wpdb->term_taxonomy} dead_tax ON dead_trope.term_taxonomy_id = dead_tax.term_taxonomy_id AND dead_tax.taxonomy = 'lez_tropes'
			LEFT JOIN {$wpdb->terms} dead_term ON dead_tax.term_id = dead_term.term_id AND dead_term.slug = 'dead-queers'
			WHERE p.post_type = 'post_type_shows'
			AND p.post_status = 'publish'"
		);
		// phpcs:enable

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
		$results = $wpdb->get_results( $queery, ARRAY_A );

		// Process results into categories
		$noneshow_death_array = array();
		$fullshow_death_array = array();
		$someshow_death_array = array();

		foreach ( $results as $row ) {
			$show_id    = (int) $row['ID'];
			$show_name  = preg_replace( '/\s*/', '', $row['post_title'] );
			$show_name  = strtolower( $show_name );
			$dead_count = (int) $row['dead_count'];
			$char_count = (int) $row['char_count'];
			$has_dead   = 'has_dead' === $row['death_status'];

			$show_data = array(
				'url'    => get_permalink( $show_id ),
				'name'   => $row['post_title'],
				'status' => $row['post_status'],
			);

			if ( ! $has_dead ) {
				// Shows with no deaths
				$noneshow_death_array[ $show_name ] = $show_data;
			} elseif ( $dead_count === $char_count && $char_count > 0 ) {
				// Shows where all characters are dead
				$fullshow_death_array[ $show_name ] = $show_data;
			} else {
				// Shows where some characters are dead
				$someshow_death_array[ $show_name ] = $show_data;
			}
		}

		if ( 'simple' === $format ) {
			return array(
				'all'  => array(
					'name'  => 'All characters are dead',
					'count' => count( $fullshow_death_array ),
					'url'   => '',
				),
				'some' => array(
					'name'  => 'Some characters are dead',
					'count' => count( $someshow_death_array ),
					'url'   => '',
				),
				'none' => array(
					'name'  => 'No characters are dead',
					'count' => count( $noneshow_death_array ),
					'url'   => '',
				),
			);
		}

		return array(
			'full' => $fullshow_death_array,
			'some' => $someshow_death_array,
			'none' => $noneshow_death_array,
		);
	}
}
