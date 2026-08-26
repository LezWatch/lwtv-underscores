<?php
/*
 * Find all Duplicates.
 */

namespace LWTV\Debugger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Actors as CPT_Actors;
use LWTV\Debugger\Build\Findings;
use LWTV\Debugger\Format\Rows;
use LWTV\Queeries\Get_ID_From_Slug;

class Dupes {

	/**
	 * Transient holding the results of find_duplicates().
	 */
	const TRANSIENT_DUPES = 'lwtv_debug_duplicates';

	/**
	 * Find Duplicates
	 *
	 * Find all posts that end in -2
	 *
	 * @param array $items - array of Posts
	 */
	public function find_duplicates( $items = array() ): array {
		$duplicates     = array();
		$items_to_check = array();

		// A recheck only revisits the posts already flagged, so it is tagged
		// against the baseline rather than diffed against it. See tag_only().
		$is_recheck = ! empty( $items ) && is_array( $items );

		// Are we a full scan or a recheck?
		if ( ! empty( $items ) && is_array( $items ) ) {
			foreach ( $items as $item ) {
				$items_to_check[] = $item['id'];
			}
		} else {
			$items_to_check = $this->get_dupes();
		}

		$findings = array();

		foreach ( $items_to_check as $maybe_dupe ) {
			$check_dupe = $this->compare_duplicates( $maybe_dupe );

			if ( false === $check_dupe ) {
				continue;
			}

			$post_type = (string) get_post_type( $maybe_dupe );

			/*
			 * Two issue types, one per post type, because a finding's level
			 * decides which cache an admin repair prunes and which tab it
			 * returns to -- and this is the only check spanning both.
			 */
			$issue_type = ( CPT_Actors::SLUG === $post_type ) ? 'actor-is-duplicate' : 'show-is-duplicate';

			// The message keeps its link to the original, as the admin table has
			// always rendered it; Findings::plain() strips it for the CLI.
			$findings[] = Findings::make( (int) $maybe_dupe, $post_type, $issue_type, $check_dupe );
		}

		$diff       = $is_recheck
			? Baseline_Store::tag_only( 'duplicates', $findings )
			: Baseline_Store::apply( 'duplicates', $findings );
		$duplicates = Rows::from_findings( $diff['findings'] );

		// `name` is not in the standard row shape: cli-dupes.php names it as an
		// output column, and a post ID alone tells you nothing in that table.
		foreach ( $duplicates as $index => $duplicate ) {
			$duplicates[ $index ]['name'] = get_the_title( (int) $duplicate['id'] );
		}

		// Save Transient
		lwtv_plugin()->set_transient( self::TRANSIENT_DUPES, $duplicates, WEEK_IN_SECONDS );

		// Update Options
		Status::record( 'duplicates', 'Duplicate Actors/Shows', count( $duplicates ), $diff['summary'] );

		return $duplicates;
	}

	/**
	 * Get Duplicates
	 *
	 * Get all posts that end in -2
	 *
	 * @return array
	 */
	public function get_dupes() {
		global $wpdb;

		// REGEXP catches any numeric suffix (-2, -3, -4, etc.), not just -2.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$all_posts      = $wpdb->get_results( "SELECT ID FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('post_type_shows', 'post_type_actors') AND post_name REGEXP '-[0-9]+$'" );
		$all_post_array = array_unique( wp_list_pluck( $all_posts, 'ID' ) );

		return $all_post_array;
	}

	/**
	 * Compare Duplicates
	 *
	 * If the duplicate has the same IMDb as the original, and isn't
	 * using an override, return true.
	 *
	 * @param int   $post_id - Post ID to check
	 * @return bool|string
	 */
	public function compare_duplicates( $post_id ) {
		$slugs = array(
			'duplicate' => get_post_field( 'post_name', $post_id ),
			// Strip any numeric suffix, not just a two-character '-2'. get_dupes()
			// matches -[0-9]+ so '-10' and up need handling too.
			'original'  => preg_replace( '/-[0-9]+$/', '', (string) get_post_field( 'post_name', $post_id ) ),
		);

		// Defaults matter: the switch below only fills these for shows and actors,
		// so an unexpected post type would otherwise hit undefined keys.
		$duplicate = array(
			'id'        => $post_id,
			'slug'      => $slugs['duplicate'],
			'post_type' => get_post_type( $post_id ),
			'imdb'      => '',
			'override'  => '',
		);
		$original  = array(
			'id'   => ( new Get_ID_From_Slug() )->make( $slugs['original'] ),
			'slug' => $slugs['original'],
			'imdb' => '',
		);

		if ( empty( $original['id'] ) ) {
			return false;
		}

		switch ( $duplicate['post_type'] ) {
			case 'post_type_shows':
				$duplicate['imdb']     = get_post_meta( $duplicate['id'], 'lezshows_imdb', true );
				$duplicate['override'] = get_post_meta( $duplicate['id'], 'lezshows_dupe_override', true );
				$original['imdb']      = get_post_meta( $original['id'], 'lezshows_imdb', true );
				break;
			case 'post_type_actors':
				$duplicate['imdb']     = get_post_meta( $duplicate['id'], 'lezactors_imdb', true );
				$duplicate['override'] = get_post_meta( $duplicate['id'], 'lezactors_dupe_override', true );
				$original['imdb']      = get_post_meta( $original['id'], 'lezactors_imdb', true );
				break;
		}

		// An override means an editor has confirmed this is not a duplicate. ACF
		// true_false fields store raw meta as '1'/'0', never a real boolean, so the
		// old `true !== $override` test could never be false and the override was
		// silently ignored.
		if ( ! empty( $duplicate['override'] ) && '0' !== $duplicate['override'] ) {
			return false;
		}

		// Two shows both missing an IMDb ID is not evidence of anything.
		if ( empty( $duplicate['imdb'] ) || empty( $original['imdb'] ) ) {
			return false;
		}

		if ( $duplicate['imdb'] === $original['imdb'] ) {
			$is_dupe = '' . get_the_title( $post_id ) . ' is a duplicate of <a href="' . get_permalink( $original['id'] ) . '">' . get_the_title( $original['id'] ) . '</a>';
			return $is_dupe;
		}

		return false;
	}
}
