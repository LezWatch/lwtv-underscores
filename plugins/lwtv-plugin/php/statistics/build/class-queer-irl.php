<?php
/**
 * Optimized Queer IRL Query Class
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

class Queer_IRL {
	/**
	 * Generate queer IRL statistics
	 *
	 * @param string $format Output format
	 * @return array Queer IRL statistics data
	 */
	public function generate( $format = 'array' ) {
		$all_data = $this->generate_all_data();
		switch ( $format ) {
			case 'count':
				return count( $all_data );
			case 'piechart':
				return $this->format_piechart( $all_data );
			case 'percentage':
				return $this->format_percentage( $all_data );
			default:
				return $all_data;
		}
	}

	/**
	 * Get all queer IRL data
	 *
	 * @return array All queer IRL data
	 */
	public function generate_all_data() {
		$transient = 'queer_irl_characters';
		$array     = lwtv_plugin()->get_transient( $transient );

		if ( false === $array ) {
			$array = $this->build_queer_irl_data();

			// Cache for 7 days since character data is relatively stable
			if ( ! empty( $array ) ) {
				lwtv_plugin()->set_transient( $transient, $array, WEEK_IN_SECONDS );
			}
		}

		return $array;
	}

	/**
	 * Build queer IRL data by querying characters with lez_cliches term of queer-irl
	 *
	 * @return array Queer IRL character data
	 */
	public function build_queer_irl_data() {
		try {
			global $wpdb;

			// Query characters that have the 'queer-irl' term in lez_cliches taxonomy
			$query = "SELECT
					COUNT(DISTINCT chars.ID) as queer_count,
					(SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post_type_characters' AND post_status = 'publish') as total_count
				FROM {$wpdb->posts} chars
				INNER JOIN {$wpdb->term_relationships} tr ON chars.ID = tr.object_id
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
				WHERE chars.post_type = 'post_type_characters'
				AND chars.post_status = 'publish'
				AND tt.taxonomy = 'lez_cliches'
				AND t.slug = 'queer-irl'";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is a prepared query (see above)
			$result = $wpdb->get_row( $query, ARRAY_A );

			if ( false === $result || is_null( $result ) ) {
				lwtv_plugin()->error_log( 'statistics', 'Query failed: ' . $wpdb->last_error );
				return array();
			}

			$queer_count     = (int) $result['queer_count'];
			$total_count     = (int) $result['total_count'];
			$not_queer_count = $total_count - $queer_count;

			return array(
				'queer'     => array(
					'name'  => 'Queer Actors',
					'count' => $queer_count,
					'url'   => home_url( '/cliches/queer-irl/' ),
				),
				'not_queer' => array(
					'name'  => 'Non-Queer Actors',
					'count' => $not_queer_count,
					'url'   => home_url( '/characters/' ),
				),
			);

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'statistics', 'Error building queer IRL data: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Format piechart
	 *
	 * @param array $all_data All queer IRL data
	 * @return array Piechart data
	 */
	public function format_piechart( $all_data ) {
		$data = array();
		foreach ( $all_data as $item ) {
			$data[ $item['name'] ] = $item['count'];
		}

		return $data;
	}

	/**
	 * Format percentage
	 *
	 * @param array $all_data All queer IRL data
	 * @return array Percentage data
	 */
	public function format_percentage( $all_data ) {
		$data = array();
		foreach ( $all_data as $key => $item ) {
			$data[ $key ] = array(
				'name'  => $item['name'],
				'count' => $item['count'],
				'url'   => $item['url'],
			);
		}

		return $data;
	}
}
