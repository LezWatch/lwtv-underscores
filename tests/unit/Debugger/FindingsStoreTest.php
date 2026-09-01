<?php
/**
 * Pure parts of the findings store: expiry boundaries and lifetime arithmetic.
 *
 * The option I/O is not covered here and cannot be under this bootstrap -- it
 * reads and writes options, which is exactly the kind of state the bootstrap
 * docblock says belongs behind a seam. What is covered is the arithmetic the
 * migration and every read depend on, because getting the boundary wrong is
 * silent: findings would either vanish a day early or linger past their TTL and
 * be presented as current.
 *
 * @package lwtv-underscores
 */

namespace LWTV\Tests\Debugger;

use PHPUnit\Framework\TestCase;
use LWTV\Debugger\Findings_Store;

class FindingsStoreTest extends TestCase {

	public function test_live_findings_are_not_expired(): void {
		$this->assertFalse( Findings_Store::expired( 2000, 1000 ) );
	}

	public function test_past_expiry_is_expired(): void {
		$this->assertTrue( Findings_Store::expired( 1000, 2000 ) );
	}

	/**
	 * Matches get_transient(): a row whose expiry is this exact second is gone,
	 * not live for one more. The nine call sites were written against that
	 * behaviour, so the replacement has to keep it.
	 */
	public function test_expiry_on_the_exact_second_is_expired(): void {
		$this->assertTrue( Findings_Store::expired( 1000, 1000 ) );
	}

	public function test_one_second_before_expiry_is_live(): void {
		$this->assertFalse( Findings_Store::expired( 1001, 1000 ) );
	}

	/**
	 * Zero means "no expiry", not "expired in 1970". Nothing writes it today, but
	 * a stored row that omits the stamp must read as live rather than silently
	 * vanishing on first load.
	 */
	public function test_zero_expiry_never_expires(): void {
		$this->assertFalse( Findings_Store::expired( 0, 1000 ) );
		$this->assertFalse( Findings_Store::expired( 0, PHP_INT_MAX ) );
	}

	public function test_negative_expiry_never_expires(): void {
		$this->assertFalse( Findings_Store::expired( -1, 1000 ) );
	}

	public function test_remaining_counts_down_to_expiry(): void {
		$this->assertSame( 1000, Findings_Store::remaining( 2000, 1000 ) );
	}

	public function test_remaining_is_zero_once_expired(): void {
		$this->assertSame( 0, Findings_Store::remaining( 1000, 2000 ) );
	}

	public function test_remaining_never_goes_negative(): void {
		$this->assertSame( 0, Findings_Store::remaining( 1, PHP_INT_MAX - 1 ) );
	}

	/**
	 * No expiry means no countdown to report. The migration reads this to decide
	 * what lifetime to carry across, and a huge number here would look like a
	 * report with years left to run.
	 */
	public function test_remaining_is_zero_when_expiry_is_unset(): void {
		$this->assertSame( 0, Findings_Store::remaining( 0, 1000 ) );
	}

	/**
	 * expired() and remaining() must never disagree: anything with time left is
	 * live, anything live has time left. They are read independently -- load()
	 * uses the first, the migration the second -- so a drift between them would
	 * show up as findings that read as present but report no lifetime.
	 */
	public function test_expired_and_remaining_agree_across_the_boundary(): void {
		$now = 5000;

		foreach ( range( 4998, 5002 ) as $expires ) {
			$this->assertSame(
				Findings_Store::expired( $expires, $now ),
				0 === Findings_Store::remaining( $expires, $now ),
				'Disagreement at expiry ' . $expires
			);
		}
	}

	/**
	 * The key is used verbatim, which is what lets the migration read
	 * `_transient_{key}` and write option `{key}` without a rename. If this ever
	 * grows a prefix, the migration has to be revisited in the same commit.
	 */
	public function test_option_name_is_the_key_verbatim(): void {
		$this->assertSame(
			'lwtv_debug_watch_urls_v2',
			Findings_Store::option_name( 'lwtv_debug_watch_urls_v2' )
		);
	}
}
