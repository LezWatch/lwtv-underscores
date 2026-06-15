<?php
/*
 * WP CLI Commands for LezWatch.TV
 *
 * These commands are 'migration' tools.
 */

// Bail if directly accessed
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	die();
}

/**
 * LezWatch.TV commands to migrate content.
 */
class WP_CLI_LWTV_Migrate {

	/**
	 * @var string
	 */
	public $format;

	/**
	 * @var string
	 */
	public $migrate_type;

	/**
	 * @var string
	 */
	public $migrate_subtype;

	/**
	 * Construct to block facet from munging results.
	 */
	public function __construct() {
		// phpcs:disable
		// Remove <!--fwp-loop--> from output
		add_filter( 'facetwp_is_main_query', function( $is_main_query, $query ) {
			return false;
		}, 10, 2 );
		// phpcs:enable
	}

	/**
	 * Run the migrator.
	 * ## OPTIONS
	 *
	 * <type>
	 * : Type to content to migrate (i.e. 'acf').
	 * options:
	 * - acf
	 * ---
	 *
	 *
	 * [<subtype>]
	 * : Optional. Secondary data. ACF uses [airdates|waystowatch|shownames|similarshows|charactor|chardeath|charimages|charimages-to-gallery|charshowgroup|autoposting|watchtermurls|debuglogging].
	 * ---
	 *
	 * @param string $migrate_type         The type of migrator to run.
	 * @param string|null $migrate_subtype An optional second argument for certain migrations.
	 */
	public function __invoke( array $args, array $assoc_args = array() ) {

		$this->format          = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$this->migrate_type    = $args[0];
		$this->migrate_subtype = ( isset( $args[1] ) ) ? $args[1] : null;

		try {
			$this->run_migrator( $this->migrate_type, $this->migrate_subtype );
		} catch ( Exception $exception ) {
			\WP_CLI::error( $exception->getMessage(), false );
		}
	}

	/**
	 * Run the migrator based on type and subtype.
	 *
	 * @param string $type The type of migrator to run.
	 * @param string|null $subtype An optional second argument for certain migrations.
	 */
	private function run_migrator( $type, $subtype = null ) {
		if ( 'acf' === $type ) {
			if ( is_null( $subtype ) ) {
				\WP_CLI::error( 'ACF migration requires a subtype. Please specify one.' );
			}

			switch ( $subtype ) {
				case 'waystowatch':
					$this->migrate_waystowatch();
					break;
				case 'shownames':
					$this->migrate_shownames();
					break;
				case 'similarshows':
					$this->migrate_similarshows();
					break;
				case 'charactor':
					$this->migrate_charactor();
					break;
				case 'chardeath':
					$this->migrate_chardeath();
					break;
				case 'charimages':
					$this->migrate_charimages();
					break;
				case 'charimages-to-gallery':
					$this->migrate_charimages_to_gallery();
					break;
				case 'charshowgroup':
					$this->migrate_charshowgroup();
					break;
				case 'autoposting':
					$this->migrate_autoposting();
					break;
				case 'watchtermurls':
					$this->migrate_watchtermurls();
					break;
				case 'airdates':
					$this->migrate_airdates();
					break;
				case 'debuglogging':
					$this->migrate_debuglogging();
					break;
				default:
					\WP_CLI::error( 'Unknown ACF migration subtype: ' . $subtype . ' does not exist.' );
			}
		} else {
			\WP_CLI::error( 'Unknown migration type: ' . $type . ' does not exist.' );
		}
	}

