<?php
/**
 * Builds and groups typed debugger findings.
 *
 * A finding is one problem on one post. Scanners emit these, and everything
 * downstream -- counts, repairs, admin tables, CLI columns -- is derived from
 * them rather than from a pre-rendered string.
 *
 * PURE. Array in, array out, no WordPress calls. The one WordPress-flavoured
 * concession is that the grouped `problem` string joins with `</br>`, which is
 * what the admin renderer has always emitted; the joining is still string work,
 * so it stays testable here.
 *
 * @package LWTV
 */

namespace LWTV\Debugger\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Findings {

	/**
	 * Separator between problems on one post.
	 *
	 * Invalid HTML, and deliberately unchanged: Admin_Menu\Validation renders
	 * `problem` through wp_kses_post() and has always been fed this. Fixing it
	 * is a rendering change, not part of the finding shape.
	 */
	const SEPARATOR = '</br>';

	/**
	 * Build one finding.
	 *
	 * @param  int    $post_id    Post the problem is on.
	 * @param  string $post_type  Post type slug.
	 * @param  string $issue_type Key into Issue_Registry.
	 * @param  string $message    Overrides the registry copy when the detail is
	 *                            per-post (a title, a bad value, a URL). Pass ''
	 *                            to use the registry's default.
	 * @param  array  $context    Optional extra data (the bad URL, the bad ID).
	 * @return array
	 */
	public static function make( int $post_id, string $post_type, string $issue_type, string $message = '', array $context = array() ): array {
		return array(
			'post_id'    => $post_id,
			'post_type'  => $post_type,
			'issue_type' => $issue_type,
			'message'    => ( '' !== $message ) ? $message : Issue_Registry::message( $issue_type ),
			'context'    => $context,
			'fixable'    => Issue_Registry::is_fixable( $issue_type ),
			'fix_label'  => Issue_Registry::fix_label( $issue_type ),
			'manual'     => Issue_Registry::is_manual( $issue_type ),
		);
	}

	/**
	 * Build findings from a list of ready-made message strings.
	 *
	 * Several checks predate the registry and return prose rather than issue
	 * types -- airdate problems, duplicate detection, the intersectionality
	 * cross-check. Those keep their per-post wording while still becoming one
	 * addressable finding each, under a type supplied by the caller.
	 *
	 * @param  int             $post_id    Post the problems are on.
	 * @param  string          $post_type  Post type slug.
	 * @param  string          $issue_type Key into Issue_Registry.
	 * @param  array|string    $messages   Message, or list of them. Empty values
	 *                                     are dropped -- these checks return an
	 *                                     empty string for "nothing wrong".
	 * @return array<int, array<string, mixed>>
	 */
	public static function from_messages( int $post_id, string $post_type, string $issue_type, $messages ): array {
		$findings = array();

		foreach ( (array) $messages as $message ) {
			$message = trim( (string) $message );

			if ( '' === $message ) {
				continue;
			}

			$findings[] = self::make( $post_id, $post_type, $issue_type, $message );
		}

		return $findings;
	}

	/**
	 * One finding's message with its repair advertised inline.
	 *
	 * This is how a *text* report says a problem can be repaired -- the CLI, and
	 * anywhere the `problem` blob is printed. The admin table renders a button
	 * per issue instead, so it uses the raw message and does not call this. Kept
	 * out of the message itself either way, so the message stays clean data.
	 *
	 * @param  array $finding Finding array.
	 * @return string
	 */
	public static function describe( array $finding ): string {
		$message = (string) ( $finding['message'] ?? '' );

		if ( empty( $finding['fixable'] ) ) {
			return $message;
		}

		$label = (string) ( $finding['fix_label'] ?? '' );

		if ( '' === $label ) {
			return $message;
		}

		// A manual repair is not something --fix-it will do, so saying plain
		// "fixable" in CLI output would be a promise the CLI does not keep.
		$prefix = empty( $finding['manual'] ) ? ' — fixable, ' : ' — fixable in wp-admin, ';

		return $message . $prefix . $label . '.';
	}

	/**
	 * Compose the `problem` blob from a row's parallel issues/messages lists.
	 *
	 * Rows store raw messages, so the fixability prose is composed on the way
	 * out. That means a row can be rebuilt after one issue is repaired without
	 * string-surgery on the blob -- which was the thing making per-issue admin
	 * repairs impossible.
	 *
	 * @param  array $issues   Issue types, in order.
	 * @param  array $messages Raw messages, same order.
	 * @return string
	 */
	public static function problem_from( array $issues, array $messages ): string {
		$described = array();
		$types     = array_values( $issues );

		foreach ( array_values( $messages ) as $index => $message ) {
			$issue_type = (string) ( $types[ $index ] ?? '' );

			$described[] = self::describe(
				array(
					'message'   => $message,
					'fixable'   => Issue_Registry::is_fixable( $issue_type ),
					'fix_label' => Issue_Registry::fix_label( $issue_type ),
					'manual'    => Issue_Registry::is_manual( $issue_type ),
				)
			);
		}

		return implode( self::SEPARATOR, $described );
	}

