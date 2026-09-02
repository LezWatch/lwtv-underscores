<?php
/**
 * Fetches what the duplicate rules need.
 *
 * The IMDb meta key differs per post type, which is the only reason this needs a
 * map rather than one read.
 *
 * @package LWTV
 */

namespace LWTV\Debugger\Collect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
	 * @param  int $post_id Candidate post ID.
	 * @return array<string, mixed>
	 */
	public function collect_one( int $post_id ): array {
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

		if ( empty( $meta ) || ! Duplicate_Rules::has_suffix( $slug ) ) {
			return $candidate;
		}

		$candidate['imdb']     = (string) get_post_meta( $post_id, $meta['imdb'], true );
		$candidate['override'] = (string) get_post_meta( $post_id, $meta['override'], true );

		$base        = Duplicate_Rules::base_slug( $slug );
		$original_id = (int) ( new Get_ID_From_Slug() )->make( $base );

		if ( ! $original_id ) {
			return $candidate;
		}

		$candidate['original'] = array(
			'id'    => $original_id,
			'slug'  => $base,
			'imdb'  => (string) get_post_meta( $original_id, $meta['imdb'], true ),
			'title' => (string) get_the_title( $original_id ),
			'url'   => (string) get_permalink( $original_id ),
		);

		return $candidate;
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
