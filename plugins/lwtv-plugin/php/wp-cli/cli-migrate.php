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
			if ( 'waystowatch' === $subtype ) {
				$this->migrate_waystowatch();
			} else {
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
}

\WP_CLI::add_command( 'lwtv migrate', 'WP_CLI_LWTV_Migrate' );
