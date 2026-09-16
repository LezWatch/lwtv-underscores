<?php

namespace LWTV\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Actor_Age {
	/**
	 * Generate Actor Age
	 *
	 * Take the birth and death and ouput as needed.
	 *
	 * An age is derived straight from a date of birth, so the actor's opt-out
	 * applies to it as much as to the date itself -- and it is checked here, in
	 * the primitive, so no call site has to remember. Hidden actors get the
	 * same empty string as an actor with no birth date on file, which is the
	 * contract callers already handle by testing is_object() on the result.
	 *
	 * There is deliberately no bypass for editors: the front end does not show
	 * them a hidden birth date either (see
	 * template-parts/partials/actors/life.php), and an age without a date would
	 * just be an inconsistency on the same page. Editorial tools read
	 * lezactors_birth directly and are unaffected.
	 *
	 * @param string  $actor  ID Actor ID
	 *
	 * @return \DateInterval|string DateInterval, or '' when unavailable.
	 */
	public function make( $actor_id ) {
		if ( lwtv_plugin()->hide_actor_data( $actor_id, 'dob' ) || lwtv_plugin()->hide_actor_data( $actor_id, 'all' ) ) {
			return '';
		}

		$output = '';
		$end    = new \DateTime();
		if ( get_post_meta( $actor_id, 'lezactors_death', true ) ) {
			$end = new \DateTime( get_post_meta( $actor_id, 'lezactors_death', true ) );
		}
		if ( get_post_meta( $actor_id, 'lezactors_birth', true ) ) {
			$start = new \DateTime( get_post_meta( $actor_id, 'lezactors_birth', true ) );
		}
		if ( isset( $start ) ) {
			$output = $start->diff( $end );
		}

		return $output;
	}
}
