<?php
/**
 * Name: Row Builder
 * Description: Pure cell values for the "To Add" sheet.
 *
 * Builds a whole new row, or the set of cell changes for appending a
 * submission to an existing row. Never overwrites a filled cell other than
 * Status, Description (append) and Form Entry (append). No WordPress calls,
 * so it is unit-testable.
 *
 * @package LWTV
 */

namespace LWTV\Plugins\Gravity_Forms\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Row_Builder {

	/**
	 * Status written by the automation.
	 */
	const STATUS = 'Needs Verification';

	/**
	 * Character field => sheet header, for the fields filled only when blank.
	 */
	const FILL_FIELDS = array(
		'actor'     => 'Actor',
		'queerness' => 'Queerness',
		'cliches'   => 'Cliches',
		'sexuality' => 'Sexuality',
		'gender'    => 'Gender',
	);

	/**
	 * Build a complete new row.
	 *
	 * @param array<string,int>    $map         Header label => 0-based column.
	 * @param array<string,string> $character   Extracted character.
	 * @param string               $show        Show title.
	 * @param string               $description Description text.
	 * @param int                  $entry_id    Form entry ID.
	 * @return string[]
	 */
	public static function new_row( array $map, array $character, string $show, string $description, int $entry_id ): array {
		$row = array_fill( 0, ( $map ? max( $map ) : -1 ) + 1, '' );

		$values = array(
			'Status'      => self::STATUS,
			'Name'        => (string) ( $character['name'] ?? '' ),
			'Show'        => $show,
			'Description' => $description,
			'Image'       => '',
			'Form Entry'  => (string) $entry_id,
		);
		foreach ( self::FILL_FIELDS as $field => $header ) {
			$values[ $header ] = (string) ( $character[ $field ] ?? '' );
		}

		foreach ( $values as $header => $value ) {
			if ( isset( $map[ $header ] ) ) {
				$row[ $map[ $header ] ] = $value;
			}
		}

		return $row;
	}

	/**
	 * Cell changes for appending a submission to an existing row.
	 *
	 * @param array<string,int>    $map         Header label => 0-based column.
	 * @param array<string,string> $cells       Existing cells keyed by header.
	 * @param array<string,string> $character   Extracted character.
	 * @param string               $description Description text.
	 * @param int                  $entry_id    Form entry ID.
	 * @param string               $date        Y-m-d date for the separator.
	 * @return array<string,string> Header label => new value.
	 */
	public static function append_changes( array $map, array $cells, array $character, string $description, int $entry_id, string $date ): array {
		unset( $map ); // Changes are keyed by label; the caller maps to columns.

		$changes           = array();
		$changes['Status'] = self::STATUS;

		$existing_description = (string) ( $cells['Description'] ?? '' );
		if ( self::is_blank( $existing_description ) ) {
			$changes['Description'] = $description;
		} else {
			$changes['Description'] = $existing_description . "\n\n— Added {$date} from form entry #{$entry_id} —\n" . $description;
		}

		$existing_entry        = (string) ( $cells['Form Entry'] ?? '' );
		$changes['Form Entry'] = self::is_blank( $existing_entry ) ? (string) $entry_id : $existing_entry . ', ' . $entry_id;

		foreach ( self::FILL_FIELDS as $field => $header ) {
			$value = (string) ( $character[ $field ] ?? '' );
			if ( ! self::is_blank( $value ) && self::is_blank( (string) ( $cells[ $header ] ?? '' ) ) ) {
				$changes[ $header ] = $value;
			}
		}

		return $changes;
	}

	/**
	 * A character with every field empty.
	 *
	 * @return array<string,string>
	 */
	public static function fallback_character(): array {
		return array(
			'name'      => '',
			'actor'     => '',
			'queerness' => '',
			'cliches'   => '',
			'sexuality' => '',
			'gender'    => '',
		);
	}

	/**
	 * Blank once invisible characters and whitespace are ignored.
	 *
	 * @param string $value Cell text.
	 * @return bool
	 */
	private static function is_blank( string $value ): bool {
		return '' === Name_Match::normalize( $value );
	}
}
