<?php
/**
 * Fetches what the duplicate rules need.
 *
 * The IMDb meta key differs per post type, which is the only reason this needs a
 * map rather than one read.
 *
 * Candidates come from two places. The original source is a slug scan: a post
 * whose slug ends in a number, paired with the post whose slug it is a
 * suffixed copy of. That only ever finds a duplicate whose title was typed
 * identically the second time, because only an identical title collides in
 * wp_unique_post_slug() and earns the `-2`.
 *
 * So actors also come from name_key_pairs(), which pairs them on the comparable
 * name keys instead. "Cynthia Hicks" and "Cynthia Jimenez-Hicks" were one
 * person under two slugs, neither suffixed, sharing an IMDb ID -- the evidence
 * the rules need was already stored and the slug scan simply never handed over
 * the pair.
 *
 * @package LWTV
 */

namespace LWTV\Debugger\Collect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Actors;
use LWTV\Debugger\Build\Duplicate_Rules;
use LWTV\Queeries\Get_ID_From_Slug;

class Duplicate_Collector {

	/**
	 * Meta keys per post type: the IMDb ID, and the "not a duplicate" override.
	 *
	 * @var array<string, array<string, string>>
	 */
	private const META = array(
		'post_type_shows'  => array(
			'imdb'     => 'lezshows_imdb',
			'override' => 'lezshows_dupe_override',
		),
		'post_type_actors' => array(
			'imdb'     => 'lezactors_imdb',
			'override' => 'lezactors_dupe_override',
		),
	);

	/**
	 * Every published show or actor whose slug ends in a number.
	 *
	 * @return array<int> Post IDs.
	 */
	public function candidate_ids(): array {
		global $wpdb;

		$post_types   = array_keys( self::META );
		$placeholders = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );

		$query = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is a generated list of %s.
			"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ({$placeholders}) AND post_name REGEXP %s",
			array_merge( $post_types, array( '-[0-9]+$' ) )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$all_posts = $wpdb->get_results( $query );

		return array_values( array_unique( array_map( 'intval', wp_list_pluck( $all_posts, 'ID' ) ) ) );
	}

	/**
	 * Collect one candidate.
	 *
	 * @param  int $post_id     Candidate post ID.
	 * @param  int $original_id The post it is paired against. Zero resolves the
	 *                          pairing from the slug, which is the original
	 *                          behaviour; a name-key pair passes it in already
	 *                          known, because there is no slug to derive it from.
	 * @return array<string, mixed>
	 */
	public function collect_one( int $post_id, int $original_id = 0 ): array {
		$post_type = (string) get_post_type( $post_id );
		$slug      = (string) get_post_field( 'post_name', $post_id );
		$meta      = self::META[ $post_type ] ?? array();

		$candidate = array(
			'post_id'   => $post_id,
			'post_type' => $post_type,
			'slug'      => $slug,
			'title'     => (string) get_the_title( $post_id ),
			'imdb'      => '',
			'override'  => '',
			'original'  => array(),
		);

		if ( empty( $meta ) ) {
			return $candidate;
		}

		// Only the slug path needs a suffix to work from.
		if ( 0 === $original_id && ! Duplicate_Rules::has_suffix( $slug ) ) {
			return $candidate;
		}

		$candidate['imdb'] = (string) get_post_meta( $post_id, $meta['imdb'], true );

		// Deliberately not cast: lezactors_dupe_override holds an array of post
		// IDs, and casting one to string would both warn and flatten the pair
		// information Duplicate_Rules::is_acknowledged() needs.
		$candidate['override'] = get_post_meta( $post_id, $meta['override'], true );

		$original_slug = '';

		if ( 0 === $original_id ) {
			$original_slug = Duplicate_Rules::base_slug( $slug );
			$original_id   = (int) ( new Get_ID_From_Slug() )->make( $original_slug );
		} else {
			$original_slug = (string) get_post_field( 'post_name', $original_id );
		}

		if ( ! $original_id ) {
			return $candidate;
		}

		$candidate['original'] = array(
			'id'    => $original_id,
			'slug'  => $original_slug,
			'imdb'  => (string) get_post_meta( $original_id, $meta['imdb'], true ),
			'title' => (string) get_the_title( $original_id ),
			'url'   => (string) get_permalink( $original_id ),
		);

		return $candidate;
	}

	/**
	 * Actors paired by a shared name key.
	 *
	 * Grouped in PHP rather than joined in SQL: postmeta has no index on
	 * meta_value, so a self-join on it across every actor is a table scan
	 * against itself, while pulling the rows the meta_key index already narrows
	 * and grouping them is linear.
	 *
	 * Both key families count. The loose first-and-last-part key is the one that
	 * catches a dropped or added middle name, which is exactly the shape the
	 * Cynthia Hicks duplicate had. Noise is not a concern here the way it is on
	 * the edit screen, because Duplicate_Rules still requires a matching IMDb ID
	 * before it will call anything a duplicate.
	 *
	 * The lower post ID is treated as the original, so a pair is reported once
	 * and the newer post is the one flagged -- the same convention the slug scan
	 * arrives at, where the `-2` copy came second.
	 *
	 * @return array<int, array{post_id: int, original_id: int}>
	 */
	public function name_key_pairs(): array {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT pm.post_id, pm.meta_key, pm.meta_value
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key IN ( %s, %s )
			AND p.post_type = %s
			AND p.post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )",
			Actors::NAME_KEY_META,
			Actors::NAME_ENDS_META,
			Actors::SLUG
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $query );

		$groups = array();

		foreach ( (array) $rows as $row ) {
			// Keyed by meta_key as well as value, so a strict key never groups
			// with a loose one that happens to read the same.
			$groups[ $row->meta_key . '|' . $row->meta_value ][] = (int) $row->post_id;
		}

		$pairs = array();

		foreach ( $groups as $ids ) {
			$ids = array_values( array_unique( $ids ) );

			if ( count( $ids ) < 2 ) {
				continue;
			}

			sort( $ids );
			$original_id = (int) array_shift( $ids );

			foreach ( $ids as $post_id ) {
				// Keyed so the same pair found on two keys is only collected once.
				$pairs[ $post_id . ':' . $original_id ] = array(
					'post_id'     => (int) $post_id,
					'original_id' => $original_id,
				);
			}
		}

		return array_values( $pairs );
	}

	/**
	 * Collect a list of already-paired candidates.
	 *
	 * @param  array<int, array{post_id: int, original_id: int}> $pairs Pairs.
	 * @return array<int, array<string, mixed>>
	 */
	public function collect_pairs( array $pairs ): array {
		$collected = array();

		foreach ( $pairs as $pair ) {
			$post_id     = (int) ( $pair['post_id'] ?? 0 );
			$original_id = (int) ( $pair['original_id'] ?? 0 );

			if ( ! $post_id || ! $original_id ) {
				continue;
			}

			$collected[] = $this->collect_one( $post_id, $original_id );
		}

		return $collected;
	}

	/**
	 * Collect a list of candidates.
	 *
	 * @param  array<int> $post_ids Candidate post IDs.
	 * @return array<int, array<string, mixed>>
	 */
	public function collect( array $post_ids ): array {
		$collected = array();

		foreach ( array_unique( array_map( 'intval', $post_ids ) ) as $post_id ) {
			if ( $post_id ) {
				$collected[] = $this->collect_one( $post_id );
			}
		}

		return $collected;
	}
}
