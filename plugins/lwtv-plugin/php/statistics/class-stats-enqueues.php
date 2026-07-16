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
		wp_enqueue_script( 'tablesorter', LWTV_PLUGIN_URL . '/assets/js/jquery.tablesorter.min.js', array( 'jquery' ), $versioning['tablesorter'], false );
		wp_enqueue_style( 'tablesorter', LWTV_PLUGIN_URL . '/assets/css/theme.bootstrap.min.css', array(), $versioning['tablesorter'], false );

		// Both are public query vars that get interpolated into inline JS selectors
		// below, so sanitize to key-safe characters (a-z0-9, dash, underscore) to
		// prevent reflected XSS via e.g. /statistics/?view=<payload>.
		$statistics = sanitize_key( get_query_var( 'statistics', 'none' ) );
		$stat_view  = sanitize_key( get_query_var( 'view', 'main' ) );

		// Overview + Shows + Characters: count-up + bar-grow animations. No jQuery dependency.
		if ( in_array( $statistics, array( 'none', 'shows', 'characters', 'actors' ), true ) ) {
			wp_enqueue_script(
				'lwtv-stats-overview',
				LWTV_PLUGIN_URL . '/assets/js/statistics-overview.js',
				array(),
				$versioning['stats-overview'],
				true
			);
		}

		switch ( $statistics ) {
			case 'nations':
			case 'stations':
				wp_add_inline_script( 'tablesorter', 'jQuery(document).ready(function($){ $("#' . $statistics . 'Table").tablesorter({ theme : "bootstrap", }); });' );
				break;
			case 'death':
				wp_add_inline_script( 'tablesorter', 'jQuery(document).ready(function($){ $("#DeadCharactersTable").tablesorter({ theme : "bootstrap", }); });' );
				if ( 'characters' === $stat_view ) {
					wp_add_inline_script( 'tablesorter', 'jQuery(document).ready(function($){ $("#sexualityTable").tablesorter({ theme : "bootstrap", }); });' );
					wp_add_inline_script( 'tablesorter', 'jQuery(document).ready(function($){ $("#genderTable").tablesorter({ theme : "bootstrap", }); });' );
					wp_add_inline_script( 'tablesorter', 'jQuery(document).ready(function($){ $("#roleTable").tablesorter({ theme : "bootstrap", }); });' );
				}

				if ( 'stations' === $stat_view ) {
					wp_add_inline_script( 'tablesorter', 'jQuery(document).ready(function($){ $("#stationTable").tablesorter({ theme : "bootstrap", }); });' );
				}
				if ( 'nations' === $stat_view ) {
					wp_add_inline_script( 'tablesorter', 'jQuery(document).ready(function($){ $("#nationTable").tablesorter({ theme : "bootstrap", }); });' );
				}
				break;
		}

		switch ( $stat_view ) {
			case 'tropes':
			case 'genres':
			case 'formats':
			case 'intersectionality':
			case 'stars':
			case 'triggers':
				wp_add_inline_script( 'tablesorter', 'jQuery(document).ready(function($){ $("#' . $stat_view . 'Table").tablesorter({ theme : "bootstrap", }); });' );
				wp_add_inline_script( 'tablesorter', 'jQuery(document).ready(function($){ $("#showTable").tablesorter({ theme : "bootstrap", }); });' );
				break;
			case 'we-love-it':
				wp_add_inline_script( 'tablesorter', 'jQuery(document).ready(function($){ $("#weloveitTable").tablesorter({ theme : "bootstrap", }); });' );
				wp_add_inline_script( 'tablesorter', 'jQuery(document).ready(function($){ $("#showTable").tablesorter({ theme : "bootstrap", }); });' );
				break;
			default:
				wp_add_inline_script( 'tablesorter', 'jQuery(document).ready(function($){ $("#' . $stat_view . 'Table").tablesorter({ theme : "bootstrap", }); });' );
				break;
		}
	}
}
