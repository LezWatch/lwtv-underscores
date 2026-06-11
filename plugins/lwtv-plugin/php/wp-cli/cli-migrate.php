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
	 * : Optional. Secondary data. ACF uses [waystowatch].
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
				case 'charshowgroup':
					$this->migrate_charshowgroup();
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
}

\WP_CLI::add_command( 'lwtv migrate', 'WP_CLI_LWTV_Migrate' );
