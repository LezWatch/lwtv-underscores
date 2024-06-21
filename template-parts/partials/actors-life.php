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
$life_array = array(
	'dates' => array(),
	'age'   => '',
);

$born = get_post_meta( $actor, 'lezactors_birth', true );
$died = get_post_meta( $actor, 'lezactors_death', true );

// If they have a birthday, let's parse it.
if ( ! empty( $born ) && ! lwtv_plugin()->hide_actor_data( $actor, 'dob' ) ) {
	$barr = explode( '-', $born );
	if ( isset( $barr[1] ) && isset( $barr[2] ) && checkdate( (int) $barr[1], (int) $barr[2], (int) $barr[0] ) ) {
		$get_birth = new DateTime( $born );

		$life_array['dates']['born'] = date_format( $get_birth, 'F j, Y' );
	}
}

// If they have a death date, let's parse it.
if ( ! empty( $died ) ) {
	$darr = explode( '-', $died );
	if ( isset( $darr[1] ) && isset( $darr[2] ) && checkdate( (int) $darr[1], (int) $darr[2], (int) $darr[0] ) ) {
		$get_death = new DateTime( $died );

		$life_array['dates']['died'] = date_format( $get_death, 'F j, Y' );
	}
}

// If they have a birth date, let's calculate their age.
if ( isset( $born ) ) {
	$age = lwtv_plugin()->get_actor_age( $actor );

	$life_array['age'] = ( is_object( $age ) ) ? $age->format( '%Y years old' ) : null;
}


// Output everything.
if ( count( $life_array['dates'] ) > 0 ) {
	echo '<ul class="list-group list-group-flush">';
	foreach ( $life_array['dates'] as $event => $date ) {
		echo '<li class="list-group-item"><strong>' . esc_html( ucfirst( $event ) ) . '</strong>:</br>' . wp_kses_post( $date ) . '</li>';
	}

	if ( ! empty( $life_array['age'] ) ) {
		echo '<li class="list-group-item"><strong>Age</strong>:</br>' . wp_kses_post( $life_array['age'] ) . '</li>';
	}

	echo '</ul>';
}
