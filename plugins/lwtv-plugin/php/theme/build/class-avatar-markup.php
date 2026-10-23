<?php
/**
 * Name: Avatar Markup
 * Description: Pure transform from get_avatar() output to Bootstrap 6 Avatar markup.
 *
 * get_avatar() puts `class="avatar"` on the <img>. In Bootstrap 6, `.avatar`
 * is the wrapper component (a 2.5rem circle), so a bare image with that class
 * gets shrunk. This moves the image onto `.avatar-img` inside a `.avatar`
 * span sized with --bs-avatar-size. No WordPress calls, so it is
 * unit-testable; Theme::get_avatar() supplies the HTML.
 *
 * @package LWTV
 */

namespace LWTV\Theme\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Avatar_Markup {

	/**
	 * Wrap an avatar <img> in Bootstrap 6 Avatar markup.
	 *
	 * @param string|false $img  HTML from get_avatar() (false when avatars are off).
	 * @param int          $size Avatar size in pixels.
	 *
	 * @return string Wrapped markup, '' for empty input, or the input unchanged
	 *                when it isn't an image or is already wrapped.
	 */
	public static function wrap( $img, int $size ): string {
		$img = (string) $img;

		if ( '' === $img ) {
			return '';
		}

		if ( false === stripos( $img, '<img' ) || str_starts_with( $img, '<span class="avatar"' ) ) {
			return $img;
		}

		return '<span class="avatar" style="--bs-avatar-size: ' . self::to_rem( $size ) . 'rem;">' . self::swap_class( $img ) . '</span>';
	}

	/**
	 * Replace the `avatar` class token on the <img> with `avatar-img`.
	 *
	 * @param string $img Image HTML.
	 *
	 * @return string
	 */
	private static function swap_class( string $img ): string {
		$swapped = preg_replace_callback(
			'/(<img\b[^>]*?\sclass=)(["\'])(.*?)\2/i',
			static function ( $m ) {
				$classes = preg_split( '/\s+/', trim( $m[3] ) );
				$classes = array_map(
					static fn( $c ) => ( 'avatar' === $c ) ? 'avatar-img' : $c,
					$classes
				);
				if ( ! in_array( 'avatar-img', $classes, true ) ) {
					array_unshift( $classes, 'avatar-img' );
				}
				return $m[1] . $m[2] . implode( ' ', $classes ) . $m[2];
			},
			$img,
			1,
			$count
		);

		if ( 0 === $count ) {
			// No class attribute at all.
			$swapped = preg_replace( '/<img\b/i', '<img class="avatar-img"', $img, 1 );
		}

		return (string) $swapped;
	}

	/**
	 * Pixels to rem (16px root), without trailing zeros.
	 *
	 * @param int $size Pixels.
	 *
	 * @return string
	 */
	private static function to_rem( int $size ): string {
		return rtrim( rtrim( sprintf( '%.4F', $size / 16 ), '0' ), '.' );
	}
}
