<?php
/**
 * Is a numerically-suffixed post actually a duplicate of the one without it?
 *
 * The data contract, as produced by Collect\Duplicate_Collector:
 *
 *     array(
 *         'post_id'   => int,
 *         'post_type' => string,
 *         'slug'      => string,
 *         'imdb'      => string,
 *         'override'  => string,   // raw meta: '1', '0', or ''
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
	 * @param  string $override Raw meta value.
	 * @return bool
	 */
	public static function is_acknowledged( string $override ): bool {
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

		if ( self::is_acknowledged( (string) ( $candidate['override'] ?? '' ) ) ) {
			return array();
		}

		// A post cannot duplicate itself. Some titles really are numbers — 90210 —
		// and stripping the "suffix" from those finds the post you started with.
		if ( (int) ( $original['id'] ?? 0 ) === $post_id ) {
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
				array( 'original_id' => (int) $original['id'] )
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
