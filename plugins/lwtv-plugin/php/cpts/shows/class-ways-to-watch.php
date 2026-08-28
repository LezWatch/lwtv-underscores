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
	 * Taxonomy holding the watch providers.
	 */
	const TAXONOMY = 'lez_watch_urls';

	/**
	 * Our custom column on the taxonomy list screen.
	 */
	const COLUMN_URLS = 'lwtv_watch_urls';

	/**
	 * Cached term_id => URL count map. Built once per request.
	 *
	 * @var array<int, int>|null
	 */
	private static $url_counts = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'manage_edit-post_type_shows_columns', array( $this, 'hide_columns' ) );
		add_filter( 'manage_edit-' . self::TAXONOMY . '_columns', array( $this, 'hide_on_edit_page' ) );
		add_filter( 'manage_' . self::TAXONOMY . '_custom_column', array( $this, 'column_content' ), 10, 3 );

		add_action( self::TAXONOMY . '_edit_form', array( $this, 'hide_description_row' ) );
		add_action( self::TAXONOMY . '_add_form', array( $this, 'hide_description_row' ) );
	}

	/**
	 * Fill in the URLs column.
	 *
	 * Taxonomy custom columns are a *filter* returning markup, unlike post
	 * columns which are an action that echoes.
	 *
	 * @param string $content Existing content.
	 * @param string $column  Column being rendered.
	 * @param int    $term_id Term ID.
	 *
	 * @return string
	 */
	public function column_content( $content, $column, $term_id ): string {
		if ( self::COLUMN_URLS !== $column ) {
			return $content;
		}

		$counts = self::get_url_counts();
		$count  = $counts[ (int) $term_id ] ?? 0;

		if ( 0 === $count ) {
			// A provider with no URLs can never be matched by
			// Watch_Hosts::term_for(), so it's dead weight.
			return '<span aria-label="' . esc_attr__( 'No URLs, so this provider can never be matched', 'lwtv' ) . '">0</span>';
		}

		return (string) $count;
	}

	/**
	 * Count non-empty URL rows per term, in one query.
	 *
	 * Read from the repeater's own subfield rows rather than ACF's
	 * `lezwatchurls_all` row-count bookkeeping, so empty rows aren't counted.
	 *
	 * @return array<int, int> term_id => count
	 */
	private static function get_url_counts(): array {
		if ( null !== self::$url_counts ) {
			return self::$url_counts;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tm.term_id, COUNT(*) AS total
				 FROM {$wpdb->termmeta} tm
				 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = tm.term_id
				 WHERE tt.taxonomy = %s
				   AND tm.meta_key REGEXP '^lezwatchurls_all_[0-9]+_url$'
				   AND tm.meta_value != ''
				 GROUP BY tm.term_id",
				self::TAXONOMY
			)
		);

		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row->term_id ] = (int) $row->total;
		}

		self::$url_counts = $counts;

		return self::$url_counts;
	}

	/**
	 * Brute Force hide the term description since we're not using it and it takes up space.
	 */
	public function hide_description_row() {
		echo '<style> .term-description-wrap, .term-slug-wrap { display:none; } </style>';
	}

	/**
	 * Columns on the lez_watch_urls list screen.
	 *
	 * `count` is dropped because it counts term *assignments*, and these terms
	 * are never assigned to shows — the show-to-provider relationship is
	 * resolved by matching URLs in term meta (see
	 * Watch_Hosts::term_for()), so it is permanently 0. The URLs
	 * count is the number that actually says whether a term is doing anything.
	 */
	public function hide_on_edit_page( $columns ) {
		unset( $columns['wpseo-inclusive-language'] );
		unset( $columns['description'] );
		unset( $columns['count'] );
		unset( $columns['slug'] );

		$columns[ self::COLUMN_URLS ] = __( 'URLs', 'lwtv' );

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
}
