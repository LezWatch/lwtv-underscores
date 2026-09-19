<?php
/**
 * namespace LWTV\Queeries;
 *
 * Does another post already claim this IMDb ID?
 *
 * An IMDb ID is an identity claim rather than a resemblance: two actor posts
 * holding the same nm ID are the same person, and two shows holding the same tt
 * ID are the same show. That makes it the one signal strong enough to refuse a
 * save over, and it is already the only evidence Debugger\Build\Duplicate_Rules
 * will call a duplicate on.
 *
 * Comparison goes through _Helpers\Imdb_Canonical::normalise(), because editors
 * paste imdb.com URLs into ID fields. A stored URL and a typed bare ID are the
 * same claim and have to compare equal.
 *
 * @since 6.0.1
 */

namespace LWTV\Queeries;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\_Helpers\Imdb_Canonical;

class Get_Post_By_Imdb {

	/**
	 * The post already holding this IMDb ID, if there is one.
	 *
	 * @param  string $imdb       An IMDb ID or imdb.com URL.
	 * @param  string $post_type  Post type to search.
	 * @param  string $meta_key   Meta key holding the ID for that post type.
	 * @param  int    $exclude_id Post being edited.
	 * @return int Post ID, or 0 when nothing else claims it.
	 */
	public function make( string $imdb, string $post_type, string $meta_key, int $exclude_id = 0 ): int {
		$wanted = Imdb_Canonical::normalise( $imdb );

		if ( '' === $wanted ) {
			return 0;
		}

		global $wpdb;

		/*
		 * LIKE rather than an exact match, because the stored value may be a
		 * full URL. It is a coarse filter on purpose -- '%nm123456%' also
		 * matches nm1234567 -- and the normalise() comparison below is what
		 * makes the answer exact.
		 */
		$query = $wpdb->prepare(
			"SELECT pm.post_id, pm.meta_value
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = %s
			AND pm.meta_value LIKE %s
			AND p.post_type = %s
			AND p.post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )
			AND p.ID != %d
			ORDER BY p.ID ASC",
			$meta_key,
			'%' . $wpdb->esc_like( $wanted ) . '%',
			$post_type,
			$exclude_id
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $query );

		foreach ( (array) $rows as $row ) {
			if ( Imdb_Canonical::normalise( $row->meta_value ) === $wanted ) {
				return (int) $row->post_id;
			}
		}

		return 0;
	}
}
