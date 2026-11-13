<?php
/**
 * The known-consumers list: who a CSV consumer label is, how to reach them,
 * and whether the traffic is our own.
 *
 * Labels are matched exactly against the `consumer` column the cron script
 * writes. Grouping beyond what the awk chain does belongs here, not in the
 * script, so the CSV stays stable.
 *
 * Pure. WordPress sanitizes each field before normalize() sees it; this only
 * decides shape: what's dropped, what wins, what counts as internal.
 *
 * @package LWTV
 */

namespace LWTV\Admin_Menu\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Api_Known_Consumers {

	/**
	 * Outreach statuses, in the order the dropdown shows them.
	 */
	const STATUSES = array( 'none', 'to-contact', 'contacted', 'replied', 'no-contact' );

	/**
	 * Longest any stored field may be.
	 */
	const MAX_LENGTH = 500;

	/**
	 * The starting list, used until someone saves the form.
	 *
	 * Names and notes come only from what the user agents and the logs say.
	 * No contact details are invented.
	 *
	 * @return array<string, array{label:string,name:string,contact:string,notes:string,status:string,internal:bool}>
	 */
	public static function seed(): array {
		return self::normalize(
			array(
				array(
					'label'    => 'uptime-kuma',
					'name'     => 'Uptime Kuma (our monitor)',
					'notes'    => 'Self-hosted monitor polling last-death.',
					'internal' => true,
				),
				array(
					'label'    => 'WP:https://www.thetracyl.com',
					'name'     => 'thetracyl.com (co-developer)',
					'notes'    => 'Polls last-death about every 15 minutes.',
					'internal' => true,
				),
				array(
					'label'  => 'EuropeanFictionalCharacterResearch',
					'notes'  => 'Uses export/full/characters/sexuality/*.',
					'status' => 'to-contact',
				),
				array(
					'label'  => 'SapphoWorkbenchCrawler',
					'notes'  => 'Uses export/raw/*.',
					'status' => 'to-contact',
				),
				array(
					'label'  => 'queer-tv-guide-pipeline',
					'notes'  => 'Uses export/raw/actors and export/list/*.',
					'status' => 'to-contact',
				),
			)
		);
	}

	/**
	 * Shape submitted rows into the stored list, keyed by label.
	 *
	 * @param array<int, array<string, mixed>> $rows Form rows (already sanitized).
	 * @return array<string, array{label:string,name:string,contact:string,notes:string,status:string,internal:bool}>
	 */
	public static function normalize( array $rows ): array {
		$out = array();

		foreach ( $rows as $row ) {
			$label = self::field( $row, 'label' );
			if ( '' === $label ) {
				continue;
			}

			$status   = self::field( $row, 'status' );
			$internal = $row['internal'] ?? false;

			$out[ $label ] = array(
				'label'    => $label,
				'name'     => self::field( $row, 'name' ),
				'contact'  => self::field( $row, 'contact' ),
				'notes'    => self::field( $row, 'notes' ),
				'status'   => in_array( $status, self::STATUSES, true ) ? $status : 'none',
				'internal' => true === $internal || '1' === $internal,
			);
		}

		return $out;
	}

	/**
	 * Labels flagged as our own traffic.
	 *
	 * @param array<string, array{internal:bool}> $known Stored list.
	 * @return list<string>
	 */
	public static function internal_labels( array $known ): array {
		$labels = array();
		foreach ( $known as $label => $row ) {
			if ( ! empty( $row['internal'] ) ) {
				$labels[] = (string) $label;
			}
		}
		return $labels;
	}

	/**
	 * What a contact value can be linked as: 'url', 'email', or '' (plain text).
	 *
	 * Display only, so no network: wp_http_validate_url() would resolve DNS on
	 * every render and reject ports and private hosts. Only http(s) links,
	 * never javascript: or other schemes.
	 *
	 * @param string $contact Stored contact.
	 * @return string
	 */
	public static function contact_kind( string $contact ): string {
		if ( false !== filter_var( $contact, FILTER_VALIDATE_EMAIL ) ) {
			return 'email';
		}
		$scheme = strtolower( (string) parse_url( $contact, PHP_URL_SCHEME ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pure class, no WP.
		$host   = parse_url( $contact, PHP_URL_HOST ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pure class, no WP.
		return ( in_array( $scheme, array( 'http', 'https' ), true ) && ! empty( $host ) ) ? 'url' : '';
	}

	/**
	 * One trimmed, length-capped string field.
	 *
	 * @param array<string, mixed> $row Form row.
	 * @param string               $key Field name.
	 * @return string
	 */
	private static function field( array $row, string $key ): string {
		$value = $row[ $key ] ?? '';
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		return mb_substr( trim( (string) $value ), 0, self::MAX_LENGTH );
	}
}
