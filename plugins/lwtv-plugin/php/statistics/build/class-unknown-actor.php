<?php
/**
 * Unknown Actor Spotlight Query Class
 *
 * Post ID 14080 is the "Unknown" placeholder actor — assigned to characters
 * with no confirmed real-world performer on record. Every other actor-facing
 * stat on the site deliberately excludes it (see Build_Actors::
 * get_actor_character_counts() and Character_Queer_Cast_Firsts::
 * build_trans_actor_oldest()); this class is the one place that queries *for*
 * it on purpose, turning a data gap into its own spotlight page: how many
 * characters are affected, who they are, and which shows carry the most of
 * them.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Unknown_Actor {

	/**
	 * Post ID of the "Unknown" placeholder actor.
	 */
	const ACTOR_ID = 14080;

	/**
	 * Full spotlight report: every facet the Actors → Unknown Actor page
	 * needs, built from one shared base character set so the handful of
	 * queries below only ever run once per cache cycle.
	 *
	 * @return array {
	 *   @type int   $character_count Characters carrying the Unknown actor.
	 *   @type int   $show_count      Distinct shows among those characters.
	 *   @type array $gender          [ slug => { 'name', 'count' } ], character-level lez_gender, count desc.
	 *   @type array $sexuality       [ slug => { 'name', 'count' } ], character-level lez_sexuality, count desc.
	 *   @type array $oldest          { 'name', 'url', 'year' } or empty.
	 *   @type array $newest          Same shape as $oldest.
	 *   @type array $top_shows       Up to 5: [ { 'show_id', 'name', 'url', 'count' } ], count desc.
	 *   @type array $roles           [ 'regular'|'recurring'|'guest' => { 'name', 'count' } ].
	 *   @type array $dead            { 'alive' => int, 'dead' => int }.
	 * }
	 */
	public function generate_report(): array {
		$transient = 'unknown_actor_report';
		$cached    = lwtv_plugin()->get_transient( $transient );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$char_rows = $this->get_characters();

		if ( empty( $char_rows ) ) {
			$empty = array(
				'character_count' => 0,
				'show_count'      => 0,
				'gender'          => array(),
				'sexuality'       => array(),
				'oldest'          => array(),
				'newest'          => array(),
				'top_shows'       => array(),
				'roles'           => array(),
				'dead'            => array(
					'alive' => 0,
					'dead'  => 0,
				),
			);
			lwtv_plugin()->set_transient( $transient, $empty, WEEK_IN_SECONDS );
			return $empty;
		}

		$char_ids = array_keys( $char_rows );

		$report = array(
			'character_count' => count( $char_ids ),
			'show_count'      => 0,
			'gender'          => $this->get_term_breakdown( $char_ids, 'lez_gender' ),
			'sexuality'       => $this->get_term_breakdown( $char_ids, 'lez_sexuality' ),
			'oldest'          => array(),
			'newest'          => array(),
			'top_shows'       => array(),
			'roles'           => array(),
			'dead'            => $this->get_dead_split( $char_ids ),
		);

		$show_group = $this->get_show_group_rows( $char_ids );

		// Time dimension: each character's own earliest on-screen year (same
		// per-character minimum every other Firsts callout on the site uses),
		// then the min/max of that across this character set.
		$years_by_char = array();
		foreach ( $show_group['appears'] as $char_id => $years ) {
			if ( empty( $years ) ) {
				continue;
			}
			$min_year = min( $years );
			if ( ! isset( $years_by_char[ $char_id ] ) || $min_year < $years_by_char[ $char_id ] ) {
				$years_by_char[ $char_id ] = $min_year;
			}
		}

		if ( ! empty( $years_by_char ) ) {
			$oldest_id = null;
			$newest_id = null;
			foreach ( $years_by_char as $char_id => $year ) {
				if ( null === $oldest_id || $year < $years_by_char[ $oldest_id ] || ( $year === $years_by_char[ $oldest_id ] && $char_id < $oldest_id ) ) {
					$oldest_id = $char_id;
				}
				if ( null === $newest_id || $year > $years_by_char[ $newest_id ] || ( $year === $years_by_char[ $newest_id ] && $char_id < $newest_id ) ) {
					$newest_id = $char_id;
				}
			}
			$report['oldest'] = array(
				'name' => $char_rows[ $oldest_id ]['name'],
				'url'  => get_permalink( $oldest_id ),
				'year' => $years_by_char[ $oldest_id ],
			);
			$report['newest'] = array(
				'name' => $char_rows[ $newest_id ]['name'],
				'url'  => get_permalink( $newest_id ),
				'year' => $years_by_char[ $newest_id ],
			);
		}

		// Shows: dedupe (show, character) pairs first so a data-entry
		// duplicate row can't inflate a show's count, then tally distinct
		// characters per show.
		$show_char_pairs = array();
		foreach ( $show_group['shows'] as $char_id => $show_ids ) {
			foreach ( array_unique( $show_ids ) as $show_id ) {
				$show_char_pairs[ $show_id . ':' . $char_id ] = $show_id;
			}
		}
		$show_counts = array();
		foreach ( $show_char_pairs as $show_id ) {
			$show_counts[ $show_id ] = ( $show_counts[ $show_id ] ?? 0 ) + 1;
		}
		$report['show_count'] = count( $show_counts );

		arsort( $show_counts );
		$top_shows = array();
		$top_i     = 0;
		foreach ( $show_counts as $show_id => $count ) {
			if ( $top_i >= 5 ) {
				break;
			}
			$top_shows[] = array(
				'show_id' => $show_id,
				'name'    => get_the_title( $show_id ),
				'url'     => get_permalink( $show_id ),
				'count'   => $count,
			);
			++$top_i;
		}
		$report['top_shows'] = $top_shows;

		// Roles: tally every show-group row's type for these characters —
		// same sitewide mechanism Build_Actors::generate_roles_totals() uses,
		// just scoped to this character set.
		$role_counts = array(
			'regular'   => 0,
			'recurring' => 0,
			'guest'     => 0,
		);
		foreach ( $show_group['types'] as $char_id => $types ) {
			foreach ( $types as $type ) {
				if ( isset( $role_counts[ $type ] ) ) {
					++$role_counts[ $type ];
				}
			}
		}
		$role_labels = array(
			'regular'   => __( 'Regular/Main Character', 'lwtv' ),
			'recurring' => __( 'Recurring Character', 'lwtv' ),
			'guest'     => __( 'Guest Character', 'lwtv' ),
		);
		$roles       = array();
		foreach ( $role_counts as $type => $count ) {
			$roles[ $type ] = array(
				'name'  => $role_labels[ $type ],
				'count' => $count,
			);
		}
		$report['roles'] = $roles;

		lwtv_plugin()->set_transient( $transient, $report, WEEK_IN_SECONDS );

		return $report;
	}

	/**
	 * Every published character carrying the Unknown actor in its
	 * lezchars_actor relationship.
	 *
	 * @return array [ int $char_id => { 'name' => string } ]
	 */
	private function get_characters(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input; all values are hardcoded literals.
		$results = $wpdb->get_results(
			"SELECT chars.ID as id, chars.post_title as name, pm.meta_value as actors
				FROM {$wpdb->posts} chars
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = chars.ID AND pm.meta_key = 'lezchars_actor'
				WHERE chars.post_type = 'post_type_characters'
				AND chars.post_status = 'publish'",
			ARRAY_A
		);

		$char_rows = array();
		if ( is_array( $results ) ) {
			foreach ( $results as $row ) {
				$actors = maybe_unserialize( $row['actors'] );
				$actors = is_array( $actors ) ? array_values( array_unique( array_filter( array_map( 'absint', $actors ) ) ) ) : array();

				if ( ! in_array( self::ACTOR_ID, $actors, true ) ) {
					continue;
				}

				$char_rows[ (int) $row['id'] ] = array(
					'name' => $row['name'],
				);
			}
		}

		return $char_rows;
	}

	/**
	 * Character-level taxonomy breakdown (lez_gender / lez_sexuality) for a
	 * given set of character IDs, ranked count-descending.
	 *
	 * @param array  $char_ids Character post IDs.
	 * @param string $taxonomy Character taxonomy.
	 * @return array [ term_slug => { 'name', 'count' } ]
	 */
	private function get_term_breakdown( array $char_ids, string $taxonomy ): array {
		if ( empty( $char_ids ) ) {
			return array();
		}

		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $char_ids ), '%d' ) );

		// $placeholders is a fixed-count list of %d tokens (not user input);
		// every actual value is still passed through prepare() below.
		// phpcs:disable
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.slug as term_slug, t.name as term_name
					FROM {$wpdb->term_relationships} tr
					INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = %s
					INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
					WHERE tr.object_id IN ($placeholders)",
				array_merge( array( $taxonomy ), $char_ids )
			),
			ARRAY_A
		);
		// phpcs:enable

		$breakdown = array();
		if ( is_array( $results ) ) {
			foreach ( $results as $row ) {
				$slug = $row['term_slug'];
				if ( ! isset( $breakdown[ $slug ] ) ) {
					$breakdown[ $slug ] = array(
						'name'  => $row['term_name'],
						'count' => 0,
					);
				}
				++$breakdown[ $slug ]['count'];
			}
		}

		uasort( $breakdown, static fn( $a, $b ) => $b['count'] <=> $a['count'] );

		return $breakdown;
	}

	/**
	 * Dead/alive split for a set of characters, same lez_cliches "dead" term
	 * check Build_Actors::generate_dead() uses per-actor, just applied
	 * directly to this character set.
	 *
	 * @param array $char_ids Character post IDs.
	 * @return array { 'alive' => int, 'dead' => int }
	 */
	private function get_dead_split( array $char_ids ): array {
		if ( empty( $char_ids ) ) {
			return array(
				'alive' => 0,
				'dead'  => 0,
			);
		}

		// Prime the term-relationship cache for every character in one query
		// so the per-character has_term() checks below don't each hit the
		// database — same priming Build_Actors::generate_dead() uses.
		update_object_term_cache( $char_ids, 'post_type_characters' );

		$alive = 0;
		$dead  = 0;
		foreach ( $char_ids as $char_id ) {
			if ( has_term( 'dead', 'lez_cliches', $char_id ) ) {
				++$dead;
			} else {
				++$alive;
			}
		}

		return array(
			'alive' => $alive,
			'dead'  => $dead,
		);
	}

	/**
	 * Raw show-group repeater data for a set of characters: each row's
	 * `appears` years, `show` ID, and `type`, gathered independently (no
	 * need to correlate row-by-row across sub-fields — appears/shows/roles
	 * are each tallied on their own, same as Build_Actors::
	 * generate_roles_totals() and generate_active_this_year() do).
	 *
	 * @param array $char_ids Character post IDs.
	 * @return array {
	 *   @type array $appears [ char_id => int[] years ].
	 *   @type array $shows   [ char_id => int[] show IDs ].
	 *   @type array $types   [ char_id => string[] role types ].
	 * }
	 */
	private function get_show_group_rows( array $char_ids ): array {
		$empty = array(
			'appears' => array(),
			'shows'   => array(),
			'types'   => array(),
		);

		if ( empty( $char_ids ) ) {
			return $empty;
		}

		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $char_ids ), '%d' ) );

		// $placeholders is a fixed-count list of %d tokens (not user input);
		// every actual value is still passed through prepare() below.
		// phpcs:disable
		$appears_results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id as id, meta_value as val
					FROM {$wpdb->postmeta}
					WHERE post_id IN ($placeholders)
					AND meta_key LIKE %s
					AND meta_value != ''",
				array_merge( $char_ids, array( $wpdb->esc_like( 'lezchars_show_group_' ) . '%' . $wpdb->esc_like( '_appears' ) ) )
			),
			ARRAY_A
		);

		$shows_results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id as id, meta_value as val
					FROM {$wpdb->postmeta}
					WHERE post_id IN ($placeholders)
					AND meta_key LIKE %s
					AND meta_value != ''",
				array_merge( $char_ids, array( $wpdb->esc_like( 'lezchars_show_group_' ) . '%' . $wpdb->esc_like( '_show' ) ) )
			),
			ARRAY_A
		);

		$types_results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id as id, meta_value as val
					FROM {$wpdb->postmeta}
					WHERE post_id IN ($placeholders)
					AND meta_key LIKE %s
					AND meta_value != ''",
				array_merge( $char_ids, array( $wpdb->esc_like( 'lezchars_show_group_' ) . '%' . $wpdb->esc_like( '_type' ) ) )
			),
			ARRAY_A
		);
		// phpcs:enable

		$appears = array();
		foreach ( (array) $appears_results as $row ) {
			$years = maybe_unserialize( $row['val'] );
			if ( is_string( $years ) && '' !== $years ) {
				$years = array( $years );
			}
			if ( ! is_array( $years ) ) {
				continue;
			}
			$char_id = (int) $row['id'];
			foreach ( $years as $year ) {
				$appears[ $char_id ][] = (int) $year;
			}
		}

		$shows = array();
		foreach ( (array) $shows_results as $row ) {
			$char_id             = (int) $row['id'];
			$shows[ $char_id ][] = (int) $row['val'];
		}

		$types = array();
		foreach ( (array) $types_results as $row ) {
			$char_id             = (int) $row['id'];
			$types[ $char_id ][] = $row['val'];
		}

		return array(
			'appears' => $appears,
			'shows'   => $shows,
			'types'   => $types,
		);
	}
}
