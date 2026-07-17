<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Characters → Queer IRL: donut (played-by-queer vs. not).
 *
 * @package LezWatch.TV
 *
 * @var int $character_count
 */

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
require_once plugin_dir_path( __DIR__ ) . 'partials/phrases.php';

$qirl_raw  = lwtv_plugin()->generate_characters_statistics( 'array', 'queer-irl' );
$qirl_data = ( is_array( $qirl_raw ) && ! empty( $qirl_raw ) ) ? (array) reset( $qirl_raw ) : array();

$qirl_yes = isset( $qirl_data['queer'] ) ? (int) $qirl_data['queer']['count'] : 0;
$qirl_no  = isset( $qirl_data['not_queer'] ) ? (int) $qirl_data['not_queer']['count'] : 0;
$qirl_tot = $qirl_yes + $qirl_no;

$donut = array(
	'segments'    => array(
		array(
			'label' => __( 'Played by queer actors', 'lwtv' ),
			'count' => $qirl_yes,
			'pct'   => ( $qirl_tot > 0 ) ? round( ( $qirl_yes / $qirl_tot ) * 100, 1 ) : 0,
			'class' => 'pink',
		),
		array(
			'label' => __( 'Straight or cis actors', 'lwtv' ),
			'count' => $qirl_no,
			'pct'   => ( $qirl_tot > 0 ) ? round( ( $qirl_no / $qirl_tot ) * 100, 1 ) : 0,
			'class' => 'grey',
		),
	),
	'center'      => $qirl_yes,
	'center_sub'  => __( 'queer actors', 'lwtv' ),
	'eyebrow'     => __( 'Queer IRL', 'lwtv' ),
	/* translators: %s: a shortfall phrase like "Fewer than a quarter". */
	'headline'    => sprintf( __( '%s are played by queer actors', 'lwtv' ), lwtv_stats_shortfall_phrase( ( $qirl_tot > 0 ) ? round( ( $qirl_yes / $qirl_tot ) * 100, 1 ) : 0 ) ),
	'description' => __( 'Most queer and trans characters are still played by straight or cisgender actors.', 'lwtv' ),
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
