<?php
/**
 * Build Actors Class
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

use LWTV\Queeries\Is_Actor_Queer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Actors {

	/**
	 * Generate roles statistics for an actor
	 *
	 * @param int $actor_id Actor ID
	 * @return array Role statistics
	 */
	public function generate_roles( $actor_id ) {
		// Get the character list meta for this actor
		$char_list = get_post_meta( $actor_id, 'lezactors_char_list', true );

		// If no meta exists, return empty counts
		if ( empty( $char_list ) ) {
			return array(
				array(
					'name'  => __( 'Regular', 'lwtv' ),
					'count' => 0,
				),
				array(
					'name'  => __( 'Recurring', 'lwtv' ),
					'count' => 0,
				),
				array(
					'name'  => __( 'Guest', 'lwtv' ),
					'count' => 0,
				),
			);
		}

		// Initialize role counters
		$role_counts = array(
			'regular'   => 0,
			'recurring' => 0,
			'guest'     => 0,
		);

		// Parse through each character and count their roles
		foreach ( $char_list as $character ) {
			if ( isset( $character['shows'] ) && is_array( $character['shows'] ) ) {
				foreach ( $character['shows'] as $show ) {
					if ( isset( $show['type'] ) && in_array( $show['type'], array( 'regular', 'recurring', 'guest' ), true ) ) {
						++$role_counts[ $show['type'] ];
					}
				}
			}
		}

		return array(
			array(
				'name'  => __( 'Regular', 'lwtv' ),
				'count' => $role_counts['regular'],
			),
			array(
				'name'  => __( 'Recurring', 'lwtv' ),
				'count' => $role_counts['recurring'],
			),
			array(
				'name'  => __( 'Guest', 'lwtv' ),
				'count' => $role_counts['guest'],
			),
		);
	}

	/**
	 * Generate dead statistics for an actor
	 *
	 * @param int $actor_id Actor ID
	 * @return array Dead statistics
	 */
	public function generate_dead( $actor_id ) {
		lwtv_plugin()->debug_log( 'statistics', 'Generating death statistics for actor: ' . $actor_id );
		// Get the character list meta for this actor
		$char_list = get_post_meta( $actor_id, 'lezactors_char_list', true );

		// If no meta exists, return empty counts
		if ( empty( $char_list ) ) {
			return array(
				array(
					'name'  => __( 'Alive', 'lwtv' ),
					'count' => 0,
				),
				array(
					'name'  => __( 'Dead', 'lwtv' ),
					'count' => 0,
				),
			);
		}

		// Initialize counters
		$alive_count = 0;
		$dead_count  = 0;

		// Prime the term-relationship cache for every character in one query so
		// the per-character has_term() checks below don't each hit the database.
		$character_ids = array();
		foreach ( $char_list as $character ) {
			if ( isset( $character['id'] ) ) {
				$character_ids[] = (int) $character['id'];
			}
		}
		if ( ! empty( $character_ids ) ) {
			update_object_term_cache( $character_ids, 'post_type_characters' );
		}

		// Extract character IDs and check their death status
		foreach ( $char_list as $character ) {
			if ( isset( $character['id'] ) ) {
				$character_id = (int) $character['id'];

				// Check if this character has the 'dead' term in lez_cliches taxonomy
				$has_dead_term = has_term( 'dead', 'lez_cliches', $character_id );

				if ( $has_dead_term ) {
					++$dead_count;
				} else {
					++$alive_count;
				}
			}
		}

		return array(
			array(
				'name'  => __( 'Alive', 'lwtv' ),
				'count' => $alive_count,
			),
			array(
				'name'  => __( 'Dead', 'lwtv' ),
				'count' => $dead_count,
			),
		);
	}

	/**
	 * Sitewide Regular/Recurring/Guest breakdown for the Actors → Roles
	 * view.
	 *
	 * "Role type" is stored on the character's show-group repeater (one
	 * `type` sub-field per show a character appears in — regular/recurring/
	 * guest), not on the actor directly. generate_roles() above tallies
	 * this per-actor from that actor's own cached character list; this
	 * tallies the same field across every published character's every
	 * tagged show appearance, sitewide — the "what kind of roles do
	 * queer characters (and by extension, the actors playing them) tend to
	 * get" figure the Roles page and the Actors overview headline need.
	 *
	 * Same LIKE-through-a-placeholder pattern Character_Identity_Trend
	 * uses for `_appears`, just matching the `_type` sub-field instead.
	 * Revision-safe: ACF copies repeater postmeta onto revisions, so this
	 * is scoped to published characters only, same guard Dead::
	 * get_death_date_rows() and Character_Identity_Trend both use.
	 *
	 * @return array [ 'regular' => ['name','count'], 'recurring' => [...], 'guest' => [...] ]
	 *               — shaped like Taxonomy_Optimized's term rows so templates
	 *               can unwrap it the same way as every other stats type.
	 */
	public function generate_roles_totals(): array {
		$transient = 'actor_roles_totals';
		$cached    = lwtv_plugin()->get_transient( $transient );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_value as role_type
					FROM {$wpdb->posts} chars
					INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = chars.ID
						AND pm.meta_key LIKE %s
						AND pm.meta_value != ''
					WHERE chars.post_type = 'post_type_characters'
					AND chars.post_status = 'publish'",
				$wpdb->esc_like( 'lezchars_show_group_' ) . '%' . $wpdb->esc_like( '_type' )
			),
			ARRAY_A
		);

		$counts = array(
			'regular'   => 0,
			'recurring' => 0,
			'guest'     => 0,
		);

		if ( is_array( $results ) ) {
			foreach ( $results as $row ) {
				$role_type = $row['role_type'] ?? '';
				if ( isset( $counts[ $role_type ] ) ) {
					++$counts[ $role_type ];
				}
			}
		} else {
			lwtv_plugin()->debug_log( 'statistics', 'Actor role totals query failed: ' . $wpdb->last_error );
		}

		$labels = array(
			'regular'   => __( 'Regular/Main Character', 'lwtv' ),
			'recurring' => __( 'Recurring Character', 'lwtv' ),
			'guest'     => __( 'Guest Character', 'lwtv' ),
		);

		$totals = array();
		foreach ( $counts as $type => $count ) {
			$totals[ $type ] = array(
				'name'  => $labels[ $type ],
				'count' => $count,
			);
		}

		// Character data is relatively stable — same week-long cadence
		// Character_Actor_Leaders/Character_Identity_Trend use.
		lwtv_plugin()->set_transient( $transient, $totals, WEEK_IN_SECONDS );

		return $totals;
	}

	/**
	 * Count of distinct actors with a character on screen this year, for
	 * the Actors overview Headlines lead plate.
	 *
	 * Two facts already tracked elsewhere, joined for a new purpose:
	 * which characters are on screen this year (the same `appears`
	 * sub-field On_Air_Optimized::build_characters() reads for the
	 * Characters/Shows "on air" trend), and which actors have ever played
	 * each character (`lezchars_actor`, the same relationship
	 * Character_Actor_Leaders reads).
	 *
	 * Recast wrinkle: lezchars_actor is a flat list — "select all actors
	 * who have played this character, most recent actor first" — with no
	 * year boundary per actor. There's no data path to know which actor
	 * specifically was on screen in a given year for a recast character.
	 * Per an explicit product decision, this takes only the first-listed
	 * (most recent) actor as the one active this year, rather than
	 * crediting every actor who's ever played an on-air character —
	 * accurate as long as that "most recent first" ordering is kept up to
	 * date whenever a character is recast.
	 *
	 * @return int Distinct actor count.
	 */
	public function generate_active_this_year(): int {
		$current_year = (int) ( new \DateTime( 'now', new \DateTimeZone( LWTV_TIMEZONE ) ) )->format( 'Y' );
		$transient    = 'actor_active_this_year_' . $current_year;
		$cached       = lwtv_plugin()->get_transient( $transient );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		global $wpdb;

		// Characters on screen this year: which appears-tagged show-group
		// row years include $current_year. Same LIKE-through-a-placeholder
		// pattern generate_roles_totals() above uses for `_type`.
		$appears_results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT chars.ID as id, appears.meta_value as years
					FROM {$wpdb->posts} chars
					INNER JOIN {$wpdb->postmeta} appears ON appears.post_id = chars.ID
						AND appears.meta_key LIKE %s
						AND appears.meta_value != ''
					WHERE chars.post_type = 'post_type_characters'
					AND chars.post_status = 'publish'",
				$wpdb->esc_like( 'lezchars_show_group_' ) . '%' . $wpdb->esc_like( '_appears' )
			),
			ARRAY_A
		);

		$active_char_ids = array();
		if ( is_array( $appears_results ) ) {
			foreach ( $appears_results as $row ) {
				$years = maybe_unserialize( $row['years'] );
				// ACF serializes multi-value years; a lone year can come back
				// as a plain string instead of a one-item array.
				if ( is_string( $years ) && '' !== $years ) {
					$years = array( $years );
				}
				if ( ! is_array( $years ) ) {
					continue;
				}
				foreach ( $years as $year ) {
					if ( (int) $year === $current_year ) {
						$active_char_ids[ (int) $row['id'] ] = true;
						break;
					}
				}
			}
		}

		if ( empty( $active_char_ids ) ) {
			lwtv_plugin()->set_transient( $transient, 0, DAY_IN_SECONDS );
			return 0;
		}

		// No user input: post_type/meta_key are hardcoded literals — same
		// fully-literal query Character_Actor_Leaders uses.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input; all values are hardcoded literals.
		$actor_results = $wpdb->get_results(
			"SELECT chars.ID as id, pm.meta_value as actors
				FROM {$wpdb->posts} chars
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = chars.ID AND pm.meta_key = 'lezchars_actor'
				WHERE chars.post_type = 'post_type_characters'
				AND chars.post_status = 'publish'",
			ARRAY_A
		);

		$active_actor_ids = array();
		if ( is_array( $actor_results ) ) {
			foreach ( $actor_results as $row ) {
				$char_id = (int) $row['id'];
				if ( ! isset( $active_char_ids[ $char_id ] ) ) {
					continue;
				}

				$actors = maybe_unserialize( $row['actors'] );
				$actors = is_array( $actors ) ? array_values( array_filter( array_map( 'absint', $actors ) ) ) : array();

				if ( empty( $actors ) ) {
					continue;
				}

				// "Most recent actor first" — see docblock above.
				$active_actor_ids[ $actors[0] ] = true;
			}
		}

		$count = count( $active_actor_ids );

		// Same daily cadence On_Air_Optimized uses for its year-keyed
		// transients — this is explicitly a "current year" snapshot, so it
		// should refresh sooner than the week-long cadence static term
		// breakdowns use.
		lwtv_plugin()->set_transient( $transient, $count, DAY_IN_SECONDS );

		return $count;
	}

	/**
	 * Actor IDs tagged with any of the given terms in an actor taxonomy, and
	 * how many of them are still counted as queer overall once
	 * Is_Actor_Queer's full multi-factor check (gender, pronouns, sexuality,
	 * romantic orientation, manual override) is run — the shared engine
	 * behind generate_straight_queer_gap() (Sexuality's "Straight" bucket)
	 * and generate_cis_queer_gap() (Gender's "Cisgender" bucket, which is
	 * itself three taxonomy terms: cis-woman, cis-man, cisgender).
	 *
	 * Deliberately reuses Is_Actor_Queer::make() rather than re-implementing
	 * its check here, so this figure can never drift out of sync with
	 * whatever "is this actor queer" means elsewhere on the site. That means
	 * one query per tagged actor (Is_Actor_Queer has no internal
	 * early-return cache check of its own — it only writes one after
	 * computing) rather than a single batched query; acceptable since the
	 * whole result is itself cached for a week by the public callers below.
	 *
	 * @param string $taxonomy   Actor taxonomy (e.g. 'lez_actor_sexuality').
	 * @param array  $term_slugs Term slugs that make up the "default" bucket.
	 * @return array { 'tagged_total' => int, 'queer_anyway' => int }
	 */
	private function count_queer_among_terms( string $taxonomy, array $term_slugs ): array {
		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $term_slugs ), '%s' ) );

		// $placeholders is a fixed-count list of %s tokens (not user input);
		// every actual value is still passed through prepare() below.
		// phpcs:disable
		$actor_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
					INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = %s
					INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id AND t.slug IN ($placeholders)
					WHERE p.post_type = 'post_type_actors'
					AND p.post_status = 'publish'",
				array_merge( array( $taxonomy ), $term_slugs )
			)
		);
		// phpcs:enable

		$tagged_total = is_array( $actor_ids ) ? count( $actor_ids ) : 0;
		$queer_anyway = 0;

		if ( $tagged_total > 0 ) {
			$is_actor_queer = new Is_Actor_Queer();
			foreach ( $actor_ids as $actor_id ) {
				if ( $is_actor_queer->make( (int) $actor_id ) ) {
					++$queer_anyway;
				}
			}
		}

		return array(
			'tagged_total' => $tagged_total,
			'queer_anyway' => $queer_anyway,
		);
	}

	/**
	 * How many actors tagged "Straight" by sexual orientation alone are
	 * still counted as queer once gender, pronouns, romantic orientation,
	 * and the manual override are factored in — for the Actors → Sexuality
	 * "Straight" bucket isn't the whole queerness story.
	 *
	 * @return array { 'straight_total' => int, 'queer_anyway' => int }
	 */
	public function generate_straight_queer_gap(): array {
		$transient = 'actor_straight_queer_gap';
		$cached    = lwtv_plugin()->get_transient( $transient );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$counts = $this->count_queer_among_terms( 'lez_actor_sexuality', array( 'heterosexual' ) );

		$gap = array(
			'straight_total' => $counts['tagged_total'],
			'queer_anyway'   => $counts['queer_anyway'],
		);

		lwtv_plugin()->set_transient( $transient, $gap, WEEK_IN_SECONDS );

		return $gap;
	}

	/**
	 * How many actors tagged "Cisgender" by gender identity alone (across
	 * the three cis slugs the Gender donut folds into one "Cisgender"
	 * segment — cis-woman, cis-man, cisgender) are still counted as queer
	 * once sexuality, pronouns, romantic orientation, and the manual
	 * override are factored in — for the Actors → Gender "Cisgender"
	 * bucket isn't the whole queerness story either.
	 *
	 * @return array { 'cis_total' => int, 'queer_anyway' => int }
	 */
	public function generate_cis_queer_gap(): array {
		$transient = 'actor_cis_queer_gap';
		$cached    = lwtv_plugin()->get_transient( $transient );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$counts = $this->count_queer_among_terms( 'lez_actor_gender', array( 'cis-woman', 'cis-man', 'cisgender' ) );

		$gap = array(
			'cis_total'    => $counts['tagged_total'],
			'queer_anyway' => $counts['queer_anyway'],
		);

		lwtv_plugin()->set_transient( $transient, $gap, WEEK_IN_SECONDS );

		return $gap;
	}

	/**
	 * Character count per actor, counting every actor-character
	 * relationship (not just "most recent", unlike
	 * generate_active_this_year()'s recast handling above) — breadth of
	 * roles played, not who's active right now, so a past recast still
	 * counts toward an actor's total. Shared engine behind every
	 * generate_prolific_by_*() method below.
	 *
	 * @return array actor_id => character count.
	 */
	private function get_actor_character_counts(): array {
		global $wpdb;

		// Character → actors, same fully-literal query Character_Actor_Leaders
		// uses (no user input, so no prepare() needed).
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input; all values are hardcoded literals.
		$char_actor_results = $wpdb->get_results(
			"SELECT chars.ID as id, pm.meta_value as actors
				FROM {$wpdb->posts} chars
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = chars.ID AND pm.meta_key = 'lezchars_actor'
				WHERE chars.post_type = 'post_type_characters'
				AND chars.post_status = 'publish'",
			ARRAY_A
		);

		$actor_char_counts = array();
		if ( is_array( $char_actor_results ) ) {
			foreach ( $char_actor_results as $row ) {
				$actors = maybe_unserialize( $row['actors'] );
				$actors = is_array( $actors ) ? array_values( array_filter( array_map( 'absint', $actors ) ) ) : array();
				foreach ( $actors as $actor_id ) {
					// Unknown_Actor::ACTOR_ID (post 14080) is the "Unknown"
					// placeholder actor — a catch-all for roles with no
					// confirmed performer — so it must never be counted
					// toward, or win, any most-prolific-actor leaderboard.
					// Same guard Characters_Builder uses for "busiest actor".
					if ( Unknown_Actor::ACTOR_ID === $actor_id ) {
						continue;
					}
					$actor_char_counts[ $actor_id ] = ( $actor_char_counts[ $actor_id ] ?? 0 ) + 1;
				}
			}
		}

		return $actor_char_counts;
	}

	/**
	 * The most-prolific actor for each term in an actor taxonomy — the
	 * actor linked to the most published characters, grouped by their own
	 * term. Shared engine behind generate_prolific_by_orientation()
	 * (lez_actor_sexuality) and generate_prolific_by_gender()
	 * (lez_actor_gender).
	 *
	 * @param string $taxonomy Actor taxonomy.
	 * @return array [ term_slug => { 'actor_id', 'name', 'url', 'count', 'term_name' } ],
	 *               only for terms with at least one actor with 1+ characters.
	 */
	private function get_prolific_by_taxonomy( string $taxonomy ): array {
		$actor_char_counts = $this->get_actor_character_counts();

		if ( empty( $actor_char_counts ) ) {
			return array();
		}

		global $wpdb;

		// Actor → taxonomy term. Ordered by ID ascending so that, on a tied
		// character count, the earliest-catalogued actor wins — deterministic
		// rather than depending on incidental row order.
		$actor_term_results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID as id, p.post_title as name, t.slug as term_slug, t.name as term_name
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
					INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = %s
					INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
					WHERE p.post_type = 'post_type_actors'
					AND p.post_status = 'publish'
					ORDER BY p.ID ASC",
				$taxonomy
			),
			ARRAY_A
		);

		$leaders = array();
		if ( is_array( $actor_term_results ) ) {
			foreach ( $actor_term_results as $row ) {
				$actor_id = (int) $row['id'];
				$count    = $actor_char_counts[ $actor_id ] ?? 0;

				if ( $count <= 0 ) {
					continue;
				}

				$slug = $row['term_slug'];

				if ( ! isset( $leaders[ $slug ] ) || $count > $leaders[ $slug ]['count'] ) {
					$leaders[ $slug ] = array(
						'actor_id'  => $actor_id,
						'name'      => $row['name'],
						'url'       => get_permalink( $actor_id ),
						'count'     => $count,
						'term_name' => $row['term_name'],
					);
				}
			}
		}

		return $leaders;
	}

	/**
	 * The most-prolific actor for each tracked sexual orientation.
	 *
	 * @return array [ term_slug => { 'actor_id', 'name', 'url', 'count', 'term_name' } ]
	 */
	public function generate_prolific_by_orientation(): array {
		$transient = 'actor_prolific_by_orientation';
		$cached    = lwtv_plugin()->get_transient( $transient );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$leaders = $this->get_prolific_by_taxonomy( 'lez_actor_sexuality' );

		// Character data is relatively stable — same week-long cadence
		// generate_roles_totals() above uses.
		lwtv_plugin()->set_transient( $transient, $leaders, WEEK_IN_SECONDS );

		return $leaders;
	}

	/**
	 * The most-prolific actor for each tracked gender identity term.
	 *
	 * Returned per raw taxonomy term (cis-woman/cis-man/cisgender kept
	 * separate, not pre-merged) — same shape as
	 * generate_prolific_by_orientation(). Templates that fold the three cis
	 * slugs into one "Cisgender" bucket (matching the Gender donut) should
	 * take the max-count entry across those three slugs themselves; the max
	 * of a union's subgroup maxes is always the union's max, so that's a
	 * safe merge to do after the fact rather than re-querying.
	 *
	 * @return array [ term_slug => { 'actor_id', 'name', 'url', 'count', 'term_name' } ]
	 */
	public function generate_prolific_by_gender(): array {
		$transient = 'actor_prolific_by_gender';
		$cached    = lwtv_plugin()->get_transient( $transient );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$leaders = $this->get_prolific_by_taxonomy( 'lez_actor_gender' );

		lwtv_plugin()->set_transient( $transient, $leaders, WEEK_IN_SECONDS );

		return $leaders;
	}

	/**
	 * The first-listed (most recent) actor for every published character —
	 * the same "most recent actor first" approximation generate_active_this_year()
	 * uses for recast attribution, reused here because role type (like
	 * on-air year) lives on the character's show-group row, not the actor,
	 * and lezchars_actor has no per-row attribution to resolve which actor
	 * actually played which specific row. A recast character's every
	 * appearance — regardless of type — ends up credited to whichever actor
	 * is listed first today.
	 *
	 * @return array [ char_id => actor_id ]. Characters with no actor on
	 *               record, or whose first-listed actor is the "Unknown"
	 *               placeholder (Unknown_Actor::ACTOR_ID), are omitted entirely.
	 */
	private function get_first_actor_by_character(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input; all values are hardcoded literals.
		$results = $wpdb->get_results(
			"SELECT chars.ID as id, pm.meta_value as actors
				FROM {$wpdb->posts} chars
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = chars.ID AND pm.meta_key = 'lezchars_actor'
				WHERE chars.post_type = 'post_type_characters'
				AND chars.post_status = 'publish'",
			ARRAY_A
		);

		$first_actor = array();
		if ( is_array( $results ) ) {
			foreach ( $results as $row ) {
				$actors = maybe_unserialize( $row['actors'] );
				$actors = is_array( $actors ) ? array_values( array_filter( array_map( 'absint', $actors ) ) ) : array();

				if ( empty( $actors ) ) {
					continue;
				}

				// Unknown_Actor::ACTOR_ID (post 14080) is the "Unknown"
				// placeholder actor — same exclusion
				// get_actor_character_counts() applies, so it can never be
				// handed credit for a "most prolific" role type.
				if ( Unknown_Actor::ACTOR_ID === $actors[0] ) {
					continue;
				}

				$first_actor[ (int) $row['id'] ] = $actors[0];
			}
		}

		return $first_actor;
	}

	/**
	 * The most-prolific actor for each Regular/Recurring/Guest role type —
	 * the actor credited with the most tagged show-group appearances of
	 * that type, via the "most recent actor first" approximation above.
	 *
	 * @return array [ 'regular'|'recurring'|'guest' => { 'actor_id', 'name', 'url', 'count' } ],
	 *               only for types with at least one qualifying actor.
	 */
	public function generate_prolific_by_role(): array {
		$transient = 'actor_prolific_by_role';
		$cached    = lwtv_plugin()->get_transient( $transient );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$first_actor = $this->get_first_actor_by_character();

		if ( empty( $first_actor ) ) {
			lwtv_plugin()->set_transient( $transient, array(), WEEK_IN_SECONDS );
			return array();
		}

		global $wpdb;

		// Every show-group row's type — same LIKE-through-a-placeholder
		// pattern generate_roles_totals() uses.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT chars.ID as id, pm.meta_value as role_type
					FROM {$wpdb->posts} chars
					INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = chars.ID
						AND pm.meta_key LIKE %s
						AND pm.meta_value != ''
					WHERE chars.post_type = 'post_type_characters'
					AND chars.post_status = 'publish'",
				$wpdb->esc_like( 'lezchars_show_group_' ) . '%' . $wpdb->esc_like( '_type' )
			),
			ARRAY_A
		);

		$tallies = array(
			'regular'   => array(),
			'recurring' => array(),
			'guest'     => array(),
		);

		if ( is_array( $results ) ) {
			foreach ( $results as $row ) {
				$type = $row['role_type'] ?? '';
				if ( ! isset( $tallies[ $type ] ) ) {
					continue;
				}
				$char_id = (int) $row['id'];
				if ( ! isset( $first_actor[ $char_id ] ) ) {
					continue;
				}
				$actor_id                      = $first_actor[ $char_id ];
				$tallies[ $type ][ $actor_id ] = ( $tallies[ $type ][ $actor_id ] ?? 0 ) + 1;
			}
		}

		$leaders = array();
		foreach ( $tallies as $type => $actor_counts ) {
			if ( empty( $actor_counts ) ) {
				continue;
			}

			// Ties broken by lowest actor ID for a deterministic pick.
			$best_actor_id = null;
			$best_count    = 0;
			foreach ( $actor_counts as $actor_id => $count ) {
				if ( null === $best_actor_id || $count > $best_count || ( $count === $best_count && $actor_id < $best_actor_id ) ) {
					$best_actor_id = $actor_id;
					$best_count    = $count;
				}
			}

			$leaders[ $type ] = array(
				'actor_id' => $best_actor_id,
				'name'     => get_the_title( $best_actor_id ),
				'url'      => get_permalink( $best_actor_id ),
				'count'    => $best_count,
			);
		}

		// Character data is relatively stable — same week-long cadence
		// generate_roles_totals() above uses.
		lwtv_plugin()->set_transient( $transient, $leaders, WEEK_IN_SECONDS );

		return $leaders;
	}
}
