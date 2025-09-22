<?php
/**
 * Characters Build Class for This Year Statistics
 *
 * @package LezWatch.TV
 */

namespace LWTV\This_Year\Build;

/**
 * Characters class for building character data for a specific year
 */
class Characters_Builder {

	/**
	 * Get all characters who appeared on air for a given year
	 *
	 * @param int $year The year to filter by
	 * @return array Array of character data with slug, name, and dead status
	 */
	public function get_characters_for_year( int $year ): array {
		global $wpdb;

		// Validate year input
		if ( $year < 1900 || $year > gmdate( 'Y' ) + 1 ) {
			return array();
		}

		// Use WordPress caching for performance
		$cache_key     = 'lwtv_characters_year_' . $year;
		$cached_result = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_result ) {
			return (array) $cached_result;
		}

		$characters = array();

		try {
			// Query to get all characters with their show group data and death status
			$query = "SELECT DISTINCT
				c.ID,
				c.post_name as slug,
				c.post_title as name,
				show_meta.meta_value as show_group_data,
				CASE
					WHEN death_term.term_id IS NOT NULL THEN 1
					ELSE 0
				END as is_dead
			FROM {$wpdb->posts} c
			INNER JOIN {$wpdb->postmeta} show_meta ON c.ID = show_meta.post_id
				AND show_meta.meta_key = 'lezchars_show_group'
			LEFT JOIN {$wpdb->term_relationships} death_rel ON c.ID = death_rel.object_id
			LEFT JOIN {$wpdb->term_taxonomy} death_tax ON death_rel.term_taxonomy_id = death_tax.term_taxonomy_id
				AND death_tax.taxonomy = 'lez_cliches'
			LEFT JOIN {$wpdb->terms} death_term ON death_tax.term_id = death_term.term_id
				AND death_term.slug = 'dead'
			WHERE c.post_type = 'post_type_characters'
				AND c.post_status = 'publish'
				AND show_meta.meta_value IS NOT NULL
				AND show_meta.meta_value != ''
			ORDER BY c.post_title ASC";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input in query
			$results = $wpdb->get_results( $query, ARRAY_A );

			// Process each character's data
			foreach ( $results as $row ) {
				$show_group_data = maybe_unserialize( $row['show_group_data'] );

				// Skip if data is not properly formatted
				if ( ! is_array( $show_group_data ) ) {
					continue;
				}

				// Check if character appeared in the specified year
				$appeared_in_year = $this->check_character_appeared_in_year( $show_group_data, $year );

				if ( $appeared_in_year ) {
					// Process death year data only if character is dead
					$death_years = array();
					if ( (bool) $row['is_dead'] ) {
						$death_year_data = get_post_meta( $row['ID'], 'lezchars_death_year', true );
						if ( ! empty( $death_year_data ) ) {
							$death_year_data = maybe_unserialize( $death_year_data );
							if ( is_array( $death_year_data ) ) {
								$death_years = array_values( $death_year_data );
							}
						}
					}

					// Use slug as unique key to prevent duplicates
					$characters[ $row['slug'] ] = array(
						'slug'        => $row['slug'],
						'name'        => $row['name'],
						'dead'        => (bool) $row['is_dead'],
						'death_years' => $death_years,
					);
				}
			}

			// Cache the results for 1 day
			lwtv_plugin()->set_transient( $cache_key, $characters, DAY_IN_SECONDS );

		} catch ( \Exception $e ) {
			// Log error and return empty array
			lwtv_plugin()->error_log( 'Error in Characters::get_characters_for_year: ' . $e->getMessage() );
			return array();
		}

