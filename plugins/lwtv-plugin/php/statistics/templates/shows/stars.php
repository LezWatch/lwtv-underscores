<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows → Stars: donut of star ratings (medal colors).
 *
 * @package LezWatch.TV
 *
 * @var int $shows_count
 */

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
require_once plugin_dir_path( __DIR__ ) . 'partials/phrases.php';

$stars_raw   = lwtv_plugin()->generate_shows_statistics( 'array', 'stars' );
$stars_data  = ( is_array( $stars_raw ) && ! empty( $stars_raw ) ) ? (array) reset( $stars_raw ) : array();
$stars_total = (int) $shows_count;

$most_stars = array(
	'count' => 0,
	'name'  => '',
);
$stars_anti = 0;

$stars_order = array(
	'gold'   => array( __( 'Gold', 'lwtv' ), 'gold' ),
	'silver' => array( __( 'Silver', 'lwtv' ), 'silver' ),
	'bronze' => array( __( 'Bronze', 'lwtv' ), 'bronze' ),
	'anti'   => array( __( 'Anti', 'lwtv' ), 'red' ),
);

$stars_segments = array();
$stars_sum      = 0;
foreach ( $stars_order as $stars_key => $stars_meta ) {
	$stars_count      = isset( $stars_data[ $stars_key ] ) ? (int) $stars_data[ $stars_key ]['count'] : 0;
	$stars_sum       += $stars_count;
	$stars_segments[] = array(
		'label' => $stars_meta[0],
		'count' => $stars_count,
		'pct'   => ( $stars_total > 0 ) ? round( ( $stars_count / $stars_total ) * 100, 1 ) : 0,
		'class' => $stars_meta[1],
	);

	// "Anti" is a demerit, not a distinction — track it separately, never as "most common".
	if ( 'anti' === $stars_key ) {
		$stars_anti = $stars_count;
	} elseif ( $stars_count > $most_stars['count'] ) {
		$most_stars['count'] = $stars_count;
		$most_stars['name']  = $stars_meta[0];
	}
}

// "No Star" leads the legend (largest); prepend.
$stars_none = max( 0, $stars_total - $stars_sum );
array_unshift(
	$stars_segments,
	array(
		'label' => __( 'No Star', 'lwtv' ),
		'count' => $stars_none,
		'pct'   => ( $stars_total > 0 ) ? round( ( $stars_none / $stars_total ) * 100, 1 ) : 0,
		'class' => 'grey',
	)
);

// Everything below derives from the real counts.
$stars_none_pct    = ( $stars_total > 0 ) ? round( ( $stars_none / $stars_total ) * 100, 1 ) : 0;
$stars_none_phrase = lcfirst( lwtv_stats_fraction_phrase( $stars_none_pct ) );

if ( $most_stars['count'] > 0 ) {
	/* translators: 1: star name (gold/silver/bronze), 2: how many shows carry it. */
	$stars_common = sprintf( __( 'Of all the stars, %1$s is the most common with %2$s.', 'lwtv' ), lcfirst( $most_stars['name'] ), number_format_i18n( $most_stars['count'] ) );
} else {
	$stars_common = __( 'Not a single show has a star yet, which is weird.', 'lwtv' );
}

if ( $stars_anti > 0 ) {
	/* translators: %s: number of shows flagged "anti". */
	$stars_anti_clause = sprintf( _n( '%s show carries an “anti” flag.', '%s shows carry an “anti” flag.', $stars_anti, 'lwtv' ), number_format_i18n( $stars_anti ) );
} else {
	$stars_anti_clause = __( 'No shows have yet earned an “anti” flag.', 'lwtv' );
}

$donut = array(
	'segments'    => $stars_segments,
	'center'      => $stars_none,
	'center_sub'  => __( 'no star', 'lwtv' ),
	'eyebrow'     => __( 'Star Ratings', 'lwtv' ),
	'headline'    => __( 'Only a small share earn a star at all', 'lwtv' ),
	'description' => '',
);

// Callouts: coverage, then average + median stars per show (across shows that have at least one).
$inter_stats   = ( new \LWTV\Statistics\Build\Taxonomy_Optimized() )->get_terms_per_object_stats( 'post_type_shows', 'lez_stars' );
$lwtv_callouts = array();
if ( (int) $inter_stats['shows'] > 0 && (int) $shows_count > 0 ) {
	$inter_with = (int) $inter_stats['shows'];
	$inter_pct  = round( ( $inter_with / (int) $shows_count ) * 100, 1 );

	$lwtv_callouts[] = array(
		'label' => __( 'Shows with stars', 'lwtv' ),
		'icon'  => 'fireworks.svg',
		/* translators: %s: percentage of all shows carrying at least one star (one decimal). */
		'text'  => sprintf( __( '%s%% of all shows have a star.', 'lwtv' ), number_format_i18n( $inter_pct, 1 ) ),
	);

	$lwtv_callouts[] = array(
		'label' => __( 'Brightest stars', 'lwtv' ),
		'icon'  => 'star.svg',
		'text'  => ucfirst( $stars_common ),
	);

	$lwtv_callouts[] = array(
		'label' => __( 'Darkest nights', 'lwtv' ),
		'icon'  => 'eye-evil.svg',
		'text'  => ucfirst( $stars_anti_clause ),
	);

	// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
	include plugin_dir_path( __DIR__ ) . 'partials/callouts.php';
}

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
