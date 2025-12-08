<?php
/**
 * Name: Of the Day
 *
 */

namespace LWTV\_Components;

use LWTV\Plugins\Cache;
use LWTV\Queeries\Post_Meta;
use LWTV\Rest_API\BYQ;
use LWTV\CPTs\Actors as CPT_Actors;

class Of_The_Day implements Component, Templater {

	/**
	 * Initialize
	 */
	public function init() {
		add_filter( 'feed_content_type', array( $this, 'feed_content_type' ), 10, 2 );
		add_action( 'init', array( $this, 'call_add_feed' ) );
	}

	/**
	 * Required steps.
	 * Build out table if it doesn't exist.
	 */
	public function __construct() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;

		$this_table_name = $wpdb->prefix . 'lwtv_otd';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $this_table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			post_datetime datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			created date DEFAULT '0000-00-00' NOT NULL,
			posts_id bigint(20) NOT NULL,
			posts_type text NOT NULL,
			content text NOT NULL,
			UNIQUE KEY id (id)
		) $charset_collate;";

		// Make sure our table exists.
		maybe_create_table( $this_table_name, $sql );
	}

	/**
	 * Gets tags to expose as methods accessible through `lwtv_plugin()`.
	 *
	 * @return array Associative array of $method_name => $callback_info pairs. Each $callback_info must either be
	 *               a callable or an array with key 'callable'. This approach is used to reserve the possibility of
	 *               adding support for further arguments in the future.
	 */
	public function get_template_tags(): array {
		return array(
			'get_wp_version'         => array( $this, 'get_wp_version' ),
			'get_rss_otd_last_build' => array( $this, 'get_rss_otd_last_build' ),
			'get_rss_otd_feed'       => array( $this, 'get_rss_otd_feed' ),
		);
	}

	/**
	 * Call the add to feed.
	 */
	public function call_add_feed() {
		add_feed( 'otd', array( $this, 'add_otd_feed' ) );
	}

	/**
	 * Add new feed.
	 */
	public function add_otd_feed() {
		get_template_part( 'rss', 'otd' );
	}

	/**
	 * Return the current version of WP.
	 */
	public function get_wp_version() {
		global $wp_version;

		return $wp_version;
	}

	/**
	 * Set the OTD values and add to the DB
	 *
	 * Called by CRON
	 */
	public function set_of_the_day( $type ) {
		global $wpdb;

		$valid_types = array( 'character', 'show' );
		$date        = current_time( 'Y-m-d' );
		$table       = $wpdb->prefix . 'lwtv_otd';

		$types = $valid_types;
		if ( in_array( $type, $valid_types, true ) ) {
			$types = array( $type );
		}

		foreach ( $types as $a_type ) {
			$new_otd = null;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$maybe_existing_otd = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE posts_type = %s AND created = %s", $a_type, $date ) );

			//[04-Dec-2025 19:07:35 UTC] [Byq-debug] Queery: [{"id":"1494","post_datetime":"2025-12-04 13:01:31","created":"2025-12-04","posts_id":"42546","posts_type":"show","content":"The LezWatch.TV show of the day is \"Cuckoo,\" with 2 characters and an overall score of 53.25. - #LWTVsotd #Cuckoo - https:\/\/lwtv.local\/show\/cuckoo\/"}]
			// If there's NO entry, we can make one.
			if ( 0 === $maybe_existing_otd || empty( $maybe_existing_otd ) ) {
				$new_otd = $this->of_the_day( $a_type, 'default' );
				$this->add_to_table( $a_type, $new_otd );
				lwtv_plugin()->error_log( 'byq-debug', 'Added OTD to table: ' . wp_json_encode( $new_otd ) );
			} else {
				$new_otd = $maybe_existing_otd[0];

				// Convert stdClass object to associative array
				if ( is_object( $new_otd ) ) {
					$new_otd = json_decode( wp_json_encode( $new_otd ), true );
				}

				lwtv_plugin()->error_log( 'byq-debug', 'OTD already exists: ' . wp_json_encode( $new_otd ) );
			}

			if ( null !== $new_otd ) {
				do_action( 'lwtv_otd_added', $a_type, $new_otd['content'], $new_otd['posts_id'], $new_otd );
			}
		}

		// Clear the cache
		( new Cache() )->clean_feed( 'otd' );

		if ( null !== $new_otd ) {
			return $new_otd;
		}

		return new \WP_Error( 'no_otd', 'No OTD found', array( 'status' => 400 ) );
	}

	/**
	 * Add the OTD to the table
	 *
	 * @param string $type type of content
	 * @param array  $data OTD array
	 */
	public function add_to_table( $type, $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'lwtv_otd';

		// table: UID | DATE | POST ID | TYPE | CONTENT
		$date    = current_time( 'Y-m-d' );
		$content = 'The LezWatch.TV ' . $type . ' of the day is';

		// Build the content by type
		switch ( $type ) {
			case 'character':
				// NAME from SHOWS - #LWTVcotd HASHTAG URL
				$content .= ' ' . $data['name'] . ' from ' . $data['shows'] . ' - #LWTVcotD';
				break;
			case 'show':
				// NAME, with CHARS characters and an overall score of SCORE - #LWTVsotd HASHTAG URL
				$content .= ' "' . $data['name'] . '," with ' . $data['characters'] . ' characters and an overall score of ' . $data['score'] . '. - #LWTVsotd';
				break;
		}

		$content .= ' ' . $data['hashtag'] . ' - ' . $data['url'];

		$array = array(
			'created'       => $date,
			'post_datetime' => current_time( 'mysql' ),
			'posts_id'      => (int) $data['pid'],
			'posts_type'    => $type,
			'content'       => $content,
		);

		// Add to the DB
		$wpdb->insert(
			$table,
			$array
		);
	}

	/*
	 * Of the Day function.
	 *
	 * @access public
	 * @param string $type   Type of content.
	 * @param string $format Type of output
	 */
	public function of_the_day( $type = 'character', $format = 'default' ) {

		// Valid types of 'of the day'.
		// If there's no known type, we'll assume character
		$valid_types = array( 'birthday', 'character', 'show', 'death' );
		$type        = ( ! in_array( $type, $valid_types, true ) ) ? 'character' : $type;

		// Valid types of 'format'
		// If there's no known format, we'll assume character
		$valid_format = array( 'default', 'socialmedia', 'json', 'table' );
		$format       = ( ! in_array( $format, $valid_format, true ) ) ? 'default' : $format;

		// Create the date with regards to timezones
		$timestamp = time();
		$dt        = new \DateTime( 'now', new \DateTimeZone( LWTV_TIMEZONE ) ); //first argument "must" be a string
		$dt->setTimestamp( $timestamp ); //adjust the object to correct timestamp
		$date = $dt->format( 'm-d' );

		// Create the array
		switch ( $type ) {
			case 'death':
				$of_the_day_array = ( new BYQ() )->on_this_day( $date, $format );
				break;
			case 'birthday':
				$of_the_day_array = self::birthday( $date, $format );
				break;
			case 'character':
			case 'show':
				$of_the_day_array = self::character_show( $date, $type, $format );
				break;
			default:
				$of_the_day_array = '';
				break;
		}

		if ( empty( $of_the_day_array ) ) {
			return new \WP_Error( 'no_type', 'Invalid content type given.', array( 'status' => 400 ) );
		}

		// No errors! Return array
		return $of_the_day_array;
	}

	/**
	 * character_show function.
	 *
	 * @access public
	 * @param string $date   (default: '')
	 * @param string $type   (default: 'character')
	 * @param string $format (default: 'format')
	 * @return array
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function character_show( $date = '', $type = 'character', $format = 'default' ) {

		// Defaults...
		$return = array();

		// Grab the options
		$default = array(
			'character' => array(
				'time' => strtotime( 'tomorrow 01:00' ),
				'post' => 'none',
			),
			'show'      => array(
				'time' => strtotime( 'tomorrow 01:00' ),
				'post' => 'none',
			),
		);
		$options = get_option( 'lwtv_otd', $default );

		// If there's no ID or the timestamp has past, we need a new ID
		// Or if we're in dev mode.
		if ( 'none' === $options[ $type ]['post'] || time() >= $options[ $type ]['time'] || ( defined( 'LWTV_DEV_SITE' ) && LWTV_DEV_SITE ) ) {
			// Get the show ID
			$id = self::find_char_show( $type, $date );

			// Update the options
			$options[ $type ]['post'] = $id;
			$options[ $type ]['time'] = strtotime( 'midnight tomorrow' );
			update_option( 'lwtv_otd', $options );

			// Set post_meta for the next available use (+4 months from now)
			update_post_meta( $id, 'lwtv_of_the_day', strtotime( '+4 months' ) );
		}

		$post_id = $options[ $type ]['post'];
		$image   = ( has_post_thumbnail( $post_id ) ) ? get_the_post_thumbnail_url( $post_id, 'full' ) : get_site_icon_url();

		// Build the Base Array:
		$return = array(
			'id'    => $post_id,
			'pid'   => $post_id,
			'name'  => $this->clean_show_title( get_the_title( $post_id ), false ),
			'url'   => get_the_permalink( $post_id ),
			'image' => $image,
		);

		// Add custom array items based on type
		switch ( $type ) {
			case 'character':
				$character         = $this->generate_cotd_data( $post_id );
				$return['status']  = $character['status'];
				$return['shows']   = $character['shows'];
				$return['hashtag'] = $character['hashtag'];
				break;
			case 'show':
				$show                 = $this->generate_sotd_data( $post_id );
				$return['loved']      = $show['loved'];
				$return['score']      = $show['score'];
				$return['characters'] = $show['characters'];
				$return['hashtag']    = $show['hashtag'];
				break;
		}

		return $return;
	}

	/**
	 * Generate data for Character. (Optimized version)
	 *
	 * @param int $post_id
	 *
	 * return array
	 */
	public function generate_cotd_data( int $post_id ): array {
		$all_shows   = get_post_meta( $post_id, 'lezchars_show_group', true );
		$shows_value = isset( $all_shows[0] ) ? $all_shows[0] : '';

		$return = array(
			'hashtag'   => '',
			'shows'     => '',
			'status'    => 'unknown',
			'showcount' => 0,
		);

		// Return early if we don't have valid data.
		if ( '' === $all_shows || empty( $shows_value ) || 'post_type_shows' !== get_post_type( $shows_value['show'] ) ) {
			return $return;
		}

		// Extract show IDs for bulk fetching
		$show_ids = array();
		foreach ( $all_shows as $each_show ) {
			if ( isset( $each_show['show'] ) ) {
				$show_id = is_array( $each_show['show'] ) ? $each_show['show'][0] : $each_show['show'];
				if ( $show_id ) {
					$show_ids[] = intval( $show_id );
				}
			}
		}

		// Bulk fetch show titles
		$show_titles = array();
		if ( ! empty( $show_ids ) ) {
			global $wpdb;
			$ids_string   = implode( ',', array_map( 'intval', $show_ids ) );
			$titles_query = "SELECT ID, post_title FROM {$wpdb->posts} WHERE ID IN ($ids_string) AND post_status = 'publish'";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- IDs are sanitized
			$show_results = $wpdb->get_results( $titles_query );

			foreach ( $show_results as $show ) {
				$show_titles[ $show->ID ] = $show->post_title;
			}
		}

		$num_shows = count( $all_shows );
		$showsmore = ( $num_shows > 1 ) ? ' (plus ' . ( $num_shows - 1 ) . ' more)' : '';

		// Get the primary show data
		$primary_show_id = is_array( $shows_value['show'] ) ? $shows_value['show'][0] : $shows_value['show'];
		$show_name       = $show_titles[ $primary_show_id ] ?? '';
		$show_title      = $this->clean_show_title( $show_name );

		$hashtag = '#' . implode( '', array_map( 'ucfirst', explode( '-', $show_title ) ) );

		// Check status efficiently
		$status = 'alive';
		if ( has_term( 'dead', 'lez_cliches', $post_id ) ) {
			$status = 'dead';
		}

		// Set the Return
		$return['hashtag']   = ( isset( $hashtag ) && '#HelloWorld' !== $hashtag ) ? $hashtag : '';
		$return['shows']     = ( isset( $show_name ) ) ? $show_name . $showsmore : '';
		$return['status']    = $status;
		$return['showcount'] = ( isset( $num_shows ) ) ? $num_shows : 0;

		return $return;
	}

	public function generate_sotd_data( int $post_id ): array {
		$return = array(
			'loved'      => '',
			'score'      => 0,
			'characters' => 0,
			'hashtag'    => 0,
		);

		// Early return if it's not a show.
		if ( 'post_type_shows' !== get_post_type( $post_id ) ) {
			return $return;
		}

		// Bulk fetch all meta data in one query
		global $wpdb;
		$meta_query = "SELECT meta_key, meta_value FROM {$wpdb->postmeta}
			WHERE post_id = %d AND meta_key IN ('lezshows_worthit_show_we_love', 'lezshows_the_score', 'lezshows_char_count')";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared query with proper escaping
		$meta_results = $wpdb->get_results( $wpdb->prepare( $meta_query, $post_id ) );
		$meta_data    = array();

		foreach ( $meta_results as $row ) {
			$meta_data[ $row->meta_key ] = $row->meta_value;
		}

		$return['loved']      = ( ! empty( $meta_data['lezshows_worthit_show_we_love'] ) ) ? 'yes' : 'no';
		$return['score']      = number_format( (float) ( $meta_data['lezshows_the_score'] ?? 0 ), 2, '.', '' );
		$return['characters'] = $meta_data['lezshows_char_count'] ?? 0;

		// Get post title efficiently
		$post_title = get_the_title( $post_id );
		$show_title = $this->clean_show_title( $post_title );

		// Hashtag
		$return['hashtag'] = '#' . implode( '', array_map( 'ucfirst', explode( '-', $show_title ) ) );

		return $return;
	}

	/**
	 * Clean the show title for sharing.
	 */
	public function clean_show_title( string $show_title, bool $sanitize = true ): string {

		if ( empty( $show_title ) ) {
			return '';
		}

		// Remove the (2018) from some shows, using ⌘ as delimiter because shows have all sorts of characters but ONLY if they have a space.
		$show_title = trim( preg_replace( '~\([^)]+\)~', '', $show_title ) );
		// change & to and for "WillAndGrace" or "LawAndOrder"
		$show_title = str_replace( ' & ', ' and ', $show_title );
		// change @ to a for "tagged"
		$show_title = str_replace( '@', 'a', $show_title );

		// If it's the title, we want to sanitize to lowercase.
		if ( $sanitize ) {
			$show_title = sanitize_title( $show_title );
		}

		return $show_title;
	}

	/**
	 * Let's find something valid... (Optimized version)
	 * @param  string $type [character|show]
	 * @return number $id   [ID of the show or character]
	 */
	public function find_char_show( $type = 'character', $date = '' ) {

		// phpcs:disable
		add_filter( 'facetwp_is_main_query', function( $is_main_query, $query ) {
			return false;
		}, 10, 2 );
		// phpcs:enable

		// Generate cache key for eligible posts
		$cache_key      = 'lwtv_otd_eligible_' . $type . '_' . $date . '_' . $this->get_data_version_hash( $type );
		$eligible_posts = lwtv_plugin()->get_transient( $cache_key );

		if ( false === $eligible_posts ) {
			$eligible_posts = $this->get_eligible_posts( $type, $date );
			// Cache for 12 hours
			lwtv_plugin()->set_transient( $cache_key, $eligible_posts, 12 * HOUR_IN_SECONDS );
		}

		if ( empty( $eligible_posts ) ) {
			return 0;
		}

		// Use array_rand for better performance than WP_Query with orderby => 'rand'
		$random_key = array_rand( $eligible_posts );
		return $eligible_posts[ $random_key ];
	}

	/**
	 * Get eligible posts for OTD selection (optimized)
	 *
	 * @param string $type Post type
	 * @param string $date Date for awareness filtering
	 * @return array Array of eligible post IDs
	 */
	private function get_eligible_posts( $type, $date ) {
		global $wpdb;

		$post_type      = 'post_type_' . $type . 's';
		$eligible_posts = array();

		switch ( $type ) {
			case 'character':
				if ( '' === $date ) {
					// Create the date with regards to timezones
					$timestamp = time();
					$dt        = new \DateTime( 'now', new \DateTimeZone( LWTV_TIMEZONE ) );
					$dt->setTimestamp( $timestamp );
					$date = $dt->format( 'm-d' );
				}

				// Build complex query for characters
				$tax_query      = $this->character_awareness( $date );
				$tax_conditions = '';

				if ( ! empty( $tax_query ) ) {
					$tax_conditions = "AND p.ID IN (
						SELECT tr.object_id
						FROM {$wpdb->term_relationships} tr
						INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
						INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
						WHERE tt.taxonomy = '" . esc_sql( $tax_query[0]['taxonomy'] ) . "'
						AND t.slug IN ('" . implode( "','", array_map( 'esc_sql', $tax_query[0]['terms'] ) ) . "')
					)";
				}

				$mystery_array  = array( 10250, 11066, 79739, 87052 );
				$mystery_string = implode( ',', array_map( 'intval', $mystery_array ) );

				$query = "SELECT DISTINCT p.ID
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_thumbnail_id'
					INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'lezchars_show_group'
					LEFT JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = 'lwtv_of_the_day'
					WHERE p.post_type = %s
					AND p.post_status = 'publish'
					AND p.post_content NOT LIKE %s
					AND pm1.meta_value NOT IN ($mystery_string)
					AND pm2.meta_value LIKE %s
					AND (pm3.meta_value IS NULL OR pm3.meta_value < %d)
					$tax_conditions
					ORDER BY RAND()
					LIMIT 50";

				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Complex query with proper escaping
				$results = $wpdb->get_results( $wpdb->prepare( $query, $post_type, '%TBD%', '%re%', time() ) );

				// Additional validation for characters
				foreach ( $results as $row ) {
					$post_id = $row->ID;

					// Check if character is a cartoon and must be regular
					$is_toon = has_term( 'cartoon', 'lez_cliches', $post_id );
					if ( $is_toon ) {
						$show_group = get_post_meta( $post_id, 'lezchars_show_group', true );
						$is_regular = is_array( $show_group ) && in_array( 'regular', $show_group, true );
						if ( ! $is_regular ) {
							continue;
						}
					}

					// Check for conditional queerness or phase cliches
					if ( has_term( array( 'conditional-queerness', 'phase' ), 'lez_cliches', $post_id ) ) {
						continue;
					}

					$eligible_posts[] = $post_id;
				}
				break;

			case 'show':
				$query = "SELECT DISTINCT p.ID
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'lezshows_the_score'
					INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'lezshows_worthit_rating'
					INNER JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = 'lezshows_char_roles'
					LEFT JOIN {$wpdb->postmeta} pm4 ON p.ID = pm4.post_id AND pm4.meta_key = 'lwtv_of_the_day'
					WHERE p.post_type = %s
					AND p.post_status = 'publish'
					AND p.post_content NOT LIKE %s
					AND CAST(pm1.meta_value AS DECIMAL(10,2)) >= 50
					AND pm2.meta_value LIKE %s
					AND pm3.meta_value LIKE %s
					AND (pm4.meta_value IS NULL OR pm4.meta_value < %d)
					ORDER BY RAND()
					LIMIT 50";

				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Complex query with proper escaping
				$results = $wpdb->get_results( $wpdb->prepare( $query, $post_type, '%TBD%', '%e%', '%regular%', time() ) );

				// Additional validation for shows
				foreach ( $results as $row ) {
					$post_id = $row->ID;

					// Check if show has at least one regular character
					$role_data = get_post_meta( $post_id, 'lezshows_char_roles', true );
					if ( isset( $role_data['regular'] ) && 0 !== $role_data['regular'] ) {
						$eligible_posts[] = $post_id;
					}
				}
				break;
		}

		return $eligible_posts;
	}

	/**
	 * Get data version hash for cache invalidation
	 *
	 * @param string $type Post type
	 * @return string Hash based on last modification time
	 */
	private function get_data_version_hash( $type ) {
		$cache_key   = 'lwtv_otd_data_version_' . $type;
		$cached_hash = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached_hash ) {
			return $cached_hash;
		}

		global $wpdb;
		$last_modified = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(post_modified) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
				'post_type_' . $type . 's'
			)
		);

		$hash = md5( $last_modified );
		lwtv_plugin()->set_transient( $cache_key, $hash, DAY_IN_SECONDS );

		return $hash;
	}

	/**
	 * Character Awareness Days
	 *
	 * On visibility/awareness days, only show characters that are those things.
	 *
	 * @param mixed $date
	 * @return array()
	 */
	public function character_awareness( $date = '' ) {

		$return = '';

		if ( '' === $date ) {
			// Create the date with regards to timezones
			$timestamp = time();
			$dt        = new \DateTime( 'now', new \DateTimeZone( LWTV_TIMEZONE ) ); //first argument "must" be a string
			$dt->setTimestamp( $timestamp ); //adjust the object to correct timestamp
			$date = $dt->format( 'm-d' );
		}

		// Missing things:
		// Asexual Awareness Week - it's in October
		// Bisexual Awareness Week - it's the week the DAY happens

		switch ( $date ) {
			case '03-31': // Transgender Day of Visibility
			case '11-20': // Transgender Day of Remembrance
				$return = array(
					array(
						'taxonomy' => 'lez_gender',
						'field'    => 'slug',
						'terms'    => array( 'trans-man', 'trans-woman' ),
					),
				);
				break;
			case '04-26': // Lesbian Visibility Day
			case '10-08': // International Lesbian Day
				$return = array(
					array(
						'taxonomy' => 'lez_sexuality',
						'field'    => 'slug',
						'terms'    => array( 'homosexual' ),
					),
					array(
						'taxonomy' => 'lez_gender',
						'field'    => 'slug',
						'terms'    => array( 'cisgender', 'trans-woman' ),
					),
				);
				break;
			case '05-24': // Pansexual Day of Visibility
			case '12-08': // Pansexual Pride Day
				$return = array(
					array(
						'taxonomy' => 'lez_sexuality',
						'field'    => 'slug',
						'terms'    => array( 'pansexual' ),
					),
					array(
						'taxonomy' => 'lez_gender',
						'field'    => 'slug',
						'terms'    => array( 'cisgender', 'trans-woman' ),
					),
				);
				break;
			case '07-14': // Non-Binary Day
				$return = array(
					array(
						'taxonomy' => 'lez_gender',
						'field'    => 'slug',
						'terms'    => array( 'non-binary' ),
					),
				);
				break;
			case '09-23': // Celebrate Bisexuality Day
				$return = array(
					array(
						'taxonomy' => 'lez_sexuality',
						'field'    => 'slug',
						'terms'    => array( 'bisexual' ),
					),
					array(
						'taxonomy' => 'lez_gender',
						'field'    => 'slug',
						'terms'    => array( 'cisgender', 'trans-woman' ),
					),
				);
				break;
			case '10-26': // Intersex Awareness Day
			case '11-08': // Intersex Day of Remembrance
				$return = array(
					array(
						'taxonomy' => 'lez_gender',
						'field'    => 'slug',
						'terms'    => array( 'intersex' ),
					),
				);
				break;
		}

		return $return;
	}

	/**
	 * You say it's your birthday!
	 *
	 * @param  string $date   [description]
	 * @param  string $format [description]
	 * @return [type]         [description]
	 */
	public function birthday( $date = '', $format = 'default' ) {

		// Get all our birthdays
		$actor_loop = ( new Post_Meta() )->make( CPT_Actors::SLUG, 'lezactors_birth', $date, 'LIKE' );

		if ( is_object( $actor_loop ) && $actor_loop->have_posts() ) {
			foreach ( $actor_loop->posts as $actor ) {

				// Get the post slug
				$post_slug = get_post_field( 'post_name', get_post( $actor ) );

				// Calculate Age
				$age_end = new \DateTime();
				if ( get_post_meta( $actor->ID, 'lezactors_death', true ) ) {
					$age_end = new \DateTime( get_post_meta( $actor->ID, 'lezactors_death', true ) );
				}
				if ( get_post_meta( $actor->ID, 'lezactors_birth', true ) ) {
					$age_start = new \DateTime( get_post_meta( $actor->ID, 'lezactors_birth', true ) );
				}
				if ( isset( $age_start ) ) {
					$alive = $age_start->diff( $age_end );
				}

				// Their age is ...
				$age = $alive->format( '%Y' );

				// Setup the WordPress name (used by LWTV News)
				$wordpress_name = '<a href="' . get_permalink( $actor ) . '">' . get_the_title( $actor ) . ' (' . $age . ')</a>';

				// If they have a Twitter handle, use that ; Else use their name
				$hashtag_name = '#' . implode( '', array_map( 'ucfirst', explode( '-', $actor->post_name ) ) );

				// Add to array:
				$twitter_array[ $post_slug ]   = $hashtag_name . ' (' . $age . ')';
				$wordpress_array[ $post_slug ] = $wordpress_name;

			}

			switch ( $format ) {
				case 'socialmedia':
					$birthdays = implode( ', ', $twitter_array );
					break;
				default:
					$birthdays = '<p>A very happy birthday to:</p><ul><li>' . implode( '</li><li>', $wordpress_array ) . '</li></ul>';
			}
		} else {
			// If no one has a birthday, whomp whomp
			switch ( $format ) {
				case 'socialmedia':
					$birthdays = false;
					break;
				default:
					$birthdays = '<p>No one has a birthday today. Who knew?</p>';
			}
		}

		$return = array(
			'date'      => $date,
			'birthdays' => $birthdays,
		);

		return $return;
	}
	/**
	 * Generate last-build
	 *
	 * This needs to be based on the last entry we added to the table.
	 */
	public function get_rss_otd_last_build() {
		global $wpdb;

		$table = $wpdb->prefix . 'lwtv_otd';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$table_data = $wpdb->get_results( "SELECT * FROM {$table} order by id desc limit 1", ARRAY_A );

		return $table_data[0]['post_datetime'];
	}

	/**
	 * Limit the actions to ONLY this feed.
	 */
	public function feed_content_type( $content_type, $type ) {
		if ( 'otd' === $type ) {
			add_filter( 'wp_title_rss', array( $this, 'rss_title' ), 20, 1 );
			$content_type = 'application/rss+xml';
		}
		return $content_type;
	}

	/**
	 * Customize RSS title.
	 */
	public function rss_title() {
		$rss_title = 'LezWatch.TV Of The Day - Feed';
		return $rss_title;
	}

	/**
	 * Customize RSS Item
	 *
	 * Adds Enclosure to RSS if it exists.
	 */
	public function customize_rss_item() {
		if ( ! has_post_thumbnail() ) {
			return;
		}

		$thumbnail_size = apply_filters( 'rss_enclosure_image_size', 'large' );
		$thumbnail_id   = get_post_thumbnail_id( get_the_ID() );
		$thumbnail      = image_get_intermediate_size( $thumbnail_id, $thumbnail_size );

		if ( empty( $thumbnail ) ) {
			return;
		}

		$upload_dir = wp_upload_dir();

		printf(
			'<enclosure url="%s" length="%s" type="%s" />',
			esc_url( $thumbnail['url'] ),
			esc_html( filesize( path_join( $upload_dir['basedir'], $thumbnail['path'] ) ) ),
			esc_html( get_post_mime_type( $thumbnail_id ) )
		);
	}

	/**
	 * Build RSS feed.
	 */
	public function get_rss_otd_feed() {

		global $wpdb;

		$table = $wpdb->prefix . 'lwtv_otd';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$table_data = $wpdb->get_results( "SELECT * FROM {$table} order by id desc limit 10" );

		foreach ( $table_data as $use_data ) {
			?>
			<item>
				<title><?php echo esc_html( ucfirst( $use_data->posts_type ) ); ?> of the Day: <?php echo esc_html( get_the_title( $use_data->posts_id ) ); ?></title>
				<link><?php echo esc_url( get_permalink( $use_data->posts_id ) ); ?></link>
				<pubDate><?php echo esc_html( mysql2date( 'D, d M Y H:i:s +0000', $use_data->post_datetime ) ); ?></pubDate>
				<dc:creator>LezWatch.TV</dc:creator>
				<guid isPermaLink="false"><?php the_guid( $use_data->posts_id ); ?></guid>
				<description><![CDATA[<?php echo wp_kses_post( $use_data->content ); ?>]]></description>
				<content:encoded><![CDATA[<?php echo wp_kses_post( $use_data->content ); ?>]]></content:encoded>
				<?php
				if ( has_post_thumbnail( $use_data->posts_id ) ) {
					$thumbnail_id = get_post_thumbnail_id( $use_data->posts_id );
					$thumbnail    = image_get_intermediate_size( $thumbnail_id, $use_data->posts_type . '-img' );

					// If there is image that size, try thumbnail.
					if ( empty( $thumbnail ) ) {
						$thumbnail = image_get_intermediate_size( $thumbnail_id );
					}

					// Now we should have one.
					if ( ! empty( $thumbnail ) ) {
						$upload_dir = wp_upload_dir();

						printf(
							'<enclosure url="%s" length="%s" type="%s" />',
							esc_url( $thumbnail['url'] ),
							esc_html( filesize( path_join( $upload_dir['basedir'], $thumbnail['path'] ) ) ),
							esc_html( get_post_mime_type( $thumbnail_id ) )
						);
					}
				}
				?>

				<?php do_action( 'rss2_item' ); ?>
			</item>
			<?php
		}
	}
}
