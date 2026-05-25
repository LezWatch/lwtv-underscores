<?php

/**
 * Yoast SEO Plugin
 *
 * Adds extra replacements for Yoast SEO
 * Enables sitemap caching
 *
 * @package LezWatch.TV Plugin
 *
 */

namespace LWTV\Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Yoast {

	public function __construct() {
		if ( ! defined( 'WPSEO_VERSION' ) ) {
			return;
		}

		// Enable Yoast SEO sitemap caching
		add_filter( 'wpseo_enable_xml_sitemap_transient_caching', '__return_true' );

		// Extra Replacement Functions for Yoast SEO
		add_action( 'wpseo_register_extra_replacements', array( $this, 'yoast_seo_register_extra_replacements' ) );
	}

	/*
	 * Extra Replacement Functions for Yoast SEO
	 */
	public function yoast_seo_register_extra_replacements() {
		\wpseo_register_var_replacement( '%%thisyear%%', array( $this, 'yoast_retrieve_year_replacement' ), 'basic', 'The year.' );
		\wpseo_register_var_replacement( '%%characters%%', array( $this, 'yoast_retrieve_characters_replacement' ), 'basic', 'Information on how many characters an actor plays.' );
		\wpseo_register_var_replacement( '%%is_queer%%', array( $this, 'yoast_retrieve_queer_replacement' ), 'basic', 'Output if the actor is queer IRL.' );
		\wpseo_register_var_replacement( '%%statistics%%', array( $this, 'yoast_retrieve_stats_replacement' ), 'basic', 'The type of stats page we\'re on.' );
		\wpseo_register_var_replacement( '%%actors%%', array( $this, 'yoast_retrieve_actors_replacement' ), 'basic', 'A list of actors who played the character, separated by commas.' );
		\wpseo_register_var_replacement( '%%shows%%', array( $this, 'yoast_retrieve_shows_replacement' ), 'basic', 'A list of shows the character was on, separated by commas.' );
	}

	/*
	 * Extra Meta Variables for Yoast and Characters
	 *
	 * Information on how many queer characters an actor plays
	 */
	public function yoast_retrieve_characters_replacement() {
		global $post;

		$characters = '0';
		if ( is_object( $post ) ) {
			$char_count = get_post_meta( $post->ID, 'lezactors_char_count', true );
			// translators: %s is the number of characters
			$characters = ( 0 === $char_count ) ? 'no characters' : sprintf( _n( '%s character', '%s characters', $char_count ), $char_count );
		}

		return $characters;
	}

	/*
	 * Extra Meta Variables for Yoast and Stats pages
	 *
	 * The type of stats page we're on
	 */
	public function yoast_retrieve_stats_replacement() {
		$statistics = get_query_var( 'statistics', 'none' );
		$return     = ( 'none' !== $statistics ) ? 'on ' . ucfirst( $statistics ) : '';
		return $return;
	}

	/*
	 * Extra Meta Variables for Yoast and Queer
	 *
	 * List of actors who played a character, for use on character pages
	 */
	public function yoast_retrieve_queer_replacement() {
		global $post;

		$queer = 'an actor';
		if ( is_object( $post ) ) {
			$is_queer = get_post_meta( $post->ID, 'lezactors_queer', true );
			$queer    = ( $is_queer ) ? 'a queer actor' : 'an actor';
		}
		return $queer;
	}

	/*
	 * Extra Meta Variables for Yoast and Year pages
	 *
	 * @return string  The year.
	 */
	public function yoast_retrieve_year_replacement() {
		$this_year = get_query_var( 'thisyear', 'none' );
		$return    = ( 'none' !== $this_year ) ? ucfirst( $this_year ) : gmdate( 'Y' );
		$return    = '(' . $return . ')';
		return $return;
	}

	/*
	 * Extra Meta Variables for Yoast and Actors
	 *
	 * List of actors who played a character, for use on character pages
	 */
	public function yoast_retrieve_actors_replacement() {
		global $post;

		$return = 'Unknown';
		if ( is_object( $post ) ) {
			$actors     = array();
			$actors_ids = get_post_meta( $post->ID, 'lezchars_actor', true );
			if ( ! is_array( $actors_ids ) ) {
				$actors_ids = array( get_post_meta( $post->ID, 'lezchars_actor', true ) );
			}
			if ( '' !== $actors_ids && ! is_null( $actors_ids ) ) {
				foreach ( $actors_ids as $each_actor ) {
					array_push( $actors, get_the_title( $each_actor ) );
				}
			}
			$return = implode( ', ', $actors );
		}

		return $return;
	}

	/*
	 * Extra Meta Variables for Yoast and Characters
	 *
	 * List of shows featuring a character, for use on character pages
	 */
	public function yoast_retrieve_shows_replacement() {
		global $post;

		$shows_string = '';
		if ( is_object( $post ) ) {
			$shows_ids    = get_post_meta( $post->ID, 'lezchars_show_group', true );
			$shows_titles = array();

			if ( ! is_array( $shows_ids ) ) {
				$shows_ids = array( $shows_ids );
			}

			if ( '' !== $shows_ids && ! is_null( $shows_ids ) ) {
				foreach ( $shows_ids as $each_show ) {

					if ( ! isset( $each_show['show'] ) ) {
						continue;
					}

					// De-Array.
					if ( is_array( $each_show['show'] ) ) {
						$each_show['show'] = $each_show['show'][0];
					}

					// Get titles.
					if ( isset( $each_show['show'] ) ) {
						array_push( $shows_titles, get_the_title( $each_show['show'] ) );
					}
				}
			}
			$shows_string = implode( ', ', $shows_titles );
		}
		return $shows_string;
	}
}
