<?php
/**
 * Whether a show's stored on-air flag agrees with its airdates.
 *
 * PURE. The current year is passed in rather than read from the clock, which is
 * the whole reason this is testable: "is this show on air" is a question about a
 * date, and a rule that asks the system what day it is can only be tested on the
 * days the answer happens to suit.
 *
 * The data contract, as produced by Collect\On_Air_Collector:
 *
 *     array(
 *         'post_id'  => int,
 *         'on_air'   => string,   // lezshows_on_air, as stored
 *         'airdates' => array{start: string, finish: string},
 *         'year'     => int,
 *     )
 *
 * @package LWTV
 */

namespace LWTV\Debugger\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Shows\Airdates;

class On_Air_Rules {

	/**
	 * Post type these findings are about.
	 */
	const POST_TYPE = 'post_type_shows';

	/**
	 * Meta key holding the stored on-air flag.
	 */
	const META_ON_AIR = 'lezshows_on_air';

	/**
	 * The flag's two values.
	 */
	const YES = 'yes';
	const NO  = 'no';

	/**
	 * What the on-air flag should say, from the airdates alone.
	 *
	 * @param  array $airdates array{start: string, finish: string}.
	 * @param  int   $year     The year to judge against.
	 * @return string self::YES or self::NO.
	 */
	public static function should_be_on_air( array $airdates, int $year ): string {
		$start  = (string) ( $airdates['start'] ?? '' );
		$finish = (string) ( $airdates['finish'] ?? '' );

		if ( '' === $start || '' === $finish ) {
			return self::NO;
		}

		// 'current' means the show is still airing, so there's nothing to compare.
		if ( Airdates::is_still_airing( $finish ) ) {
			return self::YES;
		}

		return ( (int) $start <= $year && (int) $finish >= $year ) ? self::YES : self::NO;
	}

	/**
	 * Every finding for one show.
	 *
	 * @param  array $show Collected show data.
	 * @return array<int, array<string, mixed>>
	 */
	public static function evaluate( array $show ): array {
		$post_id = (int) ( $show['post_id'] ?? 0 );

		if ( ! $post_id ) {
			return array();
		}

		$stored = (string) ( $show['on_air'] ?? '' );
		$actual = self::should_be_on_air( (array) ( $show['airdates'] ?? array() ), (int) ( $show['year'] ?? 0 ) );

		/*
		 * Nothing stored at all. The original also tested the computed value for
		 * emptiness -- "no on-air meta data and/or airdates" -- but
		 * should_be_on_air() returns 'yes' or 'no' and never '', so that half was
		 * unreachable. Harmless: a show with no airdates computes to 'no', and if
		 * the stored flag agrees there is nothing to report here, while the Shows
		 * check reports the missing airdates on their own terms.
		 */
		if ( '' === $stored ) {
			return array( Findings::make( $post_id, self::POST_TYPE, 'show-onair-no-data' ) );
		}

		// Case-insensitive: the stored value has been written by hand and by ACF.
		if ( strtolower( $stored ) === strtolower( $actual ) ) {
			return array();
		}

		return array(
			Findings::make(
				$post_id,
				self::POST_TYPE,
				'show-onair-mismatch',
				'On-air meta (' . $stored . ') does not match actual on-air status (' . $actual . ').',
				array(
					'meta'   => $stored,
					'actual' => $actual,
				)
			),
		);
	}
}
