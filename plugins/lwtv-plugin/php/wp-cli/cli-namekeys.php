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
	 * How many actors to prime and process per pass.
	 *
	 * Small enough that the primed meta cache never holds every actor's meta at
	 * once, large enough that the query count stays in the dozens.
	 */
	public const BATCH = 200;

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

		/*
		 * Every status that is really an actor. Private matters: Actors\Privacy
		 * flips a post private on request, and a duplicate of a deliberately
		 * hidden actor is the worst kind to create by accident.
		 *
		 * The title rides along with the ID on purpose. post_title as stored is
		 * exactly what get_post_field( ..., 'raw' ) hands back -- Name_Key has to
		 * see what the editor typed, not what wptexturize makes of it -- and
		 * taking it here spares the loop a get_post() query per actor, which is
		 * the larger half of this command's query count.
		 */
		$actors = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )
				ORDER BY ID ASC",
				Actors::SLUG
			)
		);

		if ( empty( $actors ) ) {
			\WP_CLI::warning( 'No actors found.' );
			return;
		}

		$total     = count( $actors );
		$progress  = \WP_CLI\Utils\make_progress_bar( 'Keying actors', $total );
		$written   = 0;
		$unchanged = 0;
		$unkeyable = 0;

		foreach ( array_chunk( $actors, self::BATCH ) as $batch ) {
			/*
			 * One meta query per batch rather than one per actor. Nothing has
			 * primed these posts -- there is no WP_Query in front of this -- so
			 * each get_post_meta() below would otherwise go to the database on
			 * its own. Primed per batch, not all at once: the whole table's meta
			 * in one array is how a backfill runs out of memory as the site
			 * grows, and this command is meant to stay re-runnable.
			 */
			update_postmeta_cache( array_map( 'intval', wp_list_pluck( $batch, 'ID' ) ) );

			foreach ( $batch as $actor ) {
				$progress->tick();

				$post_id = (int) $actor->ID;
				$title   = (string) $actor->post_title;

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

			$this->free_memory();
		}

		$progress->finish();

		\WP_CLI::log( '' );
		\WP_CLI::log( 'Actors seen:  ' . $total );
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

	/**
	 * Drop the in-process caches that grow across a long backfill.
	 *
	 * Deliberately NOT wp_cache_flush(). See free_memory() in cli-calc.php.
	 */
	private function free_memory(): void {
		global $wpdb, $wp_object_cache;

		// Only populated when SAVEQUERIES is on, but it grows without bound when
		// it is, and a debug-enabled backfill is exactly when memory runs out.
		$wpdb->queries = array();

		if ( ! is_object( $wp_object_cache ) ) {
			return;
		}

		foreach ( array( 'group_ops', 'stats', 'memcache_debug', 'cache' ) as $property ) {
			if ( property_exists( $wp_object_cache, $property ) ) {
				$wp_object_cache->$property = array();
			}
		}

		// Redis/Memcached drop-ins expose this to re-establish their connection
		// after the local cache is dropped.
		if ( method_exists( $wp_object_cache, '__remoteset' ) ) {
			$wp_object_cache->__remoteset();
		}
	}
}

\WP_CLI::add_command( 'lwtv namekeys', 'WP_CLI_LWTV_Name_Keys' );
