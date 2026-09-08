<?php
/**
 * Turns typed findings into the rows the admin tables and CLI already read.
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
			foreach ( array( 'url', 'term', 'shows', 'health', 'show_ids', 'reason' ) as $key ) {
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
