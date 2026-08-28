<?php
/**
 * Is a numerically-suffixed post actually a duplicate of the one without it?
 *
 * PURE. Three of these rules have already been wrong in production — the review
 * lists them as 1.9b — and every one was a comparison, not a query:
 *
 * - the editor override was tested with `true !== $override`, which an ACF
 *   true_false field storing '1' can never satisfy, so overrides were ignored
 * - two posts *both* missing an IMDb ID counted as a match
 * - the suffix was stripped with a two-character assumption, so `-10` and up
 *   were mangled
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
	 *
	 * Two types rather than one: a finding's level decides which cache an admin
	 * repair prunes and which tab it returns to, and this is the only check that
	 * spans two post types.
	 */
	const ISSUE_FOR_TYPE = array(
		'post_type_shows'  => 'show-is-duplicate',
		'post_type_actors' => 'actor-is-duplicate',
	);

	/**
	 * Strip a trailing `-2`, `-17`, and so on.
	 *
	 * WordPress appends a numeric suffix to a slug it has seen before, so the
	 * suffix is the signal that a post may have been entered twice. Any number of
	 * digits, not two characters: `-10` and up exist.
	 *
	 * The hyphen is required, which matches this check's candidate query
	 * (`post_name REGEXP '-[0-9]+$'`) — so a number-named show like `90210` is
	 * never a candidate. Show_Rules::numeric_suffix() is deliberately looser,
	 * because the Shows check derives candidates differently; do not harmonise
	 * them without reading both.
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
	 * Public because the collector uses it to skip the lookup entirely for the
	 * overwhelming majority of posts that do not.
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
	 * ACF true_false fields store raw meta as '1' or '0' — never a real boolean —
	 * so the original `true !== $override` test could never be false and the
	 * override was silently ignored for as long as it existed.
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
	 * The link is markup inside data, kept because the admin table has always
	 * rendered it and it is the whole point of the row — you cannot judge a
	 * duplicate without looking at the original. Findings::plain() strips it for
	 * the CLI, and `context` carries the ID for a renderer that would rather
	 * build the link itself.
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