	/**
	 * Migrate Ways to Watch URLs from old flat array to ACF repeater format.
	 * This is a one-time migration to convert existing data to the new ACF structure.
	 */
	public function migrate_waystowatch() {
		$posts = get_posts(
			array(
				'post_type'      => 'post_type_shows',
				'posts_per_page' => -1,
				'meta_key'       => 'lezshows_waystowatch',
				'meta_compare'   => 'EXISTS',
			)
		);

		if ( empty( $posts ) ) {
			\WP_CLI::log( "No posts found with 'lezshows_waystowatch' meta key." );
			return;
		}

		$post_count = 0;

		$progress_bar = \WP_CLI\Utils\make_progress_bar( sprintf( 'Starting migration. Found %d shows ...', count( $posts ) ), count( $posts ) );

		foreach ( $posts as $post ) {
			$value = get_post_meta( $post->ID, 'lezshows_waystowatch', true );

			if ( ! is_array( $value ) ) {
				continue;
			}

			$count = 0;
			foreach ( $value as $url ) {
				$url = esc_url_raw( trim( $url ) );

				if ( empty( $url ) ) {
					continue;
				}

				$key = "lezshows_waystowatch_{$count}_url";
				update_post_meta( $post->ID, $key, $url );
				update_post_meta( $post->ID, "_$key", 'field_lwtv_lezshows_waystowatch_url' );
				++$count;
			}
			update_post_meta( $post->ID, 'lezshows_waystowatch', $count );
			update_post_meta( $post->ID, '_lezshows_waystowatch', 'field_lwtv_lezshows_waystowatch' );

			$progress_bar->tick();
			++$post_count;
		}

		if ( 0 === $post_count ) {
			\WP_CLI::log( "No posts found with 'lezshows_waystowatch' meta key." );
		} else {
			\WP_CLI::success( 'Migration completed for ' . $post_count . ' post(s).' );
		}
	}

	/**
	 * Migrate Show Names from CMB2 group array to ACF repeater format.
	 *
	 * Old format: array( 0 => array( 'lezshows_alt_show_name' => 'Name', 'type' => 'en' ) )
	 * New format: ACF repeater row meta keys + reference keys.
	 */
	public function migrate_shownames() {
		$posts = get_posts(
			array(
				'post_type'      => 'post_type_shows',
				'posts_per_page' => -1,
				'meta_key'       => 'lezshows_show_names',
				'meta_compare'   => 'EXISTS',
			)
		);

		if ( empty( $posts ) ) {
			\WP_CLI::log( "No posts found with 'lezshows_show_names' meta key." );
			return;
		}

		$post_count   = 0;
		$progress_bar = \WP_CLI\Utils\make_progress_bar( sprintf( 'Starting migration. Found %d shows ...', count( $posts ) ), count( $posts ) );

		foreach ( $posts as $post ) {
			// Already migrated: ACF reference key exists.
			if ( get_post_meta( $post->ID, '_lezshows_show_names', true ) ) {
				$progress_bar->tick();
				continue;
			}

			$value = get_post_meta( $post->ID, 'lezshows_show_names', true );

			if ( ! is_array( $value ) ) {
				$progress_bar->tick();
				continue;
			}

			$count = 0;
			foreach ( $value as $row ) {
				$alt_name = isset( $row['lezshows_alt_show_name'] ) ? (string) $row['lezshows_alt_show_name'] : '';
				$type     = isset( $row['type'] ) ? (string) $row['type'] : '';

				update_post_meta( $post->ID, "lezshows_show_names_{$count}_lezshows_alt_show_name", $alt_name );
				update_post_meta( $post->ID, "_lezshows_show_names_{$count}_lezshows_alt_show_name", 'field_lwtv_lezshows_alt_show_name' );
				update_post_meta( $post->ID, "lezshows_show_names_{$count}_type", $type );
				update_post_meta( $post->ID, "_lezshows_show_names_{$count}_type", 'field_lwtv_lezshows_show_name_type' );
				++$count;
			}

			update_post_meta( $post->ID, 'lezshows_show_names', $count );
			update_post_meta( $post->ID, '_lezshows_show_names', 'field_lwtv_lezshows_show_names' );

			$progress_bar->tick();
			++$post_count;
		}

		$progress_bar->finish();

		if ( 0 === $post_count ) {
			\WP_CLI::log( 'No posts required migration.' );
		} else {
			\WP_CLI::success( 'Migration completed for ' . $post_count . ' post(s).' );
		}
	}

