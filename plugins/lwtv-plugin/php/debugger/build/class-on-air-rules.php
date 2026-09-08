<?php
/**
 * Whether a show's stored on-air flag agrees with its airdates.
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

		// All shows should have an airdate.
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
