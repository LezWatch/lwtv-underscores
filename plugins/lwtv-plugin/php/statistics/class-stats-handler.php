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

class Stats_Handler {

	/**
	 * Handle the output of the statistics
	 *
	 * @param array $data The data to handle
	 * @param string $context The context of the data
	 * @param string $view The view of the data
	 * @param string $format The format of the data
	 * @param string $source_type The source type of the data
	 * @return array The handled data
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function handle( $data, $context, $view, $format, $source_type ) {
		return $data;
	}
}
