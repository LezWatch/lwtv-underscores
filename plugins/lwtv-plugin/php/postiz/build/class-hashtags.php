<?php
/**
 * Name: Hashtags
 * Description: Social hashtags from a show or person's title.
 *
 * Pure: a title in, tags out. Built from the title, never the slug:
 * sanitize_title() percent-encodes anything outside ASCII, which turned
 * "Girl Rules กฎหลัก...ห้ามรักเธอ" into "#GirlRules%e0%b8%81...".
 *
 * @package LWTV
 */

namespace LWTV\Postiz\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hashtags {

	/**
	 * Every hashtag for a title, in posting order.
	 *
	 * Consecutive words are grouped by script and each group is one tag, so a
	 * dual-language title gives one tag per language: '#GirlRules',
	 * '#กฎหลักห้ามรักเธอ'. A Latin tag with accents is followed by its folded
	 * twin ('#Élite', '#Elite') because people search both ways.
	 *
	 * @param string        $title Title as stored; HTML entities are fine.
	 * @param callable|null $fold  Accent folder. Defaults to remove_accents().
	 *
	 * @return array<int, string> Empty when there is nothing to tag.
	 */
	public static function from_title( string $title, ?callable $fold = null ): array {
		$tags = array();

		foreach ( self::runs( self::words( $title ) ) as $run ) {
			$tag    = self::tag( $run['words'] );
			$tags[] = $tag;

			if ( 'latin' === $run['script'] ) {
				$tags[] = self::tag( array( self::fold( $tag, $fold ) ) );
			}
		}

		return array_values( array_unique( array_filter( $tags, static fn( $t ) => '#' !== $t ) ) );
	}

	/**
	 * The tags as post text: '#Élite #Elite'.
	 *
	 * @param string        $title Title as stored.
	 * @param callable|null $fold  Accent folder. Defaults to remove_accents().
	 *
	 * @return string '' when there is nothing to tag.
	 */
	public static function as_text( string $title, ?callable $fold = null ): string {
		return implode( ' ', self::from_title( $title, $fold ) );
	}

	/**
	 * The title cleanup Of the Day has always done before tagging.
	 *
	 * Drops a "(2018)"-style disambiguation, and turns '&' and '@' into the
	 * letters people type in tags: #WillAndGrace, #Tagged.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	public static function clean_title( string $title ): string {
		$title = trim( (string) preg_replace( '~\([^)]+\)~', '', $title ) );
		$title = str_replace( ' & ', ' and ', $title );

		return str_replace( '@', 'a', $title );
	}

	/**
	 * Decode, clean, and split into words.
	 *
	 * Apostrophes join (Grey's → Greys) the way sanitize_title() did. Anything
	 * else that isn't a letter, combining mark or digit is a word break. \p{M}
	 * matters: Thai vowel and tone marks are combining marks, not letters.
	 *
	 * @param string $title Title as stored.
	 * @return array<int, string>
	 */
	private static function words( string $title ): array {
		$title = html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$title = str_replace( array( "'", '’', '‘', 'ʼ', '`' ), '', self::clean_title( $title ) );

		return (array) preg_split( '/[^\p{L}\p{M}\p{N}]+/u', $title, -1, PREG_SPLIT_NO_EMPTY );
	}

	/**
	 * Group consecutive words by script.
	 *
	 * Words without letters (years, '9-1-1') have no script of their own and
	 * join whichever run they're next to, so '13 Reasons Why' stays one tag.
	 *
	 * @param array<int, string> $words Words.
	 * @return array<int, array{script: string, words: array<int, string>}>
	 */
	private static function runs( array $words ): array {
		$runs = array();

		foreach ( $words as $word ) {
			$script = self::script( $word );
			$last   = array_key_last( $runs );

			if ( null !== $last && ( '' === $script || '' === $runs[ $last ]['script'] || $script === $runs[ $last ]['script'] ) ) {
				$runs[ $last ]['words'][] = $word;
				$runs[ $last ]['script']  = $runs[ $last ]['script'] ?: $script;
				continue;
			}

			$runs[] = array(
				'script' => $script,
				'words'  => array( $word ),
			);
		}

		return $runs;
	}

	/**
	 * Which run a word belongs to.
	 *
	 * Every non-Latin script is one bucket, so a Japanese title mixing kanji
	 * and kana stays one tag.
	 *
	 * @param string $word Word.
	 * @return string 'latin', 'other', or '' for no letters at all.
	 */
	private static function script( string $word ): string {
		if ( preg_match( '/(?!\p{Latin})\p{L}/u', $word ) ) {
			return 'other';
		}

		return preg_match( '/\p{L}/u', $word ) ? 'latin' : '';
	}

	/**
	 * CamelCase words into one tag, multibyte-safe.
	 *
	 * @param array<int, string> $words Words (or one already-built tag).
	 * @return string
	 */
	private static function tag( array $words ): string {
		$words = array_map(
			static fn( $w ) => mb_strtoupper( mb_substr( $w, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $w, 1, null, 'UTF-8' ),
			$words
		);

		return '#' . preg_replace( '/[^\p{L}\p{M}\p{N}]+/u', '', implode( '', $words ) );
	}

	/**
	 * Fold accents, by whatever means the caller has.
	 *
	 * @param string        $text Text.
	 * @param callable|null $fold Injected folder, or null for the default.
	 * @return string
	 */
	private static function fold( string $text, ?callable $fold ): string {
		if ( null !== $fold ) {
			return (string) call_user_func( $fold, $text );
		}

		// Always present in a WordPress runtime.
		return function_exists( 'remove_accents' ) ? (string) remove_accents( $text ) : $text;
	}
}
