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
	 * Register the sync hooks, the Search admin menu and its pages (Rules,
	 * with its REST API, then Compare).
	 *
	 * Rules registers before Compare so it is the menu's first item.
	 *
	 * @return void
	 */
	public function register(): void {
		( new Sync( $this->indexes ) )->register();
		( new Admin_Notice( $this->indexes ) )->register();

		$rules = new Search_Rules_Page();
		( new Search_Menu( $rules ) )->register();
		$rules->register();

		$store = new Search_Rules_Store( $this->indexes, $this->client );
		( new Search_Rules_Rest( $store, $this->indexes, $this->client ) )->register();

		$jobs = new Index_Jobs( $this->indexes, $this->client, new Reindexer( $this->client, $store ) );
		$jobs->register();
		( new Search_Settings_Rest( $this->config, $this->client, $this->indexes, new Search_Settings_Store(), $store, $jobs ) )->register();

		( new Compare_Page( $this->config, $this->indexes ) )->register();
		( new Search_Settings_Page() )->register();
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
