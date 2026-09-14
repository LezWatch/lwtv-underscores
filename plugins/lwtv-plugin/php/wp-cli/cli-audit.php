<?php
/*
 * WP CLI Commands for LezWatch.TV
 *
 * Audit tools: compare on-air shows and their characters against TVMaze.
 */

// Bail if directly accessed
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	die();
}

use LWTV\Calendar\TVMaze;
use LWTV\CPTs\Actors as CPT_Actors;
use LWTV\CPTs\Shows as CPT_Shows;
use LWTV\CPTs\Shows\Airdates;
use LWTV\CPTs\Characters as CPT_Characters;
use LWTV\Debugger\Actors as Actors_Debugger;
use LWTV\Debugger\Audit;
use LWTV\Debugger\Build\Actor_Death_Rules;
use LWTV\This_Year\Build\Shared_Builder;

/**
 * LezWatch.TV commands to audit shows and characters against TVMaze.
 */
class WP_CLI_LWTV_Audit {

	/**
	 * @var string
	 */
	public $format;

	/**
	 * TV Maze API URL.
	 */
	public const TVMAZE_URL = 'https://api.tvmaze.com';

	/**
	 * Genres whose character data cannot be audited via TVMaze.
	 * Voice actors play multiple characters; mapping is manual.
	 */
	public const SKIP_GENRES = array( 'animation', 'anime' );

	/**
	 * Wait time.
	 */
	public const WAIT_TIME = 500000;

	/**
	 * Columns for an actor death audit row.
	 */
	public const ACTOR_FIELDS = array( 'actor_id', 'actor', 'issue', 'wikidata', 'birth', 'death', 'action' );

	/**
	 * Verdicts from character_appeared_in_year().
	 *
	 * The two UNKNOWN values are the important ones: TVMaze often cannot answer
	 * the question at all, and treating that silence as "did not appear" is how
	 * you end up telling an editor to add a year the character was never in.
	 */
	public const APPEARED_YES               = 'yes';
	public const APPEARED_NO                = 'no';
	public const APPEARED_UNKNOWN_MAIN_CAST = 'unknown-main-cast';
	public const APPEARED_UNKNOWN_NO_DATA   = 'unknown-no-data';

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
	 * Audit shows and characters against TVMaze.
	 *
	 * ## OPTIONS
	 *
	 * <type>
	 * : What to audit.
	 * options:
	 * - shows (catalog: all on-air shows, status + character roles)
	 * - show  (deep: one show, per-episode guest cast)
	 * - actors (all actors with no death date, against WikiData)
	 * - actor  (one actor, against WikiData)
	 * - ignore  (acknowledge one character+show+issue so it stops recurring)
	 * - ignores (list a character's acknowledged items)
	 * - reset   (clear a scope's baseline, or all baselines)
	 * ---
	 *
	 * [<id>]
	 * : Post ID (required for 'show' and 'actor').
	 *
	 * [--show=<id>]
	 * : Ignore only. Show post ID the acknowledgement applies to.
	 *
	 * [--issue=<type>]
	 * : Ignore only. Character-level issue type to acknowledge: missing-year, verify-year, unconfirmed-year.
	 *
	 * [--remove]
	 * : Ignore only. Remove a previously acknowledged item instead of adding one.
	 *
	 * [--letter=<letter>]
	 * : Catalog and actors only. Restrict to one alphabet bucket: a-z, 'num' (#), or 'intl' (-).
	 *
	 * [--unresolved]
	 * : Actors only. Also list actors we cannot check at all (no Q-ID, no usable
	 * IMDb ID, or WikiData unreadable) rather than only possible deaths.
	 *
	 * [--roles=<roles>]
	 * : Which character roles to audit.
	 * options:
	 *   - regular   (default)
	 *   - recurring
	 *   - guests
	 *   - all       (show singular ONLY)
	 *   - none      (show status ONLY)
	 * default: regular
	 *
	 * [--all]
	 * : Deep only. Audit the show's full history, not just the current year.
	 *
	 * [--show-resolved]
	 * : Also list items resolved since the last run of this scope (catalog/deep only).
	 *
	 * [--yes]
	 * : Answer yes to confirmation prompts (reset, and large deep-audit jobs).
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Quad-monthly catalog audit, one letter at a time
	 *     wp lwtv audit shows --letter=a --format=csv > audit-a.csv
	 *
	 *     # Catalog audit checking only show statuses (no characters)
	 *     wp lwtv audit shows --roles=none
	 *
	 *     # Deep historical audit of all character roles on a single show
	 *     wp lwtv audit show 12345 --roles=all --all
	 *
	 *     # Which actors does WikiData think have died since we last looked?
	 *     wp lwtv audit actors --letter=a
	 *
	 *     # Same, plus the actors we have no way to check
	 *     wp lwtv audit actors --letter=a --unresolved
	 *
	 *     # Check one actor
	 *     wp lwtv audit actor 6789
	 *
	 *     # Acknowledge a missing-year flag so it stops recurring
	 *     wp lwtv audit ignore 456 --show=123 --issue=missing-year
	 *
	 *     # List a character's acknowledgements, then remove one
	 *     wp lwtv audit ignores 456
	 *     wp lwtv audit ignore 456 --show=123 --issue=missing-year --remove
	 *
	 *     # Start a scope fresh (next run shows everything as new)
	 *     wp lwtv audit reset catalog_a_regular
	 *
	 * @when after_wp_load
	 */
	public function __invoke( array $args, array $assoc_args = array() ) {
		$this->format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$type         = $args[0] ?? 'shows';

		try {
			switch ( $type ) {
				case 'shows':
					$this->audit_catalog( $assoc_args );
					break;
				case 'show':
					$this->audit_single( (int) ( $args[1] ?? 0 ), $assoc_args );
					break;
				case 'actors':
					$this->audit_actor_deaths( $assoc_args );
					break;
				case 'actor':
					$this->audit_actor_death_single( (int) ( $args[1] ?? 0 ) );
					break;
				case 'ignore':
					$this->cmd_ignore( (int) ( $args[1] ?? 0 ), $assoc_args );
					break;
				case 'ignores':
					$this->cmd_ignores( (int) ( $args[1] ?? 0 ) );
					break;
				case 'reset':
					$this->cmd_reset( (string) ( $args[1] ?? '' ), $assoc_args );
					break;
				default:
					\WP_CLI::error( 'Invalid audit type. Use: shows, show <id>, actors, actor <id>, ignore, ignores, reset.' );
			}
		} catch ( Exception $exception ) {
			\WP_CLI::error( $exception->getMessage() );
		}
	}

	/* ------------------------------------------------------------------
	 * CATALOG AUDIT
	 * ---------------------------------------------------------------- */

	/**
	 * Audit all on-air shows (optionally one letter bucket).
	 *
	 * @param array $assoc_args Associative args.
	 */
	private function audit_catalog( array $assoc_args ): void {
		$roles_flag = strtolower( trim( \WP_CLI\Utils\get_flag_value( $assoc_args, 'roles', 'regular' ) ) );

		if ( 'all' === $roles_flag ) {
			\WP_CLI::error( 'The --roles=all option is only available when auditing a single show: wp lwtv audit show <id> --roles=all' );
		}

		$roles_to_audit = $this->parse_roles( $roles_flag );

		$current_year = (int) gmdate( 'Y' );
		$tvmaze       = new TVMaze();
		$results      = array();
		$is_table     = ( 'table' === $this->format );

		$letter_raw    = \WP_CLI\Utils\get_flag_value( $assoc_args, 'letter', '' );
		$show_resolved = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'show-resolved', false );
		$letter        = $this->parse_letter( $letter_raw );

		$show_ids = get_posts(
			array(
				'post_type'      => CPT_Shows::SLUG,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'   => 'lezshows_on_air',
						'value' => 'yes',
					),
				),
			)
		);

		if ( '' !== $letter ) {
			$builder = new Shared_Builder();
			$marker  = ( 1 === strlen( $letter ) && ctype_alpha( $letter ) ) ? strtoupper( $letter ) : $letter;

			$show_ids = array_values(
				array_filter(
					$show_ids,
					fn( $id ) => $builder->get_character_marker( get_the_title( $id ) ) === $marker
				)
			);
		}

		if ( empty( $show_ids ) ) {
			\WP_CLI::error( 'No on-air shows found to audit' . ( $letter ? ' in that letter bucket' : '' ) . '.' );
		}

		$progress = $is_table
			? \WP_CLI\Utils\make_progress_bar( sprintf( 'Auditing %d on-air shows', count( $show_ids ) ), count( $show_ids ) )
			: null;

		foreach ( $show_ids as $show_id ) {
			if ( $progress ) {
				$progress->tick();
			}

			$show_title = get_the_title( $show_id );
			$show_info  = $this->resolve_show( $tvmaze, $show_id, $show_title );

			if ( false === $show_info ) {
				$results[] = $this->build_row( $show_id, 'No Match', '', '', '', 'Add IMDb/TVMaze ID or audit manually', 'no-match' );
				usleep( self::WAIT_TIME );
				continue;
			}

			$status     = $show_info['status'] ?? 'Unknown';
			$ended_raw  = $show_info['ended'] ?? '';
			$ended_year = ! empty( $ended_raw ) ? substr( $ended_raw, 0, 4 ) : '';

			if ( 'Ended' === $status ) {
				$ended_row = $this->evaluate_ended_show( $show_id, $status, $ended_year );

				// Only bail early when the show still needs closing out; if our end
				// year is already right the final season's characters still need
				// their Years Appears audited.
				if ( null !== $ended_row ) {
					$results[] = $ended_row;

					if ( 'ended' === $ended_row['issue_type'] ) {
						usleep( self::WAIT_TIME );
						continue;
					}
				}
			}

			if ( 'To Be Determined' === $status ) {
				$results[] = $this->build_row( $show_id, $status, '', '', '', 'Review: show in limbo on TVMaze', 'tbd' );
			}

			// If roles is set to 'none', skip character auditing entirely.
			if ( empty( $roles_to_audit ) ) {
				usleep( self::WAIT_TIME );
				continue;
			}

			// Animated? Character data can't be audited via TVMaze
			if ( has_term( self::SKIP_GENRES, 'lez_genres', $show_id ) ) {
				usleep( self::WAIT_TIME );
				continue;
			}

			$aired_this_year = $this->aired_in_year( $show_info, $current_year );

			// Only audit characters on positive confirmation of current-year episodes.
			if ( true === $aired_this_year ) {
				$results = array_merge( $results, $this->audit_characters( $show_id, $status, $current_year, $roles_to_audit ) );
			}

			usleep( self::WAIT_TIME );
		}

		if ( $progress ) {
			$progress->finish();
		}

		$scope_roles = ( 'guests' === $roles_flag ) ? 'guest' : $roles_flag;
		$scope       = $this->catalog_scope( $this->letter_token( $letter_raw ), $scope_roles );
		$this->output_results( $scope, $results, $show_resolved );
	}

