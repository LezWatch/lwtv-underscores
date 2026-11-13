<?php
/**
 * Aggregate parsed API usage rows into the tables the admin page shows.
 *
 * The page's question is "what is broken, and who needs it?" before "what is
 * popular?", so every table carries status buckets and an error rate, and the
 * route table leads with 5xx. Two rules worth knowing before changing this:
 *
 * - 499 means the client gave up. It gets its own bucket and is not an error,
 *   so s4xx deliberately excludes it.
 * - A consumer's first_seen (and so its "new" flag) comes from ALL rows, not
 *   the selected range, or every long-time consumer would look new whenever
 *   the range starts after its first visit.
 *
 * Pure: rows and a matcher in, arrays out.
 *
 * @package LWTV
 */

namespace LWTV\Admin_Menu\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Api_Usage_Report {

	/**
	 * How many raw paths to keep per route.
	 */
	const TOP_PATHS = 5;

	/**
	 * Keys sort() accepts. Anything else falls back to s5xx.
	 */
	const SORTABLE = array( 's5xx', 'error_rate', 'hits', 'bytes', 'consumers', 'last_seen', 'route', 'consumer' );

	/**
	 * Build the report.
	 *
	 * @param array<int, array{date:string,endpoint:string,consumer:string,status:string,hits:int,bytes:int}> $rows Parsed CSV rows.
	 * @param Api_Route_Matcher $matcher   Route matcher.
	 * @param string            $from      First date, Y-m-d, inclusive.
	 * @param string            $to        Last date, Y-m-d, inclusive.
	 * @param array<int,string> $exclude   Consumer labels to leave out (internal traffic).
	 * @param string            $new_since Consumers first seen on or after this Y-m-d are new. '' = none.
	 * @return array{routes: list<array<string,mixed>>, consumers: list<array<string,mixed>>, no_hits: list<string>, totals: array{hits:int, bytes:int}}
	 */
	public static function build( array $rows, Api_Route_Matcher $matcher, string $from, string $to, array $exclude = array(), string $new_since = '' ): array {
		$exclude    = array_flip( $exclude );
		$first_seen = array();
		$routes     = array();
		$consumers  = array();
		$totals     = array(
			'hits'  => 0,
			'bytes' => 0,
		);

		foreach ( $rows as $row ) {
			$who = $row['consumer'];
			if ( ! isset( $first_seen[ $who ] ) || $row['date'] < $first_seen[ $who ] ) {
				$first_seen[ $who ] = $row['date'];
			}

			if ( $row['date'] < $from || $row['date'] > $to || isset( $exclude[ $who ] ) ) {
				continue;
			}

			$route  = $matcher->match( $row['endpoint'] );
			$bucket = self::bucket( $row['status'] );

			$totals['hits']  += $row['hits'];
			$totals['bytes'] += $row['bytes'];

			$routes[ $route ] ??= array_merge(
				array( 'route' => $route ),
				self::empty_counts(),
				array(
					'paths' => array(),
					'who'   => array(),
				)
			);
			self::add( $routes[ $route ], $row, $bucket );
			$routes[ $route ]['paths'][ $row['endpoint'] ] = ( $routes[ $route ]['paths'][ $row['endpoint'] ] ?? 0 ) + $row['hits'];
			$routes[ $route ]['who'][ $who ]               = true;

			$consumers[ $who ] ??= array_merge( array( 'consumer' => $who ), self::empty_counts(), array( 'by_route' => array() ) );
			self::add( $consumers[ $who ], $row, $bucket );
			$consumers[ $who ]['by_route'][ $route ] = ( $consumers[ $who ]['by_route'][ $route ] ?? 0 ) + $row['hits'];
		}

		$route_rows = array();
		foreach ( $routes as $r ) {
			arsort( $r['paths'] );
			$r['top_paths'] = self::pairs( array_slice( $r['paths'], 0, self::TOP_PATHS, true ), 'path' );
			$r['consumers'] = count( $r['who'] );
			unset( $r['paths'], $r['who'] );
			$route_rows[] = self::finish( $r );
		}

		$consumer_rows = array();
		foreach ( $consumers as $c ) {
			arsort( $c['by_route'] );
			$c['routes']     = self::pairs( $c['by_route'], 'route' );
			$c['first_seen'] = $first_seen[ $c['consumer'] ];
			$c['is_new']     = '' !== $new_since && $c['first_seen'] >= $new_since;
			unset( $c['by_route'] );
			$consumer_rows[] = self::finish( $c );
		}

		return array(
			'routes'    => self::sort( $route_rows, 's5xx' ),
			'consumers' => self::sort( $consumer_rows, 'hits' ),
			'no_hits'   => array_values( array_filter( $matcher->routes(), static fn( $route ) => ! isset( $routes[ $route ] ) ) ),
			'totals'    => $totals,
		);
	}

