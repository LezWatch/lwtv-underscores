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
	'dates' => array(
		'born' => 'Unknown',
	),
	'age'   => 'Unknown',
);

$born = get_post_meta( $actor, 'lezactors_birth', true );
$died = get_post_meta( $actor, 'lezactors_death', true );

// If they have a birthday, let's parse it.
if ( ! empty( $born ) && ! lwtv_plugin()->hide_actor_data( $actor, 'dob' ) ) {
	try {
		$get_birth = new DateTime( $born );

		$life_array['dates']['born'] = date_format( $get_birth, 'F j, Y' );
	} catch ( Exception $e ) {
		lwtv_plugin()->error_log( 'actors', 'Invalid lezactors_birth date for actor ' . $actor . ': ' . $e->getMessage() );
	}
}

// If they have a death date, let's parse it.
if ( ! empty( $died ) ) {
	try {
		$get_death = new DateTime( $died );

		// Add died to array.
		$life_array['dates']['died'] = date_format( $get_death, 'F j, Y' );
	} catch ( Exception $e ) {
		lwtv_plugin()->error_log( 'actors', 'Invalid lezactors_death date for actor ' . $actor . ': ' . $e->getMessage() );
	}
}

// If they have a birth date, let's calculate their age.
if ( isset( $life_array['dates']['born'] ) && ( 'Unknown' !== $life_array['dates']['born'] ) ) {
	// If the birthdate is unknown, we can't calculate age.
	$age = lwtv_plugin()->get_actor_age( $actor );

	$life_array['age'] = ( is_object( $age ) ) ? $age->format( '%Y years old' ) : 'Unknown';
}


// Output everything.
if ( count( $life_array['dates'] ) > 0 ) {
	echo '<ul class="list-group list-group-flush">';
	if ( ! empty( $life_array['age'] ) ) {
		echo '<li class="list-group-item"><strong>Age</strong>:</br>' . wp_kses_post( $life_array['age'] ) . '</li>';
	}
	foreach ( $life_array['dates'] as $event => $date ) {
		echo '<li class="list-group-item"><strong>' . esc_html( ucfirst( $event ) ) . '</strong>:</br>' . wp_kses_post( $date ) . '</li>';
	}
	echo '</ul>';
}
