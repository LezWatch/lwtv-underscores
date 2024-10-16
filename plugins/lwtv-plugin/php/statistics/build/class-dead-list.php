<?php

namespace LWTV\Statistics\Build;

class Dead_List {

	/**
	 * List of dead characters
	 *
	 * @param  string $format [all|YEAR]
	 * @return array          All the dead, yo
	 */
	public function make( $format = 'array' ) {
		$transient = 'dead_list_' . $format;
		$array     = lwtv_plugin()->get_transient( $transient );

		if ( false === $array ) {
			$array     = array();
			$dead_loop = lwtv_plugin()->queery_post_meta( 'post_type_characters', 'lezchars_death_year', '', '!=' );

			if ( is_object( $dead_loop ) && $dead_loop->have_posts() ) {
				$queery = wp_list_pluck( $dead_loop->posts, 'ID' );
			}

			foreach ( $queery as $char ) {
				$died = get_post_meta( $char, 'lezchars_death_year', true );

				foreach ( $died as $died_date ) {
					// If there's no entry, add it.
					if ( ! isset( $array[ $died_date ] ) ) {
						$array[ $died_date ] = array(
							'date' => $died_date,
						);
					}

					$array[ $died_date ]['chars'][ $char ] = array(
						'name' => get_the_title( $char ),
						'url'  => get_the_permalink( $char ),
					);
				}
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

		// calculate the most number of deaths per date.

		// Change what we output...
		switch ( $format ) {
			case 'array':
				$return = $array;
				break;
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
				$return = array(
					'most'  => array(
						'count' => $diff_since['most'],
						'date'  => $diff_since['most_date'],
					),
					'time'  => $diff_since['time'],
					'start' => $diff_since['start'],
					'end'   => $diff_since['end'],
				);
				break;
		}

		return $return;
	}
}
