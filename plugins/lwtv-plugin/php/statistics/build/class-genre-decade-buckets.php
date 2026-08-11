<?php
/**
 * Genre-by-decade bucketer.
 *
 * Pure array-in/array-out grouping of a per-year genre tally into decades,
 * folding the earliest sparse decades into a single leading bucket so a
 * handful of shows from the medium's early years don't render as an
 * overconfident breakdown. Mirrors Format_Decade_Buckets' fold logic, but
 * lez_genres is a multi-value taxonomy (a show can carry several genres at
 * once), so this class tracks each bucket's distinct show count separately
 * from its genre tag counts — the two numbers diverge on purpose, and the
 * resulting top-N percentages are each "% of shows in this bucket carrying
 * that genre," not a partition, so they are not expected to sum to 100.
 * Genres are keyed by slug (not name) throughout, with the display name
 * carried alongside each count, so templates can link to the real term
 * archive instead of guessing a slug from the name. No WordPress calls —
 * unit-testable without a WP runtime (see
 * tests/unit/Statistics/GenreDecadeBucketsTest.php). All labels/i18n stay in
 * the templates; this class only reports the shape.
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Genre_Decade_Buckets {

	/**
	 * Group a per-year genre tally into decade buckets.
	 *
	 * @param array $year_genre_tally [ year => { 'shows': int, 'genres': [ slug => { 'name': string, 'count': int }, … ] }, … ], any order.
	 *                                 'shows' must already be a distinct-show count for that year (a show
	 *                                 with 3 genres counts once in 'shows' but up to 3 times across 'genres').
	 * @param int   $min_bucket_size  Fold the earliest decades together, by distinct show count,
	 *                                 until their combined total reaches this. Default 20.
	 * @param int   $top_n            How many genres to keep per bucket, ranked by count. Default 3.
	 * @return array Ordered oldest → newest. Each row: {
	 *   @type string   $type   'before' (a merged leading bucket) or 'decade'.
	 *   @type int|null $from   First year in the bucket, or null for 'before'.
	 *   @type int      $to     Exclusive end year (the decade's start + 10), or null if the
	 *                          leading bucket never cleared $min_bucket_size.
	 *   @type int      $shows  Distinct shows in the bucket — the denominator behind every pct below.
	 *   @type array    $genres [ slug => { 'name': string, 'count': int }, … ], every genre seen in
	 *                          the bucket, insertion order.
	 *   @type array    $top    Up to $top_n genres ranked by count desc (ties keep first-seen order):
	 *                          [ { 'slug': string, 'name': string, 'count': int, 'pct': float }, … ].
	 * } Empty array when there is no data.
	 */
	public static function build( array $year_genre_tally, int $min_bucket_size = 20, int $top_n = 3 ): array {
		$min_bucket_size = max( 1, $min_bucket_size );
		$top_n           = max( 1, $top_n );

		// 1. Roll individual years into decades. 'shows' sums directly since
		// a show has exactly one premiere year, so it can only ever land in
		// one year (and therefore one decade) — no double-counting risk.
		$decades = array();
		foreach ( $year_genre_tally as $year => $row ) {
			$year = (int) $year;
			if ( $year <= 0 || ! is_array( $row ) || ! isset( $row['genres'] ) || ! is_array( $row['genres'] ) ) {
				continue;
			}
			$decade = (int) floor( $year / 10 ) * 10;
			if ( ! isset( $decades[ $decade ] ) ) {
				$decades[ $decade ] = array(
					'shows'  => 0,
					'genres' => array(),
				);
			}
			$decades[ $decade ]['shows'] += (int) ( $row['shows'] ?? 0 );
			foreach ( $row['genres'] as $slug => $genre_row ) {
				self::accumulate( $decades[ $decade ]['genres'], (string) $slug, $genre_row );
			}
		}

		if ( empty( $decades ) ) {
			return array();
		}

		ksort( $decades );

		// 2. Fold the earliest decades into one "before" bucket, by distinct
		// show count, until it clears $min_bucket_size, then emit every
		// decade after that on its own. A first decade that already clears
		// the floor by itself stands alone as a real 'decade' bucket rather
		// than getting wrapped in a 'before' label it doesn't need (same fix
		// as Format_Decade_Buckets).
		$buckets       = array();
		$leading_shows = 0;
		$leading       = array();
		$leading_done  = false;
		$first_decade  = array_key_first( $decades );

		foreach ( $decades as $decade => $row ) {
			if ( ! $leading_done ) {
				$current_shows = (int) $row['shows'];

				if ( $decade === $first_decade && $current_shows >= $min_bucket_size ) {
					$buckets[]    = self::describe( 'decade', $decade, $decade + 10, $current_shows, $row['genres'], $top_n );
					$leading_done = true;
					continue;
				}

				foreach ( $row['genres'] as $slug => $genre_row ) {
					self::accumulate( $leading, (string) $slug, $genre_row );
				}
				$leading_shows += $current_shows;

				if ( $leading_shows >= $min_bucket_size ) {
					$buckets[]    = self::describe( 'before', null, $decade + 10, $leading_shows, $leading, $top_n );
					$leading_done = true;
				}
				continue;
			}

			$buckets[] = self::describe( 'decade', $decade, $decade + 10, (int) $row['shows'], $row['genres'], $top_n );
		}

		// Every decade was sparse and the leading bucket never cleared the
		// threshold — emit it anyway rather than silently dropping data.
		if ( ! $leading_done ) {
			$buckets[] = self::describe( 'before', null, null, $leading_shows, $leading, $top_n );
		}

		return $buckets;
	}

	/**
	 * Add one { 'name', 'count' } row into a slug-keyed accumulator,
	 * creating the slot on first sight. The name is invariant per slug
	 * within one build() call, so later sightings just add to the count.
	 *
	 * @param array  $accumulator [ slug => { 'name', 'count' } ], modified in place.
	 * @param string $slug        Genre slug.
	 * @param array  $genre_row   { 'name': string, 'count': int }.
	 * @return void
	 */
	private static function accumulate( array &$accumulator, string $slug, array $genre_row ): void {
		if ( ! isset( $accumulator[ $slug ] ) ) {
			$accumulator[ $slug ] = array(
				'name'  => (string) ( $genre_row['name'] ?? $slug ),
				'count' => 0,
			);
		}
		$accumulator[ $slug ]['count'] += (int) ( $genre_row['count'] ?? 0 );
	}

	/**
	 * Summarize one bucket: shows, the full genre tally, and its top N.
	 *
	 * @param string   $type   'before' or 'decade'.
	 * @param int|null $from   First year in the bucket, or null for 'before'.
	 * @param int|null $to     Exclusive end year.
	 * @param int      $shows  Distinct shows in the bucket.
	 * @param array    $genres [ slug => { 'name': string, 'count': int }, … ].
	 * @param int      $top_n  How many genres to keep in 'top'.
	 * @return array See build()'s return shape for one row.
	 */
	private static function describe( string $type, ?int $from, ?int $to, int $shows, array $genres, int $top_n ): array {
		$ranked = array();
		foreach ( $genres as $slug => $genre_row ) {
			$ranked[] = array(
				'slug'  => $slug,
				'name'  => $genre_row['name'],
				'count' => $genre_row['count'],
			);
		}

		// Stable sort (guaranteed since PHP 8.0) so genres tied on count keep
		// the order they were first seen in, rather than an arbitrary one.
		usort(
			$ranked,
			function ( $a, $b ) {
				return $b['count'] <=> $a['count'];
			}
		);

		$top = array();
		foreach ( array_slice( $ranked, 0, $top_n ) as $row ) {
			$top[] = array(
				'slug'  => $row['slug'],
				'name'  => $row['name'],
				'count' => $row['count'],
				'pct'   => ( $shows > 0 ) ? round( ( $row['count'] / $shows ) * 100, 1 ) : 0.0,
			);
		}

		return array(
			'type'   => $type,
			'from'   => $from,
			'to'     => $to,
			'shows'  => $shows,
			'genres' => $genres,
			'top'    => $top,
		);
	}
}
