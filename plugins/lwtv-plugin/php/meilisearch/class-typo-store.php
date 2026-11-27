<?php
/**
 * Name: Typo Store
 * Description: The lwtv_meili_typo option: per-index typo tolerance blocks for Search → Settings → Typo tolerance.
 *
 * { version: 1, indexes: { <type>: block } }, not autoloaded. Only blocks
 * that differ from the defaults are stored, and the option is deleted when
 * none are left. Build\Typo_Tolerance checks and shapes every block.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch;

use LWTV\Meilisearch\Build\Index_Settings;
use LWTV\Meilisearch\Build\Typo_Tolerance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Typo_Store {

	const OPTION  = 'lwtv_meili_typo';
	const VERSION = 1;

	/**
	 * The index's searchable attributes, from its repo file.
	 *
	 * @param Index $index Index.
	 * @return string[] Empty when the repo file can't be read.
	 */
	public function searchable( Index $index ): array {
		try {
			$settings = Index_Settings::load( (string) file_get_contents( $index->settings_path() ) );
		} catch ( \InvalidArgumentException $e ) {
			return array();
		}

		return array_values( array_filter( (array) ( $settings['searchableAttributes'] ?? array() ), 'is_string' ) );
	}

	/**
	 * One type's raw stored block.
	 *
	 * @param string $type Index type.
	 * @return mixed Null when nothing is stored.
	 */
	public function stored( string $type ): mixed {
		$indexes = $this->indexes();

		return $indexes[ $type ] ?? null;
	}

	/**
	 * The block in effect, and any saved attributes the index no longer searches.
	 *
	 * @param Index $index Index.
	 * @return array{values: array, removed: string[]}
	 */
	public function values( Index $index ): array {
		return Typo_Tolerance::effective( $index->type(), $this->stored( $index->type() ), $this->searchable( $index ) );
	}

	/**
	 * The typoTolerance object to send to the index.
	 *
	 * @param Index $index Index.
	 * @return array
	 */
	public function payload( Index $index ): array {
		return Typo_Tolerance::payload( $this->values( $index )['values'] );
	}

	/**
	 * Validate and save one index's block. Values equal to the defaults
	 * remove the stored block.
	 *
	 * @param Index $index Index.
	 * @param mixed $input Raw block.
	 * @return array{values: array, errors: array<string, string>}
	 */
	public function save( Index $index, mixed $input ): array {
		$type    = $index->type();
		$checked = Typo_Tolerance::validate( $type, $input, $this->searchable( $index ) );
		if ( ! empty( $checked['errors'] ) ) {
			return $checked;
		}

		$indexes = $this->indexes();
		if ( Typo_Tolerance::is_default( $type, $checked['values'] ) ) {
			unset( $indexes[ $type ] );
		} else {
			$indexes[ $type ] = $checked['values'];
		}
		$this->write( $indexes );

		return $checked;
	}

	/**
	 * Go back to the defaults for one index.
	 *
	 * @param string $type Index type.
	 * @return void
	 */
	public function reset( string $type ): void {
		$indexes = $this->indexes();
		unset( $indexes[ $type ] );
		$this->write( $indexes );
	}

	/**
	 * The stored blocks, keyed by type.
	 *
	 * @return array<string, mixed>
	 */
	private function indexes(): array {
		$option = get_option( self::OPTION );

		return ( is_array( $option ) && is_array( $option['indexes'] ?? null ) ) ? $option['indexes'] : array();
	}

	/**
	 * Store the blocks, or delete the option when there are none.
	 *
	 * @param array<string, mixed> $indexes Blocks by type.
	 * @return void
	 */
	private function write( array $indexes ): void {
		if ( empty( $indexes ) ) {
			delete_option( self::OPTION );
			return;
		}

		update_option(
			self::OPTION,
			array(
				'version' => self::VERSION,
				'indexes' => $indexes,
			),
			false
		);
	}
}
