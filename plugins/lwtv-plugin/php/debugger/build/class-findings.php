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
	 * Until findings are individually addressable in the admin, this is how a
	 * report says a problem can be repaired. It is composed here rather than
	 * baked into the message so the message itself stays clean data.
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

		return ( '' === $label ) ? $message : $message . ' — fixable, ' . $label . '.';
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
				);
			}

			$issue_type = (string) ( $finding['issue_type'] ?? '' );

			$rows[ $post_id ]['issues'][]   = $issue_type;
			$rows[ $post_id ]['messages'][] = self::describe( $finding );

			// A post can carry the same fixable issue only once, and the fixer
			// is keyed on the type, so duplicates here would fix twice.
			if ( ! empty( $finding['fixable'] ) && ! in_array( $issue_type, $rows[ $post_id ]['fixable'], true ) ) {
				$rows[ $post_id ]['fixable'][] = $issue_type;
			}
		}

		// Compose the blob last so the messages array stays the source of truth.
		foreach ( $rows as $post_id => $row ) {
			$rows[ $post_id ]['problem'] = implode( self::SEPARATOR, $row['messages'] );
			unset( $rows[ $post_id ]['messages'] );
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
