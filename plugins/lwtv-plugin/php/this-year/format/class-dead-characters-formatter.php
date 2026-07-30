<?php
/**
 * Dead Characters Formatter Class for This Year Statistics
 *
 * @package LezWatch.TV
 */

namespace LWTV\This_Year\Format;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Dead_Characters_Formatter {

	/**
	 * Format the dead characters by date for a given year
	 *
	 * @param int   $this_year The year to format by
	 * @param array $characters_on_air The characters on air
	 *
	 * @return array The formatted dead characters by date
	 */
	public function format_by_date_for_year( $this_year, $characters_on_air ): array {
		$dead_by_date = array();

		try {
			foreach ( $characters_on_air as $character ) {
				if ( ! $character['dead'] ) {
					continue;
				}

				// for each death year, if the year is $this_year, add the character to the $dead_characters array
				foreach ( $character['death_years'] as $death_date ) {
					// Get the year from the death date (it's the first 4 digits).
					$death_year = substr( $death_date, 0, 4 );
					if ( (int) $death_year === (int) $this_year ) {
						// ACF's date_picker stores raw meta as Ymd; a handful of legacy
						// rows are still Y-m-d. Normalize to Y-m-d so both spellings of
						// the same day group together instead of fragmenting into two rows.
						$death_key = (string) $death_date;
						if ( false === strpos( $death_key, '-' ) && 8 === strlen( $death_key ) ) {
							$death_key = substr( $death_key, 0, 4 ) . '-' . substr( $death_key, 4, 2 ) . '-' . substr( $death_key, 6, 2 );
						}
						$dead_by_date[ $death_key ][] = $character;
					}
				}
			}

			// Sort the $dead_by_date array by the death date
			ksort( $dead_by_date );

			return $dead_by_date;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'this-year', 'Error formatting dead characters by date for year: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Format the dead characters by show for a given year
	 *
	 * @param int   $this_year The year to format by
	 * @param array $characters_on_air The characters on air
	 *
	 * @return array The formatted dead characters by show
	 */
	public function format_by_show_for_year( $this_year, $characters_on_air_by_show ): array {
		$dead_by_show = array();

		try {
			foreach ( $characters_on_air_by_show as $show_id => $show_data ) {
				foreach ( $show_data['characters'] as $character_item => $character ) {
					if ( empty( $character['dead'] ) ) {
						continue;
					}

					if ( ! str_starts_with( $character['last_death'], (string) $this_year ) ) {
						continue;
					}

					if ( ! isset( $dead_by_show[ $show_id ]['show'] ) ) {
						$dead_by_show[ $show_id ]['show'] = array(
							'name'    => $show_data['name'],
							'url'     => '/show/' . $show_data['slug'] . '/',
							'nations' => $show_data['nations'],
							'formats' => $show_data['formats'],
						);
					}

					$dead_by_show[ $show_id ]['characters'][] = $character;
				}
			}

			return $dead_by_show;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'this-year', 'Error formatting dead characters by show for year ' . $this_year . ': ' . $e->getMessage() );
			return array();
		}
	}
}
