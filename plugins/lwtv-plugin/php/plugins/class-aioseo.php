<?php
/*
 * All in One SEO (AIOSEO) hooks
 *
 * Stops new Shows/Characters/Actors from being assigned a slug that an
 * active AIOSEO redirect still points away from (e.g. a renamed character
 * freed up `esther-3`, but the redirect from `esther-3` is still live).
 *
 * @package lwtv-plugin
 */

namespace LWTV\Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIOSEO {

	/**
	 * Post types whose slugs are checked against AIOSEO redirects.
	 *
	 * @var array<string>
	 */
	const POST_TYPES = array( 'post_type_shows', 'post_type_characters', 'post_type_actors' );

	/**
	 * Hard stop for the increment loop so a bad data state can't hang a save.
	 *
	 * @var int
	 */
	const MAX_ATTEMPTS = 100;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'wp_unique_post_slug', array( $this, 'avoid_redirected_slugs' ), 10, 6 );
	}

	/**
	 * If the slug WordPress picked is the source of a live AIOSEO redirect,
	 * keep incrementing it (the same way core does for slug collisions)
	 * until it's clear of both existing posts and active redirects.
	 *
	 * @param string $slug          The post slug WordPress has settled on so far.
	 * @param int    $post_id       Post ID.
	 * @param string $post_status   Post status.
	 * @param string $post_type     Post type.
	 * @param int    $post_parent   Post parent ID.
	 * @param string $original_slug The requested slug before any de-duping.
	 * @return string
	 */
	public function avoid_redirected_slugs( $slug, $post_id, $post_status, $post_type, $post_parent, $original_slug ) {
		global $wpdb;

		if ( ! in_array( $post_type, self::POST_TYPES, true ) ) {
			return $slug;
		}

		if ( ! $this->redirects_table_exists() ) {
			return $slug;
		}

		$post_type_object = get_post_type_object( $post_type );
		$rewrite_slug     = is_array( $post_type_object->rewrite ?? null ) ? $post_type_object->rewrite['slug'] : $post_type;

		$suffix = 2;

		while ( $this->slug_has_active_redirect( $rewrite_slug, $slug ) ) {
			if ( $suffix > self::MAX_ATTEMPTS ) {
				break;
			}

			$candidate = _truncate_post_slug( $original_slug, 200 - ( strlen( (string) $suffix ) + 1 ) ) . '-' . $suffix;
			++$suffix;

			if ( $this->slug_used_by_other_post( $wpdb, $candidate, $post_type, $post_id ) ) {
				continue;
			}

			$slug = $candidate;
		}

		return $slug;
	}

	/**
	 * Check whether an enabled AIOSEO redirect still uses this slug's front-end path as its source.
	 *
	 * @param string $rewrite_slug The post type's rewrite base (e.g. 'character').
	 * @param string $slug         The candidate post slug.
	 * @return bool
	 */
	private function slug_has_active_redirect( $rewrite_slug, $slug ) {
		global $wpdb;

		$path          = "/{$rewrite_slug}/{$slug}/";
		$hash          = sha1( $path );
		$hash_no_slash = sha1( rtrim( $path, '/' ) );

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE enabled = 1 AND ( source_url_hash IN ( %s, %s ) OR source_url_match_hash IN ( %s, %s ) ) LIMIT 1',
				$wpdb->prefix . 'aioseo_redirects',
				$hash,
				$hash_no_slash,
				$hash,
				$hash_no_slash
			)
		);
	}

	/**
	 * Check whether another post of the same type is already using this slug.
	 *
	 * @param \wpdb  $wpdb      WordPress database access object.
	 * @param string $slug      The candidate post slug.
	 * @param string $post_type Post type.
	 * @param int    $post_id   Post ID to exclude from the check.
	 * @return bool
	 */
	private function slug_used_by_other_post( $wpdb, $slug, $post_type, $post_id ) {
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = %s AND ID != %d LIMIT 1",
				$slug,
				$post_type,
				$post_id
			)
		);
	}

	/**
	 * Whether the AIOSEO redirects table exists, cached for the request.
	 *
	 * @return bool
	 */
	private function redirects_table_exists() {
		static $exists = null;

		if ( null === $exists ) {
			global $wpdb;

			$table  = $wpdb->prefix . 'aioseo_redirects';
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
		}

		return $exists;
	}
}
