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
$gender_sexuality  = lwtv_plugin()->get_character_data( $character, 'gender' );
$gender_sexuality .= ' &bull; ';
$gender_sexuality .= lwtv_plugin()->get_character_data( $character, 'sexuality' );

if ( 'full' === $format ) {
	$romantic = lwtv_plugin()->get_character_data( $character, 'romantic' );
}

if ( isset( $romantic ) && ! is_null( $romantic ) && '' !== $romantic ) {
	$gender_sexuality .= ' &bull; ' . lwtv_plugin()->get_character_data( $character, 'romantic' );
}

echo '<div class="card-meta-item gender sexuality">';
echo wp_kses_post( $gender_sexuality );
echo '</div>';
