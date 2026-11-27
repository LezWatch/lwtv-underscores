<?php
/**
 * Name: Search Params
 * Description: Reads the search results page's `type` and `paged` values.
 *
 * Pure. Takes the raw request values and returns a known type (or null)
 * and a page number of at least 1, so nothing else has to trust them.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Search_Params {

	/**
	 * Highest page a request can ask for. Meilisearch stops counting at
	 * maxTotalHits (1000 by default, 50 pages of 20), so this is far past any
	 * real page; it keeps the offset an int.
	 */
	const MAX_PAGE = 1000;

	/**
	 * The requested tab.
	 *
	 * @param mixed    $raw   Raw `type` value.
	 * @param string[] $types Known tab types.
	 * @return string|null A known type, or null for none or an unknown one.
	 */
	public static function type( $raw, array $types ): ?string {
		if ( ! is_scalar( $raw ) ) {
			return null;
		}

		$type = strtolower( trim( (string) $raw ) );

		return in_array( $type, $types, true ) ? $type : null;
	}

	/**
	 * The requested page.
	 *
	 * @param mixed $raw Raw `paged` value.
	 * @return int From 1 to MAX_PAGE.
	 */
	public static function paged( $raw ): int {
		if ( ! is_scalar( $raw ) || ! is_numeric( $raw ) ) {
			return 1;
		}

		// Compare as a float first: (int) of a value past PHP_INT_MAX isn't defined.
		if ( (float) $raw >= self::MAX_PAGE ) {
			return self::MAX_PAGE;
		}

		return max( 1, (int) $raw );
	}
}
