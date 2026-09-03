<?php
/**
 * Builds and groups typed debugger findings.
 *
 * A finding is one problem on one post. Scanners emit these, and everything
 * downstream -- counts, repairs, admin tables, CLI columns -- is derived from
 * them rather than from a pre-rendered string.
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
	 */
	const SEPARATOR = '</br>';

	/**
	 * A finding about a post.
	 */
	const KIND_POST = 'post';

	/**
	 * A finding about a taxonomy term.
	 */
	const KIND_TERM = 'term';

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
			'post_id'     => $post_id,
			'post_type'   => $post_type,
			'object_kind' => self::KIND_POST,
			'issue_type'  => $issue_type,
			'message'     => ( '' !== $message ) ? $message : Issue_Registry::message( $issue_type ),
			'context'     => $context,
			'fixable'     => Issue_Registry::is_fixable( $issue_type ),
			'fix_label'   => Issue_Registry::fix_label( $issue_type ),
			'manual'      => Issue_Registry::is_manual( $issue_type ),
		);
	}

	/**
	 * Build one finding about a taxonomy term.
	 *
	 * Terms are the awkward case the whole shape had to stretch for: the Watch
	 * URL check finds problems on `lez_watch_urls` terms, not on posts, and a
	 * renderer that calls `get_the_title()` on a term ID produces nonsense
	 * silently. So a finding says what kind of thing it is about, and
	 * `object_kind` is the key to test before dereferencing `id`.
	 *
	 * `post_id` still carries the identity, rather than a parallel `term_id`.
	 * That is deliberate: renaming it would mean migrating every stored row and
	 * every reader of `id`, for no gain on the ten post-based checks. The name is
	 * a little wrong for terms; the alternative was a great deal of churn.
	 *
	 * @param  int    $term_id    Term ID.
	 * @param  string $taxonomy   Taxonomy the term belongs to.
	 * @param  string $issue_type Key into Issue_Registry.
	 * @param  string $message    Overrides the registry copy. '' to use the default.
	 * @param  array  $context    Extra data. See $identity for the URL case.
	 * @param  string $identity   Distinguishes two findings of the same type on the
	 *                            same term -- a term can have several bad URLs, and
	 *                            each is its own problem. Folded into the baseline
	 *                            key so they do not collapse into one.
	 * @return array
	 */
	public static function make_for_term( int $term_id, string $taxonomy, string $issue_type, string $message = '', array $context = array(), string $identity = '' ): array {
		$finding = self::make( $term_id, $taxonomy, $issue_type, $message, $context );

		$finding['object_kind'] = self::KIND_TERM;

		if ( '' !== $identity ) {
			$finding['identity'] = $identity;
		}

		return $finding;
	}

	/**
	 * Is this finding, or row, about a post?
	 *
	 * @param  array $finding Finding or row.
	 * @return bool
	 */
	public static function is_post( array $finding ): bool {
		return self::KIND_TERM !== ( $finding['object_kind'] ?? self::KIND_POST );
	}

	/**
	 * Build findings from a list of ready-made message strings.
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
	 * A row's problems as plain text, for a terminal.
	 *
	 * @param  array $row A row from Rows::from_findings(), or an older one.
	 * @return string
	 */
	public static function plain( array $row ): string {
		$issues   = isset( $row['issues'] ) && is_array( $row['issues'] ) ? array_values( $row['issues'] ) : array();
		$messages = isset( $row['messages'] ) && is_array( $row['messages'] ) ? array_values( $row['messages'] ) : array();

		if ( ! empty( $issues ) && ! empty( $messages ) ) {
			return self::flatten( self::problem_from( $issues, $messages ) );
		}

		return self::flatten( (string) ( $row['problem'] ?? '' ) );
	}

	/**
	 * Turn one composed blob into a single readable line.
	 *
	 * Semicolons rather than newlines: WP-CLI's table renderer wraps a long cell
	 * itself, and embedded newlines fight with the box drawing.
	 *
	 * @param  string $problem Composed problem text.
	 * @return string
	 */
	private static function flatten( string $problem ): string {
		// Every break variant the old messages used, plus the real one.
		$problem = (string) preg_replace( '#<\s*/?\s*br\s*/?\s*>#i', '; ', $problem );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- wp_strip_all_tags() is unavailable to this class's unit tests, which run with no WordPress bootstrap; this class is documented PURE for exactly that reason.
		$problem = strip_tags( $problem );
		$problem = html_entity_decode( $problem, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Collapse the whitespace that stripping tags tends to leave behind.
		$problem = (string) preg_replace( '/\s+/u', ' ', $problem );

		return trim( $problem, " \t\n\r\0\x0B;" );
	}

	/**
	 * Compose the `problem` blob from a row's parallel issues/messages lists.
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
	 * the whole findings set, which would force a full rescan of every post in the
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
			$rows[ $post_id ]['statuses'][] = (string) ( $finding['status'] ?? '' );

			if ( ! empty( $finding['fixable'] ) && ! in_array( $issue_type, $rows[ $post_id ]['fixable'], true ) ) {
				$rows[ $post_id ]['fixable'][] = $issue_type;
			}
		}

		// Compose the blob last, and keep the messages: they are what lets a row
		// be rebuilt when one issue is repaired.
		foreach ( $rows as $post_id => $row ) {
			$rows[ $post_id ]['problem'] = self::problem_from( $row['issues'], $row['messages'] );

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
	 * Findings are stored for ten days, so a payload written before
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
			if ( ! is_string( $issue_type ) || ! Issue_Registry::is_fixable( $issue_type ) ) {
				continue;
			}

			$fixable[] = $issue_type;
		}

		return $fixable;
	}
}
