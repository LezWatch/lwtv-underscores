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
}

\WP_CLI::add_command( 'lwtv migrate', 'WP_CLI_LWTV_Migrate' );
