<?php
/**
 * LWTV\_Components\Relationships class.
 *
 * @package LWTV
 */

namespace LWTV\_Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Relationships\Store;
use LWTV\Relationships\Sync;

/**
 * Installs and maintains the character link table.
 */
class Relationships implements Component {

	/**
	 * Init the component. Hooks go in here.
	 *
	 * @return void
	 */
	public function init(): void {
		new Sync();

		// Every request: Action Scheduler runs the save-time sync from WP-Cron,
		// so the table has to exist on front-end requests too.
		add_action( 'init', array( Store::class, 'maybe_install' ), 5 );
	}
}
