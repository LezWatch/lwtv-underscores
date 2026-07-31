<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows → We Love It: binary "progress ring" of loved vs. everything else.
 *
 * @package LezWatch.TV
 *
 * @var int $shows_count
 */

$love_raw   = lwtv_plugin()->generate_shows_statistics( 'array', 'we-love-it' );
$love_data  = ( is_array( $love_raw ) && ! empty( $love_raw ) ) ? (array) reset( $love_raw ) : array();
$love_total = (int) $shows_count;

$love_loved  = isset( $love_data['we_love'] ) ? (int) $love_data['we_love']['count'] : 0;
$love_others = isset( $love_data['we_do_not_love'] ) ? (int) $love_data['we_do_not_love']['count'] : max( 0, $love_total - $love_loved );

$donut = array(
	'segments'    => array(
		array(
			'label' => __( 'Shows we love', 'lwtv' ),
			'count' => $love_loved,
			'pct'   => ( $love_total > 0 ) ? round( ( $love_loved / $love_total ) * 100, 1 ) : 0,
			'class' => 'pink',
		),
		array(
			'label' => __( 'Everything else', 'lwtv' ),
			'count' => $love_others,
			'pct'   => ( $love_total > 0 ) ? round( ( $love_others / $love_total ) * 100, 1 ) : 0,
			'class' => 'grey',
		),
	),
	'center'      => $love_loved,
	'center_sub'  => __( 'we love', 'lwtv' ),
	'eyebrow'     => __( 'Shows We Love', 'lwtv' ),
	'headline'    => __( 'A rare and deliberate honor', 'lwtv' ),
	'description' => __( '“Shows We Love” is hand-picked, so it\'s a fraction of the whole database.', 'lwtv' ),
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
