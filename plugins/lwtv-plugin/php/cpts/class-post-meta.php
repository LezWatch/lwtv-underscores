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
		//
		// NOTE ON ACTOR PRIVACY: the fields an actor can opt out of via
		// 'lezactors_make_option_private' (hide_dob, hide_socials) are all
		// registered with 'show_in_rest' => false. The core wp/v2 meta endpoint
		// has no per-post read gate -- registered meta is readable by anyone who
		// can read the post, and the actor CPT is public -- so a public field
		// there bypasses the opt-out entirely. Anything needing this data
		// publicly goes through Rest_API\Export_JSON, which honors the privacy
		// settings. Do not make these public without replacing that gate.
		//
		// This is NOT the only REST surface for these values. An ACF field
		// group with 'show_in_rest' enabled publishes its fields under the
		// response's 'acf' key on its own, ignoring what is set here -- so
		// acf-json/group_lwtv_actors_details.json has to stay at 0 for the
		// above to mean anything. Check both when adding an actor field.
		'lezactors_birth'               => array(
			'post_type'    => CPT_Actors::SLUG,
			'show_in_rest' => false,
		),
		'lezactors_death'               => array(
			'post_type' => CPT_Actors::SLUG,
		),
		'lezactors_homepage'            => array(
			'post_type' => CPT_Actors::SLUG,
		),
		'lezactors_facebook'            => array(
			'post_type'    => CPT_Actors::SLUG,
			'show_in_rest' => false,
		),
		'lezactors_imdb'                => array(
			'post_type' => CPT_Actors::SLUG,
		),
		'lezactors_instagram'           => array(
			'post_type'    => CPT_Actors::SLUG,
			'show_in_rest' => false,
		),
		'lezactors_mastodon'            => array(
			'post_type'    => CPT_Actors::SLUG,
			'show_in_rest' => false,
		),
		'lezactors_tiktok'              => array(
			'post_type'    => CPT_Actors::SLUG,
			'show_in_rest' => false,
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
		// The IMDb ID TMDB holds for this actor, stored ONLY when it disagrees
		// with ours. Absent means no disagreement recorded -- which covers both
		// verified-clean and never-checked, deliberately indistinguishable so
		// nothing reads absence as a clean bill of health.
		'lezactors_imdb_canonical'      => array(
			'post_type'    => CPT_Actors::SLUG,
			'show_in_rest' => false,
		),
		'lezactors_tumblr'              => array(
			'post_type'    => CPT_Actors::SLUG,
			'show_in_rest' => false,
		),
		'lezactors_twitter'             => array(
			'post_type'    => CPT_Actors::SLUG,
			'show_in_rest' => false,
		),
		'lezactors_wikidata_qid'        => array(
			'post_type' => CPT_Actors::SLUG,
		),
		// How lezactors_wikidata_qid was resolved: 'manual', 'imdb', 'name', or
		// 'legacy'. A Q-ID is not self-describing -- a fuzzy name match and a
		// hand-checked ID look identical once stored -- and only 'manual' and
		// 'imdb' are trusted enough for an unattended process to act on. Absent
		// reads as 'legacy', which is untrusted on purpose. See Wikidata\Build\Qid_Trust.
		'lezactors_wikidata_qid_source' => array(
			'post_type'    => CPT_Actors::SLUG,
			'show_in_rest' => false,
		),
		// Timestamp of the last *attempted* Q-ID lookup. Distinguishes "WikiData
		// has no item for this person" from "never asked". Written by
		// `wp lwtv wikidata backfill` and the Q-ID scheduler task.
		'lezactors_wikidata_checked'    => array(
			'post_type'         => CPT_Actors::SLUG,
			'type'              => 'integer',
			'show_in_rest'      => false,
			'sanitize_callback' => 'absint',
		),
		// Write-lock on lezactors_wikidata_qid. Set it and Identity::store_qid()
		// refuses, so the field is editable by hand only and no backfill can
		// overwrite what an editor put there.
		//
		// 'lezactors_wikidata_qid_manual' was registered here as a second,
		// editor-only Q-ID field. Removed: two Q-IDs meant two things to keep in
		// step, and the distinction it carried -- who set this value -- is what
		// lezactors_wikidata_qid_source records. Never deployed, so no rows exist.
		'lezactors_wikidata_ignore'     => array(
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
		'lezshows_tvmaze_checked'       => array(
			'post_type'         => CPT_Shows::SLUG,
			'type'              => 'integer',
			'show_in_rest'      => false,
			'sanitize_callback' => 'absint',
		),
		'lezshows_tvmaze_ignore'        => array(
			'post_type'    => CPT_Shows::SLUG,
			'show_in_rest' => false,
		),
		'lezshows_tvmaze_id_manual'     => array(
			'post_type'         => CPT_Shows::SLUG,
			'type'              => 'integer',
			'show_in_rest'      => false,
			'sanitize_callback' => 'absint',
		),
		// The IMDb ID TVMaze holds for this show, stored ONLY when it disagrees
		// with ours. See lezactors_imdb_canonical for why absence is deliberately
		// ambiguous.
		'lezshows_imdb_canonical'       => array(
			'post_type'    => CPT_Shows::SLUG,
			'show_in_rest' => false,
		),
		'lezshows_aired_years'          => array(
			'post_type'    => CPT_Shows::SLUG,
			'show_in_rest' => false,
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
		// The same score before the 0-100 clamp. Kept so shows at the ceiling stay
		// distinguishable from each other; not for display, which uses the clamped
		// value above. show_score() alone can reach 115, so this can exceed 100.
		'lezshows_the_score_uncapped'   => array(
			'post_type'    => CPT_Shows::SLUG,
			'show_in_rest' => false,
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