		// Convert associative array back to indexed array
		return array_values( $characters );
	}

	/**
	 * Check if a character appeared in a specific year based on their show group data
	 *
	 * @param array $show_group_data The serialized show group data
	 * @param int   $year The year to check for
	 * @return bool True if character appeared in the year, false otherwise
	 */
	private function check_character_appeared_in_year( array $show_group_data, int $year ): bool {
		foreach ( $show_group_data as $show_relationship ) {
			if ( ! is_array( $show_relationship ) || ! isset( $show_relationship['appears'] ) ) {
				continue;
			}

			$appears_years = $show_relationship['appears'];
			if ( ! is_array( $appears_years ) ) {
				continue;
			}

			// Check if the year appears in the appears array
			foreach ( $appears_years as $appears_year ) {
				if ( (int) $appears_year === $year ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get count of characters for a specific year
	 *
	 * @param int $year The year to count characters for
	 * @return int Number of characters who appeared in the year
	 */
	public function get_character_count_for_year( int $year ): int {
		$characters = $this->get_characters_for_year( $year );
		return count( $characters );
	}

	/**
	 * Get count of dead characters for a specific year
	 *
	 * @param int $year The year to count dead characters for
	 * @return int Number of dead characters who appeared in the year
	 */
	public function get_dead_character_count_for_year( int $year ): int {
		$characters = $this->get_characters_for_year( $year );
		$dead_count = 0;

		foreach ( $characters as $character ) {
			if ( $character['dead'] ) {
				++$dead_count;
			}
		}

		return $dead_count;
	}

	/**
	 * Get all dead characters for a specific year
	 *
	 * @param int $year The year to filter by
	 * @return array Array of dead character data with slug, name, and death years
	 */
	public function get_dead_characters_for_year( int $year ): array {
		$characters      = $this->get_characters_for_year( $year );
		$dead_characters = array();

		foreach ( $characters as $character ) {
			if ( $character['dead'] ) {
				$dead_characters[] = $character;
			}
		}

		return $dead_characters;
	}

	/**
	 * Clear cached data for a specific year
	 *
	 * @param int $year The year to clear cache for
	 * @return void
	 */
	public function clear_year_cache( int $year ): void {
		$cache_key = 'lwtv_characters_year_' . $year;
		lwtv_plugin()->delete_transient( $cache_key );
	}

	/**
	 * Get all characters who appeared on air for a given year with their show information
	 *
	 * @param int $year The year to filter by
	 * @return array Array of character data with slug, name, dead status, and shows array
	 */
	public function get_characters_with_shows_for_year( int $year ): array {
		// Validate year input
		if ( $year < 1900 || $year > gmdate( 'Y' ) + 1 ) {
			return array();
		}

		// Use WordPress caching for performance
		$cache_key     = 'lwtv_characters_shows_year_' . $year;
		$cached_result = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_result ) {
			return (array) $cached_result;
		}

		// Get base character data
		$characters = $this->get_characters_for_year( $year );

		if ( empty( $characters ) ) {
			return array();
		}

		// Get character IDs for show data lookup
		$character_ids = array();
		foreach ( $characters as $character ) {
			$character_ids[] = $this->get_character_id_by_slug( $character['slug'] );
		}

		// Get show data for all characters
		$characters_with_shows = $this->enhance_characters_with_shows( $characters, $character_ids, $year );

		// Cache the enhanced results for 1 day
		lwtv_plugin()->set_transient( $cache_key, $characters_with_shows, DAY_IN_SECONDS );

		return $characters_with_shows;
	}

	/**
	 * Get character ID by slug
	 *
	 * @param string $slug The character slug
	 * @return int|null Character ID or null if not found
	 */
	private function get_character_id_by_slug( string $slug ): ?int {
		$post = get_page_by_path( $slug, OBJECT, 'post_type_characters' );
		return $post ? $post->ID : null;
	}

	/**
	 * Enhance characters array with show information
	 *
	 * @param array $characters Array of character data
	 * @param array $character_ids Array of character IDs
	 * @param int   $year The year to filter shows by
	 * @return array Enhanced character data with shows
	 */
	private function enhance_characters_with_shows( array $characters, array $character_ids, int $year ): array {
		global $wpdb;

		// Get all show group data for these characters
		$placeholders = implode( ',', array_fill( 0, count( $character_ids ), '%d' ) );

		// phpcs:disable
		$query = $wpdb->prepare(
			"SELECT post_id, meta_value
			FROM {$wpdb->postmeta}
			WHERE post_id IN ($placeholders)
			AND meta_key = 'lezchars_show_group'",
			$character_ids
		);
		// phpcs:enable

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above
		$show_group_results = $wpdb->get_results( $query, ARRAY_A );

		// Process show group data and collect unique show IDs
		$show_ids            = array();
		$character_show_data = array();

		foreach ( $show_group_results as $row ) {
			$show_group_data = maybe_unserialize( $row['meta_value'] );
			if ( ! is_array( $show_group_data ) ) {
				continue;
			}

			$character_id                         = (int) $row['post_id'];
			$character_show_data[ $character_id ] = array();

			foreach ( $show_group_data as $show_relationship ) {

				if ( ! is_array( $show_relationship ) || ! isset( $show_relationship['show'] ) || ! isset( $show_relationship['type'] ) ) {
					continue;
				}

				// If 'show' is an array, we need to get the first item
				if ( is_array( $show_relationship['show'] ) ) {
					$show_relationship['show'] = $show_relationship['show'][0];
				}

				$show_id        = (int) $show_relationship['show'];
				$character_type = $show_relationship['type'];

				// Check if character appeared in this show during the specified year
				if ( isset( $show_relationship['appears'] ) && is_array( $show_relationship['appears'] ) ) {
					$appeared_in_year = false;
					foreach ( $show_relationship['appears'] as $appears_year ) {
						if ( (int) $appears_year === $year ) {
							$appeared_in_year = true;
							break;
						}
					}

					if ( $appeared_in_year ) {
						$character_show_data[ $character_id ][] = array(
							'show_id' => $show_id,
							'type'    => $character_type,
						);

						$show_ids[] = $show_id;
					}
				}
			}
		}

		// Get show titles and permalinks
		$show_data = $this->get_show_data_by_ids( array_unique( $show_ids ) );

		// Enhance characters with show information
		$enhanced_characters = array();
		foreach ( $characters as $character ) {
			$character_id = $this->get_character_id_by_slug( $character['slug'] );
			$shows        = array();

			if ( isset( $character_show_data[ $character_id ] ) ) {
				// Deduplicate shows for this character (same show, different types/years)
				$unique_shows = array();
				foreach ( $character_show_data[ $character_id ] as $show_info ) {
					$show_id = $show_info['show_id'];
					if ( isset( $show_data[ $show_id ] ) ) {
						$show_key = $show_id . '_' . $show_info['type'];
						if ( ! isset( $unique_shows[ $show_key ] ) ) {
							$unique_shows[ $show_key ] = array(
								'name' => $show_data[ $show_id ]['title'],
								'url'  => $show_data[ $show_id ]['permalink'],
								'type' => $show_info['type'],
							);
						}
					}
				}
				$shows = array_values( $unique_shows );
			}

			$enhanced_characters[] = array(
				'slug'        => $character['slug'],
				'name'        => $character['name'],
				'dead'        => $character['dead'],
				'death_years' => $character['death_years'] ?? array(),
				'shows'       => $shows,
			);
		}

		return $enhanced_characters;
	}

	/**
	 * Get show data by IDs
	 *
	 * @param array $show_ids Array of show IDs
	 * @return array Array of show data keyed by show ID
	 */
	private function get_show_data_by_ids( array $show_ids ): array {
		if ( empty( $show_ids ) ) {
			return array();
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $show_ids ), '%d' ) );

		// phpcs:disable
		$query = $wpdb->prepare(
			"SELECT ID, post_title
			FROM {$wpdb->posts}
			WHERE ID IN ($placeholders)
			AND post_type = 'post_type_shows'
			AND post_status = 'publish'",
			$show_ids
		);
		// phpcs:enable

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above
		$results = $wpdb->get_results( $query, ARRAY_A );

		$show_data = array();
		foreach ( $results as $row ) {
			$show_data[ (int) $row['ID'] ] = array(
				'title'     => $row['post_title'],
				'permalink' => get_permalink( (int) $row['ID'] ),
			);
		}

		return $show_data;
	}

	/**
	 * Clear all cached character data
	 *
	 * @return void
	 */
	public function clear_all_cache(): void {
		global $wpdb;

		// Get all cached keys for this pattern
		$cache_keys = $wpdb->get_col(
			"SELECT option_name
			FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_lwtv_characters_year_%'"
		);

		foreach ( $cache_keys as $cache_key ) {
			$transient_name = str_replace( '_transient_', '', $cache_key );
			lwtv_plugin()->delete_transient( $transient_name );
		}
	}
}
