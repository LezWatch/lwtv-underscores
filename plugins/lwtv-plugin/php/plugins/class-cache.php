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
	 * Clean Feed URLs
	 */
	public function clean_feed( $type = 'main' ) {

		$main_feed = get_bloginfo( 'rss2_url' ); // /feed/

		$clear_urls = match ( $type ) {
			'characters' => array( $main_feed . '/characters/' ),
			'shows'      => array( $main_feed . '/shows/' ),
			'actors'     => array( $main_feed . '/actors/' ),
			'otd'        => array( $main_feed . '/otd/' ),
			default      => array( $main_feed ),
		};

		$this->clear_cache_plugins( $clear_urls );
	}

	/**
	 * Clean any url that exists on this website.
	 *
	 * @param array $urls - URLs to clean
	 *
	 * @return void
	 */
	public function clean_any_urls( array $urls ) {
		$clean_urls = array();

		// Validate the URLs.
		foreach ( $urls as $url ) {
			// If this is a URL, and it's for this site, we can add it.
			if ( wp_http_validate_url( $url ) && str_starts_with( $url, get_home_url() ) ) {
				$clean_urls[] = $url;
			}
		}

		$this->clear_cache_plugins( $clean_urls );
	}

	/**
	 * Collect the URLs we're going to flush for characters
	 *
	 * @param  int     $post_id ID of the character
	 * @return array   array of URLs
	 */
	public function collect_urls_for_characters( $post_id ) {

		// defaults:
		$clean_urls = array(
			get_permalink( $post_id ),
		);

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
	 *
	 * @param  int     $post_id ID of the show or actor
	 * @return array   array of URLs
	 */
	public function collect_cache_urls_for_actors_or_shows( $post_id ) {
		// Add the post URL to the list of URLs to clean.
		$clean_urls = array(
			get_permalink( $post_id ),
		);

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
	 * Clean URLs related to custom post types.
	 *
	 * Shows, Actors, and Characters have cross-references that need to be cleared.
	 *
	 * It would be preferable to use the rp_nginx filter, however that runs every time
	 * ANY page is updated. This method is slower and uglier, but more precise.
	 *
	 * @param  int    $post_id    - ID of the post
	 * @param  array  $clear_urls - Arrays of URLs to clean
	 * @return void
	 */
	public function clean_related_urls_for_cpts( $post_id, $clear_urls ) {
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

		$this->clear_cache_plugins( $clear_urls );
	}

	/**
	 * Invalidate object cache for related posts
	 *
	 * Uses WordPress clean_post_cache() to invalidate Redis object cache
	 * for the post and its related actors/shows. This ensures that post meta
	 * changes are immediately reflected when viewing related pages.
	 *
	 * @param int $post_id The post ID
	 * @return void
	 */
	public function invalidate_object_cache_for_related_posts( int $post_id ): void {
		// Clean the post's own cache
		clean_post_cache( $post_id );

		$post_type = get_post_type( $post_id );

		// For characters, also clean related actors and shows
		if ( 'post_type_characters' === $post_type ) {
			$actors = get_post_meta( $post_id, 'lezchars_actor', true );
			if ( is_array( $actors ) ) {
				foreach ( $actors as $actor_id ) {
					clean_post_cache( (int) $actor_id );
				}
			}

			$show_group = get_post_meta( $post_id, 'lezchars_show_group', true );
			if ( is_array( $show_group ) ) {
				foreach ( $show_group as $show ) {
					if ( isset( $show['show'] ) ) {
						$show_id = is_array( $show['show'] ) ? $show['show'][0] : $show['show'];
						clean_post_cache( (int) $show_id );
					}
				}
			}

			lwtv_plugin()->error_log( 'object-cache', "Invalidated object cache for character ID: {$post_id} and related actors/shows" );
		}

		// For actors/shows, also clean related characters
		if ( in_array( $post_type, array( 'post_type_actors', 'post_type_shows' ), true ) ) {
			$shadow_chars = \Shadow_Taxonomy\Core\get_the_posts( $post_id, Characters::SHADOW_TAXONOMY, Characters::SLUG );

			if ( is_array( $shadow_chars ) ) {
				foreach ( $shadow_chars as $item ) {
					if ( isset( $item->ID ) && ! empty( $item->ID ) ) {
						clean_post_cache( (int) $item->ID );
					}
				}
			}

			lwtv_plugin()->error_log( 'object-cache', "Invalidated object cache for {$post_type} ID: {$post_id} and related characters" );
		}
	}

	/**
	 * Clear the Cache
	 *
	 * This is a wrapper so we can call the other cache clearing functions from one place.
	 *
	 * @param  array  $clear_urls - URLs to clear
	 * @return void
	 */
	private function clear_cache_plugins( $clear_urls ) {
		$this->clear_nginx_helper( $clear_urls );
		$this->clear_wp_rocket( $clear_urls );
	}

	/**
	 * Clear the Nginx Helper Cache
	 *
	 * @param  array  $clear_urls - URLs to clear
	 * @return void
	 */
	private function clear_nginx_helper( $clear_urls ) {
		if ( is_plugin_active( 'nginx-helper/nginx-helper.php' ) ) {
			foreach ( $clear_urls as $url ) {
				// add /purge/ to the URL
				$url = str_replace( home_url(), home_url() . '/purge/', $url );
				wp_remote_get( $url );
			}
		}
	}

	/**
	 * Clear the WP Rocket Cache
	 *
	 * @param  array  $clear_urls - URLs to clear
	 * @return void
	 */
	private function clear_wp_rocket( $clear_urls ) {
		if ( is_plugin_active( 'wp-rocket/wp-rocket.php' ) ) {
			/** @disregard rocket_clean_files() is provided by the WP Rocket plugin **/
			rocket_clean_files( $clear_urls );
		}
	}
}
