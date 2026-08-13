<?php
/**
 * Characters Build Class for This Year Statistics
 *
 * @package LezWatch.TV
 */

namespace LWTV\This_Year\Build;

use LWTV\Statistics\Build\Unknown_Actor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
			// Query to get all characters with show group data and death status.
			// Join on ACF repeater sub-field keys (lezchars_show_group_0_appears, etc.) to filter
			// to characters who have show relationships; DISTINCT avoids duplicates from multiple rows.
			$query = "SELECT DISTINCT
				c.ID,
				c.post_name as slug,
				c.post_title as name,
				CASE
					WHEN EXISTS (
						SELECT 1
						FROM {$wpdb->term_relationships} tr
						INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
						INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
						WHERE tr.object_id = c.ID
						AND tt.taxonomy = 'lez_cliches'
						AND t.slug = 'dead'
					) THEN 1
					ELSE 0
				END as is_dead
			FROM {$wpdb->posts} c
			INNER JOIN {$wpdb->postmeta} appears_meta ON c.ID = appears_meta.post_id
			WHERE c.post_type = 'post_type_characters'
				AND c.post_status = 'publish'
				AND appears_meta.meta_key LIKE 'lezchars_show_group_%_appears'
				AND appears_meta.meta_value IS NOT NULL
				AND appears_meta.meta_value != ''
			ORDER BY c.post_title ASC";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input in query
			$results = $wpdb->get_results( $query, ARRAY_A );

			// Prime meta once so the per-character get_field()/get_post_meta() below hit cache.
			$prime_ids = array_values( array_filter( array_map( static fn( $r ) => (int) $r['ID'], (array) $results ) ) );
			if ( ! empty( $prime_ids ) ) {
				update_meta_cache( 'post', $prime_ids );
			}

			// Process each character's data
			foreach ( $results as $row ) {
				$show_group_data = get_field( 'lezchars_show_group', $row['ID'] );

				// Skip if data is not properly formatted
				if ( ! is_array( $show_group_data ) ) {
					continue;
				}

				// Check if character appeared in the specified year
				$appeared_in_year = $this->check_character_appeared_in_year( $show_group_data, $year );

				if ( $appeared_in_year ) {
					if ( (bool) $row['is_dead'] ) {
						$row['last_death'] = get_post_meta( $row['ID'], 'lezchars_last_death', true );

						$death_rows = get_field( 'lezchars_death_year', $row['ID'] );
						if ( is_array( $death_rows ) ) {
							$row['death_years'] = array_values( array_filter( array_column( $death_rows, 'date' ) ) );
						}
					}

					// Use slug as unique key to prevent duplicates
					$characters[ $row['slug'] ] = array(
						'slug'        => $row['slug'],
						'name'        => $row['name'],
						'dead'        => (bool) $row['is_dead'],
						'death_years' => $row['death_years'] ?? array(),
						'last_death'  => $row['last_death'] ?? '',
					);
				}
			}

			// Cache the results for 1 day
			lwtv_plugin()->set_transient( $cache_key, $characters, DAY_IN_SECONDS );

		} catch ( \Exception $e ) {
			// Log error and return empty array
			lwtv_plugin()->debug_log( 'this-year', 'Error in Characters::get_characters_for_year: ' . $e->getMessage() );
			return array();
		}

		return $characters;
	}

	/**
	 * Busiest-actor tally: how many of this year's queer characters each actor
	 * played. Thin reader over the combined Overview stats pass.
	 *
	 * @param int $year The year to tally.
	 * @return array [ (int) actor_id => (int) character count ].
	 */
	public function get_actor_counts_for_year( int $year ): array {
		return $this->get_overview_character_stats( $year )['actor_counts'];
	}

	/**
	 * Characters On Air panel extras (in 2+ shows / debuting / non-binary). Thin
	 * reader over the combined Overview stats pass.
	 *
	 * @param int $year The year to tally.
	 * @return array { @type int $multi_show, @type int $debuting, @type int $non_binary }
	 */
	public function get_character_extras_for_year( int $year ): array {
		return $this->get_overview_character_stats( $year )['extras'];
	}

	/**
	 * Combined Overview stats for the year, computed in a single pass over the
	 * year's characters and cached together. Walking the character set — and
	 * reading each one's show group — is the expensive part, so the busiest-actor
	 * tally and the Characters-panel extras are derived side by side rather than
	 * in two separate passes.
	 *
	 * @param int $year The year to tally.
	 * @return array {
	 *     @type array $actor_counts [ (int) actor_id => (int) character count ].
	 *     @type array $extras       { @type int $multi_show, @type int $debuting, @type int $non_binary }.
	 * }
	 */
	public function get_overview_character_stats( int $year ): array {
		$empty = array(
			'actor_counts' => array(),
			'extras'       => array(
				'multi_show' => 0,
				'debuting'   => 0,
				'non_binary' => 0,
			),
		);

		if ( $year < 1900 || $year > gmdate( 'Y' ) + 1 ) {
			return $empty;
		}

		$cache_key     = 'lwtv_overview_char_stats_year_' . $year;
		$cached_result = lwtv_plugin()->get_transient( $cache_key );
		if ( false !== $cached_result ) {
			return (array) $cached_result;
		}

		global $wpdb;
		$stats = $empty;

		try {
			// Same character selection as get_characters_for_year(): everyone with
			// a show relationship; we filter to the year per character below.
			$query = "SELECT DISTINCT c.ID
				FROM {$wpdb->posts} c
				INNER JOIN {$wpdb->postmeta} appears_meta ON c.ID = appears_meta.post_id
				WHERE c.post_type = 'post_type_characters'
					AND c.post_status = 'publish'
					AND appears_meta.meta_key LIKE 'lezchars_show_group_%_appears'
					AND appears_meta.meta_value IS NOT NULL
					AND appears_meta.meta_value != ''";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input in query
			$results = $wpdb->get_results( $query, ARRAY_A );

			$prime_ids = array_values( array_filter( array_map( static fn( $r ) => (int) $r['ID'], (array) $results ) ) );
			if ( ! empty( $prime_ids ) ) {
				// Prime post objects + meta + terms in one pass. The post-object cache
				// matters: ACF's get_field() resolves each character's WP_Post, so
				// without it every loop iteration fires get_post() (an N+1 of
				// "SELECT * FROM wp_posts WHERE ID = N"). meta/terms priming alone
				// (its own object cache) does not cover that.
				_prime_post_caches( $prime_ids, true, true );
			}

			foreach ( $results as $row ) {
				$char_id    = (int) $row['ID'];
				$show_group = get_field( 'lezchars_show_group', $char_id );

				if ( ! is_array( $show_group ) || ! $this->check_character_appeared_in_year( $show_group, $year ) ) {
					continue;
				}

				// Busiest actor: tally every performer of this character.
				$actor_ids = get_field( 'lezchars_actor', $char_id ) ?: array();
				if ( ! is_array( $actor_ids ) ) {
					$actor_ids = array( $actor_ids );
				}
				foreach ( $actor_ids as $actor ) {
					$actor_id = is_object( $actor ) ? (int) $actor->ID : (int) $actor;
					// Unknown_Actor::ACTOR_ID (post 14080) is the "Unknown"
					// placeholder actor — a catch-all for roles with no
					// confirmed performer — so it must never be counted as a
					// real actor's workload / win "busiest actor".
					if ( $actor_id <= 0 || Unknown_Actor::ACTOR_ID === $actor_id ) {
						continue;
					}
					$stats['actor_counts'][ $actor_id ] = ( $stats['actor_counts'][ $actor_id ] ?? 0 ) + 1;
				}

				// Panel extras: multi-show / debuting / non-binary.
				$facts = Character_Facts::for_year( $show_group, $year );
				if ( $facts['shows_this_year'] >= 2 ) {
					++$stats['extras']['multi_show'];
				}
				if ( $facts['debuted'] ) {
					++$stats['extras']['debuting'];
				}

				$genders = get_the_terms( $char_id, 'lez_gender' );
				if ( is_array( $genders ) ) {
					foreach ( $genders as $gender ) {
						if ( 'non-binary' === $gender->slug ) {
							++$stats['extras']['non_binary'];
							break;
						}
					}
				}
			}

			lwtv_plugin()->set_transient( $cache_key, $stats, DAY_IN_SECONDS );
		} catch ( \Exception $e ) {
			lwtv_plugin()->debug_log( 'this-year', 'Error in Characters::get_overview_character_stats: ' . $e->getMessage() );
			return $empty;
		}

		return $stats;
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
			if ( ! empty( $character['last_death'] ) ) {
				// if last_death STARTS with the year, add to the dead count
				if ( str_starts_with( $character['last_death'], (string) $year ) ) {
					lwtv_plugin()->debug_log( 'this-year', $character['name'] . ': Character is dead with last death: ' . $character['last_death'] );
					++$dead_count;
				}
			} elseif ( ! empty( $character['death_years'] ) ) {
				// Double check because last death isn't always set on older characters
				foreach ( $character['death_years'] as $death_year ) {
					if ( (int) $death_year === $year ) {
						lwtv_plugin()->debug_log( 'this-year', $character['name'] . ': Character is dead with last death: ' . $character['last_death'] );
						++$dead_count;
					}
				}
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

		// Resolve every slug -> id in one query, then hand the map to enhance().
		$slug_ids = $this->map_slugs_to_ids( wp_list_pluck( $characters, 'slug' ), 'post_type_characters' );

		// Get show data for all characters
		$characters_with_shows = $this->enhance_characters_with_shows( $characters, $slug_ids, $year );

		// Cache the enhanced results for 1 day
		lwtv_plugin()->set_transient( $cache_key, $characters_with_shows, DAY_IN_SECONDS );

		return $characters_with_shows;
	}

	/**
	 * Map post slugs to published post IDs in one query (replaces per-row
	 * get_page_by_path()). Returns [ slug => (int) id ] for slugs that resolve.
	 *
	 * @param array  $slugs     Post slugs.
	 * @param string $post_type Post type to resolve within.
	 * @return array
	 */
	private function map_slugs_to_ids( array $slugs, string $post_type ): array {
		$slugs = array_values( array_unique( array_filter( array_map( 'strval', $slugs ) ) ) );
		if ( empty( $slugs ) ) {
			return array();
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );

		// phpcs:disable
		$query = $wpdb->prepare(
			"SELECT post_name, ID FROM {$wpdb->posts}
			WHERE post_name IN ($placeholders)
			AND post_type = %s
			AND post_status = 'publish'",
			array_merge( $slugs, array( $post_type ) )
		);
		// phpcs:enable

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above
		$rows = $wpdb->get_results( $query, ARRAY_A );

		$map = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$map[ $row['post_name'] ] = (int) $row['ID'];
			}
		}

		return $map;
	}

	/**
	 * Enhance characters array with show information
	 *
	 * @param array $characters Array of character data
	 * @param array $slug_ids Map of character slug => post ID
	 * @param int   $year The year to filter shows by
	 * @return array Enhanced character data with shows
	 */
	private function enhance_characters_with_shows( array $characters, array $slug_ids, int $year ): array {
		// Get show group data for each character using ACF API (handles ACF repeater format)
		$show_ids            = array();
		$character_show_data = array();

		$character_ids = array_values( array_filter( $slug_ids ) );
		if ( ! empty( $character_ids ) ) {
			update_meta_cache( 'post', $character_ids );
		}

		foreach ( array_filter( $character_ids ) as $character_id ) {
			$character_id    = (int) $character_id;
			$show_group_data = get_field( 'lezchars_show_group', $character_id );

			if ( ! is_array( $show_group_data ) ) {
				continue;
			}

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
			$character_id = $slug_ids[ $character['slug'] ] ?? 0;
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

		_prime_post_caches( array_map( 'intval', $show_ids ), false, false );

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