	/**
	 * Migrate Similar Shows from CMB2 serialized string IDs to ACF relationship format.
	 *
	 * Old format: serialized array of string post IDs — same shape ACF uses, but missing
	 * the _lezshows_similar_shows reference key and IDs need to be integers.
	 */
	public function migrate_similarshows() {
		$posts = get_posts(
			array(
				'post_type'      => 'post_type_shows',
				'posts_per_page' => -1,
				'meta_key'       => 'lezshows_similar_shows',
				'meta_compare'   => 'EXISTS',
			)
		);

		if ( empty( $posts ) ) {
			\WP_CLI::log( "No posts found with 'lezshows_similar_shows' meta key." );
			return;
		}

		$post_count   = 0;
		$progress_bar = \WP_CLI\Utils\make_progress_bar( sprintf( 'Starting migration. Found %d shows ...', count( $posts ) ), count( $posts ) );

		foreach ( $posts as $post ) {
			// Already migrated: ACF reference key exists.
			if ( get_post_meta( $post->ID, '_lezshows_similar_shows', true ) ) {
				$progress_bar->tick();
				continue;
			}

			$value = get_post_meta( $post->ID, 'lezshows_similar_shows', true );

			if ( ! is_array( $value ) ) {
				$progress_bar->tick();
				continue;
			}

			// ACF relationship field stores integer IDs; CMB2 stored string IDs.
			$ids = array_values( array_filter( array_map( 'absint', $value ) ) );

			if ( empty( $ids ) ) {
				$progress_bar->tick();
				continue;
			}

			update_post_meta( $post->ID, 'lezshows_similar_shows', $ids );
			update_post_meta( $post->ID, '_lezshows_similar_shows', 'field_lwtv_lezshows_similar_shows' );

			$progress_bar->tick();
			++$post_count;
		}

		$progress_bar->finish();

		if ( 0 === $post_count ) {
			\WP_CLI::log( 'No posts required migration.' );
		} else {
			\WP_CLI::success( 'Migration completed for ' . $post_count . ' post(s).' );
		}
	}
	/**
	 * Migrate lezchars_actor from CMB2 flat array of string IDs to ACF relationship format.
	 *
	 * Same pattern as lezshows_similar_shows: convert string IDs to integers, add reference key.
	 */
	public function migrate_charactor() {
		$posts = get_posts(
			array(
				'post_type'      => 'post_type_characters',
				'posts_per_page' => -1,
				'meta_key'       => 'lezchars_actor',
				'meta_compare'   => 'EXISTS',
			)
		);

		if ( empty( $posts ) ) {
			\WP_CLI::log( "No posts found with 'lezchars_actor' meta key." );
			return;
		}

		$post_count   = 0;
		$progress_bar = \WP_CLI\Utils\make_progress_bar( sprintf( 'Starting migration. Found %d characters ...', count( $posts ) ), count( $posts ) );

		foreach ( $posts as $post ) {
			if ( get_post_meta( $post->ID, '_lezchars_actor', true ) ) {
				$progress_bar->tick();
				continue;
			}

			$value = get_post_meta( $post->ID, 'lezchars_actor', true );

			if ( ! is_array( $value ) ) {
				$progress_bar->tick();
				continue;
			}

			$ids = array_values( array_filter( array_map( 'absint', $value ) ) );

			if ( empty( $ids ) ) {
				$progress_bar->tick();
				continue;
			}

			update_post_meta( $post->ID, 'lezchars_actor', $ids );
			update_post_meta( $post->ID, '_lezchars_actor', 'field_lwtv_lezchars_actor' );

			$progress_bar->tick();
			++$post_count;
		}

		$progress_bar->finish();

		if ( 0 === $post_count ) {
			\WP_CLI::log( 'No posts required migration.' );
		} else {
			\WP_CLI::success( 'Migration completed for ' . $post_count . ' post(s).' );
		}
	}

	/**
	 * Migrate lezchars_death_year from CMB2 flat date array to ACF repeater format.
	 *
	 * Old format: array( '2014-10-08', '2016-05-19', ... )
	 * New format: ACF repeater rows with a 'date' sub-field per entry.
	 */
	public function migrate_chardeath() {
		$posts = get_posts(
			array(
				'post_type'      => 'post_type_characters',
				'posts_per_page' => -1,
				'meta_key'       => 'lezchars_death_year',
				'meta_compare'   => 'EXISTS',
			)
		);

		if ( empty( $posts ) ) {
			\WP_CLI::log( "No posts found with 'lezchars_death_year' meta key." );
			return;
		}

		$post_count   = 0;
		$progress_bar = \WP_CLI\Utils\make_progress_bar( sprintf( 'Starting migration. Found %d characters ...', count( $posts ) ), count( $posts ) );

		foreach ( $posts as $post ) {
			if ( get_post_meta( $post->ID, '_lezchars_death_year', true ) ) {
				$progress_bar->tick();
				continue;
			}

			$value = get_post_meta( $post->ID, 'lezchars_death_year', true );

			if ( ! is_array( $value ) ) {
				$progress_bar->tick();
				continue;
			}

			$count = 0;
			foreach ( $value as $date ) {
				$date = (string) $date;
				if ( empty( $date ) ) {
					continue;
				}
				update_post_meta( $post->ID, "lezchars_death_year_{$count}_date", $date );
				update_post_meta( $post->ID, "_lezchars_death_year_{$count}_date", 'field_lwtv_lezchars_death_year_date' );
				++$count;
			}

			update_post_meta( $post->ID, 'lezchars_death_year', $count );
			update_post_meta( $post->ID, '_lezchars_death_year', 'field_lwtv_lezchars_death_year' );

			$progress_bar->tick();
			++$post_count;
		}

		$progress_bar->finish();

		if ( 0 === $post_count ) {
			\WP_CLI::log( 'No posts required migration.' );
		} else {
			\WP_CLI::success( 'Migration completed for ' . $post_count . ' post(s).' );
		}
	}

