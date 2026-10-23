<?php
/**
 * Unit tests for the Bootstrap 5 → 6 class migration of stored post content.
 *
 * See the class docblock in Bootstrap6_Markup for the mapping.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use LWTV\_Helpers\Bootstrap6_Markup;

class Bootstrap6MarkupTest extends TestCase {

	public function test_alert_variant_becomes_theme(): void {
		$out = Bootstrap6_Markup::migrate( '<div class="alert alert-warning">Heads up</div>' );

		$this->assertSame( '<div class="alert theme-warning">Heads up</div>', $out['html'] );
		$this->assertSame( array( 'alert-warning' => 1 ), $out['changes'] );
	}

	public function test_dark_maps_to_inverse(): void {
		$out = Bootstrap6_Markup::migrate( '<a class="btn btn-dark" href="#">Go</a>' );

		$this->assertSame( '<a class="btn btn-solid theme-inverse" href="#">Go</a>', $out['html'] );
	}

	public function test_solid_and_outline_buttons(): void {
		$out = Bootstrap6_Markup::migrate( '<a class="btn btn-primary">A</a><a class="btn btn-outline-danger">B</a>' );

		$this->assertSame( '<a class="btn btn-solid theme-primary">A</a><a class="btn btn-outline theme-danger">B</a>', $out['html'] );
		$this->assertSame(
			array(
				'btn-primary'        => 1,
				'btn-outline-danger' => 1,
			),
			$out['changes']
		);
	}

	public function test_text_bg_badge(): void {
		$out = Bootstrap6_Markup::migrate( '<span class="badge text-bg-success">New</span>' );

		$this->assertSame( '<span class="badge theme-success">New</span>', $out['html'] );
	}

	public function test_single_quotes_and_extra_classes_are_kept(): void {
		$out = Bootstrap6_Markup::migrate( "<p class='mb-3 alert alert-info lead-in' id='x'>Hi</p>" );

		$this->assertSame( "<p class='mb-3 alert theme-info lead-in' id='x'>Hi</p>", $out['html'] );
	}

	public function test_does_not_duplicate_an_existing_theme_class(): void {
		$out = Bootstrap6_Markup::migrate( '<div class="alert alert-danger theme-danger">x</div>' );

		$this->assertSame( '<div class="alert theme-danger">x</div>', $out['html'] );
	}

	public function test_light_variants_are_reported_not_changed(): void {
		$html = '<div class="alert alert-light">x</div><a class="btn btn-light">y</a>';
		$out  = Bootstrap6_Markup::migrate( $html );

		$this->assertSame( $html, $out['html'] );
		$this->assertSame( array(), $out['changes'] );
		$this->assertSame( array( 'alert-light', 'btn-light' ), $out['unmapped'] );
	}

	public function test_text_content_is_never_changed(): void {
		// Prose and escaped code samples mention the classes; only real tags change.
		$html = '<p>Use alert-danger or <code>&lt;div class="alert alert-danger"&gt;</code> for errors.</p>';
		$out  = Bootstrap6_Markup::migrate( $html );

		$this->assertSame( $html, $out['html'] );
		$this->assertSame( array(), $out['changes'] );
	}

	public function test_other_attributes_are_untouched(): void {
		$html = '<div data-note="alert-danger" title="btn-primary" class="alert alert-danger">x</div>';
		$out  = Bootstrap6_Markup::migrate( $html );

		$this->assertSame( '<div data-note="alert-danger" title="btn-primary" class="alert theme-danger">x</div>', $out['html'] );
	}

	public function test_block_comment_classname_is_kept_in_step(): void {
		// A core block's HTML class must match the className in its comment, or the block goes invalid.
		$html = '<!-- wp:paragraph {"className":"alert alert-info"} --><p class="alert alert-info">x</p><!-- /wp:paragraph -->';
		$out  = Bootstrap6_Markup::migrate( $html );

		$this->assertSame( '<!-- wp:paragraph {"className":"alert theme-info"} --><p class="alert theme-info">x</p><!-- /wp:paragraph -->', $out['html'] );
		$this->assertSame( array( 'alert-info' => 2 ), $out['changes'] );
	}

	public function test_custom_blocks_are_skipped(): void {
		// Spoiler, Grade and Screener are upgraded by re-saving through Gutenberg; a string edit would invalidate them.
		$spoiler = '<!-- wp:lez-library/spoilers --><div class="wp-block-lez-library-spoilers alert alert-danger">S</div><!-- /wp:lez-library/spoilers -->';
		$grade   = '<!-- wp:lwtv/grade {"grade":"A"} --><div class="grade alert alert-info">A</div><!-- /wp:lwtv/grade -->';
		$screen  = '<!-- wp:lwtv/screener {"queer":"4"} --><button class="btn btn-dark">Q</button><!-- /wp:lwtv/screener -->';
		$html    = $spoiler . '<div class="alert alert-warning">hand</div>' . $grade . $screen;
		$out     = Bootstrap6_Markup::migrate( $html );

		$this->assertSame( $spoiler . '<div class="alert theme-warning">hand</div>' . $grade . $screen, $out['html'] );
		$this->assertSame( array( 'alert-warning' => 1 ), $out['changes'] );
	}

	public function test_unchanged_content_round_trips(): void {
		$html = '<!-- wp:paragraph --><p class="lead">Nothing to see.</p><!-- /wp:paragraph -->';
		$out  = Bootstrap6_Markup::migrate( $html );

		$this->assertSame( $html, $out['html'] );
		$this->assertSame( array(), $out['changes'] );
		$this->assertSame( array(), $out['unmapped'] );
	}
}
