<?php
/**
 * Queer/Trans Cast Firsts Query Class
 *
 * Three callouts for the Characters → Queer IRL page: the oldest and newest
 * character played by a queer-IRL actor, and the oldest character played by
 * a transgender actor. "Oldest"/"newest" means earliest/latest first-on-
 * screen year — the same per-character minimum drawn from the
 * lezchars_show_group repeater's `appears` sub-field that
 * Character_Longevity_Leaders and Character_Identity_Trend already use,
 * since characters have no premiere-year field of their own.
 *
 * "Played by a queer-IRL actor" is a flag on the CHARACTER (the lez_cliches
 * term `queer-irl` — see Queer_IRL::build_queer_irl_data()), so that half is
 * a direct character-level join. "Played by a trans actor" has no such
 * character-level flag — it lives on the ACTOR's own lez_actor_gender
 * taxonomy, reached through the lezchars_actor relationship field, which
 * (like lezchars_show_group's `appears`) is stored as one serialized array
 * per character rather than a per-row meta key, so it can't be joined
 * per-actor in SQL either — same two-step "pull the array, unserialize in
 * PHP" shape Character_Actor_Leaders already uses. A term slug counts as
 * "trans" the same way Queeries\Is_Actor_Trans decides it for a single
 * actor: it contains the substring "trans" (matches trans-woman, trans-man,
 * and non-binary-transgender without hardcoding an exhaustive list).
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Character_Queer_Cast_Firsts {

	/**
	 * Generate the oldest/newest character played by a queer-IRL actor.
	 *
	 * @return array {
	 *   @type array $oldest { 'name' => string, 'url' => string, 'year' => int } or empty if no data.
	 *   @type array $newest Same shape as $oldest.
	 * }
	 */
	public function generate_queer_actor_firsts(): array {
		$transient = 'character_queer_actor_firsts';
		$array     = lwtv_plugin()->get_transient( $transient );

		if ( false !== $array && is_array( $array ) ) {
			return $array;
		}

		$array = $this->build_queer_actor_firsts();

		if ( ! empty( $array ) ) {
			lwtv_plugin()->set_transient( $transient, $array, WEEK_IN_SECONDS );
		}

		return $array;
	}

	/**
	 * Generate the oldest character played by a transgender actor.
	 *
	 * @return array { 'name' => string, 'url' => string, 'year' => int } or empty if no data.
	 */
	public function generate_trans_actor_oldest(): array {
		$transient = 'character_trans_actor_oldest';
		$array     = lwtv_plugin()->get_transient( $transient );

		if ( false !== $array && is_array( $array ) ) {
			return $array;
		}

		$array = $this->build_trans_actor_oldest();

		if ( ! empty( $array ) ) {
			lwtv_plugin()->set_transient( $transient, $array, WEEK_IN_SECONDS );
		}

		return $array;
	}

	/**
	 * Query every published character carrying the queer-irl cliché, fold
	 * to each one's earliest on-screen year, and return the min/max rows.
	 *
	 * @return array See generate_queer_actor_firsts() for the return shape.
	 */
	private function build_queer_actor_firsts(): array {
		global $wpdb;

		// No user input: taxonomy/slug/post_type are hardcoded literals.
		// phpcs:disable
		$query = "SELECT chars.ID as id, chars.post_title as name, appears.meta_value as years
			FROM {$wpdb->posts} chars
			INNER JOIN {$wpdb->postmeta} appears ON appears.post_id = chars.ID
				AND appears.meta_key LIKE 'lezchars_show_group_%_appears'
				AND appears.meta_value != ''
			INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = chars.ID
			INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'lez_cliches'
			INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id AND t.slug = 'queer-irl'
			WHERE chars.post_type = 'post_type_characters'
			AND chars.post_status = 'publish'";
		// phpcs:enable

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input; all values are hardcoded literals.
		$results = $wpdb->get_results( $query, ARRAY_A );

		if ( empty( $results ) ) {
			lwtv_plugin()->debug_log( 'statistics', 'Queer actor firsts query returned no results: ' . $wpdb->last_error );
			return array();
		}

		$years_by_char = $this->fold_earliest_years( $results );
		if ( empty( $years_by_char ) ) {
			return array();
		}

		return array(
			'oldest' => $this->pick_extreme( $years_by_char, true ),
			'newest' => $this->pick_extreme( $years_by_char, false ),
		);
	}

	/**
	 * Query every published character whose lezchars_actor relationship
	 * includes at least one actor carrying a "trans" lez_actor_gender term,
	 * and return the one with the earliest on-screen year.
	 *
	 * Two queries rather than one: the trans-actor ID set is small and
	 * cheap to gather with a single term-relationship join, then reused as
	 * an in-memory lookup while folding characters — cheaper than trying to
	 * join a serialized-array field per row.
	 *
	 * @return array { 'name' => string, 'url' => string, 'year' => int } or empty if no data.
	 */
	private function build_trans_actor_oldest(): array {
		global $wpdb;

		// No user input: taxonomy/pattern/post_type are hardcoded literals.
		// phpcs:disable
		$trans_actor_query = "SELECT DISTINCT actors.ID as id
			FROM {$wpdb->posts} actors
			INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = actors.ID
			INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'lez_actor_gender'
			INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id AND t.slug LIKE '%trans%'
			WHERE actors.post_type = 'post_type_actors'
			AND actors.post_status = 'publish'";
		// phpcs:enable

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input; all values are hardcoded literals.
		$trans_actor_rows = $wpdb->get_results( $trans_actor_query, ARRAY_A );

		if ( empty( $trans_actor_rows ) ) {
			// No published trans-flagged actors at all — nothing can qualify.
			return array();
		}

		$trans_actor_ids = array_flip( array_map( 'absint', wp_list_pluck( $trans_actor_rows, 'id' ) ) );

		// phpcs:disable
		$query = "SELECT chars.ID as id, chars.post_title as name, appears.meta_value as years, actor_meta.meta_value as actors
			FROM {$wpdb->posts} chars
			INNER JOIN {$wpdb->postmeta} appears ON appears.post_id = chars.ID
				AND appears.meta_key LIKE 'lezchars_show_group_%_appears'
				AND appears.meta_value != ''
			INNER JOIN {$wpdb->postmeta} actor_meta ON actor_meta.post_id = chars.ID AND actor_meta.meta_key = 'lezchars_actor'
			WHERE chars.post_type = 'post_type_characters'
			AND chars.post_status = 'publish'";
		// phpcs:enable

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input; all values are hardcoded literals.
		$results = $wpdb->get_results( $query, ARRAY_A );

		if ( empty( $results ) ) {
			lwtv_plugin()->debug_log( 'statistics', 'Trans actor oldest query returned no results: ' . $wpdb->last_error );
			return array();
		}

		// Fold per character: earliest year (same normalization
		// Character_Longevity_Leaders uses for `appears`) and the set of
		// actor IDs (same normalization Character_Actor_Leaders uses for
		// lezchars_actor) — a character can appear in several rows here
		// since it's joined against every one of its own appears-rows, but
		// its actor list is identical on every row, so re-setting it each
		// time is harmless.
		$folded = array();
		foreach ( $results as $row ) {
			$years = maybe_unserialize( $row['years'] );
			if ( is_array( $years ) ) {
				$years = array_values( array_filter( array_map( 'intval', $years ) ) );
			} elseif ( is_numeric( $years ) ) {
				$years = array( (int) $years );
			} else {
				$years = array();
			}

			if ( empty( $years ) ) {
				continue;
			}

			$id = (int) $row['id'];

			$actors = maybe_unserialize( $row['actors'] );
			$actors = is_array( $actors ) ? array_values( array_filter( array_map( 'absint', $actors ) ) ) : array();

			if ( ! isset( $folded[ $id ] ) ) {
				$folded[ $id ] = array(
					'name' => $row['name'],
					'year' => min( $years ),
				);
			} else {
				$folded[ $id ]['year'] = min( $folded[ $id ]['year'], min( $years ) );
			}

			if ( ! empty( $actors ) ) {
				$folded[ $id ]['actors'] = $actors;
			}
		}

		$oldest    = array();
		$oldest_id = 0;
		foreach ( $folded as $id => $row ) {
			if ( empty( $row['actors'] ) ) {
				continue;
			}

			// Qualifies if any of this character's actors is in the
			// trans-actor set built above.
			$qualifies = false;
			foreach ( $row['actors'] as $actor_id ) {
				if ( isset( $trans_actor_ids[ $actor_id ] ) ) {
					$qualifies = true;
					break;
				}
			}
			if ( ! $qualifies ) {
				continue;
			}

			if ( empty( $oldest ) || $row['year'] < $oldest['year'] || ( $row['year'] === $oldest['year'] && $id < $oldest_id ) ) {
				$oldest    = array(
					'name' => $row['name'],
					'year' => $row['year'],
				);
				$oldest_id = $id;
			}
		}

		if ( empty( $oldest ) ) {
			return array();
		}

		return array(
			'name' => $oldest['name'],
			'url'  => get_permalink( $oldest_id ),
			'year' => $oldest['year'],
		);
	}

	/**
	 * Fold raw (id, name, years) rows into a per-character earliest year,
	 * same normalization Character_Longevity_Leaders uses for `appears`.
	 *
	 * @param array $results Raw $wpdb rows: [ 'id', 'name', 'years' (serialized) ].
	 * @return array [ int $char_id => { 'name' => string, 'year' => int } ]
	 */
	private function fold_earliest_years( array $results ): array {
		$folded = array();
		foreach ( $results as $row ) {
			$years = maybe_unserialize( $row['years'] );
			if ( is_array( $years ) ) {
				$years = array_values( array_filter( array_map( 'intval', $years ) ) );
			} elseif ( is_numeric( $years ) ) {
				$years = array( (int) $years );
			} else {
				$years = array();
			}

			if ( empty( $years ) ) {
				continue;
			}

			$id       = (int) $row['id'];
			$min_year = min( $years );

			if ( ! isset( $folded[ $id ] ) ) {
				$folded[ $id ] = array(
					'name' => $row['name'],
					'year' => $min_year,
				);
			} else {
				$folded[ $id ]['year'] = min( $folded[ $id ]['year'], $min_year );
			}
		}

		return $folded;
	}

	/**
	 * Pick the character with the earliest (or latest) year from a folded
	 * per-character map. Ties broken by the lower character ID, for a
	 * result that's stable across cache regenerations.
	 *
	 * @param array $years_by_char [ int $char_id => { 'name', 'year' } ].
	 * @param bool  $earliest      True for the minimum year, false for the maximum.
	 * @return array { 'name' => string, 'url' => string, 'year' => int } or empty if the input was empty.
	 */
	private function pick_extreme( array $years_by_char, bool $earliest ): array {
		$picked_id = 0;
		$picked    = array();

		foreach ( $years_by_char as $id => $row ) {
			if ( empty( $picked ) ) {
				$picked    = $row;
				$picked_id = $id;
				continue;
			}

			$is_better = $earliest ? ( $row['year'] < $picked['year'] ) : ( $row['year'] > $picked['year'] );
			$is_tie    = ( $row['year'] === $picked['year'] && $id < $picked_id );

			if ( $is_better || $is_tie ) {
				$picked    = $row;
				$picked_id = $id;
			}
		}

		if ( empty( $picked ) ) {
			return array();
		}

		return array(
			'name' => $picked['name'],
			'url'  => get_permalink( $picked_id ),
			'year' => $picked['year'],
		);
	}
}
