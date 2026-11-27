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
use LWTV\Meilisearch\FacetWP_Engine;
use LWTV\Meilisearch\Meilisearch as Meilisearch_Module;
use LWTV\Meilisearch\Search_Modal;
use LWTV\Meilisearch\Search_Page;

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
	 * Search results page glue; only built once the module is configured.
	 *
	 * @var Search_Page|null
	 */
	private ?Search_Page $search_page = null;

	/**
	 * Init the component. Hooks go in here.
	 *
	 * @return void
	 */
	public function init(): void {
		$config = Config::load();
		if ( ! $config->is_configured() ) {
			return;
		}

		( new Meilisearch_Module( $config ) )->register();

		add_filter( 'wp_resource_hints', array( $this->search_modal(), 'resource_hints' ), 10, 2 );

		$this->search_page = new Search_Page( $config, $this->search_modal() );
		$this->search_page->register();

		if ( function_exists( 'FWP' ) || class_exists( 'FacetWP' ) ) {
			( new FacetWP_Engine( $config, $this->search_modal() ) )->register();
		}
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
			'meili_search_results'    => array( $this, 'meili_search_results' ),
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
	 * The search results page's results, when this request is served from
	 * Meilisearch; null otherwise (switch off, unconfigured, or fell back).
	 *
	 * @return array|null
	 */
	public function meili_search_results(): ?array {
		return null === $this->search_page ? null : $this->search_page->results();
	}

	/**
	 * The search modal glue.
	 *
	 * @return Search_Modal
	 */
	private function search_modal(): Search_Modal {
		if ( null === $this->search_modal ) {
			$this->search_modal = new Search_Modal( Config::load() );
		}

		return $this->search_modal;
	}
}
