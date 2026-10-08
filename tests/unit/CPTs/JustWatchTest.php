<?php
/**
 * Unit tests for choosing between the JustWatch widget and the curated Ways to
 * Watch links, and for building the widget's data attributes.
 *
 * See docs/plans/justwatch-widget.md#decision-logic.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\CPTs;

use PHPUnit\Framework\TestCase;
use LWTV\CPTs\Shows\Watching\JustWatch;

class JustWatchTest extends TestCase {

	const KEY = 'test-key-123';

	/*
	 * decide(): no key
	 */

	public function test_no_key_with_curated_links_is_curated(): void {
		$this->assertSame( JustWatch::MODE_CURATED, JustWatch::decide( '', 'tt0330251', '', false, true )['mode'] );
	}

	public function test_no_key_without_curated_links_is_none(): void {
		$this->assertSame( JustWatch::MODE_NONE, JustWatch::decide( '', 'tt0330251', '', false, false )['mode'] );
	}

	public function test_whitespace_key_counts_as_no_key(): void {
		$this->assertSame( JustWatch::MODE_CURATED, JustWatch::decide( "  \t", 'tt0330251', '', false, true )['mode'] );
	}

	/*
	 * decide(): key and IMDb ID
	 */

	public function test_key_and_valid_id_is_justwatch(): void {
		$result = JustWatch::decide( self::KEY, 'tt0330251', '', false, true );

		$this->assertSame( JustWatch::MODE_JUSTWATCH, $result['mode'] );
		$this->assertSame( 'tt0330251', $result['imdb'] );
	}

	public function test_key_and_valid_id_without_curated_links_is_still_justwatch(): void {
		// The case the old template gate hid: an IMDb ID but no curated rows.
		$this->assertSame( JustWatch::MODE_JUSTWATCH, JustWatch::decide( self::KEY, 'tt0330251', '', false, false )['mode'] );
	}

	public function test_id_is_lowercased_and_trimmed(): void {
		$this->assertSame( 'tt0330251', JustWatch::decide( self::KEY, '  TT0330251 ', '', false, true )['imdb'] );
	}

	public function test_pasted_imdb_url_is_reduced_to_id(): void {
		$result = JustWatch::decide( self::KEY, 'https://www.imdb.com/title/tt0330251/?ref_=nv_sr_1', '', false, true );

		$this->assertSame( JustWatch::MODE_JUSTWATCH, $result['mode'] );
		$this->assertSame( 'tt0330251', $result['imdb'] );
	}

	public function test_empty_id_falls_back_to_curated(): void {
		$this->assertSame( JustWatch::MODE_CURATED, JustWatch::decide( self::KEY, '', '', false, true )['mode'] );
	}

	public function test_empty_id_without_curated_links_is_none(): void {
		$this->assertSame( JustWatch::MODE_NONE, JustWatch::decide( self::KEY, '', '', false, false )['mode'] );
	}

	public function test_junk_id_falls_back(): void {
		$this->assertSame( JustWatch::MODE_CURATED, JustWatch::decide( self::KEY, 'not an id', '', false, true )['mode'] );
		$this->assertSame( JustWatch::MODE_NONE, JustWatch::decide( self::KEY, 'not an id', '', false, false )['mode'] );
	}

	public function test_non_scalar_id_falls_back(): void {
		$this->assertSame( JustWatch::MODE_CURATED, JustWatch::decide( self::KEY, array( 'tt0330251' ), null, false, true )['mode'] );
	}

	public function test_person_id_is_not_a_show(): void {
		// Imdb_Canonical::normalise() accepts nm IDs; a show must have a tt ID.
		$this->assertSame( JustWatch::MODE_CURATED, JustWatch::decide( self::KEY, 'nm0000123', '', false, true )['mode'] );
	}

	/*
	 * decide(): canonical ID
	 */

	public function test_canonical_id_wins_over_stored_id(): void {
		$this->assertSame( 'tt9999999', JustWatch::decide( self::KEY, 'tt0330251', 'tt9999999', false, true )['imdb'] );
	}

	public function test_junk_canonical_falls_back_to_stored_id(): void {
		$this->assertSame( 'tt0330251', JustWatch::decide( self::KEY, 'tt0330251', 'garbage', false, true )['imdb'] );
	}

	public function test_canonical_alone_is_enough(): void {
		$result = JustWatch::decide( self::KEY, '', 'tt9999999', false, false );

		$this->assertSame( JustWatch::MODE_JUSTWATCH, $result['mode'] );
		$this->assertSame( 'tt9999999', $result['imdb'] );
	}

	/*
	 * decide(): override
	 */

	public function test_override_forces_curated(): void {
		$this->assertSame( JustWatch::MODE_CURATED, JustWatch::decide( self::KEY, 'tt0330251', '', true, true )['mode'] );
	}

	public function test_override_without_curated_links_is_none(): void {
		$this->assertSame( JustWatch::MODE_NONE, JustWatch::decide( self::KEY, 'tt0330251', '', true, false )['mode'] );
	}

	/*
	 * decide(): shape
	 */

	public function test_non_widget_modes_carry_no_id(): void {
		$this->assertSame( '', JustWatch::decide( '', 'tt0330251', '', false, true )['imdb'] );
		$this->assertSame( '', JustWatch::decide( self::KEY, 'tt0330251', '', true, true )['imdb'] );
	}

	/*
	 * widget_attributes()
	 */

	public function test_widget_attributes_fixed_values(): void {
		$attrs = JustWatch::widget_attributes( self::KEY, 'tt0330251', 'light' );

		$this->assertArrayHasKey( 'data-jw-widget', $attrs );
		$this->assertSame( '', $attrs['data-jw-widget'] );
		$this->assertSame( self::KEY, $attrs['data-api-key'] );
		$this->assertSame( 'show', $attrs['data-object-type'] );
		$this->assertSame( 'tt0330251', $attrs['data-id'] );
		$this->assertSame( 'imdb', $attrs['data-id-type'] );
		$this->assertSame( 'light', $attrs['data-theme'] );
	}

	public function test_widget_attributes_trim_key(): void {
		$this->assertSame( self::KEY, JustWatch::widget_attributes( ' ' . self::KEY . ' ', 'tt0330251', 'light' )['data-api-key'] );
	}

	public function test_widget_attributes_messages_present(): void {
		$attrs = JustWatch::widget_attributes( self::KEY, 'tt0330251', 'light' );

		$this->assertNotSame( '', $attrs['data-no-offers-message'] );
		$this->assertStringContainsString( '{{title}}', $attrs['data-no-offers-message'] );
		$this->assertNotSame( '', $attrs['data-title-not-found-message'] );
	}

	public function test_widget_theme_dark(): void {
		$this->assertSame( 'dark', JustWatch::widget_attributes( self::KEY, 'tt0330251', 'dark' )['data-theme'] );
	}

	public function test_widget_theme_unknown_falls_back_to_light(): void {
		$this->assertSame( 'light', JustWatch::widget_attributes( self::KEY, 'tt0330251', '' )['data-theme'] );
		$this->assertSame( 'light', JustWatch::widget_attributes( self::KEY, 'tt0330251', 'auto' )['data-theme'] );
		$this->assertSame( 'light', JustWatch::widget_attributes( self::KEY, 'tt0330251', 'DARK"><script>' )['data-theme'] );
	}

	public function test_widget_theme_is_case_insensitive(): void {
		$this->assertSame( 'dark', JustWatch::widget_attributes( self::KEY, 'tt0330251', 'Dark' )['data-theme'] );
	}
}
