<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows → Formats: donut of format breakdown (raspberry ramp).
 *
 * @package LezWatch.TV
 *
 * @var int $shows_count
 */

$formats_raw   = lwtv_plugin()->generate_shows_statistics( 'array', 'formats' );
$formats_data  = ( is_array( $formats_raw ) && ! empty( $formats_raw ) ) ? (array) reset( $formats_raw ) : array();
$formats_total = (int) $shows_count;

// Raspberry ramp classes, darkest (largest) first.
$formats_ramp = array( 'dkpink', 'pink', 'mid', 'ltpink', 'ltpink' );

$formats_segments = array();
$formats_i        = 0;
foreach ( $formats_data as $formats_row ) {
	$formats_count      = (int) $formats_row['count'];
	$formats_segments[] = array(
		'label' => $formats_row['name'],
		'count' => $formats_count,
		'pct'   => ( $formats_total > 0 ) ? round( ( $formats_count / $formats_total ) * 100, 1 ) : 0,
		'class' => $formats_ramp[ min( $formats_i, count( $formats_ramp ) - 1 ) ],
	);
	++$formats_i;
}

// Headline from the leading slice.
$formats_lead = $formats_segments[0] ?? array( 'pct' => 0 );
$formats_in10 = ( $formats_lead['pct'] > 0 ) ? (int) round( $formats_lead['pct'] / 10 ) : 0;

switch ( $formats_lead['label'] ) {
	case 'TV Show':
		$formats_top = 'TV series';
		$description = __( 'TV series include linear (over air, like ABC, NBC, CTV) and streaming (like Netflix).', 'lwtv' );
		break;
	case 'Web Series':
		$formats_top = 'web series';
		$description = __( 'Web-Series are streaming only but not on a distributor, so think YouTube web-series.', 'lwtv' );
		break;
	case 'Mini-Series':
		$formats_top = 'mini-series';
		$description = __( 'Mini-series (or limited release series) are usually found on traditional linear TV, but have been growing on streamers.', 'lwtv' );
		break;
	case 'TV Movie':
		$formats_top = 'made for TV movies';
		$description = __( 'Most Made for TV movies are on traditional linear TV, but have been growing on streamers.', 'lwtv' );
		break;
	default:
		$formats_top = '';
		$description = '';
}

$donut = array(
	'segments'    => $formats_segments,
	'center'      => $formats_total,
	'center_sub'  => __( 'shows', 'lwtv' ),
	'eyebrow'     => __( 'Format Breakdown', 'lwtv' ),
	// translators: %1$1d is "N in ten" figure for the leading format, %2$2s is the leading format
	'headline'    => ( $formats_in10 > 0 ) ? sprintf( __( '%1$1d in 10 are %2$2s', 'lwtv' ), $formats_in10, $formats_top ) : __( 'Format breakdown', 'lwtv' ),
	'description' => $description,
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