	/**
	 * Migrate lezchars_character_image_group from CMB2 group to ACF repeater format.
	 *
	 * Old format: array( 0 => array( 'alt_image_text' => 'Male', 'alt_image_file_id' => 87693, 'alt_image_file' => 'https://...' ) )
	 * New format: ACF repeater rows; alt_image_file sub-field stores attachment ID (integer).
	 */
	public function migrate_charimages() {
		$posts = get_posts(
			array(
				'post_type'      => 'post_type_characters',
				'posts_per_page' => -1,
				'meta_key'       => 'lezchars_character_image_group',
				'meta_compare'   => 'EXISTS',
			)
		);

		if ( empty( $posts ) ) {
			\WP_CLI::log( "No posts found with 'lezchars_character_image_group' meta key." );
			return;
		}

		$post_count   = 0;
		$progress_bar = \WP_CLI\Utils\make_progress_bar( sprintf( 'Starting migration. Found %d characters ...', count( $posts ) ), count( $posts ) );

		foreach ( $posts as $post ) {
			if ( get_post_meta( $post->ID, '_lezchars_character_image_group', true ) ) {
				$progress_bar->tick();
				continue;
			}

			$value = get_post_meta( $post->ID, 'lezchars_character_image_group', true );

			if ( ! is_array( $value ) ) {
				$progress_bar->tick();
				continue;
			}

			$count = 0;
			foreach ( $value as $row ) {
				$text      = isset( $row['alt_image_text'] ) ? (string) $row['alt_image_text'] : '';
				$attach_id = isset( $row['alt_image_file_id'] ) ? absint( $row['alt_image_file_id'] ) : 0;

				if ( empty( $attach_id ) ) {
					continue;
				}

				update_post_meta( $post->ID, "lezchars_character_image_group_{$count}_alt_image_text", $text );
				update_post_meta( $post->ID, "_lezchars_character_image_group_{$count}_alt_image_text", 'field_lwtv_lezchars_alt_image_text' );
				update_post_meta( $post->ID, "lezchars_character_image_group_{$count}_alt_image_file", $attach_id );
				update_post_meta( $post->ID, "_lezchars_character_image_group_{$count}_alt_image_file", 'field_lwtv_lezchars_alt_image_file' );
				++$count;
			}

			update_post_meta( $post->ID, 'lezchars_character_image_group', $count );
			update_post_meta( $post->ID, '_lezchars_character_image_group', 'field_lwtv_lezchars_character_image_group' );

			$progress_bar->tick();
			++$post_count;
		}

		$progress_bar->finish();

		if ( 0 === $post_count ) {
			\WP_CLI::log( 'No posts required migration.' );
		} else {
			\WP_CLI::success( 'Migration completed for ' . $post_count . ' post(s).' );
		}
	}

