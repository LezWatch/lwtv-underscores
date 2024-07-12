<?php
/**
 * Template part for displaying character gender and sexuality
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

$character = $args['character'] ?? null;
$format    = $args['format'] ?? 'full';

if ( ! isset( $character ) || empty( $character ) ) {
	return;
}

$gender_sexuality = array(
	'gender'    => array(
		'data'  => lwtv_plugin()->get_character_data( $character, 'gender' ),
		'label' => 'Gender Orientation',
	),
	'sexuality' => array(
		'data'  => lwtv_plugin()->get_character_data( $character, 'sexuality' ),
		'label' => 'Sexual Orientation',
	),
	'romantic'  => array(
		'data'  => lwtv_plugin()->get_character_data( $character, 'romantic' ),
		'label' => 'Romantic Orientation',
	),
);


if ( ! 'full' === $format ) {
	unset( $gender_sexuality['romantic'] );
}

foreach ( $gender_sexuality as $key => $data ) {
	if ( empty( $data['data'] ) ) {
		unset( $gender_sexuality[ $key ] );
	}
}

echo '<div class="card-meta-item gender sexuality">';
echo wp_kses_post( implode( '&nbsp;&bull;&nbsp;', array_column( $gender_sexuality, 'data' ) ) );
echo '</div>';
