<?php
/**
 * Unit tests for the Characters On Air view transforms.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\This_Year;

use PHPUnit\Framework\TestCase;
use LWTV\This_Year\Build\Characters_On_Air;

class CharactersOnAirTest extends TestCase {

	// ---- roles_by_strength(): strongest-first, tracked roles only. ----

	public function test_roles_by_strength_orders_strongest_first(): void {
		$shows = array(
			array( 'name' => "Grey's Anatomy", 'type' => 'guest' ),
			array( 'name' => 'Station 19', 'type' => 'regular' ),
		);

		$this->assertSame(
			array(
				array( 'type' => 'regular', 'show' => 'Station 19' ),
				array( 'type' => 'guest', 'show' => "Grey's Anatomy" ),
			),
			Characters_On_Air::roles_by_strength( $shows )
		);
	}

	public function test_roles_by_strength_filters_unknown_and_missing_types(): void {
		$shows = array(
			array( 'name' => 'A', 'type' => 'cameo' ),
			array( 'name' => 'B' ),
			array( 'name' => 'C', 'type' => 'recurring' ),
		);

		$this->assertSame(
			array( array( 'type' => 'recurring', 'show' => 'C' ) ),
			Characters_On_Air::roles_by_strength( $shows )
		);
	}

	public function test_roles_by_strength_empty_input(): void {
		$this->assertSame( array(), Characters_On_Air::roles_by_strength( array() ) );
	}

	// ---- bucket_for(): Latin initial or #. ----

	public function test_bucket_for_latin_initial_uppercased(): void {
		$this->assertSame( 'A', Characters_On_Air::bucket_for( 'abby Littman' ) );
		$this->assertSame( 'Z', Characters_On_Air::bucket_for( 'Zoé Lee' ) );
	}

	public function test_bucket_for_non_latin_or_empty_is_hash(): void {
		$this->assertSame( '#', Characters_On_Air::bucket_for( 'Émile' ) );
		$this->assertSame( '#', Characters_On_Air::bucket_for( '9-Ball' ) );
		$this->assertSame( '#', Characters_On_Air::bucket_for( '' ) );
	}

	// ---- alphabet(): 27-column model; bars sum to the character count. ----

	public function test_alphabet_columns_sum_to_character_count(): void {
		$chars  = array(
			array( 'name' => 'Abby' ),
			array( 'name' => 'Alix' ),
			array( 'name' => 'Bea' ),
			array( 'name' => 'Émile' ), // non-Latin initial → # bucket
		);
		$result = Characters_On_Air::alphabet( $chars );

		$this->assertCount( 27, $result['columns'] );
		$this->assertSame( 'A', $result['columns'][0]['letter'] );
		$this->assertSame( '#', $result['columns'][26]['letter'] );

		$sum = array_sum( array_column( $result['columns'], 'count' ) );
		$this->assertSame( count( $chars ), $sum );
		$this->assertSame( 1, $result['hash'] );
	}

	public function test_alphabet_marks_peak_ties_and_unused(): void {
		$chars = array(
			array( 'name' => 'Abby' ),
			array( 'name' => 'Alix' ),  // A = 2
			array( 'name' => 'Max' ),
			array( 'name' => 'Mel' ),   // M = 2  → A and M tie for peak
			array( 'name' => 'Bea' ),   // B = 1
		);
		$result = Characters_On_Air::alphabet( $chars );

		$this->assertSame( 2, $result['max'] );
		$this->assertSame( array( 'A', 'M' ), $result['top'] );
		$this->assertSame( 3, $result['in_use'] );
		$this->assertContains( 'C', $result['unused'] );
		// A and M columns are flagged peak; B is not.
		$cols = array_column( $result['columns'], 'peak', 'letter' );
		$this->assertTrue( $cols['A'] );
		$this->assertTrue( $cols['M'] );
		$this->assertFalse( $cols['B'] );
	}

	public function test_alphabet_empty_input(): void {
		$result = Characters_On_Air::alphabet( array() );
		$this->assertSame( 0, $result['max'] );
		$this->assertSame( array(), $result['top'] );
		$this->assertSame( 0, $result['in_use'] );
		$this->assertCount( 26, $result['unused'] );
		$this->assertSame( 0, $result['hash'] );
	}

	// ---- directory(): bucketed, alphabetized, role attached. ----

	public function test_directory_buckets_alphabetically_with_hash_last(): void {
		$chars  = array(
			array( 'slug' => 'bea', 'name' => 'Bea', 'dead' => false, 'shows' => array( array( 'name' => 'Hightown', 'type' => 'guest' ) ) ),
			array( 'slug' => 'abby', 'name' => 'Abby', 'dead' => false, 'shows' => array( array( 'name' => 'Ginny', 'type' => 'recurring' ) ) ),
			array( 'slug' => 'emile', 'name' => 'Émile', 'dead' => false, 'shows' => array() ),
		);
		$result = Characters_On_Air::directory( $chars );

		$this->assertSame( array( 'A', 'B', '#' ), array_column( $result, 'letter' ) );
		$this->assertSame( 'Abby', $result[0]['rows'][0]['name'] );
		$this->assertSame( 1, $result[0]['count'] );
	}

	public function test_directory_attaches_strongest_role(): void {
		$chars  = array(
			array(
				'slug'  => 'x',
				'name'  => 'Xena',
				'dead'  => false,
				'shows' => array(
					array( 'name' => "Grey's", 'type' => 'guest' ),
					array( 'name' => 'Station 19', 'type' => 'regular' ),
				),
			),
		);
		$result = Characters_On_Air::directory( $chars );
		$row    = $result[0]['rows'][0];

		$this->assertSame( 'regular', $row['role'] );
		$this->assertSame( 'regular', $row['roles'][0]['type'] );
		$this->assertCount( 2, $row['roles'] );
	}

	public function test_directory_sorts_within_bucket(): void {
		$chars  = array(
			array( 'slug' => 'ariel', 'name' => 'Ariel', 'dead' => false, 'shows' => array() ),
			array( 'slug' => 'abby', 'name' => 'Abby', 'dead' => false, 'shows' => array() ),
		);
		$result = Characters_On_Air::directory( $chars );

		$this->assertSame( array( 'Abby', 'Ariel' ), array_column( $result[0]['rows'], 'name' ) );
	}

	// ---- cast_for_show(): filter nameless, sort alphabetically. ----

	public function test_cast_for_show_filters_nameless_and_sorts(): void {
		$cast = array(
			array( 'name' => 'Zoé Lee', 'type' => 'recurring' ),
			array( 'name' => '', 'type' => 'guest' ),        // dangling reference → dropped
			array( 'name' => '   ', 'type' => 'regular' ),   // whitespace only → dropped
			array( 'name' => 'Alix Kubdel', 'type' => 'recurring' ),
		);

		$result = Characters_On_Air::cast_for_show( $cast );

		$this->assertSame(
			array( 'Alix Kubdel', 'Zoé Lee' ),
			array_column( $result, 'name' )
		);
	}

	public function test_cast_for_show_empty_input(): void {
		$this->assertSame( array(), Characters_On_Air::cast_for_show( array() ) );
	}
}