	/**
	 * Migrate lezchars_character_image_group from ACF repeater format to ACF Gallery field.
	 *
	 * Old format: ACF repeater rows; lezchars_character_image_group = integer count,
	 *             sub-fields lezchars_character_image_group_{N}_alt_image_file (attach ID)
	 *             and lezchars_character_image_group_{N}_alt_image_text (label string).
	 * New format: ACF Gallery field; lezchars_character_image_group = serialized array of attach IDs.
	 *
	 * Also copies alt_image_text to the attachment title when the attachment title
	 * looks like a raw filename (no spaces), preserving the "Crossover"/"Flashback" labels
	 * for the front-end tab UI.
	 */
	public function migrate_charimages_to_gallery() {
		$posts = get_posts(
			array(
				'post_type'      => 'post_type_characters',
				'posts_per_page' => -1,
				'meta_key'       => 'lezchars_character_image_group',
				'meta_compare'   => 'EXISTS',
			)
		);

		if ( empty( $posts ) ) {
			\WP_CLI::log( "No posts found with 'lezchars_character_image_group' meta key." );
			return;
		}

		$post_count   = 0;
		$progress_bar = \WP_CLI\Utils\make_progress_bar( sprintf( 'Starting migration. Found %d characters ...', count( $posts ) ), count( $posts ) );

		foreach ( $posts as $post ) {
			$raw = get_post_meta( $post->ID, 'lezchars_character_image_group', true );

			// Already migrated: Gallery stores a serialized array; repeater stores an integer count.
			if ( ! is_numeric( $raw ) ) {
				$progress_bar->tick();
				continue;
			}

			$count = (int) $raw;
			if ( $count <= 0 ) {
				$progress_bar->tick();
				continue;
			}

			$ids = array();
			for ( $i = 0; $i < $count; $i++ ) {
				$attach_id = absint( get_post_meta( $post->ID, "lezchars_character_image_group_{$i}_alt_image_file", true ) );
				if ( empty( $attach_id ) ) {
					continue;
				}

				$label = (string) get_post_meta( $post->ID, "lezchars_character_image_group_{$i}_alt_image_text", true );
				if ( ! empty( $label ) ) {
					$current_title = get_the_title( $attach_id );
					// Only overwrite if the attachment title looks like a raw filename (no spaces).
					if ( ! str_contains( $current_title, ' ' ) ) {
						wp_update_post(
							array(
								'ID'         => $attach_id,
								'post_title' => $label,
							)
						);
					}
				}

				$ids[] = $attach_id;

				delete_post_meta( $post->ID, "lezchars_character_image_group_{$i}_alt_image_text" );
				delete_post_meta( $post->ID, "_lezchars_character_image_group_{$i}_alt_image_text" );
				delete_post_meta( $post->ID, "lezchars_character_image_group_{$i}_alt_image_file" );
				delete_post_meta( $post->ID, "_lezchars_character_image_group_{$i}_alt_image_file" );
			}

			if ( empty( $ids ) ) {
				$progress_bar->tick();
				continue;
			}

			update_post_meta( $post->ID, 'lezchars_character_image_group', $ids );
			update_post_meta( $post->ID, '_lezchars_character_image_group', 'field_lwtv_lezchars_character_image_group' );

			$progress_bar->tick();
			++$post_count;
		}

		$progress_bar->finish();

		if ( 0 === $post_count ) {
			\WP_CLI::log( 'No posts required migration.' );
		} else {
			\WP_CLI::success( 'Migration completed for ' . $post_count . ' post(s).' );
		}
	}

