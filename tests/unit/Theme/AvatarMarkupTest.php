<?php
/**
 * Unit tests for the Bootstrap 6 avatar wrapper.
 *
 * get_avatar() puts `class="avatar"` on its <img>, which Bootstrap 6 treats
 * as the wrapper component and shrinks to 2.5rem. Avatar_Markup moves the
 * image onto `.avatar-img` inside a sized `.avatar` span.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Theme;

use PHPUnit\Framework\TestCase;
use LWTV\Theme\Build\Avatar_Markup;

class AvatarMarkupTest extends TestCase {

	/**
	 * What get_avatar() returns for a 96px avatar (core's single quotes).
	 */
	private const CORE_IMG = "<img alt='Avatar for author Mika' src='https://secure.gravatar.com/avatar/abc?s=96' srcset='https://secure.gravatar.com/avatar/abc?s=192 2x' class='avatar avatar-96 photo' height='96' width='96' loading='lazy' decoding='async'/>";

	public function test_wraps_image_in_sized_avatar_span(): void {
		$html = Avatar_Markup::wrap( self::CORE_IMG, 96 );

		$this->assertStringStartsWith( '<span class="avatar" style="--bs-avatar-size: 6rem;">', $html );
		$this->assertStringEndsWith( '</span>', $html );
	}

	public function test_swaps_avatar_class_for_avatar_img(): void {
		$html = Avatar_Markup::wrap( self::CORE_IMG, 96 );

		$this->assertStringContainsString( "class='avatar-img avatar-96 photo'", $html );
	}

	public function test_keeps_size_specific_and_default_classes(): void {
		$img  = "<img src='x' class='avatar avatar-118 photo avatar-default' height='118' width='118'/>";
		$html = Avatar_Markup::wrap( $img, 118 );

		$this->assertStringContainsString( "class='avatar-img avatar-118 photo avatar-default'", $html );
	}

	public function test_handles_double_quoted_class_attribute(): void {
		$img  = '<img src="x" class="photo avatar avatar-50" width="50" height="50">';
		$html = Avatar_Markup::wrap( $img, 50 );

		$this->assertStringContainsString( 'class="photo avatar-img avatar-50"', $html );
	}

	public function test_adds_avatar_img_when_image_has_no_class(): void {
		$img  = '<img src="x" alt="">';
		$html = Avatar_Markup::wrap( $img, 64 );

		$this->assertStringContainsString( '<img class="avatar-img" src="x" alt="">', $html );
	}

	public function test_converts_pixel_size_to_rem(): void {
		$this->assertStringContainsString( '--bs-avatar-size: 11.875rem;', Avatar_Markup::wrap( self::CORE_IMG, 190 ) );
		$this->assertStringContainsString( '--bs-avatar-size: 7.8125rem;', Avatar_Markup::wrap( self::CORE_IMG, 125 ) );
	}

	public function test_returns_empty_string_for_empty_input(): void {
		// get_avatar() returns false when avatars are disabled.
		$this->assertSame( '', Avatar_Markup::wrap( '', 96 ) );
		$this->assertSame( '', Avatar_Markup::wrap( false, 96 ) );
	}

	public function test_leaves_non_image_markup_untouched(): void {
		// A filter can replace the avatar with anything; don't wrap what we can't fix.
		$this->assertSame( '<div>no image</div>', Avatar_Markup::wrap( '<div>no image</div>', 96 ) );
	}

	public function test_does_not_double_wrap(): void {
		$once = Avatar_Markup::wrap( self::CORE_IMG, 96 );

		$this->assertSame( $once, Avatar_Markup::wrap( $once, 96 ) );
	}
}
