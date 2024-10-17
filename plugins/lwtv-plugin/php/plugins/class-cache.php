<?php
/*
 * Weird Cache commands.
 *
 * @package lwtv-plugin
 */

namespace LWTV\Plugins;

use LWTV\CPTs\Characters;

class Cache {

	/**
	 * Collect the URLs we're going to flush for characters
	 * @param  int     $post_id ID of the character
	 * @return array   array of URLs
	 */
	public function collect_urls_for_characters( $post_id ) {

		// defaults:
		$clean_urls = array();

		// Generate list of shows to purge
		$shows = get_post_meta( $post_id, 'lezchars_show_group', true );
		if ( ! empty( $shows ) ) {
			foreach ( $shows as $show ) {

				if ( ! isset( $show['show'] ) ) {
					continue;
				}

				// Remove the Array.
				if ( is_array( $show['show'] ) ) {
					$show['show'] = $show['show'][0];
				}

				// If the show is live, we'll flush it.
				if ( isset( $show['show'] ) && 'publish' === get_post_status( $show['show'] ) ) {
					$clean_urls[] = get_permalink( $show['show'] );
				}
			}
		}

		// Generate List of Actors
		$actors = get_post_meta( $post_id, 'lezchars_actor', true );
		if ( ! empty( $actors ) ) {
			if ( ! is_array( $actors ) ) {
				$actors = array( $actors );
			}

			foreach ( $actors as $actor ) {
				// If the actor is live, we'll flush them.
				if ( isset( $actor ) && 'publish' === get_post_status( $actor ) ) {
					$clean_urls[] = get_permalink( $actor );
				}
			}
		}

		if ( ! empty( $clean_urls ) ) {
			$clean_urls = array_unique( $clean_urls );
		}

		return $clean_urls;
	}

	/**
	 * Collect the URLs we're going to flush for shows or actors
	 * @param  int     $post_id ID of the show or actor
	 * @return array   array of URLs
	 */
	public function collect_cache_urls_for_actors_or_shows( $post_id ) {

		// Default
		$clean_urls = array();

		// Get the shadow characters.
		$shadow_chars = \Shadow_Taxonomy\Core\get_the_posts( $post_id, Characters::SHADOW_TAXONOMY, Characters::SLUG );

		// If it's not an array, we have no characters.
		if ( ! is_array( $shadow_chars ) ) {
			return $clean_urls;
		}

		foreach ( $shadow_chars as $shadow => $item ) {
			if ( empty( $shadow ) ) {
				continue;
			}

			if ( isset( $item->ID ) && ! empty( $item->ID ) && 'publish' === get_post_status( $item->ID ) ) {
				// Add character URL to urls to clean.
				$clean_urls[] = get_permalink( $item->ID );
			}
		}

		if ( ! empty( $clean_urls ) ) {
			$clean_urls = array_unique( $clean_urls );
		}

		return $clean_urls;
	}

	/**
	 * Clean URLs
	 *
	 * It would be preferable to use the rp_nginx filter, however that runs every time
	 * any page is updated. This method is slower and uglier, but more precise.
	 *
	 * @param  int    $post_id    - ID of the post
	 * @param  array  $clear_urls - Arrays of URLs to clean
	 * @return void
	 */
	public function clean_urls( $post_id, $clear_urls ) {

		// If it's not an array, or it's empty, we don't have anything to clear.
		if ( ! is_array( $clear_urls ) || empty( $clear_urls ) ) {
			return;
		}

		// If published within the last 15 minutes, flush home page
		$post_date = get_post_time( 'U', true, $post_id );
		$delta     = time() - $post_date;
		if ( $delta < ( 15 * 60 ) ) {
			$clear_urls[] = home_url();
		}

		// WP Rocket.
		if ( function_exists( 'rocket_clean_files' ) ) {
			rocket_clean_files( $clear_urls );
		}

		foreach ( $clear_urls as $url ) {
			// Nginx Helper.
			if ( is_plugin_active( 'nginx-helper/nginx-helper.php' ) ) {
				wp_remote_get( $url );
			}
		}
	}
}
