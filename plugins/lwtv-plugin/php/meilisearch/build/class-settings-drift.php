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
	 * Repo keys whose live value differs, in repo order, then
	 * 'typoTolerance' when a typo payload is given and the live one differs.
	 *
	 * Synonyms (the Rules page) and typo tolerance (the Typo tolerance tab)
	 * aren't repo settings, so a repo `typoTolerance` key is ignored.
	 *
	 * @param array      $repo The repo settings file, loaded.
	 * @param array      $live The index's settings from the server.
	 * @param array|null $typo The expected typoTolerance (Typo_Tolerance::payload()); null to skip it.
	 * @return string[]
	 */
	public static function differs( array $repo, array $live, ?array $typo = null ): array {
		$drift = array();
		foreach ( $repo as $key => $value ) {
			if ( 'synonyms' !== $key && 'typoTolerance' !== $key && ( $live[ $key ] ?? null ) !== $value ) {
				$drift[] = (string) $key;
			}
		}

		if ( null !== $typo && ! Typo_Tolerance::same( $typo, $live['typoTolerance'] ?? null ) ) {
			$drift[] = 'typoTolerance';
		}

		return $drift;
	}
}
