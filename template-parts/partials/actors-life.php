<?php
/**
 * Template part for displaying actor life details
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

$actor = $args['actor'] ?? null;

// Generate Life Stats.
// Usage: $life.
$life = array();
$born = get_post_meta( $actor, 'lezactors_birth', true );

// If they have a birthday, let's parse it.
if ( ! empty( $born ) && ! lwtv_plugin()->hide_actor_data( $actor, 'dob' ) ) {
	$barr = explode( '-', $born );
	if ( isset( $barr[1] ) && isset( $barr[2] ) && checkdate( (int) $barr[1], (int) $barr[2], (int) $barr[0] ) ) {
		$get_birth    = new DateTime( $born );
		$life['born'] = date_format( $get_birth, 'F j, Y' );
	}
}

// If they have a death date, let's parse it.
$died = get_post_meta( $actor, 'lezactors_death', true );
if ( ! empty( $died ) ) {
	$darr = explode( '-', $died );
	if ( isset( $darr[1] ) && isset( $darr[2] ) && checkdate( (int) $darr[1], (int) $darr[2], (int) $darr[0] ) ) {
		$get_death    = new DateTime( $died );
		$life['died'] = date_format( $get_death, 'F j, Y' );
	}
}

// If they have a birth date, let's calculate their age.
if ( isset( $life['born'] ) ) {
	$age         = lwtv_plugin()->get_actor_age( $actor );
	$life['age'] = ( is_object( $age ) ) ? $age->format( '%Y years old' ) : '';
}

// Output everything.
if ( count( $life ) > 0 ) {
	echo '<ul class="list-group list-group-horizontal">';
	foreach ( $life as $event => $date ) {
		echo '<li><strong>' . esc_html( ucfirst( $event ) ) . '</strong>: ' . wp_kses_post( $date ) . '</li>';
	}
	echo '</ul>';
	echo '<hr />';
}
