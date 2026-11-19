<?php
/**
 * Drift guard: theme.json mirrors the SCSS design tokens.
 *
 * SCSS stays the source of truth (issue #744, option B). theme.json repeats
 * the palette, font sizes, and text-input state tokens so the block editor
 * offers the same brand values. Nothing at build time keeps the two in step,
 * so these tests fail when one side changes without the other.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Theme;

use PHPUnit\Framework\TestCase;

class ThemeJsonTokensTest extends TestCase {

	private const ROOT = __DIR__ . '/../../..';

	/**
	 * Decoded theme.json.
	 *
	 * @var array<string, mixed>
	 */
	private static array $theme_json;

	public static function setUpBeforeClass(): void {
		$json = file_get_contents( self::ROOT . '/theme.json' );
		self::assertIsString( $json );
		self::$theme_json = json_decode( $json, true, 512, JSON_THROW_ON_ERROR );
	}

	/**
	 * Normalizes a hex color to lowercase six-digit form (#06c -> #0066cc).
	 */
	private static function hex( string $color ): string {
		$color = strtolower( ltrim( trim( $color ), '#' ) );
		if ( 3 === strlen( $color ) ) {
			$color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
		}
		return '#' . $color;
	}

	/**
	 * Hex values of the `$name: #hex;` variables in a SCSS file.
	 *
	 * @return array<string, string> Variable name (without `$`) => hex.
	 */
	private static function scss_hex_vars( string $file ): array {
		$scss = file_get_contents( self::ROOT . '/' . $file );
		preg_match_all( '/^\$([a-z0-9-]+):\s*(#[0-9a-fA-F]{3,6})\s*;/m', (string) $scss, $m, PREG_SET_ORDER );
		$vars = array();
		foreach ( $m as $match ) {
			$vars[ $match[1] ] = self::hex( $match[2] );
		}
		return $vars;
	}

	public function test_is_a_v3_classic_theme_json_with_brand_only_pickers(): void {
		$settings = self::$theme_json['settings'];

		$this->assertSame( 3, self::$theme_json['version'] );
		$this->assertFalse( $settings['appearanceTools'] );
		$this->assertFalse( $settings['color']['custom'] );
		$this->assertFalse( $settings['color']['customGradient'] );
		$this->assertFalse( $settings['color']['defaultPalette'] );
		$this->assertFalse( $settings['typography']['customFontSize'] );
		$this->assertFalse( $settings['typography']['defaultFontSizes'] );
	}

	public function test_palette_colors_match_scss_variables(): void {
		$scss = self::scss_hex_vars( 'scss/partials/_colors.scss' );

		foreach ( self::$theme_json['settings']['color']['palette'] as $entry ) {
			$slug = $entry['slug'];
			$this->assertArrayHasKey( $slug, $scss, "theme.json palette slug '{$slug}' has no \${$slug} in _colors.scss." );
			$this->assertSame( $scss[ $slug ], self::hex( $entry['color'] ), "theme.json '{$slug}' drifted from \${$slug} in _colors.scss." );
		}
	}

	public function test_font_sizes_are_rem_values_from_fonts_scss(): void {
		$scss = (string) file_get_contents( self::ROOT . '/scss/partials/_fonts.scss' );
		preg_match_all( '/font-size:\s*([0-9.]+rem)\s*;/', $scss, $m );
		$known = array_map( 'floatval', $m[1] );

		foreach ( self::$theme_json['settings']['typography']['fontSizes'] as $size ) {
			$this->assertMatchesRegularExpression( '/^[0-9.]+rem$/', $size['size'], "Font size '{$size['slug']}' must be in rem." );

			// _fonts.scss rounds 13px to 0.812rem; theme.json keeps the exact 0.8125rem.
			$matches = array_filter( $known, fn( $rem ) => abs( $rem - (float) $size['size'] ) < 0.001 );
			$this->assertNotEmpty( $matches, "Font size '{$size['slug']}' ({$size['size']}) is not one of the _fonts.scss sizes." );
		}
	}

	public function test_small_font_size_stays_13px(): void {
		// Existing posts use `has-small-font-size`, which core rendered at 13px.
		$sizes = array_column( self::$theme_json['settings']['typography']['fontSizes'], 'size', 'slug' );

		$this->assertSame( '0.8125rem', $sizes['small'] );
	}

	public function test_button_element_hands_its_styles_back_to_scss(): void {
		// Core's default button element (grey #32373c pill) prints unlayered
		// and beats `@layer components`. `revert-layer` hands each property
		// back to the theme's SCSS instead of copying values into theme.json.
		$button = self::$theme_json['styles']['elements']['button'];

		$this->assertSame(
			array(
				'border'  => array(
					'radius' => 'revert-layer',
					'width'  => 'revert-layer',
				),
				'color'   => array(
					'background' => 'revert-layer',
					'text'       => 'revert-layer',
				),
				'spacing' => array( 'padding' => 'revert-layer' ),
			),
			$button
		);
	}

	public function test_text_input_states_use_tokens_defined_in_forms_scss(): void {
		$forms  = (string) file_get_contents( self::ROOT . '/scss/addons/_forms.scss' );
		$colors = self::scss_hex_vars( 'scss/partials/_colors.scss' );
		$states = self::$theme_json['styles']['elements']['textInput'];

		// Base textInput properties would print unlayered and override
		// Bootstrap and _forms.scss everywhere, so only states belong here.
		$this->assertSame( array( ':focus-visible', ':user-invalid', '::placeholder' ), array_keys( $states ) );

		$values = array(
			$states[':focus-visible']['outline']['color'],
			$states[':user-invalid']['border']['color'],
			$states['::placeholder']['color']['text'],
		);

		foreach ( $values as $value ) {
			$this->assertMatchesRegularExpression( '/^var\(--lwtv-input-[a-z-]+, #[0-9a-f]{6}\)$/', $value );
			preg_match( '/^var\((--lwtv-input-[a-z-]+), (#[0-9a-f]{6})\)$/', $value, $m );
			[ , $token, $fallback ] = $m;

			$this->assertMatchesRegularExpression(
				'/' . preg_quote( $token, '/' ) . ':\s*#\{colors\.\$([a-z0-9-]+)\}/',
				$forms,
				"{$token} is not defined in _forms.scss :root."
			);
			preg_match( '/' . preg_quote( $token, '/' ) . ':\s*#\{colors\.\$([a-z0-9-]+)\}/', $forms, $var );
			$this->assertSame( $colors[ $var[1] ], $fallback, "theme.json fallback for {$token} drifted from \${$var[1]}." );
		}
	}
}
