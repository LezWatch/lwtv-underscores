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
use LWTV\Meilisearch\Search_Modal;

/**
 * Meilisearch prototype. Registers nothing unless its constants are set.
 *
 * Exposes the search modal template tags either way, so the theme can always
 * call them; they report off when the module isn't configured.
 */
class Meilisearch implements Component, Templater {

	/**
	 * Search modal glue, built on first use.
	 *
	 * @var Search_Modal|null
	 */
	private ?Search_Modal $search_modal = null;

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

		add_filter( 'wp_resource_hints', array( $this->search_modal(), 'resource_hints' ), 10, 2 );
	}

	/**
	 * Retrieve the template tags.
	 *
	 * @return array
	 */
	public function get_template_tags(): array {
		return array(
			'meili_modal_enabled'     => array( $this, 'meili_modal_enabled' ),
			'meili_modal_config_json' => array( $this, 'meili_modal_config_json' ),
		);
	}

	/**
	 * Whether this request gets the Meilisearch search modal.
	 *
	 * @return bool
	 */
	public function meili_modal_enabled(): bool {
		return $this->search_modal()->enabled();
	}

	/**
	 * The search modal's browser config as JSON; '' when the modal is off.
	 *
	 * @return string
	 */
	public function meili_modal_config_json(): string {
		return $this->search_modal()->config_json();
	}

	/**
	 * The search modal glue.
	 *
	 * @return Search_Modal
	 */
	private function search_modal(): Search_Modal {
		if ( null === $this->search_modal ) {
			$this->search_modal = new Search_Modal( Config::from_constants() );
		}

		return $this->search_modal;
	}
}
