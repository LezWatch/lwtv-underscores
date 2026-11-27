<?php
/**
 * Name: Meilisearch
 * Description: Boots the Meilisearch prototype once it is configured.
 *
 * Runs next to SearchWP and changes nothing about the live search.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Meilisearch {

	private Config $config;
	private Client $client;
	private Indexes $indexes;

	/**
	 * @param Config $config Connection settings; must be configured.
	 */
	public function __construct( Config $config ) {
		$this->config  = $config;
		$this->client  = new Client( $config );
		$this->indexes = new Indexes( $this->client, $config );
	}

	/**
	 * Register the sync hooks, the Search admin menu and its pages.
	 *
	 * Menu order (Settings, Rules, Compare) comes from each page's admin_menu
	 * priority (20, 21, 22), not from the order they're registered here.
	 *
	 * @return void
	 */
	public function register(): void {
		( new Sync( $this->indexes ) )->register();
		( new Admin_Notice( $this->indexes ) )->register();

		$settings_page = new Search_Settings_Page();
		( new Search_Menu( $settings_page ) )->register();
		$settings_page->register();
		( new Search_Rules_Page() )->register();

		$store = new Search_Rules_Store( $this->indexes, $this->client );
		( new Search_Rules_Rest( $store, $this->indexes, $this->client ) )->register();

		$typo = new Typo_Store();
		$jobs = new Index_Jobs( $this->indexes, $this->client, new Reindexer( $this->client, $store, $typo ) );
		$jobs->register();
		( new Search_Settings_Rest( $this->config, $this->client, $this->indexes, new Search_Settings_Store(), $store, $jobs, $typo ) )->register();
		( new Typo_Rest( $this->indexes, $typo, $jobs ) )->register();

		( new Compare_Page( $this->config, $this->indexes ) )->register();
	}

	/**
	 * Every index.
	 *
	 * @return Indexes
	 */
	public function indexes(): Indexes {
		return $this->indexes;
	}
}
