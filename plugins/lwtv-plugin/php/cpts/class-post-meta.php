<?php
/*
 * Registering post meta for Custom Post Types
 *
 * @since 1.0
 */

/**
 * class LWTV_CPTs_Post_Meta
 */

namespace LWTV\CPTs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Actors as CPT_Actors;
use LWTV\CPTs\Characters as CPT_Characters;
use LWTV\CPTs\Shows as CPT_Shows;
use LWTV\CPTs\TVMaze as CPT_TV_Maze;

class Post_Meta {

	const ALL_POST_META = array(
		// Meta Name                    => Post Type
		// Actors
		'lezactors_birth'               => array(
			'post_type' => CPT_Actors::SLUG,
		),
		'lezactors_death'               => array(
			'post_type' => CPT_Actors::SLUG,
		),
		'lezactors_homepage'            => array(
			'post_type' => CPT_Actors::SLUG,
		),
		'lezactors_facebook'            => array(
			'post_type' => CPT_Actors::SLUG,
		),
		'lezactors_imdb'                => array(
			'post_type' => CPT_Actors::SLUG,
		),
		'lezactors_instagram'           => array(
			'post_type' => CPT_Actors::SLUG,
		),
		'lezactors_mastodon'            => array(
			'post_type' => CPT_Actors::SLUG,
		),
		'lezactors_tiktok'              => array(
			'post_type' => CPT_Actors::SLUG,
		),
		'lezactors_tmdb_id'             => array(
			'post_type'         => CPT_Actors::SLUG,
			'sanitize_callback' => array( self::class, 'sanitize_numeric_id' ),
		),
		// Timestamp of the last attempted TMDB lookup. Distinguishes "TMDB has
		// no match" from "never asked". Written by `wp lwtv tmdb backfill`.
		'lezactors_tmdb_checked'        => array(
			'post_type'         => CPT_Actors::SLUG,
			'type'              => 'integer',
			'show_in_rest'      => false,
			'sanitize_callback' => 'absint',
		),
		'lezactors_tumblr'              => array(
			'post_type' => CPT_Actors::SLUG,
		),
		'lezactors_twitter'             => array(
			'post_type' => CPT_Actors::SLUG,
		),
		'lezactors_wikidata_qid'        => array(
			'post_type' => CPT_Actors::SLUG,
		),
		'lezactors_wikipedia'           => array(
			'post_type' => CPT_Actors::SLUG,
		),
		'lezactors_char_count'          => array(
			'post_type' => CPT_Actors::SLUG,
		),
		'lezactors_char_list'           => array(
			'post_type'    => CPT_Actors::SLUG,
			'show_in_rest' => false,
		),
		'lezactors_dead_count'          => array(
			'post_type' => CPT_Actors::SLUG,
		),
		'lezactors_dead_list'           => array(
			'post_type'    => CPT_Actors::SLUG,
			'show_in_rest' => false,
		),
		'lezactors_saved_wikidata'      => array(
			'post_type'    => CPT_Actors::SLUG,
			'type'         => 'object',
			'items_type'   => 'string',
			'show_in_rest' => false,
		),
		'lezactors_queer_override'      => array(
			'post_type' => CPT_Actors::SLUG,
		),
		// Characters
		'lezchars_death_year'           => array(
			'post_type'    => CPT_Characters::SLUG,
			'show_in_rest' => false,
		),
		'lezchars_actor'                => array(
			'post_type'    => CPT_Characters::SLUG,
			'show_in_rest' => false,
		),
		'lezchars_show_group'           => array(
			'post_type'    => CPT_Characters::SLUG,
			'show_in_rest' => false,
		),
		// Shows
		'lezshows_airdates'             => array(
			'post_type'    => CPT_Shows::SLUG,
			'show_in_rest' => false,
		),
		'lezshows_waystowatch'          => array(
			'post_type'    => CPT_Shows::SLUG,
			'show_in_rest' => false,
		),
		// 'lezshows_affiliate' was registered here as deprecated. Removed once no
		// post carried it (verified 2026-08-21) and the migration that read it
		// was deleted. It had no 'show_in_rest' key, so it defaulted to true and
		// was shipping an always-empty field in the shows REST schema.
		'lezshows_char_count'           => array(
			'post_type' => CPT_Shows::SLUG,
		),
		'lezshows_char_list'            => array(
			'post_type'    => CPT_Shows::SLUG,
			'show_in_rest' => false,
		),
		'lezshows_dead_count'           => array(
			'post_type' => CPT_Shows::SLUG,
		),
		'lezshows_dead_list'            => array(
			'post_type'    => CPT_Shows::SLUG,
			'show_in_rest' => false,
		),
		'lezshows_imdb'                 => array(
			'post_type' => CPT_Shows::SLUG,
		),
		'lezshows_tmdb_id'              => array(
			'post_type'         => CPT_Shows::SLUG,
			'sanitize_callback' => array( self::class, 'sanitize_numeric_id' ),
		),
		// Timestamp of the last attempted TMDB lookup. Distinguishes "TMDB has
		// no match" from "never asked". Written by `wp lwtv tmdb backfill`.
		'lezshows_tmdb_checked'         => array(
			'post_type'         => CPT_Shows::SLUG,
			'type'              => 'integer',
			'show_in_rest'      => false,
			'sanitize_callback' => 'absint',
		),
		'lezshows_tvmaze_id'            => array(
			'post_type' => CPT_Shows::SLUG,
		),
		'lezshows_episodes'             => array(
			'post_type' => CPT_Shows::SLUG,
		),
		'lezshows_on_air'               => array(
			'post_type' => CPT_Shows::SLUG,
		),
		'lezshows_plots'                => array(
			'post_type' => CPT_Shows::SLUG,
		),
		'lezshows_genres_primary'       => array(
			'post_type' => CPT_Shows::SLUG,
		),
		'lezshows_quality_details'      => array(
			'post_type' => CPT_Shows::SLUG,
		),
		'lezshows_quality_rating'       => array(
			'post_type' => CPT_Shows::SLUG,
		),
		'lezshows_realness_details'     => array(
			'post_type' => CPT_Shows::SLUG,
		),
		'lezshows_realness_rating'      => array(
			'post_type' => CPT_Shows::SLUG,
		),
		'lezshows_screentime_rating'    => array(
			'post_type' => CPT_Shows::SLUG,
		),
		'lezshows_screentime_details'   => array(
			'post_type' => CPT_Shows::SLUG,
		),
		'lezshows_seasons'              => array(
			'post_type' => CPT_Shows::SLUG,
		),
		'lezshows_ships'                => array(
			'post_type' => CPT_Shows::SLUG,
		),
		'lezshows_the_score'            => array(
			'post_type' => CPT_Shows::SLUG,
		),
		'lezshows_3rd_scores'           => array(
			'post_type'    => CPT_Shows::SLUG,
			'show_in_rest' => false,
		),
		'lezshows_tvmaze'               => array(
			'post_type'    => CPT_Shows::SLUG,
			'show_in_rest' => false,
		),
		'lezshows_worthit_rating'       => array(
			'post_type' => CPT_Shows::SLUG,
		),
		'lezshows_worthit_details'      => array(
			'post_type' => CPT_Shows::SLUG,
		),
		'lezshows_worthit_show_we_love' => array(
			'post_type' => CPT_Shows::SLUG,
		),
		'lezshows_similar_shows'        => array(
			'post_type'    => CPT_Shows::SLUG,
			'show_in_rest' => false,
		),
		// TV Maze
		'leztvmaze_our_show'            => array(
			'post_type' => CPT_TV_Maze::SLUG,
		),
		// Updated Characters
		'lwtv_characters_last_updated'  => array(
			'post_type' => array( CPT_Actors::SLUG, CPT_Shows::SLUG ),
			'type'      => 'timestamp',
		),
		'lwtv_has_new_char'             => array(
			'type'      => 'boolean',
			'post_type' => array( CPT_Actors::SLUG, CPT_Shows::SLUG ),
		),
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'create_meta_data' ), 0 );

		// phpcs:disable
		// Hide taxonomies from Gutenberg.
		// While this isn't the official API for this need, it works.
		// https://github.com/WordPress/gutenberg/issues/6912#issuecomment-428403380
		add_filter( 'rest_prepare_taxonomy', function( $response, $taxonomy ) {

			$all_tax_array = array();
			foreach ( self::ALL_POST_META as $post_meta ) {
				$all_tax_array[] = $post_meta;
			}

			if ( in_array( $taxonomy->name, $all_tax_array ) ) {
				$response->data['visibility']['show_ui'] = false;
			}
			return $response;
		}, 10, 2 );
		// phpcs:enable
	}

	/*
	 * Create and register the meta data for it's associated post type.
	 *
	 * Note: https://make.wordpress.org/core/2019/10/03/wp-5-3-supports-object-and-array-meta-types-in-the-rest-api/
	 */
	public function create_meta_data() {
		$arguments = array(
			'show_in_rest' => true,
		);

		// Register the metas automagically
		foreach ( self::ALL_POST_META as $meta_name => $meta_data ) {
			$post_type = $meta_data['post_type'];

			if ( ! is_array( $post_type ) ) {
				$post_type = array( $post_type );
			}

			foreach ( $post_type as $one_post_type ) {
				// Set the type.
				$arguments['type'] = ( isset( $meta_data['type'] ) ) ? $meta_data['type'] : 'string';

				// Set Items Types:
				if ( isset( $meta_data['show_in_rest'] ) && false === $meta_data['show_in_rest'] ) {
					$arguments['show_in_rest'] = false;
				} elseif ( 'string' !== $arguments['type'] && isset( $meta_data['items_type'] ) ) {
					$arguments['show_in_rest'] = array(
						'schema' => array(
							'type'                 => $meta_data['type'],
							'items'                => array(
								'type' => $meta_data['items_type'],
							),
							'additionalProperties' => array(
								'type' => 'string',
							),
						),
					);

					// Set Properties.
					if ( isset( $meta_data['properties'] ) ) {
						$arguments['show_in_rest']['schema']['items']['properties'] = $meta_data['properties'];
					}
				} else {
					$arguments['show_in_rest'] = true;
				}

				$arguments['sanitize_callback'] = $meta_data['sanitize_callback'] ?? null;

				register_post_meta( $one_post_type, $meta_name, $arguments );
			}
		}
	}

	/**
	 * Strip a stored ID meta value to digits only (TMDB IDs are numeric).
	 *
	 * @param mixed $value Raw meta value.
	 * @return string
	 */
	public static function sanitize_numeric_id( $value ): string {
		return preg_replace( '/[^0-9]/', '', (string) $value );
	}
}
