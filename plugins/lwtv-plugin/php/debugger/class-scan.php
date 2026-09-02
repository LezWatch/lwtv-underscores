<?php
/**
 * The two halves every scan shares: working out what to look at, and what to do
 * with what it found.
 *
 * @package LWTV
 */

namespace LWTV\Debugger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Debugger\Format\Rows;
use LWTV\Queeries\Post_Type;

class Scan {

	/**
	 * Write a check's findings. The only place that does.
	 *
	 * @param  string $key   Findings key.
	 * @param  array  $items Rows to store.
	 * @return array         The rows, so callers can `return Scan::store( … )`.
	 */
	public static function store( string $key, array $items ): array {
		return Findings_Store::save( $key, $items );
	}

	/**
	 * Which posts this run should look at.
	 *
	 * @param  array    $items Findings from a previous run, or empty for a full scan.
	 * @param  callable $all   Returns every ID to check, for a full scan.
	 * @return array<int>
	 */
	public static function targets( array $items, callable $all ): array {
		if ( empty( $items ) ) {
			$ids = (array) $all();
		} else {
			$ids = array();

			foreach ( $items as $item ) {
				if ( empty( $item['id'] ) ) {
					continue;
				}

				if ( 'draft' !== get_post_status( $item['id'] ) ) {
					$ids[] = $item['id'];
				}
			}
		}

		return array_values( array_unique( array_map( 'intval', $ids ) ) );
	}

	/**
	 * Targets for a whole post type.
	 *
	 * The common case: eight of the ten call sites want every published post of
	 * one type.
	 *
	 * @param  array  $items     Findings from a previous run, or empty.
	 * @param  string $post_type Post type slug.
	 * @return array<int>
	 */
	public static function post_ids( array $items, string $post_type ): array {
		return self::targets(
			$items,
			static fn () => ( new Post_Type() )->get_ids( $post_type )
		);
	}

	/**
	 * Diff a run against its baseline, render it, save it, record it.
	 *
	 * @param  array         $check    array( scope, findings, label ). `scope` keys
	 *                                 both the baseline and the status entry, so
	 *                                 they cannot drift apart.
	 * @param  array         $findings Typed findings from this run.
	 * @param  bool          $is_recheck Whether this run visited only flagged posts.
	 * @param  callable|null $to_rows  How findings become display rows. Defaults to
	 *                                 Rows::from_findings(); the term-shaped and
	 *                                 duplicate checks pass their own.
	 * @return array<int, array<string, mixed>> The rows, as the scanners return them.
	 */
	public static function finish( array $check, array $findings, bool $is_recheck, ?callable $to_rows = null ): array {
		$scope = $check['scope'];

		$diff = $is_recheck
			? Baseline_Store::tag_only( $scope, $findings )
			: Baseline_Store::apply( $scope, $findings );

		$to_rows = $to_rows ?? static fn ( array $tagged ) => Rows::from_findings( $tagged );
		$items   = (array) $to_rows( $diff['findings'] );

		self::store( $check['findings'], $items );

		Status::record( $scope, $check['label'], count( $items ), $diff['summary'] );

		return $items;
	}
}
