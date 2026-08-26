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
			$rows[] = array(
				'url'       => get_permalink( $post_id ),
				'id'        => $post_id,
				'problem'   => $row['problem'],
				'post_type' => $row['post_type'],
				'issues'    => $row['issues'],
				'messages'  => $row['messages'],
				'fixable'   => $row['fixable'],
			);
		}

		return $rows;
	}
}
