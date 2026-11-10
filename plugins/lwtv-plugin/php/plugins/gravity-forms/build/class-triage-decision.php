<?php
/**
 * Name: Triage Decision
 * Description: Pure per-character decision for submission triage.
 *
 * Given one extracted character, decides whether it is already written by
 * this entry (done), should be skipped, appended to a To Add row, or added as
 * a new row. No WordPress calls, so it is unit-testable.
 *
 * @package LWTV
 */

namespace LWTV\Plugins\Gravity_Forms\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Triage_Decision {

	/**
	 * Decide what to do with one character.
	 *
	 * @param string $name        Character name ('' for a fallback row).
	 * @param string $show        Show title.
	 * @param int    $entry_id    Gravity Forms entry ID.
	 * @param array  $lwtv_titles Titles of characters on matching LWTV shows.
	 * @param array  $tabs        Sheet_Table::rows() results keyed to_add, completed,
	 *                            questionable, legacy_new, legacy_existing.
	 * @return array{action:string, reason:string, row?:int, rows?:int[], detail?:string}
	 */
	public static function decide( string $name, string $show, int $entry_id, array $lwtv_titles, array $tabs ): array {
		$placeholder = Name_Match::is_placeholder( $name );
		$to_add      = isset( $tabs['to_add'] ) ? (array) $tabs['to_add'] : array();

		// 1. Already written by this entry (retry safety).
		foreach ( $to_add as $row ) {
			$cells = $row['cells'];
			if ( ! self::entry_listed( (string) ( $cells['Form Entry'] ?? '' ), $entry_id ) ) {
				continue;
			}
			$row_name = (string) ( $cells['Name'] ?? '' );
			if ( Name_Match::names_match( $name, $row_name ) || ( $placeholder && Name_Match::is_placeholder( $row_name ) ) ) {
				return array(
					'action' => 'done',
					'reason' => 'done',
					'row'    => (int) $row['row'],
				);
			}
		}

		// Fallback rows are always written once, never matched.
		if ( $placeholder ) {
			return array(
				'action' => 'new',
				'reason' => 'not_found',
			);
		}

		// 2. Already on LWTV.
		foreach ( $lwtv_titles as $title ) {
			if ( Name_Match::names_match( (string) $title, $name ) ) {
				return array(
					'action' => 'skip',
					'reason' => 'lwtv',
				);
			}
		}

		// 3. To Add rows.
		$matches = self::matching_rows( $to_add, $name, $show );
		if ( 1 === count( $matches ) ) {
			return array(
				'action' => 'append',
				'reason' => 'to_add',
				'row'    => (int) $matches[0]['row'],
			);
		}
		if ( count( $matches ) > 1 ) {
			$rows = array_map( 'intval', array_column( $matches, 'row' ) );
			sort( $rows );
			return array(
				'action' => 'new',
				'reason' => 'ambiguous',
				'rows'   => $rows,
			);
		}

		// 4. Completed.
		$matches = self::matching_rows( (array) ( $tabs['completed'] ?? array() ), $name, $show );
		if ( $matches ) {
			return array(
				'action' => 'skip',
				'reason' => 'completed',
				'row'    => (int) $matches[0]['row'],
				'detail' => (string) ( $matches[0]['cells']['Status'] ?? '' ),
			);
		}

		// 5. Questionable.
		$matches = self::matching_rows( (array) ( $tabs['questionable'] ?? array() ), $name, $show );
		if ( $matches ) {
			$cells = $matches[0]['cells'];
			return array(
				'action' => 'skip',
				'reason' => 'questionable',
				'row'    => (int) $matches[0]['row'],
				'detail' => trim( ( $cells['Status'] ?? '' ) . ', ' . ( $cells['Queerness'] ?? '' ), ' ,' ),
			);
		}

		// 6. Legacy tabs.
		foreach ( array( 'legacy_new', 'legacy_existing' ) as $tab ) {
			foreach ( (array) ( $tabs[ $tab ] ?? array() ) as $row ) {
				$cells = $row['cells'];
				if ( Name_Match::titles_match( (string) ( $cells['Show'] ?? '' ), $show )
					&& Name_Match::text_mentions( (string) ( $cells['Info'] ?? '' ), $name ) ) {
					return array(
						'action' => 'skip',
						'reason' => $tab,
						'row'    => (int) $row['row'],
					);
				}
			}
		}

		// 7. Nowhere.
		return array(
			'action' => 'new',
			'reason' => 'not_found',
		);
	}

	/**
	 * One human-readable line for the Gravity Forms admin note.
	 *
	 * @param string $name     Character name.
	 * @param string $show     Show title.
	 * @param array  $decision Result of decide().
	 * @return string
	 */
	public static function note_line( string $name, string $show, array $decision ): string {
		$label  = ( '' === trim( $name ) ? '(unnamed)' : $name ) . ' (' . $show . '): ';
		$action = $decision['action'] ?? '';
		$reason = $decision['reason'] ?? '';
		$detail = (string) ( $decision['detail'] ?? '' );
		$row    = (int) ( $decision['row'] ?? 0 );

		switch ( $action ) {
			case 'done':
				return $label . 'already written by this entry (row ' . $row . ').';
			case 'append':
				return $label . 'appended to To Add row ' . $row . '.';
			case 'new':
				if ( 'ambiguous' === $reason ) {
					return $label . 'matched To Add rows ' . implode( ', ', (array) ( $decision['rows'] ?? array() ) ) . '; added a new row instead.';
				}
				return $label . 'added to To Add.';
			case 'skip':
				$suffix = '' === $detail ? '' : ' (' . $detail . ')';
				switch ( $reason ) {
					case 'lwtv':
						return $label . 'already on LWTV, skipped.';
					case 'completed':
						return $label . 'on Completed' . $suffix . ', skipped.';
					case 'questionable':
						return $label . 'on Questionable' . $suffix . ', skipped.';
					case 'legacy_new':
						return $label . 'mentioned on the legacy New tab, skipped.';
					case 'legacy_existing':
						return $label . 'mentioned on the legacy Existing tab, skipped.';
				}
				return $label . 'skipped.';
		}

		return $label . 'no action.';
	}

	/**
	 * Does the Form Entry cell list this entry ID?
	 *
	 * @param string $cell     Cell text, e.g. "4100, 4521".
	 * @param int    $entry_id Entry ID.
	 * @return bool
	 */
	private static function entry_listed( string $cell, int $entry_id ): bool {
		foreach ( explode( ',', $cell ) as $id ) {
			if ( trim( $id ) === (string) $entry_id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Rows whose Show and Name both match.
	 *
	 * @param array  $rows Sheet_Table::rows() result.
	 * @param string $name Character name.
	 * @param string $show Show title.
	 * @return array
	 */
	private static function matching_rows( array $rows, string $name, string $show ): array {
		$out = array();
		foreach ( $rows as $row ) {
			$cells = $row['cells'];
			if ( Name_Match::titles_match( (string) ( $cells['Show'] ?? '' ), $show )
				&& Name_Match::names_match( (string) ( $cells['Name'] ?? '' ), $name ) ) {
				$out[] = $row;
			}
		}
		return $out;
	}
}
