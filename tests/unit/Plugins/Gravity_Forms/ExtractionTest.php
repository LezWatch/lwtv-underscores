<?php
/**
 * Unit tests for the Gemini prompt, schema and output validation.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Plugins\Gravity_Forms;

use PHPUnit\Framework\TestCase;
use LWTV\Plugins\Gravity_Forms\Build\Extraction;

class ExtractionTest extends TestCase {

	private function allowed(): array {
		return array(
			'sexuality' => array( 'Homosexual', 'Bisexual' ),
			'gender'    => array( 'Cisgender', 'Trans Woman' ),
			'queerness' => array( 'Canonical', 'Queer IRL' ),
			'cliches'   => array( 'Cartoon', 'Queer IRL' ),
		);
	}

	public function test_parse_satya_lara_example(): void {
		$rows = Extraction::parse( '{"characters":[{"name":"Satya"},{"name":"Lara"}]}', 'Blue', $this->allowed() );
		$this->assertCount( 2, $rows );
		$this->assertSame(
			array(
				'name'      => 'Satya',
				'actor'     => '',
				'queerness' => '',
				'cliches'   => '',
				'sexuality' => '',
				'gender'    => '',
			),
			$rows[0]
		);
		$this->assertSame( 'Lara', $rows[1]['name'] );
	}

	public function test_parse_blanks_values_not_on_list(): void {
		$json = '{"characters":[{"name":"Satya","gender":"Cisgender Female","sexuality":"lesbian"}]}';
		$rows = Extraction::parse( $json, 'Blue', $this->allowed() );
		$this->assertSame( '', $rows[0]['gender'] );
		$this->assertSame( '', $rows[0]['sexuality'] );
	}

	public function test_parse_returns_stored_controlled_string(): void {
		$allowed              = $this->allowed();
		$allowed['queerness'] = array( "Canonical\u{2060} " );
		$rows                 = Extraction::parse( '{"characters":[{"name":"Satya","queerness":"canonical"}]}', 'Blue', $allowed );
		$this->assertSame( "Canonical\u{2060} ", $rows[0]['queerness'] );
	}

	public function test_parse_cliches_filters_and_joins(): void {
		$json = '{"characters":[{"name":"Satya","cliches":["Cartoon","Made Up","Queer IRL"]}]}';
		$rows = Extraction::parse( $json, 'Blue', $this->allowed() );
		$this->assertSame( 'Cartoon, Queer IRL', $rows[0]['cliches'] );
	}

	public function test_parse_cliches_string_is_one_item(): void {
		$rows = Extraction::parse( '{"characters":[{"name":"Satya","cliches":"cartoon"}]}', 'Blue', $this->allowed() );
		$this->assertSame( 'Cartoon', $rows[0]['cliches'] );
	}

	public function test_parse_non_string_controlled_values_are_blank(): void {
		$json = '{"characters":[{"name":"Satya","gender":5,"sexuality":["Bisexual"],"queerness":null,"actor":12}]}';
		$rows = Extraction::parse( $json, 'Blue', $this->allowed() );
		$this->assertSame( '', $rows[0]['gender'] );
		$this->assertSame( '', $rows[0]['sexuality'] );
		$this->assertSame( '', $rows[0]['queerness'] );
		$this->assertSame( '', $rows[0]['actor'] );
	}

	public function test_parse_trims_actor(): void {
		$rows = Extraction::parse( '{"characters":[{"name":"Satya","actor":"  Jo Doe "}]}', 'Blue', $this->allowed() );
		$this->assertSame( 'Jo Doe', $rows[0]['actor'] );
	}

	public function test_parse_drops_show_title_and_placeholders(): void {
		$json = '{"characters":[{"name":"Blue"},{"name":"Multiple"},{"name":""},{"name":"Satya"},{"foo":1},"x"]}';
		$rows = Extraction::parse( $json, 'Blue', $this->allowed() );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'Satya', $rows[0]['name'] );
	}

	public function test_parse_dedupes_names(): void {
		$rows = Extraction::parse( '{"characters":[{"name":"Satya"},{"name":"satya","actor":"X"}]}', 'Blue', $this->allowed() );
		$this->assertCount( 1, $rows );
		$this->assertSame( '', $rows[0]['actor'] );
	}

	public function test_parse_throws_on_invalid_json(): void {
		foreach ( array( 'not json', '{"foo":1}', '{"characters":"x"}' ) as $bad ) {
			try {
				Extraction::parse( $bad, 'Blue', $this->allowed() );
				$this->fail( 'Expected exception for ' . $bad );
			} catch ( \UnexpectedValueException $e ) {
				$this->assertInstanceOf( \UnexpectedValueException::class, $e );
			}
		}
	}

	public function test_parse_empty_list_returns_empty_array(): void {
		$this->assertSame( array(), Extraction::parse( '{"characters":[]}', 'Blue', $this->allowed() ) );
	}

	public function test_prompt_contains_inputs_and_lists_but_no_pii(): void {
		$prompt = Extraction::prompt( 'Blue', 'Satya and Lara fall in love.', $this->allowed() );
		$this->assertStringContainsString( 'Blue', $prompt );
		$this->assertStringContainsString( 'Satya and Lara fall in love.', $prompt );
		$this->assertStringContainsString( 'Homosexual', $prompt );
		$this->assertStringContainsString( 'Trans Woman', $prompt );
		$this->assertStringContainsString( 'explicitly', $prompt );
		$this->assertSame( 3, ( new \ReflectionMethod( Extraction::class, 'prompt' ) )->getNumberOfParameters() );
	}

	public function test_schema_shape(): void {
		$schema = Extraction::schema();
		$this->assertSame( 'OBJECT', $schema['type'] );
		$item = $schema['properties']['characters']['items'];
		$this->assertSame( array( 'name' ), $item['required'] );
		$this->assertSame( array_merge( array( 'name' ), Extraction::FIELDS ), array_keys( $item['properties'] ) );
		$this->assertSame( 'ARRAY', $item['properties']['cliches']['type'] );
		$this->assertTrue( $item['properties']['gender']['nullable'] );
	}

	public function test_parse_blanks_placeholder_actor(): void {
		$rows = Extraction::parse( '{"characters":[{"name":"Satya","actor":"Unknown"}]}', 'Blue', $this->allowed() );
		$this->assertSame( '', $rows[0]['actor'] );
	}

	public function test_parse_keeps_named_actor(): void {
		$rows = Extraction::parse( '{"characters":[{"name":"Satya","actor":" Jane Doe "}]}', 'Blue', $this->allowed() );
		$this->assertSame( 'Jane Doe', $rows[0]['actor'] );
	}
}
