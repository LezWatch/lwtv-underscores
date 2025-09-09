<?php

namespace LWTV\Statistics\Build;

class Dead_List {

	/**
	 * List of dead characters - Optimized with single query
	 *
	 * @param  string $format [array|time]
	 * @return array          All the dead, yo
	 */
	public function make( $format = 'array' ) {
		try {
			$transient = 'dead_list_' . $format;
			$array     = lwtv_plugin()->get_transient( $transient );

			if ( false === $array ) {
				$array = $this->build_dead_list_optimized( $format );

				// save array as transient for a reason.
				if ( ! empty( $array ) ) {
					lwtv_plugin()->set_transient( $transient, $array, DAY_IN_SECONDS );
				}
			}

			return $array;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'dead-list-error', 'Error building dead list: ' . $e->getMessage() );
			return 'time' === $format ? array(
				'most'  => array(
					'count' => 0,
					'date'  => '0000-00-00',
				),
				'time'  => 0,
				'start' => '',
				'end'   => '',
			) : array();
		}
	}

	/**
	 * Build dead list statistics using optimized single query
	 *
	 * @param string $format Output format (array|time)
	 * @return array
	 */
	private function build_dead_list_optimized( $format ) {
		global $wpdb;

		try {
			// Single optimized query to get all dead character data
			$queery = "SELECT
				p.ID,
				p.post_title,
				p.post_name,
				p.post_status,
				death_meta.meta_value as death_years
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} death_meta ON p.ID = death_meta.post_id AND death_meta.meta_key = 'lezchars_death_year'
			WHERE p.post_type = 'post_type_characters'
			AND p.post_status = 'publish'
			AND death_meta.meta_value IS NOT NULL
			AND death_meta.meta_value != ''
			ORDER BY p.post_title";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- There's no need to prepare this query
			$results = $wpdb->get_results( $queery, ARRAY_A );

			$array = array();

			foreach ( $results as $row ) {
				$char_id     = (int) $row['ID'];
				$death_years = maybe_unserialize( $row['death_years'] );

				if ( ! is_array( $death_years ) ) {
					continue;
				}

				foreach ( $death_years as $died_date ) {
					// If there's no entry, add it.
					if ( ! isset( $array[ $died_date ] ) ) {
						$array[ $died_date ] = array(
							'date' => $died_date,
						);
					}

					$array[ $died_date ]['chars'][ $char_id ] = array(
						'name' => $row['post_title'],
						'url'  => get_permalink( $char_id ),
					);
				}
			}

			// sort by date (newest first)
			krsort( $array );

			// calculate time since last death and most dead in a day.
			$keys      = array_keys( $array );
			$key_count = count( $keys ) - 1;
			for ( $i = 0; $i < $key_count; $i++ ) {
				// Check the diff
				$date1 = date_create( $keys[ $i ] );
				$date2 = date_create( $keys[ $i + 1 ] );
				$diff  = date_diff( $date1, $date2 );
				$days  = $diff->format( '%a' );

				// Add the time since last death
				$array[ $keys[ $i ] ]['since'] = $days;

				// Add the most dead in a day
				$array[ $keys[ $i ] ]['most'] = count( $array[ $keys[ $i ] ]['chars'] );
			}

			// Change what we output...
			switch ( $format ) {
				case 'array':
					return $array;
				case 'time':
					$diff_since = array(
						'time'      => max( array_column( $array, 'since' ) ),
						'most'      => max( array_column( $array, 'most' ) ),
						'most_date' => '0000-00-00',
					);
					for ( $i = 0; $i < $key_count; $i++ ) {
						if ( $diff_since['time'] === $array[ $keys[ $i ] ]['since'] ) {
							$diff_since['end']   = $keys[ $i ];
							$diff_since['start'] = $keys[ $i + 1 ];
						}

						if ( $diff_since['most'] === $array[ $keys[ $i ] ]['most'] ) {
							if ( $diff_since['most_date'] < $array[ $keys[ $i ] ]['date'] ) {
								$diff_since['most_date'] = $array[ $keys[ $i ] ]['date'];
							}
						}
					}
					return array(
						'most'  => array(
							'count' => $diff_since['most'],
							'date'  => $diff_since['most_date'],
						),
						'time'  => $diff_since['time'],
						'start' => $diff_since['start'],
						'end'   => $diff_since['end'],
					);
			}

			return $array;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'dead-list-error', 'Error building dead list statistics: ' . $e->getMessage() );
			return 'time' === $format ? array(
				'most'  => array(
					'count' => 0,
					'date'  => '0000-00-00',
				),
				'time'  => 0,
				'start' => '',
				'end'   => '',
			) : array();
		}
	}
}
