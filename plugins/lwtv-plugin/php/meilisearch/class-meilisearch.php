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
	private Indexer $indexer;

	/**
	 * @param Config $config Connection settings; must be configured.
	 */
	public function __construct( Config $config ) {
		$this->config  = $config;
		$this->indexer = new Indexer( new Client( $config ), $config );
	}

	/**
	 * Register the sync hooks and the admin comparison page.
	 *
	 * @return void
	 */
	public function register(): void {
		( new Sync( $this->indexer ) )->register();
		( new Compare_Page( $this->config, $this->indexer ) )->register();
	}

	/**
	 * The shared indexer.
	 *
	 * @return Indexer
	 */
	public function indexer(): Indexer {
		return $this->indexer;
	}
}
