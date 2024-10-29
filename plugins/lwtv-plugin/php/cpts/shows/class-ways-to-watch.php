<?php
/**
 * Name: Ways to Watch
 * Description: Allow editors to customize the 'ways to watch' on the fly, based on networks and links
 *
 * This requires CMB2
 */

namespace LWTV\CPTs\Shows;

class Ways_To_Watch {

	// prefix for all custom fields
	const PREFIX = 'lezwatchurls_';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'cmb2_admin_init', array( $this, 'cmb2_ways_to_watch_urls' ) );

		add_filter( 'manage_edit-post_type_shows_columns', array( $this, 'hide_columns' ) );
		add_filter( 'manage_edit-lez_watch_urls_columns', array( $this, 'hide_on_edit_page' ) );

		add_action( 'lez_watch_urls_edit_form', array( $this, 'hide_description_row' ) );
		add_action( 'lez_watch_urls_add_form', array( $this, 'hide_description_row' ) );
	}

	/**
	 * Brute Force hide the term description since we're not using it and it takes up space.
	 */
	public function hide_description_row() {
		echo '<style> .term-description-wrap, .term-slug-wrap { display:none; } #lezwatchurls_all_repeat { width: 150%; }</style>';
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
	 * Build the CMB2 Meta Boxes
	 */
	public function cmb2_ways_to_watch_urls(): void {

		$cmb_watch_settings = new_cmb2_box(
			array(
				'id'           => self::PREFIX . 'settings',
				'title'        => 'Custom Settings',
				'object_types' => array( 'term' ),
				'taxonomies'   => array( 'lez_watch_urls' ),
			)
		);
		$cmb_watch_settings->add_field(
			array(
				'name' => 'Hide Display',
				'desc' => 'Do not show this watch URL on the front end.',
				'id'   => self::PREFIX . 'setting_hide_display',
				'type' => 'checkbox',
			)
		);

		/**
		 * Metabox to add URLs
		 */
		$cmb_watch_urls = new_cmb2_box(
			array(
				'id'               => self::PREFIX . 'edit',
				'title'            => 'URLs under this provider',
				'object_types'     => array( 'term' ),
				'taxonomies'       => array( 'lez_watch_urls' ),
				'new_term_section' => true, // Will display in the "Add New Category" section
			)
		);

		$cmb_watch_urls->add_field(
			array(
				'name'         => 'URL',
				'desc'         => 'URL used by this provider.',
				'id'           => self::PREFIX . 'all',
				'type'         => 'text_url',
				'protocols'    => array( 'http', 'https' ),
				'repeatable'   => true,
				'add_row_text' => 'Add Another URL',
				'attributes'   => array(
					'placeholder' => 'https://paramountplus.com/ (just the first part)',
				),
			)
		);
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
