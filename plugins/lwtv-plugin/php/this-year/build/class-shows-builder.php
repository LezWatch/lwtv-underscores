<?php
/**
 * Shows Build Class for This Year Statistics
 *
 * @package LezWatch.TV
 */

namespace LWTV\This_Year\Build;

/**
 * Shows class for building show data for a specific year
 */
class Shows_Builder {

	/**
	 * Get all shows that were on air for a given year
	 *
	 * @param int $year The year to filter by
	 * @return array Array of show data with slug, name, started, and ended status
	 */
	public function get_shows_for_year( int $year ): array {
		global $wpdb;

		// Validate year input
		if ( $year < LWTV_FIRST_YEAR || $year > gmdate( 'Y' ) + 1 ) {
			return array();
		}

		// Use WordPress caching for performance
		$cache_key     = 'lwtv_shows_year_' . $year;
		$cached_result = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_result ) {
			return (array) $cached_result;
		}

		$shows = array();

		try {
			// Query to get all shows with their airdates data
			$query = "SELECT
				s.ID,
				s.post_name as slug,
				s.post_title as name,
				air_meta.meta_value as airdates_data
			FROM {$wpdb->posts} s
			INNER JOIN {$wpdb->postmeta} air_meta ON s.ID = air_meta.post_id
				AND air_meta.meta_key = 'lezshows_airdates'
			WHERE s.post_type = 'post_type_shows'
				AND s.post_status = 'publish'
				AND air_meta.meta_value IS NOT NULL
				AND air_meta.meta_value != ''
			ORDER BY s.post_title ASC";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input in query
			$results = $wpdb->get_results( $query, ARRAY_A );

			// Process each show's data
			foreach ( $results as $row ) {
				$airdates_data = maybe_unserialize( $row['airdates_data'] );

				// Skip if data is not properly formatted
				if ( ! is_array( $airdates_data ) || ! isset( $airdates_data['start'] ) || ! isset( $airdates_data['finish'] ) ) {
					continue;
				}

				// Check if show was on air during the specified year
				$on_air_data = $this->check_show_on_air_in_year( $airdates_data, $year );

				if ( $on_air_data['on_air'] ) {
					$shows[] = array(
						'slug'    => $row['slug'],
						'name'    => $row['name'],
						'started' => $on_air_data['started'],
						'ended'   => $on_air_data['ended'],
					);
				}
			}

			// Cache the results for 1 day
			lwtv_plugin()->set_transient( $cache_key, $shows, DAY_IN_SECONDS );

		} catch ( \Exception $e ) {
			// Log error and return empty array
			lwtv_plugin()->error_log( 'Error in Shows::get_shows_for_year: ' . $e->getMessage() );
			return array();
		}

