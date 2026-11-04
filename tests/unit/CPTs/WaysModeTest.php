<?php
/**
 * Unit tests for choosing what a show's Ways to Watch section renders.
 *
 * See docs/plans/tmdb-watch-providers.md#decision-logic-pure.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\CPTs;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use LWTV\CPTs\Shows\Watching\Ways_Mode;

class WaysModeTest extends TestCase {

	/**
	 * Every combination of the three inputs.
	 *
	 * @return array<string, array{bool, bool, bool, string}>
	 */
	public static function matrix(): array {
		return array(
			'nothing at all'                => array( false, false, false, Ways_Mode::MODE_NONE ),
			'curated only'                  => array( false, true, false, Ways_Mode::MODE_CURATED ),
			'providers only'                => array( false, false, true, Ways_Mode::MODE_PROVIDERS ),
			'providers replace curated'     => array( false, true, true, Ways_Mode::MODE_PROVIDERS ),
			'override, nothing curated'     => array( true, false, false, Ways_Mode::MODE_NONE ),
			'override with curated'         => array( true, true, false, Ways_Mode::MODE_CURATED ),
			'override beats providers'      => array( true, true, true, Ways_Mode::MODE_CURATED ),
			'override, providers, no links' => array( true, false, true, Ways_Mode::MODE_NONE ),
		);
	}

	#[DataProvider( 'matrix' )]
	public function test_decide( bool $force_curated, bool $has_curated, bool $has_providers, string $expected ): void {
		$this->assertSame( $expected, Ways_Mode::decide( $force_curated, $has_curated, $has_providers ) );
	}
}
