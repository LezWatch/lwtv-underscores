<?php
/**
 * Fetches what the IMDb rules need, for either shows or actors.
 *
 * One collector for both, mirroring Build\Imdb_Rules: the reads differ only in
 * which meta keys to use and whether there is an exemption to check.
 *
 * @package LWTV
 */

namespace LWTV\Debugger\Collect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Debugger\Build\Imdb_Rules;

class Imdb_Collector {

	/**
	 * How many posts to gather per pass.
	 */
	const BATCH = 200;

	/**
	 * Where each check's values live.
	 *
	 * `exempt_term` is the one real asymmetry: a web series that was never on
	 * IMDb is not missing anything, and there is no equivalent for actors.
	 * `ignore_meta` likewise — only shows have an override.
	 *
	 * @var array<string, array<string, string>>
	 */
	private const LEVELS = array(
		Imdb_Rules::SHOW  => array(
			'imdb'        => 'lezshows_imdb',
			'canonical'   => 'lezshows_imdb_canonical',
			'ignore_meta' => 'lezshows_tvmaze_ignore',
			'exempt_tax'  => 'lez_formats',
			'exempt_term' => 'web-series',
		),
		Imdb_Rules::ACTOR => array(
			'imdb'        => 'lezactors_imdb',
			'canonical'   => 'lezactors_imdb_canonical',
			'ignore_meta' => '',
			'exempt_tax'  => '',
			'exempt_term' => '',
		),
	);

	/**
	 * Collect one batch.
	 *
	 * @param  string     $level    Imdb_Rules::SHOW or Imdb_Rules::ACTOR.
	 * @param  array<int> $post_ids Post IDs.
	 * @return array<int, array<string, mixed>>
	 */
	public function collect( string $level, array $post_ids ): array {
		$config   = self::LEVELS[ $level ] ?? array();
		$post_ids = array_values( array_unique( array_map( 'intval', $post_ids ) ) );

		if ( empty( $config ) || empty( $post_ids ) ) {
			return array();
		}

		update_postmeta_cache( $post_ids );

		$exempt    = $this->exempt_for( $config, $post_ids );
		$collected = array();

		foreach ( $post_ids as $post_id ) {
			$collected[] = array(
				'post_id'   => $post_id,
				'imdb'      => (string) get_post_meta( $post_id, $config['imdb'], true ),
				'canonical' => (string) get_post_meta( $post_id, $config['canonical'], true ),
				'exempt'    => $exempt[ $post_id ] ?? false,
				'ignored'   => '' !== $config['ignore_meta']
					&& ! empty( get_post_meta( $post_id, $config['ignore_meta'], true ) ),
			);
		}

		return $collected;
	}

	/**
	 * Which posts in the batch are exempt from needing an ID at all.
	 *
	 * One term query for the batch rather than a has_term() each.
	 *
	 * @param  array      $config   Level config.
	 * @param  array<int> $post_ids Post IDs.
	 * @return array<int, bool>
	 */
	private function exempt_for( array $config, array $post_ids ): array {
		if ( '' === $config['exempt_tax'] ) {
			return array();
		}

		$terms = wp_get_object_terms(
			$post_ids,
			$config['exempt_tax'],
			array(
				'fields' => 'all_with_object_id',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$exempt = array();

		foreach ( $terms as $term ) {
			if ( $config['exempt_term'] === $term->slug ) {
				$exempt[ (int) $term->object_id ] = true;
			}
		}

		return $exempt;
	}
}
