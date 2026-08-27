<?php
/**
 * Description: REST-API: Bury Your Queers
 *
 * The code that runs the Bury Your Queers API service
 * - Last Death - "It has been X days since the last WLW Death"
 * - On This Day - "On this day, X died"
 *
 */

namespace LWTV\Rest_API;

use LWTV\Queeries\Taxonomy_Optimized as Queery_Taxonomy;
use LWTV\CPTs\Characters as CPT_Characters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BYQ {

	/**
	 * Cache duration for death data (24 hours)
	 */
	const DEATH_DATA_CACHE_DURATION = DAY_IN_SECONDS;

	/**
	 * Cache duration for API responses (1 hour)
	 */
	const API_RESPONSE_CACHE_DURATION = HOUR_IN_SECONDS;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
	}

	/**
	 * Rest API init
	 *
	 * Creates callbacks
	 *   - /lwtv/v1/last-death/
	 *   - /lwtv/v1/on-this-day/
	 *   - /lwtv/v1/when-died/
	 */
	public function rest_api_init() {

		register_rest_route(
			'lwtv/v1',
			'/last-death',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'last_death_rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'lwtv/v1',
			'/on-this-day/',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'on_this_day_rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'lwtv/v1',
			'/on-this-day/(?P<date>[\d]{2}-[\d]{2})',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'on_this_day_rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'lwtv/v1',
			'/when-died/',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'when_died_rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'lwtv/v1',
			'/when-died/(?P<name>[a-zA-Z0-9-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'when_died_rest_api_callback' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Rest API Callback for Last Death
	 */
	public function last_death_rest_api_callback() {
		$response = $this->last_death();
		return $response;
	}

	/**
	 * Rest API Callback for On This Day
	 *v1
	 */
	public function on_this_day_rest_api_callback( $data ) {
		$params   = $data->get_params();
		$this_day = ( isset( $params['date'] ) && '' !== $params['date'] ) ? $params['date'] : 'today';
		$response = $this->on_this_day( $this_day, 'json' );
		return $response;
	}

	/**
	 * Rest API Callback for When someone Died
	 */
	public function when_died_rest_api_callback( $data ) {
		$params   = $data->get_params();
		$name     = ( isset( $params['name'] ) && '' !== $params['name'] ) ? $params['name'] : 'no-name';
		$response = $this->when_died( $name );
		return $response;
	}

	/**
	 * Generate the massive list of all the dead
	 *
	 * This is a separate function because otherwise I use the same call twice
	 * and that's stupid
	 */
	public function list_of_dead_characters( $dead_chars_loop = null ) {
		global $wp_current_filter;

		// Prevent running during actor save operations - actors don't affect death data
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			lwtv_plugin()->debug_log( 'buryqueers', 'Skipping list_of_dead_characters during autosave operation' );
			return array();
		}

		// Establish the cache key and check the cache
		$cache_key   = 'byq_death_list_' . $this->get_data_version_hash();
		$cached_list = lwtv_plugin()->get_transient( $cache_key );

		// Detect if we're in a character save context
		$is_character_save = false;
		if ( is_array( $wp_current_filter ) ) {
			foreach ( $wp_current_filter as $hook ) {
				lwtv_plugin()->debug_log( 'buryqueers', 'hook: ' . $hook );
				// If we're in a save_post hook that's NOT for characters, skip it
				if ( strpos( $hook, 'save_post' ) === 0 && 'save_post_post_type_characters' !== $hook ) {
					lwtv_plugin()->debug_log( 'buryqueers', 'Skipping list_of_dead_characters during non-character save operation (hook: ' . $hook . ')' );
					return array();
				}
				// If we're in a character save hook, mark it
				if ( 'save_post_post_type_characters' === $hook ) {
					$is_character_save = true;
				}
			}
		}

		// On page loads (not character saves), check cache first
		if ( ! $is_character_save ) {
			if ( false !== $cached_list ) {
				lwtv_plugin()->debug_log( 'buryqueers', 'Returning cached death list on page load' );
				return $cached_list;
			}
		}

		$death_list_array = $this->generate_death_list_array( $dead_chars_loop, $cache_key );
		return $death_list_array;
	}

	/**
	 * Generate the death list array
	 *
	 * @param object $dead_chars_loop The loop of dead characters.
	 * @return array The death list array.
	 */
	public function generate_death_list_array( $dead_chars_loop = null, $cache_key = null ): array {

		if ( null === $cache_key ) {
			lwtv_plugin()->debug_log( 'buryqueers', 'Cache key is null, returning empty array' );
			return array();
		}

		// If no loop provided, get all dead characters efficiently
		if ( null === $dead_chars_loop || ! is_object( $dead_chars_loop ) || ! $dead_chars_loop->have_posts() ) {
			lwtv_plugin()->debug_log( 'buryqueers', 'No loop provided, getting all dead characters efficiently' );
			$dead_chars_loop = ( new Queery_Taxonomy() )->get_posts_for_terms( CPT_Characters::SLUG, 'lez_cliches', 'dead' );
		}

		$death_list_array = array();

		if ( $dead_chars_loop->have_posts() ) {
			// Pre-fetch all meta data in one query to reduce database calls
			$character_ids = wp_list_pluck( $dead_chars_loop->posts, 'ID' );
			lwtv_plugin()->debug_log( 'buryqueers', 'Character IDs count: ' . count( $character_ids ) );

			$meta_data = $this->get_bulk_death_meta_data( $character_ids );
			lwtv_plugin()->debug_log( 'buryqueers', 'Meta data count: ' . count( $meta_data ) );

			// Build the death list
			$processed_count = 0;
			$skipped_count   = 0;
			foreach ( $dead_chars_loop->posts as $dead_char ) {
				$character_id = $dead_char->ID;

				$died_date = $meta_data[ $character_id ]['lezchars_death_year'] ?? '';

				// Fallback: if no ACF repeater date rows exist, try lezchars_last_death.
				if ( empty( $died_date ) ) {
					$last_death = $meta_data[ $character_id ]['lezchars_last_death'] ?? '';
					if ( ! empty( $last_death ) ) {
						$died_date = array( $last_death );
						lwtv_plugin()->debug_log( 'buryqueers', "Using lezchars_last_death fallback for character $character_id ({$dead_char->post_title}): $last_death" );
					}
				}
				$show_data = $meta_data[ $character_id ]['lezchars_show_group'] ?? array();

				if ( empty( $died_date ) ) {
					++$skipped_count;
					lwtv_plugin()->debug_log( 'buryqueers', "Skipped character $character_id ({$dead_char->post_title}) - no death date. Meta keys: " . implode( ', ', array_keys( $meta_data[ $character_id ] ?? array() ) ) );
					continue; // Skip characters without death dates
				}

				++$processed_count;

				$died_date_array = array();
				if ( ! is_array( $died_date ) ) {
					$died_date = array( $died_date );
				}

				// For each death date, create an item in an array with the unix timestamp
				// We default to 8pm for prime-time reasons.
				foreach ( $died_date as $date ) {
					if ( empty( $date ) ) {
						continue;
					}
					$date_parse = date_parse_from_format( 'Y-m-d', $date );
					if ( $date_parse['year'] && $date_parse['month'] && $date_parse['day'] ) {
						$died_date_array[] = mktime( 20, $date_parse['minute'], $date_parse['second'], $date_parse['month'], $date_parse['day'], $date_parse['year'] );
					}
				}

				if ( empty( $died_date_array ) ) {
					continue; // Skip if no valid dates
				}

				// Grab the highest date (aka most recent)
				$died = max( $died_date_array );

				// Get the post slug efficiently
				$post_slug = $dead_char->post_name;

				// Process shows efficiently
				$show_ids = array();
				if ( is_array( $show_data ) ) {
					foreach ( $show_data as $show ) {
						if ( isset( $show['show'] ) ) {
							$show_id = is_array( $show['show'] ) ? $show['show'][0] : $show['show'];
							if ( $show_id ) {
								$show_ids[] = $show_id;
							}
						}
					}
				}

				// Create character data array
				$character_data = array(
					'id'    => $character_id,
					'slug'  => $post_slug,
					'name'  => $dead_char->post_title,
					'url'   => get_permalink( $character_id ),
					'shows' => $show_ids,
					'died'  => $died,
					'date'  => $died_date,
				);

				// Use Unix timestamp as key for proper chronological ordering
				$death_timestamp_key = $died;
				lwtv_plugin()->debug_log( 'buryqueers', "Processing character {$character_id} ({$dead_char->post_title}) with death timestamp key: {$death_timestamp_key} (" . gmdate( 'Y-m-d', $died ) . ')' );

				// Handle timestamp collisions by adding small increments
				$increment     = 0;
				$max_increment = 1000; // Maximum 1000 characters per timestamp (more than enough for any realistic scenario)
				while ( isset( $death_list_array[ $death_timestamp_key ] ) && $increment < $max_increment ) {
					++$increment;
					$death_timestamp_key = $died + $increment;
					lwtv_plugin()->debug_log( 'buryqueers', "Timestamp collision detected, using incremented key: {$death_timestamp_key} (+{$increment} seconds)" );
				}

				// If we hit the maximum increment limit, use a large increment to maintain integer keys
				if ( $increment >= $max_increment ) {
					$death_timestamp_key = $died + 10000 + $character_id; // Use large increment + character ID to maintain integer keys
					lwtv_plugin()->debug_log( 'buryqueers', "WARNING: Maximum increment limit reached for character {$character_id} ({$dead_char->post_title}). Using large increment key: {$death_timestamp_key}" );
				}

				// Store character with unique timestamp key
				$death_list_array[ $death_timestamp_key ] = $character_data;
				lwtv_plugin()->debug_log( 'buryqueers', "Added character with unique timestamp key: {$death_timestamp_key}" );
			}

			// Sort array by timestamp keys to ensure chronological order
			lwtv_plugin()->debug_log( 'buryqueers', 'Array keys before sorting: ' . implode( ', ', array_keys( $death_list_array ) ) );
			ksort( $death_list_array );
			lwtv_plugin()->debug_log( 'buryqueers', 'Array keys after sorting: ' . implode( ', ', array_keys( $death_list_array ) ) );

			lwtv_plugin()->debug_log( 'buryqueers', 'Total characters: ' . count( $dead_chars_loop->posts ) . ', Processed: ' . $processed_count . ', Skipped: ' . $skipped_count . ', Final array count: ' . count( $death_list_array ) );
		}

		// Cache the generated list
		lwtv_plugin()->set_transient( $cache_key, $death_list_array, self::DEATH_DATA_CACHE_DURATION );
		lwtv_plugin()->debug_log( 'buryqueers', 'Cached death list with key: ' . $cache_key );

		return $death_list_array;
	}

	/**
	 * Generate List of Dead
	 *
	 * @return array with last dead character data
	 */
	public function last_death() {
		$return = '';

		$cache_key     = 'byq_last_death_' . $this->get_data_version_hash();
		$cached_result = lwtv_plugin()->get_transient( $cache_key );
		if ( false !== $cached_result && ! is_wp_error( $cached_result ) ) {
			// 83580 is Frankie, the first dead character added — if she appears as "last death"
			// the death_list cache has stale/wrong data; purge both caches and regenerate.
			if ( 83580 === (int) $cached_result['id'] ) {
				lwtv_plugin()->debug_log( 'buryqueers', 'Stale Frankie data detected in last_death cache — purging and regenerating' );
				lwtv_plugin()->delete_transient( $cache_key );
				lwtv_plugin()->delete_transient( 'byq_death_list_' . $this->get_data_version_hash() );
			} else {
				lwtv_plugin()->debug_log( 'buryqueers', 'Returning cached last death: ' . wp_json_encode( $cached_result ) );
				return $cached_result;
			}
		} else {
			lwtv_plugin()->debug_log( 'buryqueers', 'No cached last death found, generating new one' );
		}

		// Get all dead characters and find the most recent death
		$death_list_array = $this->list_of_dead_characters();

		// Extract the last death (most recent timestamp)
		if ( ! empty( $death_list_array ) ) {
			// Debug: Log the array structure
			$array_keys     = array_keys( $death_list_array );
			$formatted_keys = array_map(
				function ( $key ) {
					// Ensure we have an integer timestamp for gmdate()
					$timestamp = is_numeric( $key ) ? intval( $key ) : 0;
					return $key . ' (' . gmdate( 'Y-m-d', $timestamp ) . ')';
				},
				$array_keys
			);
			lwtv_plugin()->debug_log( 'buryqueers', 'Death list array keys: ' . implode( ', ', $formatted_keys ) );
			lwtv_plugin()->debug_log( 'buryqueers', 'Death list array count: ' . count( $death_list_array ) );

			// Get the last (most recent) death timestamp key
			$last_death_timestamp = array_key_last( $death_list_array );
			$timestamp_for_date   = is_numeric( $last_death_timestamp ) ? intval( $last_death_timestamp ) : 0;
			lwtv_plugin()->debug_log( 'buryqueers', 'Last death timestamp key: ' . $last_death_timestamp . ' (' . gmdate( 'Y-m-d', $timestamp_for_date ) . ')' );
			$last_death_data = $death_list_array[ $last_death_timestamp ];
			lwtv_plugin()->debug_log( 'buryqueers', 'Last death data type: ' . gettype( $last_death_data ) );
			lwtv_plugin()->debug_log( 'buryqueers', 'Last death data: ' . wp_json_encode( $last_death_data ) );

			// Each timestamp key now contains a single character (no more arrays)
			if ( is_array( $last_death_data ) && isset( $last_death_data['died'] ) ) {
				$last_death = $last_death_data;
				lwtv_plugin()->debug_log( 'buryqueers', 'Using character for this timestamp' );
			} else {
				lwtv_plugin()->debug_log( 'buryqueers', 'Invalid data structure for last death' );
				$last_death = null;
			}

			// Calculate the difference between then and now
			if ( $last_death && isset( $last_death['died'] ) && ! is_null( $last_death['died'] ) ) {
				$diff                = abs( time() - $last_death['died'] );
				$last_death['since'] = $diff;
				$return              = $last_death;
				lwtv_plugin()->debug_log( 'buryqueers', 'Successfully found last death: ' . $last_death['name'] );

				// Cache the result
				lwtv_plugin()->set_transient( $cache_key, $last_death, self::API_RESPONSE_CACHE_DURATION );
			} else {
				lwtv_plugin()->debug_log( 'buryqueers', 'Last death has no valid died timestamp' );
			}
		} else {
			lwtv_plugin()->debug_log( 'buryqueers', 'Death list array is empty' );
		}

		return $return;
	}

	/**
	 * Generate On This Day
	 *
	 * @return array with character data
	 */
	public function on_this_day( $this_day = 'today', $type = 'json' ) {
		// Default to today
		if ( 'today' === $this_day ) {
			// Create the date with regards to timezones
			$timestamp = time();
			$dt        = new \DateTime( 'now', new \DateTimeZone( LWTV_TIMEZONE ) ); //first argument "must" be a string
			$dt->setTimestamp( $timestamp ); //adjust the object to correct timestamp
			$this_day = $dt->format( 'm-d' );
		}

		// Default to JSON (i.e. what the plugin uses)
		$valid_types = array( 'json', 'socialmedia' );
		$type        = ( ! in_array( $type, $valid_types, true ) ) ? 'json' : $type;

		// Generate cache key
		$cache_key = 'byq_on_this_day_' . md5( $this_day . '_' . $type ) . '_' . $this->get_data_version_hash();

		// Try to get from cache first
		$cached_result = lwtv_plugin()->get_transient( $cache_key );
		if ( false !== $cached_result ) {
			return $cached_result;
		}

		// Use optimized query to get characters who died on this day
		$died_today_array = $this->get_characters_died_on_date( $this_day, $type );

		// Cache the result
		lwtv_plugin()->set_transient( $cache_key, $died_today_array, self::API_RESPONSE_CACHE_DURATION );

		return $died_today_array;
	}

	/**
	 * Get characters who died on a specific date
	 *
	 * @param string $date Date in MM-DD format
	 * @param string $type Response type (json or tweet)
	 * @return array
	 */
	private function get_characters_died_on_date( $date, $type ) {
		$died_today_array = array();

		// Parse the MM-DD date
		$date_parts = explode( '-', $date );
		if ( count( $date_parts ) !== 2 ) {
			return $died_today_array;
		}

		$month = intval( $date_parts[0] );
		$day   = intval( $date_parts[1] );

		// Build date patterns to match against stored dates
		// We need to match any year with this month-day combination.
		// ACF's date_picker stores raw postmeta as Ymd; legacy pre-migration
		// rows that haven't been re-saved may still be in Y-m-d format, so
		// the separator between each part is optional.
		$date_patterns = array();
		$current_year  = gmdate( 'Y' );
		for ( $year = 1950; $year <= $current_year; $year++ ) {
			$date_patterns[] = sprintf( '^%04d-?%02d-?%02d$', $year, $month, $day );
		}

		// Use REGEXP to match any of our date patterns
		global $wpdb;
		$date_regex = implode( '|', $date_patterns );

		// Bound rather than inlined, for two reasons beyond tidiness.
		//
		// The literal it replaced was 'lezchars_death_year_%_date'. Every '_' in
		// that is a single-character LIKE wildcard, so it also matched keys like
		// 'lezcharsXdeath_year_1_date'. Harmless with today's data, but not what
		// it says. esc_like() makes them literal.
		//
		// It also put a bare '%' inside a prepare() string, which is what
		// WordPress.DB.PreparedSQLPlaceholders.LikeWildcardsInQuery exists to
		// catch -- the version this replaced had to phpcs:disable that sniff.
		// Binding the pattern removes the cause rather than the warning.
		$meta_key_like = $wpdb->esc_like( 'lezchars_death_year_' ) . '%' . $wpdb->esc_like( '_date' );

		$query = $wpdb->prepare(
			"SELECT DISTINCT p.ID, p.post_title, p.post_name
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
			INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
			WHERE p.post_type = %s
			AND p.post_status = 'publish'
			AND pm.meta_key LIKE %s
			AND pm.meta_value REGEXP %s
			AND tt.taxonomy = 'lez_cliches'
			AND t.slug = 'dead'
			ORDER BY p.post_title",
			CPT_Characters::SLUG,
			$meta_key_like,
			$date_regex
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Complex regex pattern for date matching
		$results = $wpdb->get_results( $query );

		if ( ! empty( $results ) ) {
			$characters_died_today = array();

			foreach ( $results as $row ) {
				// Get the actual death year for this character
				$death_rows  = get_field( 'lezchars_death_year', $row->ID );
				$death_years = is_array( $death_rows ) ? array_filter( array_column( $death_rows, 'date' ) ) : array();
				$death_year  = '';

				if ( ! empty( $death_years ) ) {
					foreach ( $death_years as $death_date ) {
						if ( ! empty( $death_date ) ) {
							$date_parse = date_parse_from_format( 'Y-m-d', $death_date );
							if ( $date_parse['month'] === $month && $date_parse['day'] === $day ) {
								$death_year = $date_parse['year'];
								break;
							}
						}
					}
				} else {
					$date_parse = date_parse_from_format( 'Y-m-d', '' );
					if ( $date_parse['month'] === $month && $date_parse['day'] === $day ) {
						$death_year = $date_parse['year'];
					}
				}

				if ( $death_year ) {
					$characters_died_today[] = array(
						'id'        => $row->ID,
						'slug'      => $row->post_name,
						'name'      => $row->post_title,
						'url'       => get_permalink( $row->ID ),
						'died'      => $death_year,
						'timestamp' => mktime( 20, 0, 0, $month, $day, $death_year ),
					);
				}
			}

			switch ( $type ) {
				case 'socialmedia':
					$the_dead_array = array();
					foreach ( $characters_died_today as $the_dead ) {
						$data             = $the_dead['name'] . ' (' . $the_dead['died'] . ') -- ' . $the_dead['url'];
						$the_dead_array[] = $data;
					}
					if ( empty( $the_dead_array ) ) {
						$content = 'NONE';
					} else {
						$the_dead_string = implode( '\n', $the_dead_array );
						$count_the_dead  = count( $the_dead_array );
						// translators: %s is the number of characters
						$characters = sprintf( _n( '%s character', '%s characters', $count_the_dead ), $count_the_dead );
						$content    = 'On ' . $date . ', the following ' . $characters . ' died: \n' . $the_dead_string;
					}
					$died_today_array['content'] = $content;
					break;
				case 'json':
					foreach ( $characters_died_today as $the_dead ) {
						$died_today_array[ $the_dead['slug'] ] = array(
							'id'   => $the_dead['id'],
							'name' => $the_dead['name'],
							'url'  => $the_dead['url'],
							'died' => $the_dead['died'],
						);
					}
					if ( empty( $died_today_array ) ) {
						$died_today_array['none'] = array(
							'id'   => 0,
							'name' => 'No One',
							'url'  => site_url( '/cliche/dead/' ),
							'died' => 'n/a',
						);
					}
					break;
				default:
					$died_today_array = new \WP_Error( 'invalid', 'An unexpected error has occurred.' );
			}
		} elseif ( 'json' === $type ) {
			// No results found
			$died_today_array['none'] = array(
				'id'   => 0,
				'name' => 'No One',
				'url'  => site_url( '/cliche/dead/' ),
				'died' => 'n/a',
			);
		} else {
			$died_today_array['content'] = 'NONE';
		}

		return $died_today_array;
	}

	/**
	 * Get bulk meta data for multiple characters
	 *
	 * @param array $character_ids Array of character IDs
	 * @return array Meta data organized by character ID
	 */
	private function get_bulk_death_meta_data( $character_ids ) {
		if ( empty( $character_ids ) ) {
			return array();
		}

		global $wpdb;

		// Sanitize IDs to ensure they're integers
		$character_ids = array_map( 'intval', $character_ids );

		if ( empty( $character_ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $character_ids ), '%d' ) );

		// Bound, not inlined. Every '_' in these keys is a literal, but an
		// unescaped one is a single-character LIKE wildcard, and a bare '%'
		// inside a prepare() string is what LikeWildcardsInQuery flags. Binding
		// them removes the cause instead of silencing the sniff.
		$death_date_like = $wpdb->esc_like( 'lezchars_death_year_' ) . '%' . $wpdb->esc_like( '_date' );
		$show_group_like = $wpdb->esc_like( 'lezchars_show_group_' ) . '%' . $wpdb->esc_like( '_show' );

		// Query ACF repeater subfields — lezchars_death_year and lezchars_show_group now
		// store a count integer in their parent key; actual values live in indexed subfields.
		//
		// Argument order matters: the IN placeholders come first in the SQL, so
		// the two LIKE patterns are appended after the character IDs.
		//
		// ReplacementsWrongNumber is suppressed for the same reason
		// UnfinishedPrepare already is: the placeholder count is dynamic
		// ({$placeholders}), so the sniff compares the 2 literal %s it can see
		// against the 1 replacement expression it can count and reports a
		// mismatch that isn't there. Neither the spread form nor a single array
		// avoids it -- both count as one. Verified by hand instead: IN(...) takes
		// count($character_ids) %d, then the two %s take the two LIKE patterns.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$query = $wpdb->prepare(
			"SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
			WHERE post_id IN ({$placeholders})
			AND (
				meta_key LIKE %s
				OR meta_key LIKE %s
				OR meta_key = 'lezchars_last_death'
			)",
			array_merge( $character_ids, array( $death_date_like, $show_group_like ) )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- query built with prepare() above
		$results = $wpdb->get_results( $query );

		lwtv_plugin()->debug_log( 'buryqueers', 'Bulk meta query results count: ' . count( $results ) );

		$meta_data = array();
		foreach ( $results as $row ) {
			$post_id = (int) $row->post_id;
			$value   = maybe_unserialize( $row->meta_value );

			if ( preg_match( '/^lezchars_death_year_\d+_date$/', $row->meta_key ) ) {
				$meta_data[ $post_id ]['lezchars_death_year'][] = $value;
			} elseif ( preg_match( '/^lezchars_show_group_\d+_show$/', $row->meta_key ) ) {
				$meta_data[ $post_id ]['lezchars_show_group'][] = array( 'show' => $value );
			} elseif ( 'lezchars_last_death' === $row->meta_key ) {
				$meta_data[ $post_id ]['lezchars_last_death'] = $value;
			}
		}

		lwtv_plugin()->debug_log( 'buryqueers', 'Processed meta data count: ' . count( $meta_data ) );

		return $meta_data;
	}

	/**
	 * Get data version hash for cache invalidation
	 *
	 * @return string Hash based on last modification time of character data
	 */
	private function get_data_version_hash() {
		$cache_key   = 'byq_data_version_hash';
		$cached_hash = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_hash ) {
			return $cached_hash;
		}

		// Get the most recent modification time of any character post
		global $wpdb;
		$last_modified = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(post_modified) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
				CPT_Characters::SLUG
			)
		);

		$hash = md5( $last_modified );

		// Cache for 1 hour
		lwtv_plugin()->set_transient( $cache_key, $hash, HOUR_IN_SECONDS );

		return $hash;
	}

	/**
	 * Generate when a character died
	 *
	 * If no name is passed, kick back last death
	 *
	 * @return array with character data
	 */
	public function when_died( $name = 'no-name' ) {
		// Normalize input
		$name = str_replace( '-', ' ', $name );

		// Handle special case
		if ( 'no-name' === $name ) {
			return array(
				'id'    => 0,
				'name'  => 'No Name',
				'shows' => 'None',
				'url'   => 'None',
				'died'  => 'None',
			);
		}

		// Generate cache key with data version hash for proper invalidation
		$cache_key = 'character_death_' . md5( $name ) . '_' . $this->get_data_version_hash();

		// Try to get from cache first
		$cached_result = lwtv_plugin()->get_transient( $cache_key );
		if ( false !== $cached_result ) {
			return $cached_result;
		}

		// Use optimized single query with LIKE for better performance
		$character = $this->find_character_by_name( $name );

		// Default result
		$result = array(
			'id'    => 0,
			'name'  => 'No Name',
			'shows' => 'None',
			'url'   => 'None',
			'died'  => 'None',
		);

		if ( $character ) {
			$character_id = $character['ID'];

			// Process death information
			$died = 'alive';
			if ( ! empty( $character['death_years'] ) ) {
				if ( is_array( $character['death_years'] ) ) {
					$died = implode( ', ', array_filter( $character['death_years'] ) );
				} else {
					$died = $character['death_years'];
				}
			}

			// Process show information efficiently
			$shows = '';
			if ( ! empty( $character['show_titles'] ) ) {
				$shows = implode( ', ', array_filter( $character['show_titles'] ) );
			}

			$result = array(
				'id'    => $character_id,
				'name'  => $character['post_title'],
				'shows' => ! empty( $shows ) ? $shows : 'None',
				'url'   => get_permalink( $character_id ),
				'died'  => $died,
			);
		}

		// Cache the result
		lwtv_plugin()->set_transient( $cache_key, $result, self::API_RESPONSE_CACHE_DURATION );

		return $result;
	}

	/**
	 * Find character by name using optimized single query
	 *
	 * @param string $name Character name to search for
	 * @return array|null Character data or null if not found
	 */
	private function find_character_by_name( $name ) {
		global $wpdb;

		// Use direct SQL for better performance than WP_Query
		$query = $wpdb->prepare(
			"SELECT DISTINCT p.ID, p.post_title, p.post_name
			FROM {$wpdb->posts} p
			WHERE p.post_type = %s
			AND p.post_status = 'publish'
			AND (p.post_title = %s OR p.post_title LIKE %s)
			ORDER BY CASE WHEN p.post_title = %s THEN 1 ELSE 2 END
			LIMIT 1",
			CPT_Characters::SLUG,
			$name,
			'%' . $wpdb->esc_like( $name ) . '%',
			$name
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Complex query with proper escaping
		$result = $wpdb->get_row( $query, ARRAY_A );

		if ( ! $result ) {
			return null;
		}

		$character_id = (int) $result['ID'];

		// Fetch show titles via ACF.
		$show_titles = array();
		$shows_data  = get_field( 'lezchars_show_group', $character_id );
		if ( is_array( $shows_data ) ) {
			$show_ids = array();
			foreach ( $shows_data as $show ) {
				if ( isset( $show['show'] ) ) {
					$show_id = is_array( $show['show'] ) ? $show['show'][0] : $show['show'];
					if ( $show_id ) {
						$show_ids[] = intval( $show_id );
					}
				}
			}

			if ( ! empty( $show_ids ) ) {
				$show_ids_string = implode( ',', array_map( 'intval', $show_ids ) );
				$titles_query    = "SELECT ID, post_title FROM {$wpdb->posts} WHERE ID IN ($show_ids_string) AND post_status = 'publish'";

				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- IDs are sanitized integers
				$show_results = $wpdb->get_results( $titles_query );
				foreach ( $show_results as $show ) {
					$show_titles[] = $show->post_title;
				}
			}
		}

		// Fetch death dates via ACF repeater.
		$death_rows  = get_field( 'lezchars_death_year', $character_id );
		$death_years = is_array( $death_rows ) ? array_filter( array_column( $death_rows, 'date' ) ) : array();

		return array(
			'ID'          => $result['ID'],
			'post_title'  => $result['post_title'],
			'post_name'   => $result['post_name'],
			'death_years' => $death_years,
			'show_titles' => $show_titles,
		);
	}

	/**
	 * Check if character exists in cached death list with same death date
	 *
	 * @param int $character_id Character ID to check
	 * @return bool True if character exists in cached list with same date, false otherwise
	 */
	public function is_character_in_cached_list( $character_id ) {
		$cache_key   = 'byq_death_list_' . $this->get_data_version_hash();
		$cached_list = lwtv_plugin()->get_transient( $cache_key );

		if ( false === $cached_list || empty( $cached_list ) || ! is_array( $cached_list ) ) {
			return false;
		}

		// Get current character death date
		$death_rows           = get_field( 'lezchars_death_year', $character_id );
		$character_death_date = is_array( $death_rows ) ? array_filter( array_column( $death_rows, 'date' ) ) : array();

		if ( empty( $character_death_date ) ) {
			$last = get_post_meta( $character_id, 'lezchars_last_death', true );
			if ( ! empty( $last ) ) {
				$character_death_date = array( $last );
			}
		}

		if ( empty( $character_death_date ) ) {
			return false;
		}

		// Check if character exists in cached list with matching death date
		foreach ( $cached_list as $death_data ) {
			if ( isset( $death_data['id'] ) && (int) $death_data['id'] === (int) $character_id ) {
				$cached_dates = $death_data['date'] ?? array();
				if ( ! is_array( $cached_dates ) ) {
					$cached_dates = array( $cached_dates );
				}

				// Compare death dates - check if any dates match
				foreach ( $character_death_date as $char_date ) {
					if ( in_array( $char_date, $cached_dates, true ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Invalidate the death list cache
	 *
	 * @return void
	 */
	public function invalidate_death_list_cache() {
		/*
		 * Deletes go through lwtv_plugin()->delete_transient(), not core's, for
		 * the same reason the writes do: _Components\Transients is the seam for
		 * swapping the transient store. Today the wrapper is a passthrough and
		 * the two are identical -- but every key below is *written* through the
		 * wrapper, so busting them through core would, after a swap, write to the
		 * new store and delete from the old. That is cache you cannot clear, on
		 * the endpoint that feeds Bury Your Queers. Keep both sides on the same
		 * side of the seam.
		 */

		// Get the current hash BEFORE we delete it
		$current_hash = $this->get_data_version_hash();

		// Delete the hash transient first so next request generates fresh hash
		lwtv_plugin()->delete_transient( 'byq_data_version_hash' );

		// Delete all related caches using the OLD hash
		$death_list_key = 'byq_death_list_' . $current_hash;
		$last_death_key = 'byq_last_death_' . $current_hash;

		lwtv_plugin()->delete_transient( $death_list_key );
		lwtv_plugin()->delete_transient( $last_death_key );

		// Also delete on_this_day caches for today (they share the hash)
		$today          = gmdate( 'm-d' );
		$otd_json_key   = 'byq_on_this_day_' . md5( $today . '_json' ) . '_' . $current_hash;
		$otd_social_key = 'byq_on_this_day_' . md5( $today . '_socialmedia' ) . '_' . $current_hash;

		lwtv_plugin()->delete_transient( $otd_json_key );
		lwtv_plugin()->delete_transient( $otd_social_key );

		lwtv_plugin()->debug_log( 'buryqueers', 'Invalidated BYQ caches: ' . $death_list_key . ', ' . $last_death_key . ', byq_data_version_hash, and on_this_day caches' );
	}

	/**
	 * Force a complete refresh of all BYQ caches
	 *
	 * This should be called during daily cron to ensure fresh data each day.
	 * It invalidates all caches and pre-warms the death list.
	 *
	 * @return bool True if refresh was successful
	 */
	public function daily_cache_refresh(): bool {
		lwtv_plugin()->debug_log( 'buryqueers', 'Starting daily BYQ cache refresh' );

		// First, invalidate all existing caches
		$this->invalidate_death_list_cache();

		// Force regenerate the death list by calling list_of_dead_characters
		// which will query fresh data and cache it
		$death_list = $this->generate_death_list_array( null, 'byq_death_list_' . $this->get_data_version_hash() );

		if ( empty( $death_list ) ) {
			lwtv_plugin()->debug_log( 'buryqueers', 'Daily refresh: Death list is empty - this may indicate a problem' );
			return false;
		}

		// Pre-warm the last_death cache
		$last_death = $this->last_death();

		if ( empty( $last_death ) ) {
			lwtv_plugin()->debug_log( 'buryqueers', 'Daily refresh: Last death is empty - this may indicate a problem' );
			return false;
		}

		lwtv_plugin()->debug_log( 'buryqueers', 'Daily BYQ cache refresh completed. Last death: ' . $last_death['name'] . ' (ID: ' . $last_death['id'] . ')' );
		return true;
	}
}