	/**
	 * A row with one issue type repaired and removed.
	 *
	 * Lets a single admin repair prune the cached findings instead of dropping
	 * the whole transient, which would force a full rescan of every post in the
	 * CPT on the next page view.
	 *
	 * @param  array  $row        A row from group_by_post()/Rows::from_findings().
	 * @param  string $issue_type Issue type that was repaired.
	 * @return array  The updated row, or empty when nothing is left to report.
	 */
	public static function without_issue( array $row, string $issue_type ): array {
		$issues   = array_values( (array) ( $row['issues'] ?? array() ) );
		$messages = array_values( (array) ( $row['messages'] ?? array() ) );

		// No per-issue data: a pre-reshape cached row. There is nothing to prune
		// surgically, and guessing would corrupt the row, so leave it alone.
		if ( empty( $issues ) ) {
			return $row;
		}

		$statuses = array_values( (array) ( $row['statuses'] ?? array() ) );

		$kept_issues   = array();
		$kept_messages = array();
		$kept_statuses = array();

		foreach ( $issues as $index => $type ) {
			if ( $type === $issue_type ) {
				continue;
			}

			$kept_issues[]   = $type;
			$kept_messages[] = $messages[ $index ] ?? Issue_Registry::message( $type );

			if ( isset( $statuses[ $index ] ) ) {
				$kept_statuses[] = $statuses[ $index ];
			}
		}

		if ( empty( $kept_issues ) ) {
			return array();
		}

		$row['issues']   = $kept_issues;
		$row['messages'] = $kept_messages;
		$row['problem']  = self::problem_from( $kept_issues, $kept_messages );

		if ( ! empty( $kept_statuses ) ) {
			$row['statuses'] = $kept_statuses;
			$row['status']   = Baseline::row_status( $kept_statuses );
		}

		// Through fixable_issues() so cached junk is filtered the same way here
		// as it is on read, and a retired repair cannot survive a prune.
		$row['fixable'] = array_values( array_unique( self::fixable_issues( array( 'fixable' => $kept_issues ) ) ) );

		return $row;
	}

	/**
	 * Collapse per-issue findings into one row per post.
	 *
	 * Rows keep first-seen order, and each carries both the derived `problem`
	 * blob the old consumers read and the structured issue lists the new ones
	 * need. Counting rows therefore still counts posts, not problems -- the
	 * report says "10 shows need attention" as it always has, while `issues`
	 * makes the individual problems addressable.
	 *
	 * @param  array $findings List of findings from make().
	 * @return array<int, array<string, mixed>> Keyed by post ID.
	 */
	public static function group_by_post( array $findings ): array {
		$rows = array();

		foreach ( $findings as $finding ) {
			$post_id = (int) ( $finding['post_id'] ?? 0 );

			if ( ! $post_id ) {
				continue;
			}

			if ( ! isset( $rows[ $post_id ] ) ) {
				$rows[ $post_id ] = array(
					'id'        => $post_id,
					'post_type' => (string) ( $finding['post_type'] ?? '' ),
					'issues'    => array(),
					'fixable'   => array(),
					'messages'  => array(),
					'statuses'  => array(),
				);
			}

			$issue_type = (string) ( $finding['issue_type'] ?? '' );

			$rows[ $post_id ]['issues'][]   = $issue_type;
			$rows[ $post_id ]['messages'][] = (string) ( $finding['message'] ?? '' );

			// Present only once a run has been diffed against a baseline; an
			// undiffed finding is neither new nor open, it is just unknown.
			$rows[ $post_id ]['statuses'][] = (string) ( $finding['status'] ?? '' );

			// A post can carry the same fixable issue only once, and the fixer
			// is keyed on the type, so duplicates here would fix twice.
			if ( ! empty( $finding['fixable'] ) && ! in_array( $issue_type, $rows[ $post_id ]['fixable'], true ) ) {
				$rows[ $post_id ]['fixable'][] = $issue_type;
			}
		}

		// Compose the blob last, and keep the messages: they are what lets a row
		// be rebuilt when one issue is repaired.
		foreach ( $rows as $post_id => $row ) {
			$rows[ $post_id ]['problem'] = self::problem_from( $row['issues'], $row['messages'] );

			// Row-level status, so a table can flag the row as well as the line.
			// Dropped entirely on an undiffed run rather than defaulted, so no
			// surface can mistake "not compared yet" for "nothing is new".
			$statuses = array_filter( $row['statuses'] );

			if ( ! empty( $statuses ) ) {
				$rows[ $post_id ]['status'] = Baseline::row_status( $row['statuses'] );
			}
		}

		return $rows;
	}

	/**
	 * How many findings of each issue type.
	 *
	 * Sorted by count, highest first, so a caller can lead with the common
	 * problem without re-sorting.
	 *
	 * @param  array $findings List of findings.
	 * @return array<string, int>
	 */
	public static function count_by_issue( array $findings ): array {
		$counts = array();

		foreach ( $findings as $finding ) {
			$issue_type = (string) ( $finding['issue_type'] ?? '' );

			if ( '' === $issue_type ) {
				continue;
			}

			$counts[ $issue_type ] = ( $counts[ $issue_type ] ?? 0 ) + 1;
		}

		arsort( $counts );

		return $counts;
	}

	/**
	 * The fixable issue types on one row, tolerating the pre-reshape shape.
	 *
	 * Findings live in week-long transients, so a cached payload written before
	 * this existed has no `fixable` key. Returning an empty list for those is
	 * correct: they fall back to the check-level fixer.
	 *
	 * @param  array $row A row from group_by_post(), or an older finding row.
	 * @return array<string>
	 */
	public static function fixable_issues( array $row ): array {
		if ( empty( $row['fixable'] ) || ! is_array( $row['fixable'] ) ) {
			return array();
		}

		$fixable = array();

		foreach ( $row['fixable'] as $issue_type ) {
			// Cached transient data, so do not trust it to be strings, and drop
			// any type whose repair has since been retired from the registry.
			if ( ! is_string( $issue_type ) || ! Issue_Registry::is_fixable( $issue_type ) ) {
				continue;
			}

			$fixable[] = $issue_type;
		}

		return $fixable;
	}
}
