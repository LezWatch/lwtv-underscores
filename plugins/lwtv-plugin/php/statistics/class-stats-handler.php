<?php
/**
 * Statistics Handler Class
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Statistics\Format\Percentage_Optimized;
use LWTV\Statistics\Format\Lists_Optimized;

class Stats_Handler {

	/**
	 * Handle the output of the statistics
	 *
	 * @param array $data The data to handle
	 * @param string $context The context of the data
	 * @param string $view The view of the data
	 * @param string $format The format of the data
	 * @param string $source_type The source type of the data
	 * @param array $custom_data The custom data. Unused now that barchart/piechart/trendline formats are gone; kept for call-site compatibility.
	 * @param string $bar_direction The direction of the bar chart. Unused now that barchart/piechart/trendline formats are gone; kept for call-site compatibility.
	 * @return array The handled data
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function handle( $data, $context, $view, $format, $source_type, $custom_data, $bar_direction ) {
		switch ( $format ) {
			case 'percentage':
				return ( new Percentage_Optimized() )->format( $data, $context, $view, $source_type );
			case 'list':
				if ( 'death' === $source_type ) {
					return ( new Lists_Optimized() )->format_dead_list( $data, $context, $view, $source_type );
				}
				return ( new Lists_Optimized() )->format( $data, $context, $view, $source_type );
			default:
				return $data;
		}
	}
}
