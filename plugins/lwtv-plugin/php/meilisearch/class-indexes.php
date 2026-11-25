<?php
/**
 * Name: Indexes
 * Description: Every Meilisearch index this site keeps, in one place.
 *
 * The only list of index types: sync, the CLI, the admin notice and Search
 * Compare all read it from here.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Indexes {

	/**
	 * @var array<string, Index>
	 */
	private array $indexes = array();

	/**
	 * @param Client $client Meilisearch client.
	 * @param Config $config Connection settings.
	 */
	public function __construct( Client $client, Config $config ) {
		foreach ( array( new Characters_Index( $client, $config ), new Shows_Index( $client, $config ) ) as $index ) {
			$this->indexes[ $index->type() ] = $index;
		}
	}

	/**
	 * One index by type.
	 *
	 * @param string $type Index type.
	 * @return Index|null
	 */
	public function get( string $type ): ?Index {
		return $this->indexes[ $type ] ?? null;
	}

	/**
	 * Every index, keyed by type, in registry order.
	 *
	 * @return array<string, Index>
	 */
	public function all(): array {
		return $this->indexes;
	}

	/**
	 * Index types, in registry order.
	 *
	 * @return string[]
	 */
	public function types(): array {
		return array_keys( $this->indexes );
	}
}
