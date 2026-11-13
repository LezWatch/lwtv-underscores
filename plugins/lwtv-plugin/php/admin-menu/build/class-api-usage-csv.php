<?php
/**
 * Parse the API usage rollup CSV.
 *
 * cron/api-roundup.sh writes one row per day / raw path / consumer label /
 * HTTP status: `date,endpoint,consumer,status,hits,bytes`. The file is
 * appended to by every run, so the same key can show up more than once; we
 * sum rather than trust it. Every field comes from request headers somebody
 * else controls, so a line that doesn't look exactly like a rollup row is
 * dropped, never repaired.
 *
 * Pure: takes lines, returns arrays. Admin_Menu\Api_Usage does the file read.
 *
 * @package LWTV
 */

namespace LWTV\Admin_Menu\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Api_Usage_Csv {

	/**
	 * Label for rows whose consumer column is empty.
	 *
	 * The cron script's fallback keeps the UA up to the first `/` or space, so
	 * a UA starting with either becomes ''. Those are real hits; keep them.
	 */
	const EMPTY_CONSUMER = '(empty)';

	/**
	 * Turn CSV lines into typed rows, summing repeated keys.
	 *
	 * @param iterable<string> $lines Raw lines, with or without line endings.
	 * @return list<array{date:string,endpoint:string,consumer:string,status:string,hits:int,bytes:int}>
	 */
	public static function parse( iterable $lines ): array {
		$rows = array();

		foreach ( $lines as $line ) {
			$fields = str_getcsv( rtrim( (string) $line, "\r\n" ), ',', '"', '' );
			if ( 6 !== count( $fields ) ) {
				continue;
			}

			list( $date, $endpoint, $consumer, $status, $hits, $bytes ) = array_map( 'strval', $fields );

			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date )
				|| ! preg_match( '/^\d{3}$/', $status )
				|| ! ctype_digit( $hits )
				|| ! ctype_digit( $bytes )
				|| ! str_starts_with( $endpoint, '/' )
			) {
				continue;
			}

			if ( '' === $consumer ) {
				$consumer = self::EMPTY_CONSUMER;
			}

			$key = $date . '|' . $endpoint . '|' . $consumer . '|' . $status;
			if ( isset( $rows[ $key ] ) ) {
				$rows[ $key ]['hits']  += (int) $hits;
				$rows[ $key ]['bytes'] += (int) $bytes;
				continue;
			}

			$rows[ $key ] = array(
				'date'     => $date,
				'endpoint' => $endpoint,
				'consumer' => $consumer,
				'status'   => $status,
				'hits'     => (int) $hits,
				'bytes'    => (int) $bytes,
			);
		}

		return array_values( $rows );
	}

	/**
	 * The newest date in the rows. The file isn't globally sorted, so scan.
	 *
	 * @param array<int, array{date:string}> $rows Parsed rows.
	 * @return string|null Y-m-d, or null when there are no rows.
	 */
	public static function newest_date( array $rows ): ?string {
		$newest = null;
		foreach ( $rows as $row ) {
			if ( null === $newest || $row['date'] > $newest ) {
				$newest = $row['date'];
			}
		}
		return $newest;
	}
}
