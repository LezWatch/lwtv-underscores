<?php
/**
 * Name: Ways Mode
 * Description: Decide what a show's Ways to Watch section renders.
 *
 * Pure: the caller reads the meta and passes booleans in, so this is
 * unit-testable. See docs/plans/tmdb-watch-providers.md#decision-logic-pure.
 *
 * @package LWTV
 */

namespace LWTV\CPTs\Shows\Watching;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ways_Mode {

	/**
	 * The TMDB provider logos, with the country switcher. Curated links are not
	 * shown alongside them; they are the fallback when TMDB has nothing.
	 */
	const MODE_PROVIDERS = 'providers';

	/**
	 * The curated lezshows_waystowatch links only.
	 */
	const MODE_CURATED = 'curated';

	/**
	 * Render nothing.
	 */
	const MODE_NONE = 'none';

	/**
	 * Which Ways to Watch to render for a show.
	 *
	 * The per-show override beats everything: an editor turns it on when TMDB
	 * lists the wrong show, or a list that is wrong for what we know.
	 *
	 * @param bool $force_curated  The per-show override.
	 * @param bool $has_curated    Whether the show has curated links.
	 * @param bool $has_providers  Whether TMDB holds any displayable provider for it.
	 *
	 * @return string One of the MODE_ constants.
	 */
	public static function decide( bool $force_curated, bool $has_curated, bool $has_providers ): string {
		if ( ! $force_curated && $has_providers ) {
			return self::MODE_PROVIDERS;
		}

		return $has_curated ? self::MODE_CURATED : self::MODE_NONE;
	}
}
