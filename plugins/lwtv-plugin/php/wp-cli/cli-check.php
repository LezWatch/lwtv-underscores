<?php
/*
 * WP CLI Commands for LezWatch.TV
 *
 * These commands are 'check' tools.
 */

// Bail if directly accessed
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	die();
}

use LWTV\Debugger\Actors as Actors_Debugger;
use LWTV\Debugger\Queers as Queers_Debugger;
use LWTV\Queeries\Is_Actor_Queer;
use LWTV\CPTs\Actors as CPT_Actors;

/**
 * LezWatch.TV commands to check the sanctity of content.
 */
class WP_CLI_LWTV_Check {

	/**
	 * @var string
	 */
	public $format;

	/**
	 * @var string
	 */
	public $check;

	/**
	 * @var string
	 */
	public $second;

	/**
	 * @var bool
	 */
	public $fix_it = false;

	/**
	 * @var bool
	 */
	public $dry_run = true;

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
	 * Check that all 'types' of a thing are okay.
	 *
	 * ## OPTIONS
	 *
	 * <check_name>
	 * : Type to check (i.e. 'queerchars').
	 *
	 * [<actor_id>]
	 * : Post ID to check
	 *
	 * [--fix-it]
	 * : Attempt to fix issues (not available for all checks).
	 * default: false
	 *
	 * [--dry-run]
	 * : Preview what would be changed without making any modifications.
	 * default: false
	 *
	 * ## EXAMPLES
	 *
	 * wp lwtv check queerchars
	 * wp lwtv check wiki [id]
	 * wp lwtv check isqueer [id]
	 * wp lwtv check badmeta characters --dry-run
	 * wp lwtv check badmeta shows
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function __invoke( $args, $assoc_args = array() ) {

		$this->format  = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$this->fix_it  = \WP_CLI\Utils\get_flag_value( $assoc_args, 'fix-it', null );
		$this->dry_run = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$this->check   = $args[0];
		$this->second  = isset( $args[1] ) ? $args[1] : '';

		try {
			$this->run_checker( $this->check, $this->second );
		} catch ( \Exception $exception ) {
			\WP_CLI::error( $exception->getMessage(), false );
		}
	}

	/**
	 * Check what we've got.
	 */
	public function run_checker( $check_type, $second ) {
		$valid_types = array( 'queerchars', 'wiki', 'isqueer', 'badmeta' );

		// Last sanity check: Is the post ID a member of THIS post type...
		if ( ! in_array( $check_type, $valid_types, true ) ) {
			$display_types = implode( ' or ', $valid_types );

			// Language check.
			if ( 3 >= count( $valid_types ) ) {
				$last          = array_pop( $valid_types );
				$display_types = implode( ', ', $valid_types ) . ' or ' . $last;
			}

			\WP_CLI::error( 'You can only run checks on ' . $display_types . '.' . $check_type . ' is invalid.' );
		}

		// Run the appropriate checker:
		switch ( $check_type ) {
			case 'queerchars':
				$this->run_queerchecker();
				break;
			case 'wiki':
				$this->run_wiki( $second );
				break;
			case 'isqueer':
				$this->run_isqueer( $second );
				break;
			case 'badmeta':
				$this->run_badmeta_checker( $second, (bool) $this->dry_run );
				break;
		}
	}

	/**
	 * Check wiki data.
	 *
	 * Currently only supports actors.
	 */
	public function run_wiki( $actor_id ) {
		$post_type    = get_post_type( $actor_id );
		$items        = array();
		$return_array = array( 'id', 'name', 'wikidata', 'birth', 'death', 'imdb', 'wikipedia', 'website', 'instagram', 'twitter', 'facebook' );

		// Last sanity check: Is the post ID a member of THIS post type...
		if ( CPT_Actors::SLUG !== $post_type ) {
			$real_post_type = rtrim( str_replace( 'post_type_', '', $post_type ), 's' );
			\WP_CLI::error( 'You are currently checking wikidata for actors, but ' . get_the_title( $actor_id ) . ' (#' . $actor_id . ') is a ' . $real_post_type . ', not an actor.' );
		}

		// Even though we only support actors...
		if ( CPT_Actors::SLUG === $post_type ) {
			// Do the thing!
			$items        = ( new Actors_Debugger() )->check_actors_wikidata( $actor_id );

			if ( empty( $items ) ) {
				\WP_CLI::error( 'Something has gone horribly wrong. Go get Mika.' );
			} elseif ( ! isset( $items[ $actor_id ]['wikipedia'] ) ) {
				\WP_CLI::error( 'No data from WikiData.' );
			}
		}

		\WP_CLI::success( 'WikiData comparison for ' . get_the_title( $actor_id ) . ' complete!' );
		\WP_CLI\Utils\format_items( $this->format, $items, $return_array );
	}

