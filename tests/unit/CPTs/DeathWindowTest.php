<?php
/**
 * Tests for Death_Window: whether a dead character counts as dead for one show.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\CPTs;

use LWTV\CPTs\Shows\Scoring\Death_Window;
use PHPUnit\Framework\TestCase;

final class DeathWindowTest extends TestCase {

	public function test_year_of_reads_every_stored_date_format(): void {
		$this->assertSame( 2014, Death_Window::year_of( '20141219' ) );
		$this->assertSame( 2014, Death_Window::year_of( '2014-12-19' ) );
		$this->assertSame( 2014, Death_Window::year_of( '2014' ) );
		$this->assertSame( 2014, Death_Window::year_of( 20141219 ) );
	}

	public function test_year_of_rejects_junk_and_empty_sentinels(): void {
		$this->assertSame( 0, Death_Window::year_of( '' ) );
		$this->assertSame( 0, Death_Window::year_of( null ) );
		$this->assertSame( 0, Death_Window::year_of( '0000-00-00' ) );
		$this->assertSame( 0, Death_Window::year_of( 'L' ) );
		$this->assertSame( 0, Death_Window::year_of( array() ) );
	}

	public function test_no_dead_cliche_is_never_dead(): void {
		$this->assertFalse( Death_Window::is_dead_for_show( false, array( '2013-01-01' ), array( 2014 ) ) );
	}

	public function test_dead_cliche_without_a_death_date_stays_dead(): void {
		$this->assertTrue( Death_Window::is_dead_for_show( true, array(), array( 2014 ) ) );
		$this->assertTrue( Death_Window::is_dead_for_show( true, array( '', '0000-00-00' ), array( 2014 ) ) );
	}

	public function test_dead_cliche_without_appears_stays_dead(): void {
		$this->assertTrue( Death_Window::is_dead_for_show( true, array( '2020-01-01' ), array() ) );
	}

	public function test_death_during_the_run_counts(): void {
		$this->assertTrue( Death_Window::is_dead_for_show( true, array( '2013-06-01' ), array( 2012, 2013, 2014 ) ) );
	}

	public function test_death_in_the_final_appearance_year_counts(): void {
		$this->assertTrue( Death_Window::is_dead_for_show( true, array( '20140101' ), array( 2012, 2014 ) ) );
	}

	public function test_death_before_the_show_started_counts(): void {
		// By convention these are dated to the first episode, but an earlier date must also hold.
		$this->assertTrue( Death_Window::is_dead_for_show( true, array( '2009-01-01' ), array( 2012, 2014 ) ) );
	}

	public function test_death_after_the_final_appearance_year_does_not_count(): void {
		// Korra: the original show ended 2014, the death aired in a later show.
		$this->assertFalse( Death_Window::is_dead_for_show( true, array( '2025-05-01' ), array( 2012, 2014 ) ) );
	}

	public function test_any_death_inside_the_window_counts_for_resurrections(): void {
		$this->assertTrue( Death_Window::is_dead_for_show( true, array( '2013-01-01', '2025-01-01' ), array( 2012, 2014 ) ) );
	}

	public function test_a_later_death_with_an_unparseable_sibling_does_not_count(): void {
		$this->assertFalse( Death_Window::is_dead_for_show( true, array( 'L', '2025-01-01' ), array( 2014 ) ) );
	}

	public function test_appears_years_may_be_strings(): void {
		$this->assertFalse( Death_Window::is_dead_for_show( true, array( '2025-01-01' ), array( '2012', '2014' ) ) );
	}
}
