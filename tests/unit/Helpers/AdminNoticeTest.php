<?php
/**
 * Unit tests for the shared admin notice helper.
 *
 * Only css_class() is testable here -- set() and show() write transients and
 * echo markup. It is also the part most likely to drift: any type the mapping
 * does not name falls through to a success notice.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use LWTV\_Helpers\Admin_Notice;

class AdminNoticeTest extends TestCase {

	public function test_the_three_known_types_map_to_wordpress_classes(): void {
		$this->assertSame( 'notice-success', Admin_Notice::css_class( 'success' ) );
		$this->assertSame( 'notice-error', Admin_Notice::css_class( 'error' ) );
		$this->assertSame( 'notice-info', Admin_Notice::css_class( 'info' ) );
	}

	public function test_an_unknown_type_falls_back_to_success(): void {
		// What all three originals did. Pinned so it stays a decision rather
		// than an accident of ternary nesting.
		$this->assertSame( 'notice-success', Admin_Notice::css_class( 'banana' ) );
	}

	public function test_an_empty_type_falls_back_to_success(): void {
		// Reachable: show() reads $notice['type'] off a stored array with ?? ''.
		$this->assertSame( 'notice-success', Admin_Notice::css_class( '' ) );
	}

	public function test_the_mapping_is_case_sensitive_and_exact(): void {
		// Callers pass lowercase literals. If that ever stops being true this
		// should fail loudly rather than quietly showing the wrong colour.
		$this->assertSame( 'notice-success', Admin_Notice::css_class( 'Error' ) );
		$this->assertSame( 'notice-success', Admin_Notice::css_class( 'ERROR' ) );
	}

	public function test_every_class_it_returns_is_one_wordpress_styles(): void {
		$allowed = array( 'notice-success', 'notice-error', 'notice-info' );

		foreach ( array( 'success', 'error', 'info', '', 'nonsense' ) as $type ) {
			$this->assertContains( Admin_Notice::css_class( $type ), $allowed );
		}
	}

	public function test_the_ttl_is_long_enough_to_survive_a_redirect(): void {
		// The whole point is to outlive one wp_safe_redirect(). A value of 0
		// would make every notice vanish before it was ever shown.
		$this->assertGreaterThanOrEqual( 1, Admin_Notice::TTL_MINUTES );
	}
}
