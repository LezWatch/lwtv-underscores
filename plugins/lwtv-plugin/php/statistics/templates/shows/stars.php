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

$stars_raw   = lwtv_plugin()->generate_shows_statistics( 'array', 'stars' );
$stars_data  = ( is_array( $stars_raw ) && ! empty( $stars_raw ) ) ? (array) reset( $stars_raw ) : array();
$stars_total = (int) $shows_count;

$most_stars = array(
	'count' => 0,
	'name'  => '',
);

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

	if ( $stars_count > $most_stars['count'] ) {
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

// translators: %1$s is the name of the star with the most, %2$d is the number of stars it has.
$description = sprintf( __( 'A star is a mark of distinction, so most shows carry none. Of those that do, %1$s is the most common with %2$d. Thankfully none have yet to earn an “anti” flag.', 'lwtv' ), lcfirst( $most_stars['name'] ), $most_stars['count'] );

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
