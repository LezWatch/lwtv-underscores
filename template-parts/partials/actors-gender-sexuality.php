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
$gender_sexuality = array();
$gender           = lwtv_plugin()->get_actor_gender( $actor );
$sexuality        = lwtv_plugin()->get_actor_sexuality( $actor );
$pronouns         = lwtv_plugin()->get_actor_pronouns( $actor );

if ( isset( $gender ) && ! empty( $gender ) ) {
	$gender_sexuality['Gender Orientation'] = $gender;
}

if ( isset( $sexuality ) && ! empty( $sexuality ) ) {
	$gender_sexuality['Sexual Orientation'] = $sexuality;
}

if ( isset( $pronouns ) && ! empty( $pronouns ) ) {
	$gender_sexuality['Pronouns'] = $pronouns;
}

if ( count( $gender_sexuality ) === 0 ) {
	return;
}

if ( 'full' === $format ) {
	echo '<ul class="list-group list-group-horizontal">';
	foreach ( $gender_sexuality as $item => $data ) {
		echo '<li><strong>' . esc_html( ucfirst( $item ) ) . '</strong>:<br />' . wp_kses_post( $data ) . '</li>';
	}
	echo '</ul>';
	echo '<hr />';
} elseif ( isset( $gender ) || isset( $sexuality ) ) {
	echo '<div class="card-meta-item gender sexuality"><p>';
	if ( isset( $gender ) ) {
		echo '&bull; <strong>Gender:</strong> ' . wp_kses_post( $gender ) . '<br />';
	}
	if ( isset( $sexuality ) ) {
		echo '&bull; <strong>Sexuality:</strong> ' . wp_kses_post( $sexuality );
	}
	echo '</p></div>';
}
