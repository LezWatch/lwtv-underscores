<?php
/**
 * Name: Ways to Watch
 * Description: Allow editors to customize the 'ways to watch' on the fly, based on networks and links
 */

namespace LWTV\CPTs\Shows;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ways_To_Watch {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'manage_edit-post_type_shows_columns', array( $this, 'hide_columns' ) );
		add_filter( 'manage_edit-lez_watch_urls_columns', array( $this, 'hide_on_edit_page' ) );

		add_action( 'lez_watch_urls_edit_form', array( $this, 'hide_description_row' ) );
		add_action( 'lez_watch_urls_add_form', array( $this, 'hide_description_row' ) );
	}

	/**
	 * Brute Force hide the term description since we're not using it and it takes up space.
	 */
	public function hide_description_row() {
		echo '<style> .term-description-wrap, .term-slug-wrap { display:none; } </style>';
	}

	/**
	 * Hide columns on EDIT page not needed for this term.
	 */
	public function hide_on_edit_page( $columns ) {
		unset( $columns['wpseo-inclusive-language'] );
		unset( $columns['description'] );
		unset( $columns['count'] );
		unset( $columns['slug'] );

		return $columns;
	}

	/**
	 * Hide the ways to watch column from the TV SHOW list since it's not actually used here.
	 *
	 * @param array $columns
	 *
	 * @return array $columns
	 */
	public function hide_columns( $columns ) {
		// Change categories for your custom taxonomy
		unset( $columns['taxonomy-lez_watch_urls'] );
		return $columns;
	}

	/**
	 * Check the ways to watch as we moved over.
	 *
	 * @param int $show_id The show ID.
	 */
	public function migrate_ways_to_watch( int $show_id ): void {
		$old_watch_urls = get_post_meta( $show_id, 'lezshows_affiliate', true );
		$new_watch_urls = get_post_meta( $show_id, 'lezshows_waystowatch', true );

		if ( empty( $new_watch_urls ) && ! empty( $old_watch_urls ) ) {
			update_post_meta( $show_id, 'lezshows_waystowatch', $old_watch_urls );
			delete_post_meta( $show_id, 'lezshows_affiliate' );
		}
	}
}
