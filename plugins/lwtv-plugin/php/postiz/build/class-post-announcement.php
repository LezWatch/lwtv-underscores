<?php
/**
 * Name: Post Announcement
 * Description: Pure rules for the text of a new blog post announcement.
 *
 * No WordPress calls, so it is unit-testable. Postiz\New_Post gathers the
 * inputs and does the posting.
 *
 * @package LWTV
 */

namespace LWTV\Postiz\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Post_Announcement {

	/**
	 * Bluesky's post limit, the smallest of our channels.
	 */
	const LIMIT = 300;

	/**
	 * Blank line between title, excerpt, and link.
	 */
	const SEPARATOR = "\n\n";

	/**
	 * Fewest excerpt characters worth including; any less and it's dropped.
	 */
	const MIN_EXCERPT = 20;

	/**
	 * The post text: "Title\n\nExcerpt\n\nURL", trimmed to LIMIT characters.
	 *
	 * The link is never cut. The title is only cut if it alone won't fit with
	 * the link. The excerpt gets whatever room is left, or is dropped.
	 *
	 * @param string $title   Post title (may contain HTML entities).
	 * @param string $excerpt Post excerpt (may contain HTML entities), or ''.
	 * @param string $url     Permalink.
	 * @return string
	 */
	public static function content( string $title, string $excerpt, string $url ): string {
		$title   = self::clean( $title );
		$excerpt = self::clean( $excerpt );
		$tail    = self::SEPARATOR . trim( $url );

		if ( $excerpt === $title ) {
			$excerpt = '';
		}

		$title = self::truncate( $title, self::LIMIT - mb_strlen( $tail ) );
		$room  = self::LIMIT - mb_strlen( $title ) - mb_strlen( $tail ) - mb_strlen( self::SEPARATOR );

		if ( '' === $excerpt || $room < self::MIN_EXCERPT ) {
			return $title . $tail;
		}

		return $title . self::SEPARATOR . self::truncate( $excerpt, $room ) . $tail;
	}

	/**
	 * Plain text: tags stripped, entities decoded, whitespace collapsed.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function clean( string $text ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- wp_strip_all_tags() is unavailable to the unit tests, which run with no WordPress bootstrap.
		$text = strip_tags( $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Cut to at most $max characters on a word boundary, ending in an ellipsis.
	 *
	 * @param string $text Text.
	 * @param int    $max  Maximum characters, ellipsis included.
	 * @return string
	 */
	private static function truncate( string $text, int $max ): string {
		if ( mb_strlen( $text ) <= $max ) {
			return $text;
		}

		$cut   = mb_substr( $text, 0, max( 0, $max - 1 ) );
		$space = mb_strrpos( $cut, ' ' );

		if ( false !== $space && $space > 0 ) {
			$cut = mb_substr( $cut, 0, $space );
		}

		return rtrim( $cut, " \t.,;:-" ) . '…';
	}
}
