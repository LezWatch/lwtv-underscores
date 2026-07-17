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
	$stars_common = sprintf( __( 'Of those that do, %1$s is the most common with %2$s.', 'lwtv' ), lcfirst( $most_stars['name'] ), number_format_i18n( $most_stars['count'] ) );
} else {
	$stars_common = __( 'None have earned one yet.', 'lwtv' );
}

if ( $stars_anti > 0 ) {
	/* translators: %s: number of shows flagged "anti". */
	$stars_anti_clause = sprintf( _n( '%s carries an “anti” flag.', '%s carry an “anti” flag.', $stars_anti, 'lwtv' ), number_format_i18n( $stars_anti ) );
} else {
	$stars_anti_clause = __( 'None have yet to earn an “anti” flag.', 'lwtv' );
}

/* translators: 1: fraction phrase (e.g. "nearly all"), 2: "most common star" clause, 3: "anti" clause. */
$description = sprintf( __( 'A star is a mark of distinction, so %1$s carry none. %2$s %3$s', 'lwtv' ), $stars_none_phrase, $stars_common, $stars_anti_clause );

$donut = array(
	'segments'    => $stars_segments,
	'center'      => $stars_none,
	'center_sub'  => __( 'no star', 'lwtv' ),
	'eyebrow'     => __( 'Star Ratings', 'lwtv' ),
	'headline'    => __( 'Only a small share earn a star at all', 'lwtv' ),
	'description' => $description,
);


// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
