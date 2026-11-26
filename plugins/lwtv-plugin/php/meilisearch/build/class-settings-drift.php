<?php
/**
 * Name: Settings Drift
 * Description: Which repo settings an index on the server doesn't match.
 *
 * Pure. Shared by `wp lwtv meili status` and the Search Settings dashboard,
 * so both report the same drift. `synonyms` is skipped: it comes from the
 * Search Rules option and is compared separately.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings_Drift {

	/**
	 * Repo keys whose live value differs, in repo order.
	 *
	 * @param array $repo The repo settings file, loaded.
	 * @param array $live The index's settings from the server.
	 * @return string[]
	 */
	public static function differs( array $repo, array $live ): array {
		$drift = array();
		foreach ( $repo as $key => $value ) {
			if ( 'synonyms' !== $key && ( $live[ $key ] ?? null ) !== $value ) {
				$drift[] = (string) $key;
			}
		}

		return $drift;
	}
}
