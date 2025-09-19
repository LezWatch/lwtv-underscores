<?php
/**
 * LezWatch.TV Custom Queries for Stats
 *
 * @package LezWatch.TV Plugin
 *
 */

namespace LWTV\Statistics;

class Query_Vars {

	/**
	 * Construct
	 * Runs the Code
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Main Plugin setup
	 *
	 * Adds actions, filters, etc. to WP
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	public function init() {
		// Plugin requires permalink usage - Only setup handling if permalinks enabled
		if ( '' !== get_option( 'permalink_structure' ) ) {

			// tell WP not to override query vars
			add_action( 'query_vars', array( $this, 'query_vars' ) );

			// add filter for pages
			add_filter( 'page_template', array( $this, 'page_template' ) );

			$views = array( 'actors', 'characters', 'death', 'nations', 'shows', 'stations' );

			foreach ( $views as $a_view ) {
				add_rewrite_rule(
					'^statistics/' . $a_view . '/?$',
					'index.php?pagename=statistics&statistics=' . $a_view,
					'top'
				);

				add_rewrite_rule(
					'^statistics/' . $a_view . '/([^/]+)/?$',
					'index.php?pagename=statistics&statistics=' . $a_view . '&view=$matches[1]',
					'top'
				);

			}
		} else {
			add_action( 'admin_notices', array( $this, 'admin_notice_permalinks' ) );
		}
	}

	/**
	 * No Permalinks Notice
	 *
	 * @since 1.0
	 */
	public function admin_notice_permalinks() {
		echo '<div class="error"><p><strong>LezWatch.TV Query Vars</strong> requires you use custom permalinks.</p></div>';
	}

	/**
	 * Add the query variables so WordPress won't override it
	 *
	 * @return $vars
	 */
	public function query_vars( $vars ) {
		$vars[] = 'statistics';
		$vars[] = 'view';
		$vars[] = 'for';
		$vars[] = 'format';
		$vars[] = 'country';
		$vars[] = 'station';
		$vars[] = 'nation';
		return $vars;
	}

	/**
	 * Adds a custom template to the query queue.
	 *
	 * @return $templates
	 */
	public function page_template( $templates = '' ) {
		if ( isset( $wp_query->query['statistics'] ) ) {
			$templates = get_stylesheet_directory() . '/page-templates/statistics.php';
		}
		return $templates;
	}
}