	/**
	 * Audit living characters on one show for the current year based on specified roles.
	 *
	 * The catalog pass deliberately fetches no per-episode cast -- that is the
	 * deep audit's job, and doing it for every on-air show would run to thousands
	 * of API calls. So every finding here is a gap in *our* data awaiting human
	 * confirmation, never a claim that TVMaze confirms the appearance.
	 *
	 * @param int    $show_id        Show post ID.
	 * @param string $status         TVMaze status (for the row).
	 * @param int    $current_year   Current year.
	 * @param array  $roles_to_audit Roles array to check against.
	 * @return array
	 */
	private function audit_characters( int $show_id, string $status, int $current_year, array $roles_to_audit ): array {
		$rows = array();

		foreach ( $this->get_show_characters( $show_id ) as $char_id ) {
			if ( has_term( 'dead', 'lez_cliches', $char_id ) ) {
				continue; // Skip the tragically troped.
			}

			foreach ( $this->get_show_rows_for_character( $char_id, $show_id ) as $row ) {
				$type = $row['type'] ?? '';

				if ( ! in_array( $type, $roles_to_audit, true ) ) {
					continue;
				}

				$appears = $this->clean_appears( $row );

				if ( in_array( $current_year, $appears, true ) ) {
					continue; // Already recorded; nothing to ask about.
				}

				$verdict = $this->character_appeared_in_year( $this->character_names( $char_id ), array() );

				$rows[] = $this->build_row(
					$show_id,
					$status,
					'',
					get_the_title( $char_id ),
					$type,
					$this->unconfirmed_action( $verdict, $current_year ),
					'unconfirmed-year',
					$char_id,
					$current_year
				);
			}
		}

		return $rows;
	}

	/* ------------------------------------------------------------------
	 * DEEP AUDIT (single show)
	 * ---------------------------------------------------------------- */

