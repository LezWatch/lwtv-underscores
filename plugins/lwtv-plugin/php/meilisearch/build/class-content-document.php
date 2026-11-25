<?php
/**
 * Name: Content Document
 * Description: Builds a post's or page's Meilisearch document.
 *
 * Pure: the caller gathers the title, excerpt, plain-text body, term names
 * and publish time; this decides what goes in. Posts and pages share one
 * index, told apart by `type`.
 *
 * - The whole body is indexed so text deep in a long article can be found,
 *   but the index's displayedAttributes leave it out of every result: a
 *   result shows the excerpt.
 * - A post without a written excerpt gets the first EXCERPT_WORDS words of
 *   its body.
 * - Ties fall through to type_rank (pages first), then date (newest first):
 *   Mika's call, 2026-11-25.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Content_Document {

	/**
	 * Post types the content index holds.
	 */
	const POST_TYPES = array( 'post', 'page' );

	/**
	 * Words in an excerpt made from the body (WordPress's own default).
	 */
	const EXCERPT_WORDS = 55;

	/**
	 * Whether a post belongs in the index: a published post or page with no
	 * password. Private, draft, scheduled and trashed posts stay out.
	 *
	 * @param string $post_type Post type.
	 * @param string $status    Post status.
	 * @param string $password  Post password, '' for none.
	 * @return bool
	 */
	public static function eligible( string $post_type, string $status, string $password ): bool {
		return in_array( $post_type, self::POST_TYPES, true ) && 'publish' === $status && '' === $password;
	}

	/**
	 * Build the document.
	 *
	 * @param int    $id         Post ID.
	 * @param string $post_type  'post' or 'page'.
	 * @param string $title      Raw title.
	 * @param string $excerpt    Written excerpt, or ''.
	 * @param string $body       Body as plain text (shortcodes and tags already stripped).
	 * @param array  $categories Category names.
	 * @param array  $tags       Tag names.
	 * @param int    $date       Publish time, Unix seconds (GMT).
	 * @param string $url        Permalink.
	 * @param string $thumb      Thumbnail URL, or ''.
	 * @return array<string, mixed>
	 */
	public static function build( int $id, string $post_type, string $title, string $excerpt, string $body, array $categories, array $tags, int $date, string $url, string $thumb ): array {
		$body    = self::text( $body );
		$excerpt = self::text( $excerpt );

		return array(
			'id'         => $id,
			'type'       => $post_type,
			'type_rank'  => ( 'page' === $post_type ) ? 1 : 0,
			'title'      => self::text( $title ),
			'excerpt'    => '' !== $excerpt ? $excerpt : self::first_words( $body, self::EXCERPT_WORDS ),
			'body'       => $body,
			'categories' => self::names( $categories ),
			'tags'       => self::names( $tags ),
			'date'       => max( 0, $date ),
			'url'        => $url,
			'thumb'      => $thumb,
		);
	}

	/**
	 * The first words of a text, with an ellipsis if any were cut.
	 *
	 * @param string $text  Plain, collapsed text.
	 * @param int    $count Words to keep.
	 * @return string
	 */
	private static function first_words( string $text, int $count ): string {
		if ( '' === $text ) {
			return '';
		}

		$words = explode( ' ', $text );
		if ( count( $words ) <= $count ) {
			return $text;
		}

		return implode( ' ', array_slice( $words, 0, $count ) ) . '…';
	}

	/**
	 * Decoded, collapsed, non-empty, de-duplicated strings, in order.
	 *
	 * @param array $values Raw values.
	 * @return string[]
	 */
	private static function names( array $values ): array {
		$out = array();
		foreach ( $values as $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}

			$text = self::text( (string) $value );
			if ( '' !== $text ) {
				$out[ $text ] = $text;
			}
		}

		return array_values( $out );
	}

	/**
	 * Plain text: entities decoded, non-breaking spaces and runs of
	 * whitespace collapsed to one space, trimmed.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	private static function text( string $text ): string {
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return trim( (string) preg_replace( '/[\s\x{00A0}]+/u', ' ', $text ) );
	}
}
