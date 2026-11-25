<?php
/**
 * Unit tests for classifying whether an index is set up.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Meilisearch;

use PHPUnit\Framework\TestCase;
use LWTV\Meilisearch\Build\Index_State;

class IndexStateTest extends TestCase {

	public function test_no_error_means_exists(): void {
		$this->assertSame( Index_State::EXISTS, Index_State::classify( null ) );
	}

	public function test_index_not_found_means_missing(): void {
		$this->assertSame( Index_State::MISSING, Index_State::classify( 'HTTP 404: index_not_found: Index `local_shows` not found.' ) );
	}

	public function test_server_down_is_unknown(): void {
		$this->assertSame( Index_State::UNKNOWN, Index_State::classify( 'cURL error 7: Failed to connect' ) );
	}

	public function test_bad_key_is_unknown(): void {
		$this->assertSame( Index_State::UNKNOWN, Index_State::classify( 'HTTP 403: invalid_api_key: The provided API key is invalid.' ) );
	}

	public function test_admin_pages_recheck_unknown_after_five_minutes(): void {
		// A down server must not cost every admin page load a timeout.
		$this->assertSame( 300, Index_State::recheck_after( Index_State::UNKNOWN ) );
		$this->assertSame( 0, Index_State::recheck_after( Index_State::EXISTS ) );
		$this->assertSame( 0, Index_State::recheck_after( Index_State::MISSING ) );
	}

	public function test_ttls(): void {
		$this->assertSame( 3600, Index_State::ttl( Index_State::EXISTS ) );
		$this->assertSame( 300, Index_State::ttl( Index_State::MISSING ) );
		$this->assertSame( 0, Index_State::ttl( Index_State::UNKNOWN ) );
		$this->assertSame( 0, Index_State::ttl( 'bogus' ) );
	}
}
