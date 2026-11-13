<?php
/**
 * Unit tests for the known-consumers list shaping.
 *
 * WordPress sanitizes each field before normalize() sees it; these tests pin
 * the structural rules only: what gets dropped, what wins, and which labels
 * count as our own traffic.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Admin_Menu;

use PHPUnit\Framework\TestCase;
use LWTV\Admin_Menu\Build\Api_Known_Consumers;

class ApiKnownConsumersTest extends TestCase {

	public function test_seed_marks_our_traffic_internal(): void {
		$seed = Api_Known_Consumers::seed();

		$this->assertSame(
			array( 'uptime-kuma', 'WP:https://www.thetracyl.com' ),
			Api_Known_Consumers::internal_labels( $seed )
		);
		$this->assertArrayHasKey( 'EuropeanFictionalCharacterResearch', $seed );
		$this->assertSame( '', $seed['SapphoWorkbenchCrawler']['contact'] );
		$this->assertSame( 'to-contact', $seed['queer-tv-guide-pipeline']['status'] );
	}

	public function test_empty_labels_are_dropped(): void {
		$out = Api_Known_Consumers::normalize(
			array(
				array(
					'label' => '   ',
					'name'  => 'ghost',
				),
				array( 'label' => 'curl' ),
			)
		);

		$this->assertSame( array( 'curl' ), array_keys( $out ) );
		$this->assertSame(
			array(
				'label'    => 'curl',
				'name'     => '',
				'contact'  => '',
				'notes'    => '',
				'status'   => 'none',
				'internal' => false,
			),
			$out['curl']
		);
	}

	public function test_unknown_status_falls_back(): void {
		$out = Api_Known_Consumers::normalize(
			array(
				array(
					'label'  => 'x',
					'status' => 'hacked',
				),
			)
		);

		$this->assertSame( 'none', $out['x']['status'] );
	}

	public function test_internal_only_from_checkbox_values(): void {
		$out = Api_Known_Consumers::normalize(
			array(
				array(
					'label'    => 'a',
					'internal' => '1',
				),
				array(
					'label'    => 'b',
					'internal' => 'yes',
				),
				array( 'label' => 'c' ),
			)
		);

		$this->assertSame( array( 'a' ), Api_Known_Consumers::internal_labels( $out ) );
	}

	public function test_duplicate_label_last_wins(): void {
		$out = Api_Known_Consumers::normalize(
			array(
				array(
					'label' => 'node',
					'name'  => 'first',
				),
				array(
					'label' => ' node ',
					'name'  => 'second',
				),
			)
		);

		$this->assertCount( 1, $out );
		$this->assertSame( 'second', $out['node']['name'] );
	}

	public function test_contact_kind_needs_no_network(): void {
		// Was wp_http_validate_url(), which resolves DNS and rejects ports and
		// private hosts. This is a display decision, not a fetch.
		$this->assertSame( 'url', Api_Known_Consumers::contact_kind( 'https://example.org:8443/contact' ) );
		$this->assertSame( 'url', Api_Known_Consumers::contact_kind( 'http://not-a-real-host.invalid' ) );
		$this->assertSame( 'email', Api_Known_Consumers::contact_kind( 'someone@example.org' ) );
		$this->assertSame( '', Api_Known_Consumers::contact_kind( 'javascript:alert(1)' ) );
		$this->assertSame( '', Api_Known_Consumers::contact_kind( 'ask in Slack' ) );
		$this->assertSame( '', Api_Known_Consumers::contact_kind( '' ) );
	}

	public function test_long_fields_are_capped(): void {
		$out = Api_Known_Consumers::normalize(
			array(
				array(
					'label' => 'x',
					'notes' => str_repeat( 'a', 900 ),
				),
			)
		);

		$this->assertSame( 500, strlen( $out['x']['notes'] ) );
	}
}
