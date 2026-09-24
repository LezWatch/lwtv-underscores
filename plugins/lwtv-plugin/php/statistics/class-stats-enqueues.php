<?php
/**
 * Statistics Enqueues Class
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Stats_Enqueues {

	/**
	 * Enqueue the scripts and styles depending on the view
	 *
	 * @param array $versioning The versioning array
	 */
	public function enqueue_scripts( $versioning ) {
		$statistics = sanitize_key( get_query_var( 'statistics', 'none' ) );
		$stat_view  = sanitize_key( get_query_var( 'view', 'main' ) );

		// Overview + Shows + Characters + Actors + Nations + Stations + Death:
		// count-up + bar-grow animations. No jQuery dependency.
		if ( in_array( $statistics, array( 'none', 'shows', 'characters', 'actors', 'nations', 'stations', 'death' ), true ) ) {
			wp_enqueue_script(
				'lwtv-stats-overview',
				LWTV_PLUGIN_URL . '/assets/js/statistics-overview.js',
				array(),
				$versioning['stats-overview'],
				true
			);
		}

		// Only the death record list uses tablesorter, so load it (and its single
		// init) on that view alone — every other page skips the library + theme CSS.
		if ( 'death' === $statistics && 'list' === $stat_view ) {
			wp_enqueue_script( 'tablesorter', LWTV_PLUGIN_URL . '/assets/js/jquery.tablesorter.min.js', array( 'jquery' ), $versioning['tablesorter'], false );
			wp_enqueue_style( 'tablesorter', LWTV_PLUGIN_URL . '/assets/css/theme.bootstrap.min.css', array(), $versioning['tablesorter'], false );
			wp_add_inline_script( 'tablesorter', 'jQuery(document).ready(function($){ $("#DeadCharactersTable").tablesorter({ theme:"bootstrap", sortList:[[1,1]] }); });' );
		}
	}
}