	/**
	 * Check the queers.
	 */
	public function run_queerchecker() {
		$items = ( new Queers_Debugger() )->find_queer_chars();

		if ( ! isset( $items ) ) {
			\WP_CLI::error( 'An unexpected error has occurred. Go get Mika.' );
		} elseif ( empty( $items ) || ! is_array( $items ) ) {
			// Everything passed.
			\WP_CLI::success( 'Awesome! Check passes without any attention needed.' );
		} else {
			// These need attention
			\WP_CLI::log( count( $items ) . ' character(s) need your attention.' );
			\WP_CLI\Utils\format_items( $this->format, $items, array( 'url', 'id', 'problem' ) );
			\WP_CLI::success( 'Search complete.' );
		}
	}

	/**
	 * Check if an actor is queer.
	 *
	 * @param string $actor_id Post ID of the actor to check.
	 */
	public function run_isqueer( $actor_id ) {
		$post_type = get_post_type( $actor_id );

		// Last sanity check: Is the post ID a member of THIS post type...
		if ( CPT_Actors::SLUG !== $post_type ) {
			$real_post_type = rtrim( str_replace( 'post_type_', '', $post_type ), 's' );
			\WP_CLI::error( 'You are currently checking wikidata for actors, but ' . get_the_title( $actor_id ) . ' (#' . $actor_id . ') is a ' . $real_post_type . ', not an actor.' );
		}

		// Check 'em!
		$is_queer = ( new Is_Actor_Queer() )->make( $actor_id ) ? 'is queer' : 'is NOT queer';

		\WP_CLI::success( get_the_title( $actor_id ) . ' ' . $is_queer );
	}

	/**
	 * Find (and optionally delete) meta keys that belong to the wrong post type.
	 *
	 * - badmeta characters: characters carrying lezshows_ meta
	 * - badmeta shows:      shows carrying lezchars_ meta
	 *
	 * @param string $cpt_type  'characters' or 'shows'.
	 * @param bool   $dry_run   When true, list matches without deleting.
	 */
	public function run_badmeta_checker( string $cpt_type, bool $dry_run ): void {
		global $wpdb;

		$config = array(
			'characters' => array(
				'post_type'   => 'post_type_characters',
				'meta_prefix' => 'lezshows\_%',
				'label'       => 'character',
			),
			'shows'      => array(
				'post_type'   => 'post_type_shows',
				'meta_prefix' => 'lezchars\_%',
				'label'       => 'show',
			),
		);

		if ( ! isset( $config[ $cpt_type ] ) ) {
			\WP_CLI::error( 'Invalid type. Use: characters or shows.' );
		}

		$post_type   = $config[ $cpt_type ]['post_type'];
		$meta_prefix = $config[ $cpt_type ]['meta_prefix'];
		$label       = $config[ $cpt_type ]['label'];

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, pm.meta_key
				 FROM {$wpdb->posts} p
				 JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				 WHERE p.post_type = %s
				   AND pm.meta_key LIKE %s
				 ORDER BY p.ID, pm.meta_key",
				$post_type,
				$meta_prefix
			)
		);
		// phpcs:enable

		if ( empty( $results ) ) {
			\WP_CLI::success( "No {$label}s found with mismatched meta. All clear!" );
			return;
		}

		$total    = count( $results );
		$post_ids = array_unique( array_column( (array) $results, 'ID' ) );
		$n_posts  = count( $post_ids );

		if ( $dry_run ) {
			\WP_CLI::log( "[DRY RUN] Found {$total} mismatched meta row(s) across {$n_posts} {$label}(s)." );
			$items = array_map(
				static function ( $row ) {
					return array(
						'id'       => $row->ID,
						'title'    => $row->post_title,
						'meta_key' => $row->meta_key,
					);
				},
				(array) $results
			);
			\WP_CLI\Utils\format_items( $this->format, $items, array( 'id', 'title', 'meta_key' ) );
			\WP_CLI::log( 'Run without --dry-run to delete these rows.' );
			return;
		}

		$deleted  = 0;
		$progress = \WP_CLI\Utils\make_progress_bar( 'Deleting  mismatched meta row(s) for ' . $label . '(s) ', $n_posts );

		foreach ( $results as $row ) {
			if ( delete_post_meta( (int) $row->ID, $row->meta_key ) ) {
				$progress->tick();
				++$deleted;
			}
		}

		\WP_CLI::success( "Deleted {$deleted} of {$total} mismatched meta row(s) from {$n_posts} {$label}(s)." );
	}
}

\WP_CLI::add_command( 'lwtv check', 'WP_CLI_LWTV_Check' );
