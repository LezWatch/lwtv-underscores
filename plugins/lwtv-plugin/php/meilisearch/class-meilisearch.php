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
	private Indexes $indexes;

	/**
	 * @param Config $config Connection settings; must be configured.
	 */
	public function __construct( Config $config ) {
		$this->config  = $config;
		$this->indexes = new Indexes( new Client( $config ), $config );
	}

	/**
	 * Register the sync hooks and the admin comparison page.
	 *
	 * @return void
	 */
	public function register(): void {
		( new Sync( $this->indexes ) )->register();
		( new Compare_Page( $this->config, $this->indexes ) )->register();
		( new Admin_Notice( $this->indexes ) )->register();
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
