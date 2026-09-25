<?php
/**
 * Name: Show Announcement
 * Description: Pure rules for announcing a new show through Postiz.
 *
 * No WordPress calls, so it is unit-testable. Postiz\New_Post gathers the
 * inputs and does the posting.
 *
 * @package LWTV
 */

namespace LWTV\Postiz\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Show_Announcement {

	/**
	 * How long after first publish a show can still be announced.
	 *
	 * Shows are often published before their characters exist, so the
	 * announcement waits for the first character. The window stops a corpus
	 * recalculation (`wp lwtv calc --all`) from announcing the back catalogue.
	 */
	const WINDOW_DAYS = 30;

	/**
	 * Most stations to name in the post.
	 */
	const MAX_STATIONS = 2;

	/**
	 * Should this show be announced now?
	 *
	 * @param array $show {
	 *     @type string $status       Post status.
	 *     @type int    $char_count   lezshows_char_count.
	 *     @type bool   $announced    Whether it has already been announced.
	 *     @type int    $published_at First-publish Unix time (GMT); 0 if unknown.
	 * }
	 * @param int   $now  Current Unix time.
	 * @return bool
	 */
	public static function should_announce( array $show, int $now ): bool {
		if ( 'publish' !== ( $show['status'] ?? '' ) ) {
			return false;
		}

		if ( ! empty( $show['announced'] ) ) {
			return false;
		}

		if ( (int) ( $show['char_count'] ?? 0 ) < 1 ) {
			return false;
		}

		$published_at = (int) ( $show['published_at'] ?? 0 );

		if ( $published_at < 1 ) {
			return false;
		}

		return ( $now - $published_at ) <= self::WINDOW_DAYS * DAY_IN_SECONDS;
	}

	/**
	 * The same title cleanup Of the Day uses before building a hashtag,
	 * minus sanitize_title(), which the caller applies.
	 *
	 * @param string $title Show title.
	 * @return string
	 */
	public static function clean_title( string $title ): string {
		$title = trim( (string) preg_replace( '~\([^)]+\)~', '', $title ) );
		$title = str_replace( ' & ', ' and ', $title );

		return str_replace( '@', 'a', $title );
	}

	/**
	 * CamelCase hashtag from a sanitized slug: 'the-l-word' becomes '#TheLWord'.
	 *
	 * @param string $slug Sanitized title.
	 * @return string '' when there is nothing to tag.
	 */
	public static function hashtag( string $slug ): string {
		$words = array_filter( explode( '-', $slug ), 'strlen' );

		return empty( $words ) ? '' : '#' . implode( '', array_map( 'ucfirst', $words ) );
	}

	/**
	 * Four-digit year from a start value ('2004' or an ACF 'Ymd' date).
	 *
	 * @param string $start Start value.
	 * @return string '' when it isn't a year.
	 */
	public static function year( string $start ): string {
		return preg_match( '/^(\d{4})/', trim( $start ), $m ) ? $m[1] : '';
	}

	/**
	 * The post text: "New on LezWatch.TV: Show (Station, Year) #Hashtag - URL".
	 *
	 * @param string $name     Show title.
	 * @param array  $stations Station names, in order.
	 * @param string $year     Start year, or ''.
	 * @param string $hashtag  Hashtag, or ''.
	 * @param string $url      Permalink.
	 * @return string
	 */
	public static function content( string $name, array $stations, string $year, string $hashtag, string $url ): string {
		$details = array_slice( array_values( array_filter( array_map( 'strval', $stations ), 'strlen' ) ), 0, self::MAX_STATIONS );

		if ( '' !== $year ) {
			$details[] = $year;
		}

		$content = 'New on LezWatch.TV: ' . $name;

		if ( ! empty( $details ) ) {
			$content .= ' (' . implode( ', ', $details ) . ')';
		}

		if ( '' !== $hashtag ) {
			$content .= ' ' . $hashtag;
		}

		return $content . ' - ' . $url;
	}
}
