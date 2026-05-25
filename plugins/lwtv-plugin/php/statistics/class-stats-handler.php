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

use LWTV\Statistics\Format\Barcharts_Optimized;
use LWTV\Statistics\Format\Trendline_Optimized;
use LWTV\Statistics\Format\Piecharts_Optimized;
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
	 * @param array $custom_data The custom data
	 * @param string $bar_direction The direction of the bar chart
	 * @return array The handled data
	 */
	public function handle( $data, $context, $view, $format, $source_type, $custom_data, $bar_direction ) {
		$stat_view = get_query_var( 'view', 'main' );

		switch ( $format ) {
			case 'barchart':
				return ( new Barcharts_Optimized() )->format( $data, $context, $view, $source_type, $custom_data, $bar_direction );
			case 'trendline':
				return ( new Trendline_Optimized() )->format( $data, $context, $view, $source_type );
			case 'piechart':
				return ( new Piecharts_Optimized() )->format( $data, $context, $view, $source_type, $stat_view );
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