	/**
	 * Migrate lezchars_show_group from CMB2 repeatable group to ACF repeater format.
	 *
	 * Old format: array( 0 => array( 'show' => array(0 => '1607') OR '1607', 'type' => 'regular', 'appears' => array('2022', ...) ) )
	 * New format: ACF repeater rows with show (post ID int), type (string), appears (array) sub-fields.
	 *
	 * NOTE: After running this, LIKE meta queries against lezchars_show_group will stop
	 * working. Run Phase 4 consuming-code updates before or immediately after.
	 */
	public function migrate_charshowgroup() {
		$posts = get_posts(
			array(
				'post_type'      => 'post_type_characters',
				'posts_per_page' => -1,
				'meta_key'       => 'lezchars_show_group',
				'meta_compare'   => 'EXISTS',
			)
		);

		if ( empty( $posts ) ) {
			\WP_CLI::log( "No posts found with 'lezchars_show_group' meta key." );
			return;
		}

		$post_count   = 0;
		$progress_bar = \WP_CLI\Utils\make_progress_bar( sprintf( 'Starting migration. Found %d characters ...', count( $posts ) ), count( $posts ) );

		foreach ( $posts as $post ) {
			if ( get_post_meta( $post->ID, '_lezchars_show_group', true ) ) {
				$progress_bar->tick();
				continue;
			}

			$value = get_post_meta( $post->ID, 'lezchars_show_group', true );

			if ( ! is_array( $value ) ) {
				$progress_bar->tick();
				continue;
			}

			$count = 0;
			foreach ( $value as $row ) {
				if ( ! isset( $row['show'] ) ) {
					continue;
				}

				// CMB2 stored show as array(0 => 'ID') in most records, plain string in older ones.
				$show_id = is_array( $row['show'] ) ? ( $row['show'][0] ?? 0 ) : $row['show'];
				$show_id = absint( $show_id );

				if ( empty( $show_id ) ) {
					continue;
				}

				$type    = isset( $row['type'] ) ? (string) $row['type'] : '';
				$appears = isset( $row['appears'] ) && is_array( $row['appears'] ) ? $row['appears'] : array();

				update_post_meta( $post->ID, "lezchars_show_group_{$count}_show", $show_id );
				update_post_meta( $post->ID, "_lezchars_show_group_{$count}_show", 'field_lwtv_lezchars_show_group_show' );
				update_post_meta( $post->ID, "lezchars_show_group_{$count}_type", $type );
				update_post_meta( $post->ID, "_lezchars_show_group_{$count}_type", 'field_lwtv_lezchars_show_group_type' );
				update_post_meta( $post->ID, "lezchars_show_group_{$count}_appears", $appears );
				update_post_meta( $post->ID, "_lezchars_show_group_{$count}_appears", 'field_lwtv_lezchars_show_group_appears' );
				++$count;
			}

			update_post_meta( $post->ID, 'lezchars_show_group', $count );
			update_post_meta( $post->ID, '_lezchars_show_group', 'field_lwtv_lezchars_show_group' );

			$progress_bar->tick();
			++$post_count;
		}

		$progress_bar->finish();

		if ( 0 === $post_count ) {
			\WP_CLI::log( 'No posts required migration.' );
		} else {
			\WP_CLI::success( 'Migration completed for ' . $post_count . ' post(s).' );
		}
	}

	/**
	 * Migrate Auto-Posting (Postiz) settings from CMB2 options page to ACF options page.
	 *
	 * CMB2 stores all settings under a single serialized option key.
	 * ACF options pages store each field as its own wp_options row.
	 *
	 * Run AFTER syncing group_lwtv_auto_posting.json in ACF → Sync.
	 */
	public function migrate_autoposting() {
		$old_options = get_option( 'lwtv_auto_posting_options', array() );

		if ( empty( $old_options ) ) {
			\WP_CLI::log( 'No auto-posting options found in lwtv_auto_posting_options. Nothing to migrate.' );
			return;
		}

		$migrated = 0;

		// Simple scalar fields.
		$simple = array(
			'lwtv_postiz_api_key'   => 'field_lwtv_postiz_api_key',
			'lwtv_postiz_api_url'   => 'field_lwtv_postiz_api_url',
			'lwtv_postiz_post_type' => 'field_lwtv_postiz_post_type',
			'lwtv_postiz_triggers'  => 'field_lwtv_postiz_triggers',
		);

		foreach ( $simple as $key => $field_key ) {
			if ( array_key_exists( $key, $old_options ) ) {
				update_option( $key, $old_options[ $key ] );
				update_option( '_' . $key, $field_key );
				++$migrated;
				\WP_CLI::log( "Migrated: {$key}" );
			}
		}

		// Channels repeater.
		$channels = $old_options['lwtv_postiz_channels'] ?? array();
		if ( ! empty( $channels ) && is_array( $channels ) ) {
			$count = 0;
			foreach ( $channels as $channel ) {
				update_option( "lwtv_postiz_channels_{$count}_name", $channel['name'] ?? '' );
				update_option( "_lwtv_postiz_channels_{$count}_name", 'field_lwtv_postiz_channels_name' );

				update_option( "lwtv_postiz_channels_{$count}_channel_id", $channel['channel_id'] ?? '' );
				update_option( "_lwtv_postiz_channels_{$count}_channel_id", 'field_lwtv_postiz_channels_channel_id' );

				// CMB2 checkbox stores 'on'; ACF true_false stores '1'/'0'.
				$active = ( isset( $channel['active'] ) && 'on' === $channel['active'] ) ? '1' : '0';
				update_option( "lwtv_postiz_channels_{$count}_active", $active );
				update_option( "_lwtv_postiz_channels_{$count}_active", 'field_lwtv_postiz_channels_active' );

				\WP_CLI::log( "Migrated channel {$count}: " . ( $channel['name'] ?? '(unnamed)' ) );
				++$count;
			}
			update_option( 'lwtv_postiz_channels', $count );
			update_option( '_lwtv_postiz_channels', 'field_lwtv_postiz_channels' );
			++$migrated;
		}

		if ( 0 === $migrated ) {
			\WP_CLI::log( 'No fields required migration.' );
		} else {
			\WP_CLI::success( "Migration completed. {$migrated} setting group(s) migrated." );
		}
	}

