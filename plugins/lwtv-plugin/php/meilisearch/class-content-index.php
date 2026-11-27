<?php
/**
 * Name: Content Index
 * Description: Gathers a post's or page's data from WordPress for its document.
 *
 * WordPress glue: verified against the running site, not unit-tested. The
 * decisions live in Build\Content_Document. Posts and pages share this one
 * index; `type` tells them apart.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Meilisearch\Build\Content_Document;

class Content_Index extends Index {

	const TYPE = 'content';

	/**
	 * @inheritDoc
	 */
	public function type(): string {
		return self::TYPE;
	}

	/**
	 * @inheritDoc
	 */
	public function post_type(): string {
		return Content_Document::POST_TYPES[0];
	}

	/**
	 * Posts and pages.
	 *
	 * @return string[]
	 */
	public function post_types(): array {
		return Content_Document::POST_TYPES;
	}

	/**
	 * @inheritDoc
	 */
	public function label(): string {
		return __( 'Content', 'lwtv-underscores' );
	}

	/**
	 * SearchWP has no content engine; `default` covers posts and pages
	 * (and CPTs, which documents() leaves out).
	 *
	 * @return string
	 */
	public function searchwp_engine(): string {
		return 'default';
	}

	/**
	 * Content documents quote no other post: only a post's own change
	 * touches its document.
	 *
	 * @param string $post_type Changed post's type.
	 * @param int    $post_id   Changed post's ID.
	 * @return int[]
	 */
	public function affected_by( string $post_type, int $post_id ): array {
		return $this->holds( $post_type ) ? array( $post_id ) : array();
	}

	/**
	 * Documents for published, unprotected posts and pages; others are
	 * skipped. Primes post, meta and term caches first.
	 *
	 * @param int[] $ids Post IDs.
	 * @return array[]
	 */
	public function documents( array $ids ): array {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		if ( empty( $ids ) ) {
			return array();
		}

		_prime_post_caches( $ids, true, true );

		$documents = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post instanceof \WP_Post || ! Content_Document::eligible( (string) $post->post_type, (string) $post->post_status, (string) $post->post_password ) ) {
				continue;
			}

			$thumb       = get_the_post_thumbnail_url( $id, 'thumbnail' );
			$documents[] = Content_Document::build(
				$id,
				(string) $post->post_type,
				(string) $post->post_title,
				(string) $post->post_excerpt,
				$this->plain_text( (string) $post->post_content ),
				$this->term_names( $id, 'category' ),
				$this->term_names( $id, 'post_tag' ),
				(int) get_post_timestamp( $post ),
				(string) get_permalink( $post ),
				$thumb ? (string) $thumb : ''
			);
		}

		return $documents;
	}

	/**
	 * Post content as plain text: shortcodes, block comments, scripts and
	 * tags removed. A space goes in before each tag first, so text in
	 * neighbouring blocks or paragraphs doesn't run together.
	 *
	 * @param string $content Raw post_content.
	 * @return string
	 */
	private function plain_text( string $content ): string {
		return wp_strip_all_tags( str_replace( '<', ' <', strip_shortcodes( $content ) ) );
	}

	/**
	 * Names of a post's terms in one taxonomy (none for a page, whose
	 * type has no categories or tags).
	 *
	 * @param int    $id       Post ID.
	 * @param string $taxonomy Taxonomy.
	 * @return string[]
	 */
	private function term_names( int $id, string $taxonomy ): array {
		$terms = get_the_terms( $id, $taxonomy );

		return is_array( $terms ) ? wp_list_pluck( $terms, 'name' ) : array();
	}
}
