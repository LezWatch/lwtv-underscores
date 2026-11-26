<?php
/**
 * Name: Modal Switch
 * Description: Decides whether the Meilisearch search modal is on for a request.
 *
 * Pure. Reads the LWTV_MEILI_MODAL value it is given: 'editors' (users who
 * can edit_posts) or 'everyone'. Anything else, including a typo or true,
 * is off, so a mistake in wp-config.php leaves visitors on SearchWP.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Modal_Switch {

	const OFF      = 'off';
	const EDITORS  = 'editors';
	const EVERYONE = 'everyone';

	/**
	 * Normalize the switch value.
	 *
	 * @param mixed $setting The LWTV_MEILI_MODAL value, or null when undefined.
	 * @return string 'off', 'editors' or 'everyone'.
	 */
	public static function mode( mixed $setting ): string {
		if ( ! is_string( $setting ) ) {
			return self::OFF;
		}

		$value = strtolower( trim( $setting ) );

		return in_array( $value, array( self::EDITORS, self::EVERYONE ), true ) ? $value : self::OFF;
	}

	/**
	 * Whether the modal is on for this request.
	 *
	 * @param mixed $setting    The LWTV_MEILI_MODAL value, or null when undefined.
	 * @param bool  $configured Whether the Meilisearch module is configured.
	 * @param bool  $can_edit   Whether the current user can edit_posts.
	 * @return bool
	 */
	public static function enabled( mixed $setting, bool $configured, bool $can_edit ): bool {
		if ( ! $configured ) {
			return false;
		}

		switch ( self::mode( $setting ) ) {
			case self::EVERYONE:
				return true;
			case self::EDITORS:
				return $can_edit;
			default:
				return false;
		}
	}
}