	/**
	 * Migrate lez_watch_urls term meta from CMB2 format to ACF repeater format.
	 *
	 * CMB2 repeatable text_url stores lezwatchurls_all as a serialized PHP array.
	 * ACF repeater stores individual rows: lezwatchurls_all_N_url.
	 * CMB2 checkbox stores lezwatchurls_setting_hide_display as 'on'; ACF true_false uses '1'.
	 *
	 * Run AFTER syncing group_lwtv_term_watch_urls.json in ACF → Sync.
	 */
	public function migrate_watchtermurls() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'lez_watch_urls',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			\WP_CLI::log( 'No lez_watch_urls terms found.' );
			return;
		}

		$term_count   = 0;
		$progress_bar = \WP_CLI\Utils\make_progress_bar( sprintf( 'Starting migration. Found %d terms ...', count( $terms ) ), count( $terms ) );

		foreach ( $terms as $term ) {
			$term_id = $term->term_id;

			// Migrate lezwatchurls_all (serialized array → ACF repeater rows).
			if ( ! get_term_meta( $term_id, '_lezwatchurls_all', true ) ) {
				$value = get_term_meta( $term_id, 'lezwatchurls_all', true );

				if ( is_array( $value ) ) {
					$count = 0;
					foreach ( $value as $url ) {
						$url = esc_url_raw( trim( $url ) );
						if ( empty( $url ) ) {
							continue;
						}
						update_term_meta( $term_id, "lezwatchurls_all_{$count}_url", $url );
						update_term_meta( $term_id, "_lezwatchurls_all_{$count}_url", 'field_lwtv_lezwatchurls_all_url' );
						++$count;
					}
					update_term_meta( $term_id, 'lezwatchurls_all', $count );
					update_term_meta( $term_id, '_lezwatchurls_all', 'field_lwtv_lezwatchurls_all' );
					\WP_CLI::log( "Migrated URLs for term {$term_id}: {$term->name} ({$count} rows)" );
					++$term_count;
				}
			}

			// Migrate lezwatchurls_setting_hide_display: CMB2 'on' → ACF '1'.
			$hide = get_term_meta( $term_id, 'lezwatchurls_setting_hide_display', true );
			if ( 'on' === $hide ) {
				update_term_meta( $term_id, 'lezwatchurls_setting_hide_display', '1' );
				update_term_meta( $term_id, '_lezwatchurls_setting_hide_display', 'field_lwtv_lezwatchurls_setting_hide_display' );
				\WP_CLI::log( "Migrated hide_display for term {$term_id}: {$term->name}" );
			}

			$progress_bar->tick();
		}

		$progress_bar->finish();

		if ( 0 === $term_count ) {
			\WP_CLI::log( 'No terms required URL migration.' );
		} else {
			\WP_CLI::success( 'Migration completed for ' . $term_count . ' term(s).' );
		}
	}

	/**
	 * Migrate lezshows_airdates start/finish to separate ACF meta keys.
	 *
	 * CMB2 stored both dates as one serialized array: lezshows_airdates['start'] / ['finish'].
	 * ACF uses separate keys: lezshows_airdates_start and lezshows_airdates_finish.
	 * The load_value filters bridge the gap at display time, but the separate keys must
	 * exist in the DB for direct get_post_meta() reads (on-air checker, calculations) to
	 * work correctly without relying on the legacy fallback.
	 *
	 * Skips shows where the separate key already has a non-empty value.
	 */
	public function migrate_airdates() {
		$posts = get_posts(
			array(
				'post_type'      => 'post_type_shows',
				'posts_per_page' => -1,
				'meta_key'       => 'lezshows_airdates',
				'meta_compare'   => 'EXISTS',
			)
		);

		if ( empty( $posts ) ) {
			\WP_CLI::log( "No posts found with 'lezshows_airdates' meta key." );
			return;
		}

		$post_count   = 0;
		$skip_count   = 0;
		$progress_bar = \WP_CLI\Utils\make_progress_bar( sprintf( 'Starting migration. Found %d shows ...', count( $posts ) ), count( $posts ) );

		foreach ( $posts as $post ) {
			$legacy = get_post_meta( $post->ID, 'lezshows_airdates', true );

			if ( ! is_array( $legacy ) ) {
				$progress_bar->tick();
				continue;
			}

			$legacy_start  = isset( $legacy['start'] ) ? (string) $legacy['start'] : '';
			$legacy_finish = isset( $legacy['finish'] ) ? (string) $legacy['finish'] : '';

			if ( empty( $legacy_start ) && empty( $legacy_finish ) ) {
				$progress_bar->tick();
				continue;
			}

			$current_start  = get_post_meta( $post->ID, 'lezshows_airdates_start', true );
			$current_finish = get_post_meta( $post->ID, 'lezshows_airdates_finish', true );

			$wrote = false;

			if ( empty( $current_start ) && ! empty( $legacy_start ) ) {
				update_post_meta( $post->ID, 'lezshows_airdates_start', $legacy_start );
				update_post_meta( $post->ID, '_lezshows_airdates_start', 'field_lwtv_lezshows_airdates_start' );
				$wrote = true;
			}

			if ( empty( $current_finish ) && ! empty( $legacy_finish ) ) {
				update_post_meta( $post->ID, 'lezshows_airdates_finish', $legacy_finish );
				update_post_meta( $post->ID, '_lezshows_airdates_finish', 'field_lwtv_lezshows_airdates_finish' );
				$wrote = true;
			}

			if ( $wrote ) {
				++$post_count;
			} else {
				++$skip_count;
			}

			$progress_bar->tick();
		}

		$progress_bar->finish();

		\WP_CLI::log( sprintf( '%d show(s) skipped (separate keys already populated).', $skip_count ) );

		if ( 0 === $post_count ) {
			\WP_CLI::log( 'No posts required migration.' );
		} else {
			\WP_CLI::success( 'Migration completed for ' . $post_count . ' post(s).' );
		}
	}

	/**
	 * Migrate Debug Logging settings from CMB2 options page to ACF options page.
	 *
	 * CMB2 stores all settings under lwtv_debug_logging_options as a serialized array.
	 * ACF options pages store each field as its own wp_options row.
	 *
	 * Run AFTER syncing group_lwtv_debug_logging.json in ACF → Sync.
	 */
	public function migrate_debuglogging() {
		$old_options = get_option( 'lwtv_debug_logging_options', array() );

		if ( empty( $old_options ) ) {
			\WP_CLI::log( 'No debug logging options found in lwtv_debug_logging_options. Nothing to migrate.' );
			return;
		}

		$migrated = 0;

		// debug_mode: CMB2 checkbox 'on' → ACF true_false '1'.
		if ( array_key_exists( 'debug_mode', $old_options ) ) {
			$value = ( 'on' === $old_options['debug_mode'] ) ? '1' : '0';
			update_option( 'options_debug_mode', $value );
			update_option( '_options_debug_mode', 'field_lwtv_debug_mode' );
			++$migrated;
			\WP_CLI::log( 'Migrated: debug_mode = ' . $value );
		}

		// log_topics: CMB2 multicheck array → ACF checkbox array (values are the same).
		if ( array_key_exists( 'log_topics', $old_options ) && is_array( $old_options['log_topics'] ) ) {
			update_option( 'options_log_topics', $old_options['log_topics'] );
			update_option( '_options_log_topics', 'field_lwtv_log_topics' );
			++$migrated;
			\WP_CLI::log( 'Migrated: log_topics (' . count( $old_options['log_topics'] ) . ' topics)' );
		}

		if ( 0 === $migrated ) {
			\WP_CLI::log( 'No fields required migration.' );
		} else {
			\WP_CLI::success( "Migration completed. {$migrated} field(s) migrated." );
		}
	}
}

\WP_CLI::add_command( 'lwtv migrate', 'WP_CLI_LWTV_Migrate' );
