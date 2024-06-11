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
		'label' => 'Gender Orientation',
	),
	'sexuality' => array(
		'data'  => lwtv_plugin()->get_actor_sexuality( $actor ),
		'label' => 'Sexual Orientation',
	),
	'pronouns'  => array(
		'data'  => strtolower( lwtv_plugin()->get_actor_pronouns( $actor ) ),
		'label' => 'Pronouns',
	),
);

if ( 'full' === $format ) {
	echo '<ul class="list-group list-group-horizontal">';
	foreach ( $gender_sexuality as $item => $key ) {
		if ( empty( $key['data'] ) ) {
			continue;
		}
		echo '<li><strong>' . esc_html( ucfirst( $key['label'] ) ) . '</strong>:<br />' . wp_kses_post( $key['data'] ) . '</li>';
	}
	echo '</ul>';
	echo '<hr />';
} else {
	unset( $gender_sexuality['pronouns'] );
	echo '<div class="card-meta-item gender sexuality"><p>';
	foreach ( $gender_sexuality as $item => $key ) {
		if ( empty( $key['data'] ) || ! isset( $key['item'] ) ) {
			continue;
		}
		echo '&bull; <strong>' . esc_html( ucfirst( $key['item'] ) ) . ':</strong> ' . wp_kses_post( $key['data'] ) . '<br />';
	}
	echo '</p></div>';
}
