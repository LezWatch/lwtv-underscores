<?php
/**
 * Turns typed findings into the rows the admin tables and CLI already read.
 *
 * This is the seam between the pure layer and WordPress: grouping and copy
 * happen in Debugger\Build\Findings, and the only thing added here is the
 * permalink, which needs a live site.
 *
 * The row is an additive superset of the old `array( url, id, problem )` shape,
 * so every existing consumer keeps working untouched -- Admin_Menu\Validation
 * reads `id` and `problem`, the CLI names its columns, and `count()` still
 * counts posts. `issues` and `fixable` are the new structured truth alongside
 * them. Because nothing was removed, findings cached before this change stay
 * readable and simply have no `issues` key; callers treat that as "no per-issue
 * information" rather than an error, which avoids throwing away a week of
 * scans for a transient key bump.
 *
 * @package LWTV
 */

namespace LWTV\Debugger\Format;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Debugger\Build\Findings;

class Rows {

	/**
	 * Build display rows from a run's findings.
	 *
	 * @param  array $findings List of findings from Findings::make().
	 * @return array<int, array<string, mixed>> One row per post, first-seen order.
	 */
	public static function from_findings( array $findings ): array {
		$rows = array();

		foreach ( Findings::group_by_post( $findings ) as $post_id => $row ) {
			$item = array(
				'url'       => get_permalink( $post_id ),
				'id'        => $post_id,
				'problem'   => $row['problem'],
				'post_type' => $row['post_type'],
				'issues'    => $row['issues'],
				'messages'  => $row['messages'],
				'fixable'   => $row['fixable'],
			);

			// Only on a run that was diffed against a baseline.
			if ( isset( $row['status'] ) ) {
				$item['status']   = $row['status'];
				$item['statuses'] = $row['statuses'];
			}

			$rows[] = $item;
		}

		return $rows;
	}

	/**
	 * Build display rows from term findings, one row per finding.
	 *
	 * Deliberately not grouped. The post checks collapse to one row per post
	 * because the post is both what you triage and what you edit. A watch
	 * provider term is what you edit, but a *URL* is what you triage, and the
	 * report has a URL column per row — grouping a term's three broken URLs into
	 * one row would cost information the report currently gives.
	 *
	 * Context is lifted to the top level because that is where the renderer and
	 * the CLI columns look for it.
	 *
	 * @param  array $findings List of findings from Findings::make_for_term().
	 * @return array<int, array<string, mixed>>
	 */
	public static function from_term_findings( array $findings ): array {
		$rows = array();

		foreach ( $findings as $finding ) {
			$context = (array) ( $finding['context'] ?? array() );
			$issue   = (string) ( $finding['issue_type'] ?? '' );

			$row = array(
				'id'          => (int) ( $finding['post_id'] ?? 0 ),
				'object_kind' => Findings::KIND_TERM,
				'object_type' => (string) ( $finding['post_type'] ?? '' ),
				'issues'      => array( $issue ),
				'messages'    => array( (string) ( $finding['message'] ?? '' ) ),
				'fixable'     => Findings::fixable_issues( array( 'fixable' => array( $issue ) ) ),
				'problem'     => Findings::problem_from( array( $issue ), array( (string) ( $finding['message'] ?? '' ) ) ),
			);

			// The keys this check's own renderer and CLI columns read.
			foreach ( array( 'url', 'term', 'shows', 'health' ) as $key ) {
				if ( isset( $context[ $key ] ) ) {
					$row[ $key ] = $context[ $key ];
				}
			}

			if ( isset( $finding['status'] ) ) {
				$row['status']   = $finding['status'];
				$row['statuses'] = array( $finding['status'] );
			}

			$rows[] = $row;
		}

		return $rows;
	}
}
