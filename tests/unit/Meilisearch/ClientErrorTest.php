<?php
/**
 * Unit tests for Meilisearch error messages, which end up in logs.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Meilisearch;

use PHPUnit\Framework\TestCase;
use LWTV\Meilisearch\Client;

class ClientErrorTest extends TestCase {

	public function test_meili_error_body(): void {
		$this->assertSame(
			'HTTP 401: invalid_api_key — The provided API key is invalid.',
			Client::describe_error( 401, '{"message":"The provided API key is invalid.","code":"invalid_api_key","type":"auth"}' )
		);
	}

	public function test_non_json_body(): void {
		$this->assertSame( 'HTTP 502', Client::describe_error( 502, '<html>Bad Gateway</html>' ) );
	}

	public function test_never_echoes_bearer(): void {
		$this->assertStringNotContainsString( 'abc123', Client::describe_error( 500, 'Bearer abc123' ) );
	}
}
