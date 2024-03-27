<?php
/**
 * Template for displaying the show airdates.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 * @package LezWatch.TV
 */

$show_id = $args['show_id'] ?? null;
$format  = $args['format'] ?? 'sidebar';

if ( is_null( $show_id ) || empty( $show_id ) ) {
	return;
}

$airdates = get_post_meta( $show_id, 'lezshows_airdates', true );

if ( ! $airdates ) {
	return;
}

// If the start is 'current' make it this year (though it really never should be.)
if ( 'current' === $airdates['start'] ) {
	$airdates['start'] = gmdate( 'Y' );
}

// If this is for an embedded post, return early.
if ( 'embed' === $format ) {
	$airdate = $airdates['start'] . ' - ' . $airdates['finish'];
	if ( $airdates['start'] === $airdates['finish'] ) {
		$airdate = $airdates['finish'];
	}
	echo ' (' . esc_html( $airdate ) . ')';
	return;
}

$air_title = 'Airdate';

// Link the year to the year.
$airdate = '<a href="/this-year/' . $airdates['start'] . '/shows-on-air/">' . $airdates['start'] . '</a>';

// If the start and end date are NOT the same, then let's show the end.
if ( $airdates['finish'] && $airdates['start'] !== $airdates['finish'] ) {
	// If the end date is a number, it's a year, so link it.
	if ( is_numeric( $airdates['finish'] ) && $airdates['finish'] <= gmdate( 'Y' ) ) {
		$airdates['finish'] = '<a href="/this-year/' . $airdates['finish'] . '/shows-on-air/">' . $airdates['finish'] . '</a>';
	}
	// No matter what, add it.
	$airdate  .= ' - ' . $airdates['finish'];
	$air_title = 'Airdates';
}

// If the end is a number (i.e. a year) AND we have a seasons value,
// and it's NOT a TV movie, show it.
$season_count = get_post_meta( $show_id, 'lezshows_seasons', true );
if ( ! has_term( 'movie', 'lez_formats' ) && 'current' !== $airdates['finish'] && isset( $season_count ) && $season_count >= 1 ) {
		$seasons  = _n( 'season', 'seasons', $season_count );
		$airdate .= ' (' . $season_count . ' ' . $seasons . ')';
}

echo '<li class="list-group-item network airdates"><strong>' . wp_kses_post( $air_title ) . ':</strong> ' . wp_kses_post( $airdate );

if ( 'current' === $airdates['finish'] || empty( $airdates['finish'] ) ) {
	echo '&nbsp;<span class="badge text-bg-success">On Air</span>';
}

echo '</li>';
