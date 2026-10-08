<?php
/**
 * Name: JustWatch
 * Description: Decide between the JustWatch widget and curated Ways to Watch.
 *
 * Pure: no WordPress calls and no constants read here. The caller reads the
 * define and the show meta and passes them in, so this is unit-testable.
 * See docs/plans/justwatch-widget.md#decision-logic.
 *
 * @package LWTV
 */

namespace LWTV\CPTs\Shows\Watching;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\_Helpers\Imdb_Canonical;

class JustWatch {

	/**
	 * Render the JustWatch widget.
	 */
	const MODE_JUSTWATCH = 'justwatch';

	/**
	 * Render the curated lezshows_waystowatch links.
	 */
	const MODE_CURATED = 'curated';

	/**
	 * Render nothing.
	 */
	const MODE_NONE = 'none';

	/**
	 * Widget themes JustWatch accepts. Anything else falls back to the first.
	 */
	const THEMES = array( 'light', 'dark' );

	/**
	 * Which Ways to Watch to render for a show.
	 *
	 * Order matters: the override beats everything, then a missing key, then a
	 * missing or unusable IMDb ID. The canonical ID wins over the stored one,
	 * because Imdb_Verify_Task only writes it when the stored ID still redirects.
	 *
	 * @param string $api_key        JUSTWATCH_API_KEY, or '' when undefined.
	 * @param mixed  $imdb           Raw lezshows_imdb.
	 * @param mixed  $imdb_canonical Raw lezshows_imdb_canonical.
	 * @param bool   $force_curated  The per-show override.
	 * @param bool   $has_curated    Whether the show has curated links.
	 *
	 * @return array{mode: string, imdb: string}
	 */
	public static function decide( string $api_key, $imdb, $imdb_canonical, bool $force_curated, bool $has_curated ): array {
		$fallback = array(
			'mode' => $has_curated ? self::MODE_CURATED : self::MODE_NONE,
			'imdb' => '',
		);

		if ( $force_curated || '' === trim( $api_key ) ) {
			return $fallback;
		}

		$id = self::show_id( $imdb_canonical );
		if ( '' === $id ) {
			$id = self::show_id( $imdb );
		}

		if ( '' === $id ) {
			return $fallback;
		}

		return array(
			'mode' => self::MODE_JUSTWATCH,
			'imdb' => $id,
		);
	}

	/**
	 * The widget's data-* attributes, unescaped. Escape on output.
	 *
	 * @param string $api_key The widget key.
	 * @param string $imdb    A normalised IMDb ID from decide().
	 * @param string $theme   'light' or 'dark'; anything else becomes 'light'.
	 *
	 * @return array<string, string>
	 */
	public static function widget_attributes( string $api_key, string $imdb, string $theme ): array {
		return array(
			'data-jw-widget'               => '',
			'data-api-key'                 => trim( $api_key ),
			'data-object-type'             => 'show',
			'data-id'                      => $imdb,
			'data-id-type'                 => 'imdb',
			'data-theme'                   => self::theme( $theme ),
			// translators: {{title}} is replaced by JustWatch with the show's title. Keep it as-is.
			'data-no-offers-message'       => __( 'We don\'t know of anywhere streaming {{title}} right now.', 'lwtv' ),
			'data-title-not-found-message' => __( 'We couldn\'t find where to watch this show right now.', 'lwtv' ),
		);
	}

	/**
	 * A show's IMDb ID, or '' when the value is not a title ID.
	 *
	 * Imdb_Canonical::normalise() also accepts nm (person) IDs, since it serves
	 * actors too. A show needs a tt ID.
	 *
	 * @param mixed $value Raw meta value.
	 *
	 * @return string
	 */
	private static function show_id( $value ): string {
		$id = Imdb_Canonical::normalise( $value );

		return str_starts_with( $id, 'tt' ) ? $id : '';
	}

	/**
	 * A theme JustWatch accepts.
	 *
	 * @param string $theme Requested theme.
	 *
	 * @return string
	 */
	private static function theme( string $theme ): string {
		$theme = strtolower( trim( $theme ) );

		return in_array( $theme, self::THEMES, true ) ? $theme : self::THEMES[0];
	}
}