	/**
	 * Sort report rows. Ties fall back to error rate, then hits, both descending.
	 *
	 * @param list<array<string,mixed>> $rows    Route or consumer rows.
	 * @param string                    $orderby One of SORTABLE; anything else means s5xx.
	 * @param string                    $order   'asc' or 'desc'.
	 * @return list<array<string,mixed>>
	 */
	public static function sort( array $rows, string $orderby, string $order = 'desc' ): array {
		$key = in_array( $orderby, self::SORTABLE, true ) ? $orderby : 's5xx';
		$dir = ( 'asc' === $order ) ? 1 : -1;

		usort(
			$rows,
			static function ( $a, $b ) use ( $key, $dir ) {
				return ( $dir * ( ( $a[ $key ] ?? 0 ) <=> ( $b[ $key ] ?? 0 ) ) )
					?: ( ( $b['error_rate'] ?? 0 ) <=> ( $a['error_rate'] ?? 0 ) )
					?: ( ( $b['hits'] ?? 0 ) <=> ( $a['hits'] ?? 0 ) );
			}
		);

		return $rows;
	}

	/**
	 * Which status bucket a code belongs in.
	 *
	 * @param string $status Three-digit HTTP status.
	 * @return string s2xx|s3xx|s4xx|s499|s5xx|other
	 */
	private static function bucket( string $status ): string {
		if ( '499' === $status ) {
			return 's499';
		}
		$class = 's' . $status[0] . 'xx';
		return in_array( $class, array( 's2xx', 's3xx', 's4xx', 's5xx' ), true ) ? $class : 'other';
	}

	/**
	 * Zeroed counters shared by route and consumer rows.
	 *
	 * @return array<string, int|string>
	 */
	private static function empty_counts(): array {
		return array(
			'hits'      => 0,
			's2xx'      => 0,
			's3xx'      => 0,
			's4xx'      => 0,
			's499'      => 0,
			's5xx'      => 0,
			'other'     => 0,
			'bytes'     => 0,
			'last_seen' => '',
		);
	}

	/**
	 * Add one CSV row into a running total.
	 *
	 * @param array<string,mixed>                               $into   Row being built.
	 * @param array{date:string,hits:int,bytes:int}              $row    CSV row.
	 * @param string                                             $bucket Status bucket.
	 */
	private static function add( array &$into, array $row, string $bucket ): void {
		$into['hits']    += $row['hits'];
		$into[ $bucket ] += $row['hits'];
		$into['bytes']   += $row['bytes'];
		if ( $row['date'] > $into['last_seen'] ) {
			$into['last_seen'] = $row['date'];
		}
	}

	/**
	 * Add the error rate: (4xx + 5xx) / hits, 0 when there are no hits.
	 *
	 * @param array<string,mixed> $row Row being built.
	 * @return array<string,mixed>
	 */
	private static function finish( array $row ): array {
		$row['error_rate'] = $row['hits'] > 0 ? ( $row['s4xx'] + $row['s5xx'] ) / $row['hits'] : 0.0;
		return $row;
	}

	/**
	 * [ name => hits ] to [ [ $label => name, 'hits' => hits ], ... ].
	 *
	 * @param array<string,int> $counts Counts keyed by name.
	 * @param string            $label  Key to use for the name.
	 * @return list<array<string,int|string>>
	 */
	private static function pairs( array $counts, string $label ): array {
		$out = array();
		foreach ( $counts as $name => $hits ) {
			$out[] = array(
				$label => (string) $name,
				'hits' => $hits,
			);
		}
		return $out;
	}
}
