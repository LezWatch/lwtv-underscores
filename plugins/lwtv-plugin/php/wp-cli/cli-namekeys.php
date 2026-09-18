<?php
/*
 * WP CLI Commands for LezWatch.TV
 *
 * Backfills the comparable name keys that the duplicate-actor check reads.
 * CPTs\Actors::save_name_keys() keeps them current from then on, so this is for
 * the actors that existed before the keys did.
 */

// Bail if directly accessed
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	die();
}

use LWTV\CPTs\Actors;
use LWTV\_Helpers\Name_Key;

/**
 * LezWatch.TV commands for actor name keys.
 */
class WP_CLI_LWTV_Name_Keys {

	/**
	 * Write the comparable name keys for every actor.
	 *
	 * Safe to re-run: an actor whose keys already match is left alone, so a
	 * second pass writes nothing.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report what would change without writing anything.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lwtv namekeys backfill --dry-run
	 *     wp lwtv namekeys backfill
	 *
	 * ## A NOTE ON PRODUCTION
	 *
	 * WP-CLI on prod does not load the object-cache drop-in, so meta written
	 * here lands in the database without invalidating what the web layer has
	 * cached for those posts. Nothing breaks -- the duplicate warning simply
	 * will not see the backfilled actors until that cache turns over -- but do
	 * not read a quiet warning straight after a prod backfill as proof the keys
	 * are missing.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function backfill( $args, $assoc_args = array() ) {
		global $wpdb;

		$dry_run = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

		// Every status that is really an actor. Private matters: Actors\Privacy
		// flips a post private on request, and a duplicate of a deliberately
		// hidden actor is the worst kind to create by accident.
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )
				ORDER BY ID ASC",
				Actors::SLUG
			)
		);

		$post_ids = array_map( 'intval', (array) $post_ids );

		if ( empty( $post_ids ) ) {
			\WP_CLI::warning( 'No actors found.' );
			return;
		}

		$progress  = \WP_CLI\Utils\make_progress_bar( 'Keying actors', count( $post_ids ) );
		$written   = 0;
		$unchanged = 0;
		$unkeyable = 0;

		foreach ( $post_ids as $post_id ) {
			$progress->tick();

			$title = (string) get_post_field( 'post_title', $post_id, 'raw' );

			$variants = Name_Key::variants( $title );
			$ends     = Name_Key::ends( $title );

			if ( empty( $variants ) ) {
				++$unkeyable;
				\WP_CLI::debug( sprintf( 'Actor %d has no keyable name: "%s"', $post_id, $title ), 'lwtv' );
				continue;
			}

			// $single false on purpose: one row per reading of the name.
			$stored_variants = get_post_meta( $post_id, Actors::NAME_KEY_META, false );
			$stored_ends     = (string) get_post_meta( $post_id, Actors::NAME_ENDS_META, true );
			$wanted_ends     = $ends[0] ?? '';

			if ( $stored_variants === $variants && $stored_ends === $wanted_ends ) {
				++$unchanged;
				continue;
			}

			++$written;

			if ( $dry_run ) {
				continue;
			}

			delete_post_meta( $post_id, Actors::NAME_KEY_META );

			foreach ( $variants as $variant ) {
				add_post_meta( $post_id, Actors::NAME_KEY_META, $variant );
			}

			if ( '' === $wanted_ends ) {
				delete_post_meta( $post_id, Actors::NAME_ENDS_META );
			} else {
				update_post_meta( $post_id, Actors::NAME_ENDS_META, $wanted_ends );
			}
		}

		$progress->finish();

		\WP_CLI::log( '' );
		\WP_CLI::log( 'Actors seen:  ' . count( $post_ids ) );
		\WP_CLI::log( ( $dry_run ? 'Would write:  ' : 'Written:      ' ) . $written );
		\WP_CLI::log( 'Unchanged:    ' . $unchanged );
		\WP_CLI::log( 'Unkeyable:    ' . $unkeyable );
		\WP_CLI::log( '' );

		if ( $dry_run ) {
			\WP_CLI::success( 'Dry run. Nothing was written.' );
			return;
		}

		\WP_CLI::success( 'Name keys are up to date.' );
	}
}

\WP_CLI::add_command( 'lwtv namekeys', 'WP_CLI_LWTV_Name_Keys' );
