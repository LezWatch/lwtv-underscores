<?php
/**
 * Name: Triage Plan
 * Description: Pure write plan for one entry's characters on the To Add tab.
 *
 * Decides every character first, in order, so a later character sees the rows
 * planned for earlier ones (and is "done" instead of a duplicate). The writes
 * come back split: cell updates on existing rows, then new rows. The runner
 * does every update before any new row, so a row number read at the start of
 * the run is never shifted by our own insert before we write to it. No
 * WordPress calls, so it is unit-testable.
 *
 * @package LWTV
 */

namespace LWTV\Plugins\Gravity_Forms\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Triage_Plan {

	/**
	 * Decide every character and plan the writes.
	 *
	 * New rows get provisional row numbers right after the last row the sheet
	 * read returned ($last_row + 1, + 2, ...). The runner asks the API to write
	 * each one there and maps provisional => actual for the note.
	 *
	 * @param array<int, array<string,string>> $characters  Extracted characters (or the fallback one).
	 * @param string                           $show        Show title.
	 * @param string                           $info        Description text.
	 * @param int                              $entry_id    Form entry ID.
	 * @param string                           $date        Y-m-d date for the append separator.
	 * @param array                            $lwtv_titles Titles of characters on matching LWTV shows.
	 * @param array                            $tabs        Sheet_Table::rows() results by tab key.
	 * @param array<string,int>                $to_add_map  To Add header label => 0-based column.
	 * @param int                              $last_row    Last sheet row the To Add read returned (header included).
	 * @return array{
	 *     items: array<int, array{name:string, decision:array}>,
	 *     updates: array<int, array{item:int, row:int, letters:array<string,string>}>,
	 *     appends: array<int, array{item:int, row:int, values:string[]}>
	 * }
	 * @throws \UnexpectedValueException When an append targets a row that was not read from the sheet.
	 */
	public static function build( array $characters, string $show, string $info, int $entry_id, string $date, array $lwtv_titles, array $tabs, array $to_add_map, int $last_row ): array {
		$items   = array();
		$updates = array();
		$appends = array();

		$tabs['to_add'] = isset( $tabs['to_add'] ) ? array_values( (array) $tabs['to_add'] ) : array();

		foreach ( array_values( $characters ) as $i => $character ) {
			$name     = (string) ( $character['name'] ?? '' );
			$decision = Triage_Decision::decide( $name, $show, $entry_id, $lwtv_titles, $tabs );
			$items[]  = array(
				'name'     => $name,
				'decision' => $decision,
			);

			if ( 'new' === $decision['action'] ) {
				$values = Row_Builder::new_row( $to_add_map, $character, $show, $info, $entry_id );
				$row    = $last_row + 1 + count( $appends );

				$appends[] = array(
					'item'   => $i,
					'row'    => $row,
					'values' => $values,
				);

				// Let later characters in this run see the planned row.
				$cells = array();
				foreach ( $to_add_map as $label => $col ) {
					$cells[ $label ] = (string) ( $values[ $col ] ?? '' );
				}
				$tabs['to_add'][] = array(
					'row'   => $row,
					'cells' => $cells,
				);
			} elseif ( 'append' === $decision['action'] ) {
				$row   = (int) $decision['row'];
				$index = self::row_index( $tabs['to_add'], $row );
				if ( null === $index || $row > $last_row ) {
					// A planned row always lists this entry, so it is "done", never appended to.
					throw new \UnexpectedValueException( 'To Add row ' . $row . ' is not a row read from the sheet.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- internal diagnostic, never rendered.
				}

				$changes = Row_Builder::append_changes( $to_add_map, $tabs['to_add'][ $index ]['cells'], $character, $info, $entry_id, $date );
				$letters = array();
				foreach ( $changes as $label => $value ) {
					if ( isset( $to_add_map[ $label ] ) ) {
						$letters[ Sheet_Table::column_letter( $to_add_map[ $label ] ) ] = $value;
					}
				}

				$updates[] = array(
					'item'    => $i,
					'row'     => $row,
					'letters' => $letters,
				);

				$tabs['to_add'][ $index ]['cells'] = array_merge( $tabs['to_add'][ $index ]['cells'], $changes );
			}
		}

		return array(
			'items'   => $items,
			'updates' => $updates,
			'appends' => $appends,
		);
	}

	/**
	 * Note lines for the plan, in character order.
	 *
	 * @param array          $plan    Result of build().
	 * @param string         $show    Show title.
	 * @param array<int,int> $row_map Provisional row => row the API actually wrote.
	 * @param int[]|null     $only    Item indexes to include (null for all).
	 * @return string[]
	 */
	public static function note_lines( array $plan, string $show, array $row_map = array(), ?array $only = null ): array {
		$lines = array();

		foreach ( (array) ( $plan['items'] ?? array() ) as $i => $item ) {
			if ( null !== $only && ! in_array( $i, $only, true ) ) {
				continue;
			}

			$decision = (array) $item['decision'];
			if ( isset( $decision['row'] ) && isset( $row_map[ (int) $decision['row'] ] ) ) {
				$decision['row'] = $row_map[ (int) $decision['row'] ];
			}

			$lines[] = Triage_Decision::note_line( (string) $item['name'], $show, $decision );
		}

		return $lines;
	}

	/**
	 * Index of the row with this sheet row number.
	 *
	 * @param array $rows Rows.
	 * @param int   $row  1-based sheet row.
	 * @return int|null
	 */
	private static function row_index( array $rows, int $row ): ?int {
		foreach ( $rows as $index => $item ) {
			if ( (int) $item['row'] === $row ) {
				return (int) $index;
			}
		}
		return null;
	}
}