	/**
	 * Deep-audit one show against per-episode TVMaze guest cast.
	 *
	 * @param int   $show_id    Show post ID.
	 * @param array $assoc_args Associative args.
	 */
	private function audit_single( int $show_id, array $assoc_args ): void {
		if ( ! $show_id || CPT_Shows::SLUG !== get_post_type( $show_id ) ) {
			\WP_CLI::error( 'Please provide a valid show ID: wp lwtv audit show <id>' );
		}

		if ( has_term( self::SKIP_GENRES, 'lez_genres', $show_id ) ) {
			\WP_CLI::error( 'This show is animated/anime. Voice actors play multiple characters, so TVMaze cast data cannot be mapped to LWTV characters. This one stays manual (bless the fan wikis).' );
		}

		$roles_flag     = strtolower( trim( \WP_CLI\Utils\get_flag_value( $assoc_args, 'roles', 'regular' ) ) );
		$roles_to_audit = $this->parse_roles( $roles_flag );

		$do_all       = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );
		$current_year = (int) gmdate( 'Y' );
		$is_table     = ( 'table' === $this->format );

		$show_resolved = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'show-resolved', false );
		$scope         = $this->show_scope( $show_id, $roles_to_audit, $do_all );

		$show_title = get_the_title( $show_id );
		$tvmaze     = new TVMaze();
		$show_info  = $this->resolve_show( $tvmaze, $show_id, $show_title );

		if ( false === $show_info ) {
			\WP_CLI::error( 'No TVMaze match for "' . $show_title . '". Add an IMDb or TVMaze ID first.' );
		}

		$status     = $show_info['status'] ?? 'Unknown';
		$ended_raw  = $show_info['ended'] ?? '';
		$ended_year = ! empty( $ended_raw ) ? substr( $ended_raw, 0, 4 ) : '';
		$tvmaze_id  = (int) $show_info['id'];

		// If roles is 'none', output show status if needed and terminate.
		if ( empty( $roles_to_audit ) ) {
			$results = array();
			if ( 'Ended' === $status ) {
				$ended_row = $this->evaluate_ended_show( $show_id, $status, $ended_year );
				if ( null !== $ended_row ) {
					$results[] = $ended_row;
				}
			} elseif ( 'To Be Determined' === $status ) {
				$results[] = $this->build_row( $show_id, $status, '', '', '', 'Review: show in limbo on TVMaze', 'tbd' );
			}
			$this->output_results( $scope, $results, $show_resolved );
			return;
		}

		// All-time main cast.
		$cast_names = array();
		$cast       = $this->tvmaze_get( self::TVMAZE_URL . '/shows/' . $tvmaze_id . '/cast' );
		if ( is_array( $cast ) ) {
			foreach ( $cast as $member ) {
				$cast_names[] = $this->normalize_name( $member['person']['name'] ?? '' );
				$cast_names[] = $this->normalize_name( $member['character']['name'] ?? '' );
			}
			$cast_names = array_filter( array_unique( $cast_names ) );
		}

		// Full episode list, grouped by year.
		$episodes = $this->tvmaze_get( self::TVMAZE_URL . '/shows/' . $tvmaze_id . '/episodes' );
		if ( ! is_array( $episodes ) ) {
			\WP_CLI::error( 'Could not fetch the episode list from TVMaze.' );
		}

		$eps_by_year = array();
		foreach ( $episodes as $episode ) {
			if ( ! empty( $episode['airdate'] ) && ! empty( $episode['id'] ) ) {
				$eps_by_year[ (int) substr( $episode['airdate'], 0, 4 ) ][] = (int) $episode['id'];
			}
		}

		$years = $do_all ? array_keys( $eps_by_year ) : array( $current_year );
		sort( $years );

		// Estimate uncached API calls and confirm big jobs.
		$uncached = 0;
		foreach ( $years as $year ) {
			if ( false === lwtv_plugin()->get_transient( 'lwtv_audit_names_' . $show_id . '_' . $year ) ) {
				$uncached += count( $eps_by_year[ $year ] ?? array() );
			}
		}
		if ( $uncached > 300 ) {
			\WP_CLI::confirm(
				sprintf(
					'This needs ~%d uncached TVMaze calls (roughly %d minutes). Continue?',
					$uncached,
					(int) ceil( $uncached / 100 )
				),
				$assoc_args
			);
		}

		// Build normalized name sets per year.
		$names_by_year = array();
		foreach ( $years as $year ) {
			$names_by_year[ $year ] = $this->get_year_names( $show_id, $year, $eps_by_year[ $year ] ?? array(), $current_year, $is_table );
		}

		// Audit the characters.
		$results = array();

		foreach ( $this->get_show_characters( $show_id ) as $char_id ) {
			$is_dead = has_term( 'dead', 'lez_cliches', $char_id );

			if ( $is_dead && ! $do_all ) {
				continue;
			}

			$char_names = $this->character_names( $char_id );

			foreach ( $this->get_show_rows_for_character( $char_id, $show_id ) as $row ) {
				$type = $row['type'] ?? '';
				if ( ! in_array( $type, $roles_to_audit, true ) ) {
					continue;
				}

				$appears = $this->clean_appears( $row );

				foreach ( $years as $year ) {
					if ( empty( $eps_by_year[ $year ] ) ) {
						continue;
					}

					$verdict = $this->character_appeared_in_year(
						$char_names,
						array(
							'main_cast'      => $cast_names,
							'year_names'     => $names_by_year[ $year ],
							'has_year_names' => true,
						)
					);
					$has     = in_array( $year, $appears, true );

					if ( self::APPEARED_YES === $verdict && ! $has ) {
						$results[] = $this->build_row( $show_id, $status, '', get_the_title( $char_id ), $type, 'TVMaze shows ' . $year . ' -- add?', 'missing-year', $char_id, $year );
					} elseif ( self::APPEARED_NO === $verdict && $has ) {
						$results[] = $this->build_row( $show_id, $status, '', get_the_title( $char_id ), $type, 'Verify ' . $year . ' -- no TVMaze appearance found', 'verify-year', $char_id, $year );
					} elseif ( $this->is_unknown_appearance( $verdict ) && ! $has ) {
						$results[] = $this->build_row( $show_id, $status, '', get_the_title( $char_id ), $type, $this->unconfirmed_action( $verdict, $year ), 'unconfirmed-year', $char_id, $year );
					}
				}
			}
		}

		$this->output_results( $scope, $results, $show_resolved );
	}

	/**
	 * Get (and cache) the normalized names seen in a show's episodes for one year.
	 *
	 * @param int   $show_id      Show post ID (cache key only).
	 * @param int   $year         Year being compiled.
	 * @param array $episode_ids  TVMaze episode IDs for that year.
	 * @param int   $current_year Current year.
	 * @param bool  $is_table     Whether to show progress.
	 * @return array Normalized names.
	 */
	private function get_year_names( int $show_id, int $year, array $episode_ids, int $current_year, bool $is_table ): array {
		$cache_key = 'lwtv_audit_names_' . $show_id . '_' . $year;
		$cached    = lwtv_plugin()->get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$names     = array();
		$had_error = false;
		$progress  = ( $is_table && count( $episode_ids ) > 10 )
			? \WP_CLI\Utils\make_progress_bar( sprintf( 'Fetching %d guest casts for %d', count( $episode_ids ), $year ), count( $episode_ids ) )
			: null;

		foreach ( $episode_ids as $episode_id ) {
			if ( $progress ) {
				$progress->tick();
			}

			$guestcast = $this->tvmaze_get( self::TVMAZE_URL . '/episodes/' . $episode_id . '/guestcast' );

			if ( is_array( $guestcast ) ) {
				foreach ( $guestcast as $member ) {
					$names[] = $this->normalize_name( $member['person']['name'] ?? '' );
					$names[] = $this->normalize_name( $member['character']['name'] ?? '' );
				}
			} else {
				// A failed fetch (not an empty guest cast) means this name set
				// is incomplete; cache it briefly so a rerun can heal it.
				$had_error = true;
			}

			usleep( self::WAIT_TIME );
		}

		if ( $progress ) {
			$progress->finish();
		}

		$names = array_values( array_filter( array_unique( $names ) ) );

		if ( $had_error ) {
			$ttl = 30 * MINUTE_IN_SECONDS;
		} else {
			$ttl = ( $year < $current_year ) ? YEAR_IN_SECONDS : DAY_IN_SECONDS;
		}

		lwtv_plugin()->set_transient( $cache_key, $names, $ttl );

		return $names;
	}

	/**
	 * Acknowledge (or un-acknowledge) a character+show+issue.
	 *
	 * @param int   $char_id    Character post ID.
	 * @param array $assoc_args Associative args.
	 */
	private function cmd_ignore( int $char_id, array $assoc_args ): void {
		if ( ! $char_id || CPT_Characters::SLUG !== get_post_type( $char_id ) ) {
			\WP_CLI::error( 'Please provide a valid character ID: wp lwtv audit ignore <char_id> --show=<id> --issue=<type>' );
		}

		$audit   = new Audit();
		$show_id = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'show', 0 );
		$issue   = strtolower( trim( (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'issue', '' ) ) );
		$remove  = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'remove', false );
		$valid   = $audit->character_issue_types();

		if ( ! $show_id ) {
			\WP_CLI::error( 'Please provide --show=<show_id>.' );
		}
		if ( ! in_array( $issue, $valid, true ) ) {
			\WP_CLI::error( 'Please provide --issue=<type>, one of: ' . implode( ', ', $valid ) . '.' );
		}

		$char_name = html_entity_decode( get_the_title( $char_id ), ENT_QUOTES, 'UTF-8' );
		$show_name = html_entity_decode( get_the_title( $show_id ), ENT_QUOTES, 'UTF-8' );

		if ( $remove ) {
			$audit->remove_ignore( $char_id, $show_id, $issue );
			\WP_CLI::success( sprintf( 'Removed acknowledgement: %1$s (#%2$d) on %3$s (#%4$d), %5$s.', $char_name, $char_id, $show_name, $show_id, $issue ) );
			return;
		}

		$audit->add_ignore( $char_id, $show_id, $issue );
		\WP_CLI::success( sprintf( 'Acknowledged: %1$s (#%2$d) on %3$s (#%4$d), %5$s. Hidden from future audits.', $char_name, $char_id, $show_name, $show_id, $issue ) );
	}

	/**
	 * List a character's acknowledgements.
	 *
	 * @param int $char_id Character post ID.
	 */
	private function cmd_ignores( int $char_id ): void {
		if ( ! $char_id || CPT_Characters::SLUG !== get_post_type( $char_id ) ) {
			\WP_CLI::error( 'Please provide a valid character ID: wp lwtv audit ignores <char_id>' );
		}

		$audit   = new Audit();
		$ignores = $audit->get_ignores( $char_id );

		if ( empty( $ignores ) ) {
			\WP_CLI::success( sprintf( 'Character %1$d (%2$s) has no acknowledged audit items.', $char_id, get_the_title( $char_id ) ) );
			return;
		}

		$rows = array();
		foreach ( $ignores as $key ) {
			list( $show_id, $issue ) = array_pad( explode( ':', $key, 2 ), 2, '' );
			$rows[]                  = array(
				'show_id' => (int) $show_id,
				'show'    => get_the_title( (int) $show_id ),
				'issue'   => $issue,
			);
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'show_id', 'show', 'issue' ) );
	}

	/**
	 * Clear a scope's baseline, or all baselines.
	 *
	 * @param string $scope      Scope string, or '' for all.
	 * @param array  $assoc_args Associative args (for --yes).
	 */
	private function cmd_reset( string $scope, array $assoc_args ): void {
		$audit = new Audit();

		if ( '' === $scope ) {
			\WP_CLI::confirm( 'Reset ALL audit baselines? Every scope will show all items as new next run.', $assoc_args );
			$audit->reset_baseline();
			\WP_CLI::success( 'All audit baselines cleared.' );
			return;
		}

		\WP_CLI::confirm( sprintf( 'Reset baseline for scope "%s"?', $scope ), $assoc_args );
		$audit->reset_baseline( $scope );
		\WP_CLI::success( sprintf( 'Baseline "%s" cleared.', $scope ) );
	}

	/* ------------------------------------------------------------------
	 * SHARED HELPERS
	 * ---------------------------------------------------------------- */

	/**
	 * Can TVMaze confirm this character appeared in this year?
	 *
	 * The single place that answers this question, so the data source can change
	 * without touching either audit path. Today the public API cannot answer it
	 * for main cast: /shows/:id/cast lists them once for the whole show with no
	 * years attached, and /episodes/:id/guestcast contains *only* guest cast, so
	 * a regular never shows up in any year's episode data no matter how many
	 * episodes they are in. That is why absence returns UNKNOWN, not NO.
	 *
	 * When TVMaze exposes per-character appearances (requested upstream), answer
	 * from that first and leave everything below as the fallback for characters
	 * it cannot resolve.
	 *
	 * @param array $char_names Normalized character + actor names.
	 * @param array $evidence   array{
	 *     main_cast: array,      Normalized all-time main cast names.
	 *     year_names: array,     Normalized names seen in that year's episodes.
	 *     has_year_names: bool,  Whether year_names was actually fetched.
	 * }
	 * @return string One of the self::APPEARED_* verdicts.
	 */
	private function character_appeared_in_year( array $char_names, array $evidence ): string {
		$main_cast      = (array) ( $evidence['main_cast'] ?? array() );
		$year_names     = (array) ( $evidence['year_names'] ?? array() );
		$has_year_names = (bool) ( $evidence['has_year_names'] ?? false );

		// Positive evidence: named in that year's episode cast.
		if ( $this->name_found( $char_names, $year_names ) ) {
			return self::APPEARED_YES;
		}

		// Main cast: TVMaze carries no per-year data for them, so their absence
		// from the year's guest cast is not evidence of anything.
		if ( $this->name_found( $char_names, $main_cast ) ) {
			return self::APPEARED_UNKNOWN_MAIN_CAST;
		}

		// Nobody fetched the year's episode cast, so there is nothing to judge.
		if ( ! $has_year_names ) {
			return self::APPEARED_UNKNOWN_NO_DATA;
		}

		return self::APPEARED_NO;
	}

	/**
	 * Is this verdict a "TVMaze cannot tell us" rather than a yes or a no?
	 *
	 * @param string $verdict A self::APPEARED_* verdict.
	 * @return bool
	 */
	private function is_unknown_appearance( string $verdict ): bool {
		return in_array( $verdict, array( self::APPEARED_UNKNOWN_MAIN_CAST, self::APPEARED_UNKNOWN_NO_DATA ), true );
	}

	/**
	 * Action text for an unconfirmed year, naming why it is unconfirmed.
	 *
	 * @param string $verdict A self::APPEARED_* unknown verdict.
	 * @param int    $year    Year concerned.
	 * @return string
	 */
	private function unconfirmed_action( string $verdict, int $year ): string {
		return ( self::APPEARED_UNKNOWN_MAIN_CAST === $verdict )
			// translators: %1 - Year
			? sprintf( __( 'Confirm %1$d -- TVMaze lists main cast without years', 'lwtv' ), $year )
			// translators: %1 - Year
			: sprintf( __( 'Confirm %1$d -- no TVMaze episode data checked', 'lwtv' ), $year );
	}

	/**
	 * Normalized names a character might be credited under: their own, plus
	 * every actor who plays them.
	 *
	 * @param int $char_id Character post ID.
	 * @return array Normalized, unique, non-empty names.
	 */
	private function character_names( int $char_id ): array {
		$names  = array( $this->normalize_name( get_the_title( $char_id ) ) );
		$actors = get_field( 'lezchars_actor', $char_id ) ?: array();

		foreach ( $actors as $actor_id ) {
			$names[] = $this->normalize_name( get_the_title( (int) $actor_id ) );
		}

		return array_values( array_filter( array_unique( $names ) ) );
	}

	/**
	 * Judge a TVMaze 'Ended' status against the end year we already store.
	 *
	 * TVMaze saying a show has ended is not on its own a finding: the usual case
	 * is that we already closed the show out and there is nothing to do. So this
	 * compares TVMaze's ended year against lezshows_airdates_finish and returns
	 * a row only when our data actually needs a human.
	 *
	 * A show finishing in the current year legitimately keeps lezshows_on_air at
	 * 'yes' until the year turns over, which is why these shows keep appearing in
	 * the catalog audit's queue.
	 *
	 * @param int    $show_id    Show post ID.
	 * @param string $status     TVMaze status (for the row).
	 * @param string $ended_year TVMaze ended year, '' when TVMaze has no date.
	 * @return array|null Finding row, or null when our end year is already right.
	 */
	private function evaluate_ended_show( int $show_id, string $status, string $ended_year ): ?array {
		$finish = Airdates::get( $show_id )['finish'];

		// No end year, or still flagged as airing: this is the close-out case.
		if ( '' === $finish || Airdates::is_still_airing( $finish ) ) {
			$action = $ended_year
				// translators: %s - Year
				? sprintf( __( 'Set end year (TVMaze: ended %s)', 'lwtv' ), $ended_year )
				: __( 'Set end year (TVMaze: ended date unknown)', 'lwtv' );

			return $this->build_row(
				$show_id,
				$status,
				$ended_year,
				'',
				'',
				$action,
				'ended'
			);
		}

		// We have a year and TVMaze does not, so there is nothing to compare.
		if ( '' === $ended_year ) {
			return null;
		}

		if ( (int) $finish !== (int) $ended_year ) {
			return $this->build_row(
				$show_id,
				$status,
				$ended_year,
				'',
				'',
				// translators: %1 - LWTV end Year ; %2 TVMaze End Year
				sprintf( __( 'End year mismatch (site: %1$s, TVMaze: %2$s)', 'lwtv' ), $finish, $ended_year ),
				'end-year-mismatch'
			);
		}

		return null;
	}

	/**
	 * Parse --roles flag into an array of allowed character types.
	 *
	 * @param string $roles_flag Flag string.
	 * @return array Allowed role values.
	 */
	private function parse_roles( string $roles_flag ): array {
		switch ( $roles_flag ) {
			case 'all':
				return array( 'regular', 'recurring', 'guest' );
			case 'regular':
				return array( 'regular' );
			case 'recurring':
				return array( 'recurring' );
			case 'guests':
			case 'guest':
				return array( 'guest' );
			case 'none':
				return array();
			default:
				\WP_CLI::error( 'Invalid --roles option. Allowed values: all, regular, recurring, guests, none.' );
				return array();
		}
	}

	/**
	 * Resolve a show on TVMaze with one retry for null/429 bodies.
	 *
	 * @param TVMaze $tvmaze     TVMaze instance.
	 * @param int    $show_id    Show post ID.
	 * @param string $show_title Show title.
	 * @return array|false
	 */
	private function resolve_show( TVMaze $tvmaze, int $show_id, string $show_title ): array|false {
		$show_info = $tvmaze->get_tvmaze_info_show( $show_id, $show_title );

		if ( ! is_array( $show_info ) || ! isset( $show_info['id'] ) ) {
			sleep( 10 );
			$show_info = $tvmaze->get_tvmaze_info_show( $show_id, $show_title );
		}

		return ( is_array( $show_info ) && isset( $show_info['id'] ) ) ? $show_info : false;
	}

	/**
	 * Check whether a show aired an episode in a given year based on TVMaze show data.
	 *
	 * @param array $show_info Show info array returned by TVMaze class.
	 * @param int   $year      Year to check against.
	 * @return bool|null True if it aired, false if not, null if uncertain.
	 */
	private function aired_in_year( array $show_info, int $year ): ?bool {
		if ( ! empty( $show_info['_links']['previousepisode']['href'] ) ) {
			$prev_ep_url = $show_info['_links']['previousepisode']['href'];
			$prev_ep     = $this->tvmaze_get( $prev_ep_url );

			if ( is_array( $prev_ep ) && ! empty( $prev_ep['airdate'] ) ) {
				$air_year = (int) substr( $prev_ep['airdate'], 0, 4 );
				return ( $air_year === $year );
			}
		}

		$premiered_year = ! empty( $show_info['premiered'] ) ? (int) substr( $show_info['premiered'], 0, 4 ) : null;
		$ended_year     = ! empty( $show_info['ended'] ) ? (int) substr( $show_info['ended'], 0, 4 ) : null;

		if ( $premiered_year && $premiered_year > $year ) {
			return false;
		}

		if ( $ended_year && $ended_year < $year ) {
			return false;
		}

		if ( 'Running' === ( $show_info['status'] ?? '' ) && $premiered_year && $premiered_year <= $year ) {
			return true;
		}

		return null;
	}

	/**
	 * Character IDs for a show: cached list first, REGEXP sub-field fallback.
	 *
	 * @param int $show_id Show post ID.
	 * @return array Published character IDs.
	 */
	private function get_show_characters( int $show_id ): array {
		$char_ids = get_post_meta( $show_id, 'lezshows_char_list', true );

		if ( empty( $char_ids ) || ! is_array( $char_ids ) ) {
			global $wpdb;

			// Both meta_key predicates are intentional. REGEXP is the exact test
			// but is not sargable, and this query has no other selective filter
			// to drive off, so without the LIKE it scans wp_postmeta outright.
			// The LIKE gives the meta_key index a constant prefix. esc_like() is
			// required: an unescaped '_' is a LIKE wildcard.
			$like = $wpdb->esc_like( 'lezchars_show_group_' ) . '%' . $wpdb->esc_like( '_show' );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$char_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT post_id
					FROM {$wpdb->postmeta}
					WHERE meta_key LIKE %s
					AND meta_key REGEXP 'lezchars_show_group_[0-9]+_show'
					AND meta_value = %d",
					$like,
					$show_id
				)
			);
			// phpcs:enable
		}

		$char_ids = array_unique( array_map( 'intval', (array) $char_ids ) );

		return array_values( array_filter( $char_ids, fn( $id ) => 'publish' === get_post_status( $id ) ) );
	}

	/**
	 * Repeater rows on a character that belong to this show.
	 *
	 * @param int $char_id Character post ID.
	 * @param int $show_id Show post ID.
	 * @return array
	 */
	private function get_show_rows_for_character( int $char_id, int $show_id ): array {
		$show_group = get_field( 'lezchars_show_group', $char_id );
		if ( ! is_array( $show_group ) ) {
			return array();
		}

		return array_filter(
			$show_group,
			function ( $row ) use ( $show_id ) {
				if ( ! isset( $row['show'] ) ) {
					return false;
				}
				$row_show = is_array( $row['show'] ) ? (int) reset( $row['show'] ) : (int) $row['show'];
				return $row_show === $show_id;
			}
		);
	}

	/**
	 * Normalize the appears sub-field to an int array.
	 *
	 * @param array $row Repeater row.
	 * @return array
	 */
	private function clean_appears( array $row ): array {
		return ( isset( $row['appears'] ) && is_array( $row['appears'] ) )
			? array_map( 'intval', $row['appears'] )
			: array();
	}

	/**
	 * Normalize a name for fuzzy matching: strip accents, strip
	 * disambiguation parentheticals, lowercase.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	private function normalize_name( string $name ): string {
		$name = remove_accents( $name );
		$name = trim( current( explode( ' (', $name ) ) );
		return strtolower( $name );
	}

	/**
	 * Is any of the character's names in the found-names set?
	 *
	 * @param array $char_names  Normalized character + actor names.
	 * @param array $found_names Normalized names seen on TVMaze.
	 * @return bool
	 */
	private function name_found( array $char_names, array $found_names ): bool {
		foreach ( $char_names as $name ) {
			if ( in_array( $name, $found_names, true ) ) {
				return true;
			}
		}
		return false;
	}

	/* ------------------------------------------------------------------
	 * ACTOR DEATH AUDIT
	 * ---------------------------------------------------------------- */

	/**
	 * Which of our actors does WikiData think have died?
	 *
	 * Deliberately not wired into the Audit baseline tracker. The shows audit
	 * needs new/open/resolved because its findings are judgement calls that
	 * recur for months; a death finding is acted on once and then never appears
	 * again, because the moment the date goes in the actor is skipped on rule 1.
	 * A baseline would be bookkeeping for a list that empties itself.
	 *
	 * @param array $assoc_args Associative args.
	 */
	private function audit_actor_deaths( array $assoc_args ): void {
		$letter        = $this->parse_letter( (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'letter', '' ) );
		$do_unresolved = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'unresolved', false );
		$is_table      = ( 'table' === $this->format );

		$actor_ids = get_posts(
			array(
				'post_type'      => CPT_Actors::SLUG,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		if ( '' !== $letter ) {
			$builder = new Shared_Builder();
			$marker  = ( 1 === strlen( $letter ) && ctype_alpha( $letter ) ) ? strtoupper( $letter ) : $letter;

			$actor_ids = array_values(
				array_filter(
					$actor_ids,
					fn( $id ) => $builder->get_character_marker( get_the_title( $id ) ) === $marker
				)
			);
		}

		if ( empty( $actor_ids ) ) {
			\WP_CLI::error( 'No actors found to audit' . ( $letter ? ' in that letter bucket' : '' ) . '.' );
		}

		$progress = $is_table
			? \WP_CLI\Utils\make_progress_bar( sprintf( 'Checking %d actors', count( $actor_ids ) ), count( $actor_ids ) )
			: null;

		$debugger = new Actors_Debugger();
		$rows     = array();
		$tally    = array();

		foreach ( $actor_ids as $actor_id ) {
			if ( $progress ) {
				$progress->tick();
			}

			$result  = $debugger->check_actor_death( (int) $actor_id );
			$verdict = $result['verdict'];

			$tally[ $verdict ] = ( $tally[ $verdict ] ?? 0 ) + 1;

			// An actor we already have a date for cost us no request, so don't
			// pay the throttle for them either -- that is what keeps a full run
			// proportional to the work actually left to do.
			if ( Actor_Death_Rules::HAS_DATE === $verdict ) {
				continue;
			}

			if ( Actor_Death_Rules::is_reportable( $verdict )
				&& ( $do_unresolved || ! Actor_Death_Rules::is_unresolved( $verdict ) ) ) {
				$rows[] = $this->build_actor_row( (int) $actor_id, $result );
			}

			usleep( self::WAIT_TIME );
		}

		if ( $progress ) {
			$progress->finish();
		}

		if ( ! empty( $rows ) ) {
			\WP_CLI\Utils\format_items( $this->format, $rows, self::ACTOR_FIELDS );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI STDERR write, must bypass STDOUT so redirected CSV/JSON stays clean
		fwrite( STDERR, $this->actor_summary_line( $tally, count( $rows ), $do_unresolved ) . "\n" );
	}

	/**
	 * Check one actor, and say so even when there is nothing to report.
	 *
	 * @param int $actor_id Actor post ID.
	 */
	private function audit_actor_death_single( int $actor_id ): void {
		if ( ! $actor_id || CPT_Actors::SLUG !== get_post_type( $actor_id ) ) {
			\WP_CLI::error( 'Pass the post ID of an actor: wp lwtv audit actor <id>' );
		}

		$result = ( new Actors_Debugger() )->check_actor_death( $actor_id );
		$name   = html_entity_decode( get_the_title( $actor_id ), ENT_QUOTES, 'UTF-8' );

		if ( Actor_Death_Rules::HAS_DATE === $result['verdict'] ) {
			\WP_CLI::success( $name . ' already has a death date. Nothing to check.' );
			return;
		}

		if ( Actor_Death_Rules::ALIVE === $result['verdict'] ) {
			\WP_CLI::success( $name . ' has no death date on WikiData either (' . $result['qid'] . ').' );
			return;
		}

		\WP_CLI\Utils\format_items(
			$this->format,
			array( $this->build_actor_row( $actor_id, $result ) ),
			self::ACTOR_FIELDS
		);
	}

	/**
	 * One actor finding row.
	 *
	 * Both birth dates travel in the 'birth' column, ours first, because on a
	 * suspect-match row the disagreement between them IS the finding -- and on a
	 * genuine one, seeing them agree is what lets an editor trust the row
	 * without opening WikiData.
	 *
	 * @param int   $actor_id Actor post ID.
	 * @param array $result   Result from Actors::check_actor_death().
	 * @return array
	 */
	private function build_actor_row( int $actor_id, array $result ): array {
		$ours   = $result['our_birth'];
		$theirs = $result['wiki_birth'];

		if ( '' === $ours && '' === $theirs ) {
			$birth = '';
		} elseif ( $ours === $theirs || '' === $theirs ) {
			$birth = $ours;
		} else {
			$birth = ( '' === $ours ? '?' : $ours ) . ' vs ' . $theirs;
		}

		return array(
			'actor_id' => $actor_id,
			'actor'    => html_entity_decode( get_the_title( $actor_id ), ENT_QUOTES, 'UTF-8' ),
			'issue'    => $result['verdict'],
			'wikidata' => $result['qid'],
			'birth'    => $birth,
			'death'    => $result['death'],
			'action'   => $result['action'],
		);
	}

	/**
	 * One-line summary of an actor death audit run.
	 *
	 * Reports the checked/skipped split as well as the findings, because the
	 * interesting failure of this command is silence: "0 items need attention"
	 * reads like good news whether we checked four thousand actors or couldn't
	 * identify any of them.
	 *
	 * @param array $tally         Verdict => count.
	 * @param int   $shown         Rows actually printed.
	 * @param bool  $do_unresolved Whether unresolved rows were listed.
	 * @return string
	 */
	private function actor_summary_line( array $tally, int $shown, bool $do_unresolved ): string {
		$parts = array();

		$parts[] = sprintf(
			/* translators: 1: actors with a death date already, 2: actors checked against WikiData. */
			__( '%1$d already had a date, %2$d checked', 'lwtv' ),
			$tally[ Actor_Death_Rules::HAS_DATE ] ?? 0,
			array_sum( $tally ) - ( $tally[ Actor_Death_Rules::HAS_DATE ] ?? 0 )
		);

		$found   = $tally[ Actor_Death_Rules::FOUND ] ?? 0;
		$suspect = $tally[ Actor_Death_Rules::SUSPECT ] ?? 0;

		$parts[] = sprintf(
			/* translators: %d: number of possible deaths found. */
			_n( '%d possible death to verify', '%d possible deaths to verify', $found, 'lwtv' ),
			$found
		);

		if ( $suspect ) {
			$parts[] = sprintf(
				/* translators: %d: number of rows whose WikiData match looks wrong. */
				_n( '%d death claim withheld (match looks wrong)', '%d death claims withheld (match looks wrong)', $suspect, 'lwtv' ),
				$suspect
			);
		}

		$unresolved = ( $tally[ Actor_Death_Rules::NO_IDENTITY ] ?? 0 )
			+ ( $tally[ Actor_Death_Rules::AMBIGUOUS ] ?? 0 )
			+ ( $tally[ Actor_Death_Rules::NO_DATA ] ?? 0 );

		if ( $unresolved ) {
			$parts[] = $do_unresolved
				? sprintf(
					/* translators: %d: number of actors that could not be checked. */
					_n( '%d not checkable (listed)', '%d not checkable (listed)', $unresolved, 'lwtv' ),
					$unresolved
				)
				: sprintf(
					/* translators: %d: number of actors that could not be checked. */
					_n( '%d not checkable (--unresolved to list)', '%d not checkable (--unresolved to list)', $unresolved, 'lwtv' ),
					$unresolved
				);
		}

		if ( 0 === $shown ) {
			$parts[] = __( 'nothing to act on', 'lwtv' );
		}

		return __( 'Actor death audit complete.', 'lwtv' ) . ' ' . implode( '. ', $parts ) . '.';
	}

	/**
	 * Map the --letter flag to a Shared_Builder marker.
	 *
	 * @param string $letter Raw flag value.
	 * @return string Marker ('' = no filter, 'a'-'z', '#', '-').
	 */
	private function parse_letter( string $letter ): string {
		$letter = strtolower( trim( $letter ) );

		if ( '' === $letter ) {
			return '';
		}
		if ( in_array( $letter, array( 'num', '#', '0-9' ), true ) ) {
			return '#';
		}
		if ( in_array( $letter, array( 'intl', 'other', '-' ), true ) ) {
			return '-';
		}
		if ( preg_match( '/^[a-z]$/', $letter ) ) {
			return (string) $letter;
		}

		\WP_CLI::error( 'Invalid --letter. Use a-z, num (#), or intl (-).' );
		return '';
	}

	/**
	 * Scope-name token for a raw --letter flag (a-z / num / intl / '').
	 *
	 * Uses the flag token, never the raw marker (# / -), so option names stay
	 * storage-safe.
	 *
	 * @param string $raw Raw --letter flag value.
	 * @return string
	 */
	private function letter_token( string $raw ): string {
		$raw = strtolower( trim( $raw ) );
		if ( '' === $raw ) {
			return '';
		}
		if ( in_array( $raw, array( 'num', '#', '0-9' ), true ) ) {
			return 'num';
		}
		if ( in_array( $raw, array( 'intl', 'other', '-' ), true ) ) {
			return 'intl';
		}
		return $raw;
	}

	/**
	 * Catalog scope string: catalog[_<letter>]_<roles>.
	 *
	 * @param string $letter_token Letter token (may be '').
	 * @param string $roles_flag   Normalized --roles flag.
	 * @return string
	 */
	private function catalog_scope( string $letter_token, string $roles_flag ): string {
		$parts = array( 'catalog' );
		if ( '' !== $letter_token ) {
			$parts[] = $letter_token;
		}
		$parts[] = $roles_flag;
		return implode( '_', $parts );
	}

	/**
	 * Deep-audit scope string: show_<id>_<rolesig>[_all].
	 *
	 * @param int   $show_id        Show post ID.
	 * @param array $roles_to_audit Roles being audited.
	 * @param bool  $do_all         Whether --all is set.
	 * @return string
	 */
	private function show_scope( int $show_id, array $roles_to_audit, bool $do_all ): string {
		$roles = $roles_to_audit;
		sort( $roles );
		$scope = 'show_' . $show_id . '_' . ( ! empty( $roles ) ? implode( '-', $roles ) : 'none' );
		if ( $do_all ) {
			$scope .= '_all';
		}
		return $scope;
	}

	/**
	 * GET a TVMaze URL with 429 retry handling.
	 *
	 * @param string $url     URL to fetch.
	 * @param int    $attempt Current attempt number.
	 * @return array|false Decoded body or false.
	 */
	private function tvmaze_get( string $url, int $attempt = 1 ): array|false {
		$response = wp_remote_get( $url );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 429 === $code && $attempt < 3 ) {
			$retry_after = (int) wp_remote_retrieve_header( $response, 'retry-after' );
			sleep( max( $retry_after, 5 ) );
			return $this->tvmaze_get( $url, $attempt + 1 );
		}

		if ( 200 !== $code ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return is_array( $body ) ? $body : false;
	}

	/**
	 * Build one finding row (identity keys + display keys). The 'scope' key is
	 * stamped later by Audit::finalize().
	 *
	 * @param int    $show_id    Show post ID.
	 * @param string $status     TVMaze status.
	 * @param string $ended      TVMaze ended date or year.
	 * @param string $character  Character name (empty for show-level rows).
	 * @param string $role       Character role type.
	 * @param string $action     Action needed.
	 * @param string $issue_type Issue type (see Audit::ISSUE_TYPES).
	 * @param int    $char_id    Character post ID (0 for show-level).
	 * @param int    $year       Year the finding concerns (0 for show-level).
	 * @return array
	 */
	private function build_row( int $show_id, string $status, string $ended, string $character, string $role, string $action, string $issue_type, int $char_id = 0, int $year = 0 ): array {
		$ended_year = ! empty( $ended ) ? ' (' . substr( $ended, 0, 4 ) . ')' : '';

		return array(
			'scope'         => '',
			'show_id'       => $show_id,
			'char_id'       => $char_id,
			'issue_type'    => $issue_type,
			'year'          => $year,
			'show'          => html_entity_decode( get_the_title( $show_id ), ENT_QUOTES, 'UTF-8' ),
			'tvmaze_status' => $status . $ended_year,
			'character'     => html_entity_decode( $character, ENT_QUOTES, 'UTF-8' ),
			'role'          => $role,
			'action'        => $action,
		);
	}

	/**
	 * Diff the run against its baseline, render rows, and emit the summary.
	 *
	 * @param string $scope         Scope string.
	 * @param array  $results       Raw finding rows.
	 * @param bool   $show_resolved Whether to also list resolved items.
	 */
	private function output_results( string $scope, array $results, bool $show_resolved ): void {
		$audit     = new Audit();
		$finalized = $audit->finalize( $scope, $results );

		$rows = $finalized['rows'];
		if ( $show_resolved ) {
			$rows = array_merge( $rows, $finalized['resolved'] );
		}

		if ( ! empty( $rows ) ) {
			// show_id is here so a flagged row can be fed straight back in as
			// `wp lwtv audit show <show_id>` for the deep per-episode audit.
			$fields = array( 'status', 'show_id', 'show', 'tvmaze_status', 'character', 'role', 'action' );
			\WP_CLI\Utils\format_items( $this->format, $rows, $fields );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI STDERR write, must bypass STDOUT so redirected CSV/JSON stays clean
		fwrite( STDERR, $this->summary_line( $finalized['summary'] ) . "\n" );
	}

	/**
	 * Human-readable one-line summary from a finalize() summary block.
	 *
	 * @param array $summary Summary block.
	 * @return string
	 */
	private function summary_line( array $summary ): string {
		if ( 0 === $summary['total'] && 0 === $summary['resolved'] && 0 === $summary['ignored'] ) {
			return __( 'Audit complete. Nothing needs attention!', 'lwtv' );
		}

		$parts   = array();
		$parts[] = sprintf(
			/* translators: %d: number of items needing attention. */
			_n( '%d item needs attention', '%d items need attention', $summary['total'], 'lwtv' ),
			$summary['total']
		);

		if ( ! empty( $summary['by_issue'] ) ) {
			$bits = array();
			foreach ( $summary['by_issue'] as $type => $count ) {
				$label  = Audit::ISSUE_TYPES[ $type ]['label'] ?? $type;
				$bits[] = $count . ' ' . $label;
			}
			$parts[] = '(' . implode( ', ', $bits ) . ')';
		}

		if ( $summary['had_baseline'] ) {
			$since   = $summary['previous_run']
				? wp_date( get_option( 'date_format' ), $summary['previous_run'] )
				: __( 'last run', 'lwtv' );
			$parts[] = sprintf(
				/* translators: 1: new count, 2: still-open count, 3: resolved count, 4: date of last run. */
				__( '%1$d new / %2$d still open / %3$d resolved since %4$s', 'lwtv' ),
				$summary['new'],
				$summary['open'],
				$summary['resolved'],
				$since
			);
		} else {
			$parts[] = __( 'first run for this scope -- all items are new', 'lwtv' );
		}

		if ( $summary['ignored'] ) {
			$parts[] = sprintf(
				/* translators: %d: number of acknowledged (hidden) items. */
				_n( '%d acknowledged (hidden)', '%d acknowledged (hidden)', $summary['ignored'], 'lwtv' ),
				$summary['ignored']
			);
		}

		return __( 'Audit complete.', 'lwtv' ) . ' ' . implode( '. ', $parts ) . '.';
	}
}

\WP_CLI::add_command( 'lwtv audit', 'WP_CLI_LWTV_Audit' );
