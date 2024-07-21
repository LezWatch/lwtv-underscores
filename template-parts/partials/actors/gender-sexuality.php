<?php
/**
 * Template part for displaying actor gender and sexuality
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

$actor  = $args['actor'] ?? null;
$format = $args['format'] ?? 'full';

if ( ! isset( $actor ) || empty( $actor ) ) {
	return;
}
// Generate Gender & Sexuality & Pronoun Data.
// Usage: $gender_sexuality.
$gender_sexuality = array(
	'gender'    => array(
		'data'  => lwtv_plugin()->get_actor_gender( $actor ),
		'label' => ( 'full' === $format ) ? 'Gender Orientation' : 'Gender',
	),
	'sexuality' => array(
		'data'  => lwtv_plugin()->get_actor_sexuality( $actor ),
		'label' => ( 'full' === $format ) ? 'Sexual Orientation' : 'Sexuality',
	),
);

if ( 'full' === $format ) {
	// Used on Actor pages.
	$pronouns = strtolower( lwtv_plugin()->get_actor_pronouns( $actor ) );

	echo '<ul class="list-group list-group-flush">';

	foreach ( $gender_sexuality as $item => $key ) {
		if ( empty( $key['data'] ) ) {
			continue;
		}
		echo '<li class="list-group-item"><strong>' . esc_html( ucfirst( $key['label'] ) ) . '</strong>:<br />' . wp_kses_post( $key['data'] ) . '</li>';
	}
	if ( ! empty( $pronouns ) ) {
		echo '<li class="list-group-item"><strong>Pronouns</strong>:<br />' . wp_kses_post( $pronouns ) . '</li>';
	}

	echo '</ul>';
} else {
	// Used on cards.
	unset( $gender_sexuality['pronouns'] );
	echo '<div class="card-meta-item gender sexuality"><p>';
	foreach ( $gender_sexuality as $item => $key ) {
		$gen_data  = empty( $key['data'] ) ? 'Unknown' : $key['data'];
		$gen_label = $key['label'];
		echo '&bull; <strong>' . esc_html( $gen_label ) . ':</strong> ' . wp_kses_post( $gen_data ) . '<br />';
	}
	echo '</p></div>';
}
