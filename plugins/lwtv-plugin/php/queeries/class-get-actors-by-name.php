<?php
/**
 * namespace LWTV\Queeries;
 *
 * Find the actors already in the database who might be the person whose name
 * someone just typed.
 *
 * The editor workflow is show, then actors, then characters, so an actor is
 * almost always created from a blank Add Actor screen rather than found by
 * search. That makes the title field the only place a duplicate can be caught
 * before it exists, and this is the lookup behind it.
 *
 * Matching is by the comparable keys in _Helpers\Name_Key, stored per actor as
 * lezactors_name_key and lezactors_name_key_ends, so "Doona Bae" finds the
 * existing "Bae Doona" and an accent or a comma stops mattering. Results carry
 * the tier they matched at: 'full' is every name part accounted for, 'ends' is
 * first and last part only and is the noisier of the two.
 *
 * Nothing here is a verdict. Two people really do share a name, so the caller
 * shows candidates and lets a human decide.
 *
 * @since 6.0.1
 */

namespace LWTV\Queeries;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Actors;
use LWTV\_Helpers\Name_Key;

class Get_Actors_By_Name {

	/**
	 * Most candidates worth returning.
	 *
	 * A real collision is two or three actors. Anything approaching this many is
	 * a sign the name keyed to something far too common to be useful, and the
	 * cap keeps a pathological case from dragging the edit screen down with it.
	 */
	const MAX_RESULTS = 50;

	/**
	 * Actors whose stored name keys match this name.
	 *
	 * Deliberately uncached. The case this exists for is an actor added minutes
	 * ago by the same person now typing a character's cast list, and a transient
	 * would answer that question with a snapshot from before they existed. The
	 * query is one indexed meta lookup; there is nothing here worth caching.
	 *
	 * @param  string $name       The name as typed.
	 * @param  int    $exclude_id Post being edited, so it never matches itself.
	 * @return array<int, array<string, mixed>> Strict matches first. Each entry
	 *                                          has id, title, slug, status, tier.
	 */
	public function make( string $name, int $exclude_id = 0 ): array {
		$variants = Name_Key::variants( $name );
		$ends     = Name_Key::ends( $name );

		if ( empty( $variants ) && empty( $ends ) ) {
			return array();
		}

		global $wpdb;

		$clauses = array();
		$params  = array( Actors::SLUG );

		if ( ! empty( $variants ) ) {
			$clauses[] = '( pm.meta_key = %s AND pm.meta_value IN ( ' . self::placeholders( $variants ) . ' ) )';
			$params[]  = Actors::NAME_KEY_META;
			$params    = array_merge( $params, $variants );
		}

		if ( ! empty( $ends ) ) {
			$clauses[] = '( pm.meta_key = %s AND pm.meta_value IN ( ' . self::placeholders( $ends ) . ' ) )';
			$params[]  = Actors::NAME_ENDS_META;
			$params    = array_merge( $params, $ends );
		}

		// Interpolated rather than concatenated, so the generated fragments read
		// as one query string -- the same shape Duplicate_Collector uses.
		$conditions = implode( ' OR ', $clauses );
		$limit      = (int) self::MAX_RESULTS;

		/*
		 * Disabled across the whole statement rather than one line, because the
		 * interpolations sit on separate lines of the string and phpcs:ignore
		 * only reaches the line after itself.
		 *
		 * Neither interpolation carries a value. $conditions is a generated list
		 * of %s placeholders and fixed meta_key comparisons; $limit is an integer
		 * cast of a class constant. Every actual value -- the post type, the meta
		 * keys and every name key -- is bound through $params.
		 *
		 * $limit stays interpolated rather than bound as %d on purpose.
		 * PreparedSQLPlaceholders counts the arguments after the query, not the
		 * elements of an array, so a second placeholder against a single $params
		 * warns as a mismatch -- and suppressing that sniff in a file of
		 * hand-built SQL costs more than binding a class constant gains. A
		 * spread does not help; it is still one argument.
		 */
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $wpdb->prepare(
			"SELECT p.ID, p.post_title, p.post_name, p.post_status, pm.meta_key
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE p.post_type = %s
			AND p.post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )
			AND ( {$conditions} )
			LIMIT {$limit}",
			$params
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $query );

		return $this->to_candidates( (array) $rows, $exclude_id );
	}

	/**
	 * Collapse the result rows into one entry per actor.
	 *
	 * An actor can match on more than one row -- both hyphen readings of a name,
	 * or the strict and loose key at once -- and the strongest tier wins.
	 *
	 * @param  array<int, object> $rows       Raw result rows.
	 * @param  int                $exclude_id Post being edited.
	 * @return array<int, array<string, mixed>>
	 */
	private function to_candidates( array $rows, int $exclude_id ): array {
		$candidates = array();

		foreach ( $rows as $row ) {
			$post_id = (int) $row->ID;

			if ( $post_id === $exclude_id ) {
				continue;
			}

			$tier = ( Actors::NAME_KEY_META === $row->meta_key ) ? 'full' : 'ends';

			if ( isset( $candidates[ $post_id ] ) ) {
				// Already seen on the other key. Strict beats loose.
				if ( 'full' === $tier ) {
					$candidates[ $post_id ]['tier'] = 'full';
				}
				continue;
			}

			$candidates[ $post_id ] = array(
				'id'     => $post_id,
				'title'  => (string) $row->post_title,
				'slug'   => (string) $row->post_name,
				'status' => (string) $row->post_status,
				'tier'   => $tier,
			);
		}

		$candidates = array_values( $candidates );

		// Strict first, then alphabetical, so the likeliest duplicate is the one
		// an editor reads first.
		usort(
			$candidates,
			static function ( $one, $two ) {
				if ( $one['tier'] !== $two['tier'] ) {
					return ( 'full' === $one['tier'] ) ? -1 : 1;
				}

				return strcasecmp( $one['title'], $two['title'] );
			}
		);

		return $candidates;
	}

	/**
	 * A %s list of the right length for an IN clause.
	 *
	 * @param  array<int, string> $values Values to be placed.
	 * @return string
	 */
	private static function placeholders( array $values ): string {
		return implode( ', ', array_fill( 0, count( $values ), '%s' ) );
	}
}
