<?php
/**
 * LWTV\_Components\Queeries class.
 *
 * @package LWTV
 */

namespace LWTV\_Components;

use LWTV\Queeries\Is_Actor_Queer;

/**
 * Class for adding primary theme support.
 *
 * Exposes template tags
 *
 */
class Queeries implements Component, Templater {

	/**
	 * Init the component. Hooks go in here.
	 *
	 * @return void
	 */
	public function init(): void {
		// Void on purpose.
	}

	/**
	 * Retrieve the template tags.
	 *
	 * @return array
	 */
	public function get_template_tags(): array {
		return array(
			'is_actor_queer' => array( $this, 'is_actor_queer' ),
		);
	}

	/**
	 * Is an Actor Queer?
	 *
	 * @param  int   $the_id
	 * @return bool
	 */
	public function is_actor_queer( $the_id ): bool {
		return ( new Is_Actor_Queer() )->make( $the_id );
	}
}