		return $shows;
	}

	/**
	 * Check if a show was on air during a specific year based on their airdates data
	 *
	 * @param array $airdates_data The serialized airdates data
	 * @param int   $year The year to check for
	 * @return array Array with on_air, started, and ended boolean values
	 */
	private function check_show_on_air_in_year( array $airdates_data, int $year ): array {
		$start_year  = (int) $airdates_data['start'];
		$finish_year = $airdates_data['finish'];

		// Handle 'current' finish year
		if ( 'current' === $finish_year ) {
			$finish_year = gmdate( 'Y' );
		} else {
			$finish_year = (int) $finish_year;
		}

		// Check if year falls within the show's airing period (inclusive)
		$on_air = ( $year >= $start_year && $year <= $finish_year );

		// Check if show started in the specified year
		$started = ( $start_year === $year );

		// Check if show ended in the specified year
		$ended = ( $finish_year === $year );

		return array(
			'on_air'  => $on_air,
			'started' => $started,
			'ended'   => $ended,
		);
	}

	/**
	 * Get count of shows for a specific year
	 *
	 * @param int $year The year to count shows for
	 * @return int Number of shows that were on air in the year
	 */
	public function get_show_count_for_year( int $year ): int {
		$shows = $this->get_shows_for_year( $year );
		return count( $shows );
	}

	/**
	 * Get count of shows that started in a specific year
	 *
	 * @param int $year The year to count started shows for
	 * @return int Number of shows that started in the year
	 */
	public function get_started_show_count_for_year( int $year ): int {
		$shows         = $this->get_shows_for_year( $year );
		$started_count = 0;

		foreach ( $shows as $show ) {
			if ( $show['started'] ) {
				++$started_count;
			}
		}

		return $started_count;
	}

	/**
	 * Get count of shows that ended in a specific year
	 *
	 * @param int $year The year to count ended shows for
	 * @return int Number of shows that ended in the year
	 */
	public function get_ended_show_count_for_year( int $year ): int {
		$shows       = $this->get_shows_for_year( $year );
		$ended_count = 0;

		foreach ( $shows as $show ) {
			if ( $show['ended'] ) {
				++$ended_count;
			}
		}

		return $ended_count;
	}

	/**
	 * Clear cached data for a specific year
	 *
	 * @param int $year The year to clear cache for
	 * @return void
	 */
	public function clear_year_cache( int $year ): void {
		$cache_key = 'lwtv_shows_year_' . $year;
		lwtv_plugin()->delete_transient( $cache_key );
	}

	/**
	 * Get all shows that were on air for a given year with their character information
	 *
	 * @param int $year The year to filter by
	 * @return array Array of show data with characters, nations, and formats
	 */
	public function get_shows_with_characters_for_year( int $year ): array {
		// Validate year input
		if ( $year < LWTV_FIRST_YEAR || $year > gmdate( 'Y' ) + 1 ) {
			return array();
		}

		// Use WordPress caching for performance
		$cache_key     = 'lwtv_shows_characters_year_' . $year;
		$cached_result = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_result ) {
			return (array) $cached_result;
		}

		// Get base show data
		$shows = $this->get_shows_for_year( $year );

		if ( empty( $shows ) ) {
			return array();
		}

		// Get show IDs for character lookup
		$show_ids = array();
		foreach ( $shows as $show ) {
			$show_ids[] = $this->get_show_id_by_slug( $show['slug'] );
		}

		// Get characters and taxonomy data for these shows
		$shows_with_characters = $this->enhance_shows_with_characters( $shows, $show_ids, $year );

		// Cache the enhanced results for 1 day
		lwtv_plugin()->set_transient( $cache_key, $shows_with_characters, DAY_IN_SECONDS );

		return $shows_with_characters;
	}

	/**
	 * Get show ID by slug
	 *
	 * @param string $slug The show slug
	 * @return int|null Show ID or null if not found
	 */
	private function get_show_id_by_slug( string $slug ): ?int {
		$post = get_page_by_path( $slug, OBJECT, 'post_type_shows' );
		return $post ? $post->ID : null;
	}

	/**
	 * Enhance shows array with character and taxonomy information
	 *
	 * @param array $shows Array of show data
	 * @param array $show_ids Array of show IDs
	 * @param int   $year The year to filter characters by
	 * @return array Enhanced show data with characters and taxonomies
	 */
	private function enhance_shows_with_characters( array $shows, array $show_ids, int $year ): array {
		global $wpdb;

		// Get all characters who appeared in these shows during the specified year
		$characters_data = $this->get_characters_for_shows_in_year( $show_ids, $year );

		// Get taxonomy data for shows (nations and formats)
		$taxonomy_data = $this->get_show_taxonomy_data( $show_ids );

		// Enhance shows with character and taxonomy information
		$enhanced_shows = array();
		foreach ( $shows as $show ) {
			$show_id    = $this->get_show_id_by_slug( $show['slug'] );
			$characters = isset( $characters_data[ $show_id ] ) ? $characters_data[ $show_id ] : array();

			// Only include shows that have characters for this year
			if ( ! empty( $characters ) ) {
				$enhanced_shows[] = array(
					'slug'       => $show['slug'],
					'name'       => $show['name'],
					'started'    => $show['started'],
					'ended'      => $show['ended'],
					'characters' => $characters,
					'nations'    => isset( $taxonomy_data[ $show_id ]['nations'] ) ? $taxonomy_data[ $show_id ]['nations'] : array(),
					'formats'    => isset( $taxonomy_data[ $show_id ]['formats'] ) ? $taxonomy_data[ $show_id ]['formats'] : array(),
				);
			}
		}

		return $enhanced_shows;
	}

	/**
	 * Get characters who appeared in specific shows during a given year
	 *
	 * @param array $show_ids Array of show IDs
	 * @param int   $year The year to filter by
	 * @return array Characters grouped by show ID
	 */
	private function get_characters_for_shows_in_year( array $show_ids, int $year ): array {
		if ( empty( $show_ids ) ) {
			return array();
		}

		global $wpdb;

		// Get ALL character show group data (much simpler than trying to match serialized patterns)
		$query = "SELECT post_id, meta_value
			FROM {$wpdb->postmeta}
			WHERE meta_key = 'lezchars_show_group'
			AND meta_value IS NOT NULL
			AND meta_value != ''";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input in query
		$show_group_results = $wpdb->get_results( $query, ARRAY_A );

		// Process show group data and collect characters by show
		$characters_by_show = array();
		$character_ids      = array();

		foreach ( $show_group_results as $row ) {
			$show_group_data = maybe_unserialize( $row['meta_value'] );
			if ( ! is_array( $show_group_data ) ) {
				continue;
			}

			$character_id    = (int) $row['post_id'];
			$character_ids[] = $character_id;

			foreach ( $show_group_data as $show_relationship ) {
				if ( ! is_array( $show_relationship ) || ! isset( $show_relationship['show'] ) || ! isset( $show_relationship['type'] ) ) {
					continue;
				}

				// Handle array format for show ID
				if ( is_array( $show_relationship['show'] ) ) {
					$show_relationship['show'] = $show_relationship['show'][0];
				}

				$show_id        = (int) $show_relationship['show'];
				$character_type = $show_relationship['type'];

				// Only process if this show is in our target show IDs
				if ( in_array( $show_id, $show_ids, true ) ) {
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
							if ( ! isset( $characters_by_show[ $show_id ] ) ) {
								$characters_by_show[ $show_id ] = array();
							}

							$characters_by_show[ $show_id ][] = array(
								'character_id' => $character_id,
								'type'         => $character_type,
							);
						}
					}
				}
			}
		}

		// Get character names and permalinks
		$character_data = $this->get_character_data_by_ids( array_unique( $character_ids ) );

		// Enhance character data with names and URLs
		foreach ( $characters_by_show as $show_id => $characters ) {
			foreach ( $characters as $index => $character ) {
				$character_id = $character['character_id'];
				if ( isset( $character_data[ $character_id ] ) ) {
					$characters_by_show[ $show_id ][ $index ]['dead']       = $this->check_character_dead( $character_id );
					$characters_by_show[ $show_id ][ $index ]['last_death'] = get_post_meta( $character_id, 'lezchars_last_death', true );
					$characters_by_show[ $show_id ][ $index ]['name']       = $character_data[ $character_id ]['name'];
					$characters_by_show[ $show_id ][ $index ]['url']        = $character_data[ $character_id ]['permalink'];
				}
			}
		}

		return $characters_by_show;
	}

	/**
	 * Check if a character is dead
	 *
	 * @param int $character_id The character ID
	 * @return bool True if the character is dead, false otherwise
	 */
	private function check_character_dead( int $character_id ): bool {
		$has_last_death = get_post_meta( $character_id, 'lezchars_last_death', true ) ? true : false;

		// See if the term 'dead' is set
		$has_dead_term = has_term( 'dead', 'lez_cliches', $character_id );

		return $has_last_death && $has_dead_term;
	}

	/**
	 * Get character data by IDs
	 *
	 * @param array $character_ids Array of character IDs
	 * @return array Array of character data keyed by character ID
	 */
	private function get_character_data_by_ids( array $character_ids ): array {
		if ( empty( $character_ids ) ) {
			return array();
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $character_ids ), '%d' ) );

		// phpcs:disable
		$query = $wpdb->prepare(
			"SELECT ID, post_title, post_name
			FROM {$wpdb->posts}
			WHERE ID IN ($placeholders)
			AND post_type = 'post_type_characters'
			AND post_status = 'publish'",
			$character_ids
		);
		// phpcs:enable

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above
		$results = $wpdb->get_results( $query, ARRAY_A );

		$character_data = array();
		foreach ( $results as $row ) {
			$character_data[ (int) $row['ID'] ] = array(
				'name'      => $row['post_title'],
				'permalink' => get_permalink( (int) $row['ID'] ),
			);
		}

		return $character_data;
	}

	/**
	 * Get taxonomy data for shows (nations and formats)
	 *
	 * @param array $show_ids Array of show IDs
	 * @return array Taxonomy data grouped by show ID
	 */
	private function get_show_taxonomy_data( array $show_ids ): array {
		if ( empty( $show_ids ) ) {
			return array();
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $show_ids ), '%d' ) );

		// phpcs:disable
		$query = $wpdb->prepare(
			"SELECT tr.object_id, tt.taxonomy, t.name, t.slug, t.term_id
			FROM {$wpdb->term_relationships} tr
			INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
			WHERE tr.object_id IN ($placeholders)
			AND tt.taxonomy IN ('lez_country', 'lez_formats')",
			$show_ids
		);
		// phpcs:enable

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above
		$results = $wpdb->get_results( $query, ARRAY_A );

		$taxonomy_data = array();
		foreach ( $results as $row ) {
			$show_id   = (int) $row['object_id'];
			$taxonomy  = $row['taxonomy'];
			$term_data = array(
				'name' => $row['name'],
				'slug' => $row['slug'],
				'url'  => get_term_link( (int) $row['term_id'], $taxonomy ),
			);

			if ( ! isset( $taxonomy_data[ $show_id ] ) ) {
				$taxonomy_data[ $show_id ] = array(
					'nations' => array(),
					'formats' => array(),
				);
			}

			if ( 'lez_country' === $taxonomy ) {
				$taxonomy_data[ $show_id ]['nations'][] = $term_data;
			} elseif ( 'lez_formats' === $taxonomy ) {
				$taxonomy_data[ $show_id ]['formats'][] = $term_data;
			}
		}

		return $taxonomy_data;
	}

	/**
	 * Get all shows that were on air for a given year, sorted alphabetically by name
	 *
	 * @param int $year The year to filter by
	 * @return array Array of show data grouped by first character (A-Z, # for numbers, - for special chars)
	 */
	public function get_shows_for_year_by_name( int $year ): array {
		// Validate year input
		if ( $year < LWTV_FIRST_YEAR || $year > gmdate( 'Y' ) + 1 ) {
			return array();
		}

		// Use WordPress caching for performance
		$cache_key     = 'lwtv_shows_year_by_name_' . $year;
		$cached_result = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_result ) {
			return (array) $cached_result;
		}

		// Get base show data
		$shows = $this->get_shows_for_year( $year );

		if ( empty( $shows ) ) {
			return array();
		}

		$shows_by_name = array();

		foreach ( $shows as $show ) {
			$show_id = $this->get_show_id_by_slug( $show['slug'] );

			if ( ! $show_id ) {
				continue;
			}

			$show_name = $show['name'];

			// Get additional show data
			$airdates  = get_post_meta( $show_id, 'lezshows_airdates', true );
			$countries = get_the_term_list( $show_id, 'lez_country', '', ', ', '' );
			$format    = get_the_term_list( $show_id, 'lez_formats' );

			// Build the first character marker
			$marker = ( new Shared_Builder() )->get_character_marker( $show_name );

			// Build the array
			$shows_by_name[ $marker ][ $show_name ] = array(
				'url'      => get_permalink( $show_id ),
				'name'     => $show_name,
				'country'  => wp_strip_all_tags( $countries ),
				'format'   => wp_strip_all_tags( $format ),
				'airdates' => $airdates,
			);
		}

		// Sort each group alphabetically by show name
		foreach ( $shows_by_name as $marker => $shows_group ) {
			ksort( $shows_group );
			$shows_by_name[ $marker ] = $shows_group;
		}

		// Sort the markers (#, -, then A-Z)
		$sorted_shows = array();
		$markers      = array_keys( $shows_by_name );

		// Custom sort: # first, then -, then alphabetical
		usort(
			$markers,
			function ( $a, $b ) {
				if ( '#' === $a && '#' !== $b ) {
					return -1;
				}
				if ( '#' !== $a && '#' === $b ) {
					return 1;
				}
				if ( '-' === $a && '-' !== $b && '-' !== '#' ) {
					return -1;
				}
				if ( '-' !== $a && '-' === $b && '-' !== '#' ) {
					return 1;
				}
				return strcmp( $a, $b );
			}
		);

		foreach ( $markers as $marker ) {
			$sorted_shows[ $marker ] = $shows_by_name[ $marker ];
		}

		// Cache the results for 1 day
		lwtv_plugin()->set_transient( $cache_key, $sorted_shows, DAY_IN_SECONDS );

		return $sorted_shows;
	}

	/**
	 * Get all shows that were on air for a given year, sorted by format
	 *
	 * @param int $year The year to filter by
	 * @return array Array of show data grouped by format, then alphabetically by show name
	 */
	public function get_shows_for_year_by_format( int $year ): array {
		// Validate year input
		if ( $year < LWTV_FIRST_YEAR || $year > gmdate( 'Y' ) + 1 ) {
			return array();
		}

		// Use WordPress caching for performance
		$cache_key     = 'lwtv_shows_year_by_format_' . $year;
		$cached_result = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_result ) {
			return (array) $cached_result;
		}

		// Get base show data
		$shows = $this->get_shows_for_year( $year );

		if ( empty( $shows ) ) {
			return array();
		}

		$shows_by_format = array();

		foreach ( $shows as $show ) {
			$show_id = $this->get_show_id_by_slug( $show['slug'] );

			if ( ! $show_id ) {
				continue;
			}

			$show_name = $show['name'];

			// Get additional show data
			$airdates  = get_post_meta( $show_id, 'lezshows_airdates', true );
			$countries = get_the_term_list( $show_id, 'lez_country', '', ', ', '' );
			$formats   = get_the_terms( $show_id, 'lez_formats' );

			// If no formats, skip this show
			if ( empty( $formats ) || is_wp_error( $formats ) ) {
				continue;
			}

			// Group by each format
			foreach ( $formats as $format ) {
				$format_name = $format->name;

				// Build the array
				$shows_by_format[ $format_name ][ $show_name ] = array(
					'url'      => get_permalink( $show_id ),
					'name'     => $show_name,
					'country'  => wp_strip_all_tags( $countries ),
					'format'   => wp_strip_all_tags( get_the_term_list( $show_id, 'lez_formats' ) ),
					'airdates' => $airdates,
				);
			}
		}

		// Sort each format group alphabetically by show name
		foreach ( $shows_by_format as $format_name => $format_group ) {
			ksort( $format_group );
			$shows_by_format[ $format_name ] = $format_group;
		}

		// Sort the formats alphabetically
		$sorted_shows = array();
		$format_names = array_keys( $shows_by_format );
		sort( $format_names );

		foreach ( $format_names as $format_name ) {
			$sorted_shows[ $format_name ] = $shows_by_format[ $format_name ];
		}

		// Cache the results for 1 day
		lwtv_plugin()->set_transient( $cache_key, $sorted_shows, DAY_IN_SECONDS );

		return $sorted_shows;
	}

	/**
	 * Get all shows that were on air for a given year, sorted by nation/country
	 *
	 * @param int $year The year to filter by
	 * @return array Array of show data grouped by country, then alphabetically by show name
	 */
	public function get_shows_for_year_by_nation( int $year ): array {
		// Validate year input
		if ( $year < LWTV_FIRST_YEAR || $year > gmdate( 'Y' ) + 1 ) {
			return array();
		}

		// Use WordPress caching for performance
		$cache_key     = 'lwtv_shows_year_by_nation_' . $year;
		$cached_result = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_result ) {
			return (array) $cached_result;
		}

		// Get base show data
		$shows = $this->get_shows_for_year( $year );

		if ( empty( $shows ) ) {
			return array();
		}

		$shows_by_nation = array();

		foreach ( $shows as $show ) {
			$show_id = $this->get_show_id_by_slug( $show['slug'] );

			if ( ! $show_id ) {
				continue;
			}

			$show_name = $show['name'];

			// Get additional show data
			$airdates  = get_post_meta( $show_id, 'lezshows_airdates', true );
			$countries = get_the_terms( $show_id, 'lez_country' );
			$format    = get_the_term_list( $show_id, 'lez_formats' );

			// If no countries, skip this show
			if ( empty( $countries ) || is_wp_error( $countries ) ) {
				continue;
			}

			// Group by each country
			foreach ( $countries as $country ) {
				$country_name = $country->name;

				// Build the array
				$shows_by_nation[ $country_name ][ $show_name ] = array(
					'url'      => get_permalink( $show_id ),
					'name'     => $show_name,
					'country'  => wp_strip_all_tags( get_the_term_list( $show_id, 'lez_country' ) ),
					'format'   => wp_strip_all_tags( $format ),
					'airdates' => $airdates,
				);
			}
		}

		// Sort each country group alphabetically by show name
		foreach ( $shows_by_nation as $country_name => $country_group ) {
			ksort( $country_group );
			$shows_by_nation[ $country_name ] = $country_group;
		}

		// Sort the countries alphabetically
		$sorted_shows  = array();
		$country_names = array_keys( $shows_by_nation );
		sort( $country_names );

		foreach ( $country_names as $country_name ) {
			$sorted_shows[ $country_name ] = $shows_by_nation[ $country_name ];
		}

		// Cache the results for 1 day
		lwtv_plugin()->set_transient( $cache_key, $sorted_shows, DAY_IN_SECONDS );

		return $sorted_shows;
	}

	/**
	 * Clear all cached show data
	 *
	 * @return void
	 */
	public function clear_all_cache(): void {
		global $wpdb;

		// Get all cached keys for this pattern
		$cache_keys = $wpdb->get_col(
			"SELECT option_name
			FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_lwtv_shows_year_%'"
		);

		foreach ( $cache_keys as $cache_key ) {
			$transient_name = str_replace( '_transient_', '', $cache_key );
			lwtv_plugin()->delete_transient( $transient_name );
		}
	}

	/**
	 * Get all shows that started in a specific year
	 *
	 * @param int $year The year to filter by
	 * @return array Array of show data that started in the year
	 */
	public function get_new_shows_for_year( int $year ): array {
		$shows     = $this->get_shows_for_year( $year );
		$new_shows = array();

		foreach ( $shows as $show ) {
			if ( $show['started'] ) {
				$new_shows[] = $show;
			}
		}

		return $new_shows;
	}

	/**
	 * Get all shows that ended in a specific year
	 *
	 * @param int $year The year to filter by
	 * @return array Array of show data that ended in the year
	 */
	public function get_ended_shows_for_year( int $year ): array {
		$shows       = $this->get_shows_for_year( $year );
		$ended_shows = array();

		foreach ( $shows as $show ) {
			if ( $show['ended'] ) {
				$ended_shows[] = $show;
			}
		}

		return $ended_shows;
	}
}
