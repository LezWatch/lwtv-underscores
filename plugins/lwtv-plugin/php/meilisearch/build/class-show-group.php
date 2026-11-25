<?php
/**
 * Name: Show Group
 * Description: Normalizes raw lezchars_show_group repeater rows.
 *
 * Pure. get_field( ..., false ) keys repeater rows by ACF field key, not by
 * sub-field name; this maps them to array( 'show' => int, 'type' => string )
 * and drops rows with no usable show.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Show_Group {

	/**
	 * Sub-field keys from acf-json/group_lwtv_chars_details.json.
	 */
	const KEYS = array(
		'field_lwtv_lezchars_show_group_show' => 'show',
		'field_lwtv_lezchars_show_group_type' => 'type',
	);

	/**
	 * Normalize raw repeater rows.
	 *
	 * @param mixed $rows get_field( 'lezchars_show_group', $id, false ) value.
	 * @return array<int, array{show:int, type:string}>
	 */
	public static function normalize( $rows ): array {
		$out = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$named = array();
			foreach ( $row as $key => $value ) {
				$named[ self::KEYS[ $key ] ?? $key ] = $value;
			}

			$show = $named['show'] ?? 0;
			if ( is_array( $show ) ) {
				$show = reset( $show );
			}

			$show = is_numeric( $show ) ? (int) $show : 0;
			if ( $show > 0 ) {
				$out[] = array(
					'show' => $show,
					'type' => is_string( $named['type'] ?? null ) ? $named['type'] : '',
				);
			}
		}

		return $out;
	}
}
