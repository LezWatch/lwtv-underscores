<?php
/*
 * Weird Cache commands.
 *
 * @package lwtv-plugin
 */

namespace LWTV\Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Actors;
use LWTV\CPTs\Characters;
use LWTV\CPTs\Shows;
use LWTV\Relationships\Character_Links;

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
		$shows = get_field( 'lezchars_show_group', $post_id );
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
		$actors = get_field( 'lezchars_actor', $post_id ) ?: array();
		if ( ! empty( $actors ) ) {
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

		foreach ( $this->related_character_ids( (int) $post_id ) as $character_id ) {
			if ( 'publish' === get_post_status( $character_id ) ) {
				// Add character URL to urls to clean.
				$clean_urls[] = get_permalink( $character_id );
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
			$actors = get_field( 'lezchars_actor', $post_id ) ?: array();
			if ( ! empty( $actors ) ) {
				foreach ( $actors as $actor_id ) {
					clean_post_cache( (int) $actor_id );
				}
			}

			$show_group = get_field( 'lezchars_show_group', $post_id );
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
			foreach ( $this->related_character_ids( $post_id ) as $character_id ) {
				clean_post_cache( $character_id );
			}

			lwtv_plugin()->error_log( 'object-cache', "Invalidated object cache for {$post_type} ID: {$post_id} and related characters" );
		}
	}

	/**
	 * Characters linked to a post, looked up by its post type.
	 *
	 * Shows and actors get the characters that list them. A character gets its
	 * legacy character links (Character_Links::characters_referencing()), a
	 * superset of what the old shadow-term lookup returned for a character.
	 * Anything else has no linked characters.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return int[] Character post IDs (any status).
	 */
	private function related_character_ids( int $post_id ): array {
		$links = new Character_Links();

		return match ( get_post_type( $post_id ) ) {
			Shows::SLUG      => $links->characters_for_show( $post_id ),
			Actors::SLUG     => $links->characters_for_actor( $post_id ),
			Characters::SLUG => $links->characters_referencing( $post_id ),
			default          => array(),
		};
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
	}

	/**
	 * Clear the Nginx Helper Cache
	 *
	 * @param  array  $clear_urls - URLs to clear
	 * @return void
	 */
	private function clear_nginx_helper( $clear_urls ) {
		if ( ! $this->is_nginx_helper_active() ) {
			return;
		}

		foreach ( $clear_urls as $url ) {
			// add /purge/ to the URL
			$url = str_replace( home_url(), home_url() . '/purge/', $url );
			wp_remote_get( $url );
		}
	}

	/**
	 * Whether Nginx Helper is active.
	 *
	 * Deliberately does NOT use is_plugin_active(): that lives in
	 * wp-admin/includes/plugin.php, which is not loaded on front-end, REST, or
	 * wp-cron.php requests. Cache clears reach here from the `shutdown` hook
	 * (Schedulers\Cache_Queue) and from cron/Action Scheduler tasks, so calling
	 * it would be a fatal on exactly those paths.
	 *
	 * @return bool
	 */
	private function is_nginx_helper_active(): bool {
		$plugin = 'nginx-helper/nginx-helper.php';

		if ( in_array( $plugin, (array) get_option( 'active_plugins', array() ), true ) ) {
			return true;
		}

		if ( is_multisite() ) {
			$network_plugins = (array) get_site_option( 'active_sitewide_plugins', array() );
			return isset( $network_plugins[ $plugin ] );
		}

		return false;
	}
}
