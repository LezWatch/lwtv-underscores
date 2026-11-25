<?php
/**
 * LWTV\_Components\Meilisearch class.
 *
 * @package LWTV
 */

namespace LWTV\_Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Meilisearch\Config;
use LWTV\Meilisearch\Meilisearch as Meilisearch_Module;

/**
 * Meilisearch prototype. Registers nothing unless its constants are set.
 */
class Meilisearch implements Component {

	/**
	 * Init the component. Hooks go in here.
	 *
	 * @return void
	 */
	public function init(): void {
		$config = Config::from_constants();
		if ( ! $config->is_configured() ) {
			return;
		}

		( new Meilisearch_Module( $config ) )->register();
	}
}
