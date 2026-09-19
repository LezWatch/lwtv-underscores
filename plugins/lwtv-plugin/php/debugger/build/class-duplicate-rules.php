<?php
/**
 * Is a numerically-suffixed post actually a duplicate of the one without it?
 *
 * In addition, is an actor a duplicate of another based on name parts.
 *
 * The data contract, as produced by Collect\Duplicate_Collector:
 *
 *     array(
 *         'post_id'   => int,
 *         'post_type' => string,
 *         'slug'      => string,
 *         'imdb'      => string,
 *         'override'  => string|array,  // see is_acknowledged()
 *         'original'  => array{}|array{id: int, slug: string, imdb: string},
 *     )
 *
 * @package LWTV
 */

namespace LWTV\Debugger\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Duplicate_Rules {

	/**
	 * Issue type per post type.
	 */
	const ISSUE_FOR_TYPE = array(
		'post_type_shows'  => 'show-is-duplicate',
		'post_type_actors' => 'actor-is-duplicate',
	);

	/**
	 * Strip a trailing `-2`, `-17`, and so on.
	 *
	 * @param  string $slug Post slug.
	 * @return string The slug without its numeric suffix. Unchanged when it has none.
	 */
	public static function base_slug( string $slug ): string {
		return (string) preg_replace( '/-[0-9]+$/', '', $slug );
	}

	/**
	 * Does this slug carry a numeric suffix at all?
	 *
	 * @param  string $slug Post slug.
	 * @return bool
	 */
	public static function has_suffix( string $slug ): bool {
		return self::base_slug( $slug ) !== $slug;
	}

	/**
	 * Has an editor confirmed this is not a duplicate?
	 *
	 * Two shapes, because the two post types store this differently.
	 *
	 * An array is the pair-scoped form that lezactors_dupe_override now holds: a
	 * list of actor IDs an editor has confirmed are different people. It has to
	 * be per pair -- saying this Sarah Jones is not that Sarah Jones must not
	 * also silence a third Sarah Jones added next year, which a single flag
	 * would. Names collide far more often than slugs do, so a blanket exemption
	 * on a common name would hide real duplicates indefinitely.
	 *
	 * A scalar is the original flag, still what lezshows_dupe_override holds,
	 * and still means "not a duplicate of anything".
	 *
	 * @param  mixed $override Raw meta value: an array of IDs, or a flag.
	 * @param  int   $against  The post this candidate is being compared to.
	 * @return bool
	 */
	public static function is_acknowledged( $override, int $against = 0 ): bool {
		if ( is_array( $override ) ) {
			return in_array( $against, array_map( 'intval', $override ), true );
		}

		$override = (string) $override;

		return '' !== $override && '0' !== $override;
	}

	/**
	 * Every finding for one candidate.
	 *
	 * @param  array $candidate Collected candidate data.
	 * @return array<int, array<string, mixed>>
	 */
	public static function evaluate( array $candidate ): array {
		$post_id  = (int) ( $candidate['post_id'] ?? 0 );
		$original = (array) ( $candidate['original'] ?? array() );

		if ( ! $post_id || empty( $original ) ) {
			return array();
		}

		$original_id = (int) ( $original['id'] ?? 0 );

		// A post cannot duplicate itself. Some titles really are numbers — 90210 —
		// and stripping the "suffix" from those finds the post you started with.
		if ( $original_id === $post_id ) {
			return array();
		}

		// Checked after the pairing is known, because an acknowledgement is now
		// about a specific pair rather than the post as a whole.
		if ( self::is_acknowledged( $candidate['override'] ?? '', $original_id ) ) {
			return array();
		}

		$ours   = (string) ( $candidate['imdb'] ?? '' );
		$theirs = (string) ( $original['imdb'] ?? '' );

		// Two posts both *missing* an IMDb ID is not evidence of anything.
		if ( '' === $ours || '' === $theirs || $ours !== $theirs ) {
			return array();
		}

		$post_type  = (string) ( $candidate['post_type'] ?? '' );
		$issue_type = self::ISSUE_FOR_TYPE[ $post_type ] ?? null;

		// A post type nobody taught this check about. Silence beats a finding
		// whose level no surface knows how to render or repair.
		if ( null === $issue_type ) {
			return array();
		}

		return array(
			Findings::make(
				$post_id,
				$post_type,
				$issue_type,
				self::message( $candidate, $original ),
				array( 'original_id' => $original_id )
			),
		);
	}

	/**
	 * "X is a duplicate of Y", with Y linked.
	 *
	 * @param  array $candidate Collected candidate data.
	 * @param  array $original  The original it duplicates.
	 * @return string
	 */
	private static function message( array $candidate, array $original ): string {
		return (string) ( $candidate['title'] ?? '' )
			. ' is a duplicate of <a href="' . (string) ( $original['url'] ?? '' ) . '">'
			. (string) ( $original['title'] ?? '' ) . '</a>';
	}
}
