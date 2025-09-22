<?php
/**
 * Canceled Shows Formatter Class for This Year Statistics
 *
 * @package LezWatch.TV
 */

namespace LWTV\This_Year\Format;

class Canceled_Shows_Formatter {

	public function format_by_name_for_year( $this_year, $shows_by_name ): array {
		$canceled_shows_by_name = array();

		try {
			foreach ( $shows_by_name as $marker => $shows ) {
				// each show in the array
				foreach ( $shows as $show ) {
					if ( $show['airdates']['finish'] === $this_year ) {
						$canceled_shows_by_name[ $marker ][ $show['name'] ] = $show;
					}
				}
			}

			ksort( $canceled_shows_by_name );

			return $canceled_shows_by_name;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'canceled-shows-formatter-debug', 'Error formatting canceled shows by name for year: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Format canceled shows by format for a given year
	 *
	 * @param int $this_year The year to format by
	 * @param array $shows_by_format The shows by format data
	 * @return array The formatted canceled shows by format data
	 */
	public function format_by_format_for_year( $this_year, $shows_by_format ): array {
		$canceled_shows_by_format = array();

		try {
			foreach ( $shows_by_format as $marker => $shows ) {
				// each show in the array
				foreach ( $shows as $show ) {
					if ( $show['airdates']['finish'] === $this_year ) {
						$canceled_shows_by_format[ $marker ][ $show['name'] ] = $show;
					}
				}
			}

			ksort( $canceled_shows_by_format );

			return $canceled_shows_by_format;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'canceled-shows-formatter-debug', 'Error formatting canceled shows by format for year: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Format canceled shows by country for a given year
	 *
	 * @param int $this_year The year to format by
	 * @param array $shows_by_country The shows by country data
	 * @return array The formatted canceled shows by country data
	 */
	public function format_by_country_for_year( $this_year, $shows_by_country ): array {
		$canceled_shows_by_country = array();

		try {
			foreach ( $shows_by_country as $marker => $shows ) {
				// each show in the array
				foreach ( $shows as $show ) {
					if ( $show['airdates']['finish'] === $this_year ) {
						$canceled_shows_by_country[ $marker ][ $show['name'] ] = $show;
					}
				}
			}
			ksort( $canceled_shows_by_country );

			return $canceled_shows_by_country;
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'canceled-shows-formatter-debug', 'Error formatting canceled shows by country for year: ' . $e->getMessage() );
			return array();
		}
	}
}
