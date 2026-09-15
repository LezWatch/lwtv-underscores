<?php

namespace LWTV\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Actor_Birthday {
	/**
	 * Is today a birthday we may celebrate?
	 *
	 * The privacy check lives here, in the predicate, rather than at each call
	 * site. Every consumer uses this to decide whether to show a birthday
	 * flourish -- the banner below, the cake icon and "Happy Birthday" tooltip
	 * in the actor header -- and each of those discloses the birth month and
	 * day. So an actor who opted out of publishing their date of birth simply
	 * reads as "not today", and a new caller inherits that without knowing to
	 * ask.
	 *
	 * @access public
	 *
	 * @param  string $the_id
	 * @return bool
	 */
	public function make( $the_id ) {
		if ( lwtv_plugin()->hide_actor_data( $the_id, 'dob' ) || lwtv_plugin()->hide_actor_data( $the_id, 'all' ) ) {
			return false;
		}

		$today_is  = gmdate( 'm-d' );
		$birth_raw = get_post_meta( $the_id, 'lezactors_birth', true );
		$birthday  = $birth_raw ? gmdate( 'm-d', strtotime( $birth_raw ) ) : '';
		if ( $birthday === $today_is ) {
			return true;
		}

		return false;
	}

	/**
	 * Output birthday
	 *
	 * @param  [type] $the_id
	 * @return void
	 */
	public function get( $the_id ) {
		// Honor the actor's DOB/all privacy request — the birthday banner
		// reveals birth month/day and exact age. make() checks this too; the
		// duplicate is deliberate, so neither the predicate nor the output can
		// be the single point of failure.
		if ( lwtv_plugin()->hide_actor_data( $the_id, 'dob' ) || lwtv_plugin()->hide_actor_data( $the_id, 'all' ) ) {
			return;
		}

		if ( $this->make( $the_id ) && ! get_post_meta( $the_id, 'lezactors_death', true ) ) {
			$old = ' ';
			$end = array( 'th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th' );
			$age = lwtv_plugin()->get_actor_age( $the_id );
			$num = ( is_object( $age ) ) ? $age->format( '%y' ) : 0;

			// If their age is 0, something's wrong.
			if ( 0 === $num ) {
				return;
			}
			if ( ( $num % 100 ) >= 11 && ( $num % 100 ) <= 13 ) {
				$years_old = $num . 'th';
			} else {
				$years_old = $num . $end[ $num % 10 ];
			}
			$old = ' ' . $years_old . ' ';
			echo '<div class="alert alert-info" role="alert">Happy' . esc_html( $old ) . 'Birthday, ' . esc_html( get_the_title() ) . '!</div>';
		}
	}
}
