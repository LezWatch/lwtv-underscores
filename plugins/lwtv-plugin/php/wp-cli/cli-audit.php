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
use LWTV\CPTs\Shows as CPT_Shows;
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
	 * - shows (catalog: all on-air shows, status + regular characters)
	 * - show  (deep: one show, per-episode guest cast)
	 * ---
	 *
	 * [<id>]
	 * : Show post ID (required for 'show').
	 *
	 * [--letter=<letter>]
	 * : Catalog only. Restrict to one alphabet bucket: a-z, 'num' (#), or 'intl' (-).
	 *
	 * [--recurring]
	 * : Deep only. Also audit recurring characters.
	 *
	 * [--guests]
	 * : Deep only. Also audit guest characters.
	 *
	 * [--all]
	 * : Deep only. Audit the show's full history, not just the current year.
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
	 *     # The '#' and international buckets
	 *     wp lwtv audit shows --letter=num
	 *     wp lwtv audit shows --letter=intl
	 *
	 *     # Deep historical audit of a newly added show
	 *     wp lwtv audit show 12345 --recurring --all
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
				default:
					\WP_CLI::error( 'Invalid audit type. Use: shows, show <id>' );
			}
		} catch ( Exception $exception ) {
			\WP_CLI::error( $exception->getMessage(), false );
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
		$current_year = (int) gmdate( 'Y' );
		$tvmaze       = new TVMaze();
		$results      = array();
		$is_table     = ( 'table' === $this->format );

		$letter = $this->parse_letter( \WP_CLI\Utils\get_flag_value( $assoc_args, 'letter', '' ) );

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

		// Letter filtering happens in PHP via the same bucketing the site
		// uses (Shared_Builder), NOT via SQL LIKE: utf8mb4 collations are
		// accent-insensitive, so LIKE 'a%' would steal titles from the
		// international bucket.
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
				$results[] = $this->build_row( $show_id, 'No Match', '', '', '', '', 'Add IMDb/TVMaze ID or audit manually' );
				usleep( 500000 );
				continue;
			}

			$status = $show_info['status'] ?? 'Unknown';
			$ended  = $show_info['ended'] ?? '';

			if ( 'Ended' === $status ) {
				$results[] = $this->build_row( $show_id, $status, $ended, '', '', '', 'Set end year (TVMaze: ended ' . ( $ended ?: 'date unknown' ) . ')' );
				usleep( 500000 );
				continue; // No point auditing characters on a show we're about to close out.
			}

			if ( 'To Be Determined' === $status ) {
				$results[] = $this->build_row( $show_id, $status, '', '', '', '', 'Review: show in limbo on TVMaze' );
			}

			// Animated? Character data can't be audited via TVMaze
			// (voice actors play multiple characters). Note it and move on.
			if ( has_term( self::SKIP_GENRES, 'lez_genres', $show_id ) ) {
				usleep( 500000 );
				continue;
			}

			$aired_this_year = $this->aired_in_year( $show_info, $current_year );

			// Only audit characters on positive confirmation of current-year
			// episodes. Between-seasons shows produce no rows.
			if ( true === $aired_this_year ) {
				$results = array_merge( $results, $this->audit_regulars( $show_id, $status, $current_year ) );
			}

			// Respect TVMaze rate limits (~20 calls per 10 seconds, no auth).
			usleep( 500000 );
		}

		if ( $progress ) {
			$progress->finish();
		}

		$this->output_results( $results, $is_table );
	}

	/**
	 * Audit living regular characters on one show for the current year.
	 *
	 * @param int    $show_id      Show post ID.
	 * @param string $status       TVMaze status (for the row).
	 * @param int    $current_year Current year.
	 * @return array
	 */
	private function audit_regulars( int $show_id, string $status, int $current_year ): array {
		$rows = array();

		foreach ( $this->get_show_characters( $show_id ) as $char_id ) {
			if ( has_term( 'dead', 'lez_cliches', $char_id ) ) {
				continue; // Skip the tragically troped.
			}

			foreach ( $this->get_show_rows_for_character( $char_id, $show_id ) as $row ) {
				if ( 'regular' !== ( $row['type'] ?? '' ) ) {
					continue;
				}

				$appears = $this->clean_appears( $row );

				if ( ! in_array( $current_year, $appears, true ) ) {
					$rows[] = $this->build_row(
						$show_id,
						$status,
						'',
						get_the_title( $char_id ),
						$this->get_actor_name( $char_id ),
						'regular',
						'Add ' . $current_year . ' to Years Appears',
						$char_id
					);
				}
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

		$do_all       = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );
		$current_year = (int) gmdate( 'Y' );
		$is_table     = ( 'table' === $this->format );

		$roles = array( 'regular' );
		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'recurring', false ) ) {
			$roles[] = 'recurring';
		}
		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'guests', false ) ) {
			$roles[] = 'guest';
		}

		$show_title = get_the_title( $show_id );
		$tvmaze     = new TVMaze();
		$show_info  = $this->resolve_show( $tvmaze, $show_id, $show_title );

		if ( false === $show_info ) {
			\WP_CLI::error( 'No TVMaze match for "' . $show_title . '". Add an IMDb or TVMaze ID first.' );
		}

		$status    = $show_info['status'] ?? 'Unknown';
		$tvmaze_id = (int) $show_info['id'];

		// All-time main cast. TVMaze doesn't date cast membership, so these
		// names count as "present" for every audited year.
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

		// Estimate uncached API calls and confirm big jobs (soaps!).
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
					(int) ceil( $uncached / 100 ) // ~100 calls/minute at 0.5s spacing + overhead.
				),
				$assoc_args
			);
		}

		// Build normalized name sets per year (cached; episodes are immutable).
		$names_by_year = array();
		foreach ( $years as $year ) {
			$names_by_year[ $year ] = $this->get_year_names( $show_id, $year, $eps_by_year[ $year ] ?? array(), $current_year, $is_table );
		}

		// Audit the characters.
		$results = array();

		foreach ( $this->get_show_characters( $show_id ) as $char_id ) {
			$is_dead = has_term( 'dead', 'lez_cliches', $char_id );

			// Dead characters: skipped for current-year-only runs (noise),
			// included for --all (their historical years are the point).
			if ( $is_dead && ! $do_all ) {
				continue;
			}

			$char_names = array( $this->normalize_name( get_the_title( $char_id ) ) );
			$actors     = get_field( 'lezchars_actor', $char_id ) ?: array();
			foreach ( $actors as $actor_id ) {
				$char_names[] = $this->normalize_name( get_the_title( (int) $actor_id ) );
			}
			$char_names = array_filter( array_unique( $char_names ) );

			foreach ( $this->get_show_rows_for_character( $char_id, $show_id ) as $row ) {
				$type = $row['type'] ?? '';
				if ( ! in_array( $type, $roles, true ) ) {
					continue;
				}

				$appears = $this->clean_appears( $row );

				foreach ( $years as $year ) {
					// Years with no TVMaze episode data can't be judged.
					if ( empty( $eps_by_year[ $year ] ) ) {
						continue;
					}

					$found = $this->name_found( $char_names, array_merge( $cast_names, $names_by_year[ $year ] ) );
					$has   = in_array( $year, $appears, true );

					if ( $found && ! $has ) {
						$results[] = $this->build_row( $show_id, $status, '', get_the_title( $char_id ), $this->get_actor_name( $char_id ), $type, 'TVMaze shows ' . $year . ' -- add?', $char_id );
					} elseif ( ! $found && $has ) {
						$results[] = $this->build_row( $show_id, $status, '', get_the_title( $char_id ), $this->get_actor_name( $char_id ), $type, 'Verify ' . $year . ' -- no TVMaze appearance found', $char_id );
					}
				}
			}
		}

		$this->output_results( $results, $is_table );
	}

	/**
	 * Get (and cache) the normalized names seen in a show's episodes for one year.
	 *
	 * Aired episodes are immutable: past years cache for a year, the current
	 * year for a day (new episodes keep landing).
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

		$names    = array();
		$progress = ( $is_table && count( $episode_ids ) > 10 )
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
			}

			usleep( 500000 );
		}

		if ( $progress ) {
			$progress->finish();
		}

		$names = array_values( array_filter( array_unique( $names ) ) );
		$ttl   = ( $year < $current_year ) ? YEAR_IN_SECONDS : DAY_IN_SECONDS;
		lwtv_plugin()->set_transient( $cache_key, $names, $ttl );

		return $names;
	}

	/* ------------------------------------------------------------------
	 * SHARED HELPERS
	 * ---------------------------------------------------------------- */

	/**
	 * Resolve a show on TVMaze with one retry for null/429 bodies.
	 *
	 * Passes the title explicitly: the calendar class's name-search fallback
	 * otherwise queries an empty string when no IDs are stored.
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
	 * Character IDs for a show: cached list first, REGEXP sub-field fallback.
	 *
	 * @param int $show_id Show post ID.
	 * @return array Published character IDs.
	 */
	private function get_show_characters( int $show_id ): array {
		$char_ids = get_post_meta( $show_id, 'lezshows_char_list', true );

		if ( empty( $char_ids ) || ! is_array( $char_ids ) ) {
			global $wpdb;
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$char_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT post_id
					FROM {$wpdb->postmeta}
					WHERE meta_key REGEXP 'lezchars_show_group_[0-9]+_show'
					AND meta_value = %d",
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
				// De-array pre-migration CMB2 data.
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
	 * First actor's name for a character.
	 *
	 * @param int $char_id Character post ID.
	 * @return string
	 */
	private function get_actor_name( int $char_id ): string {
		$actors = get_field( 'lezchars_actor', $char_id ) ?: array();
		return ! empty( $actors ) ? get_the_title( (int) reset( $actors ) ) : '';
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
			return $letter;
		}

		\WP_CLI::error( 'Invalid --letter. Use a-z, num (#), or intl (-).' );
	}

	/**
	 * GET a TVMaze URL with 429 retry handling.
	 *
	 * TVMaze's public API is keyless; the limit is roughly 20 calls per
	 * 10 seconds. On 429 we honor Retry-After (min 5s) up to 3 attempts.
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
	 * Build one result row.
	 *
	 * @param int    $show_id   Show post ID.
	 * @param string $status    TVMaze status.
	 * @param string $ended     TVMaze ended date.
	 * @param string $character Character name (empty for show-level rows).
	 * @param string $actor     Actor name.
	 * @param string $role      Character role type.
	 * @param string $action    Action needed.
	 * @param int    $edit_id   Post to link for editing (defaults to the show).
	 * @return array
	 */
	private function build_row( int $show_id, string $status, string $ended, string $character, string $actor, string $role, string $action, int $edit_id = 0 ): array {
		$edit_id = $edit_id ?: $show_id;

		return array(
			'show'          => get_the_title( $show_id ),
			'tvmaze_status' => $status,
			'tvmaze_ended'  => $ended,
			'character'     => $character,
			'actor'         => $actor,
			'role'          => $role,
			'action'        => $action,
			'edit_url'      => get_edit_post_link( $edit_id, '' ),
		);
	}

	/**
	 * Output the results (or celebrate their absence).
	 *
	 * @param array $results  Result rows.
	 * @param bool  $is_table Table format?
	 */
	private function output_results( array $results, bool $is_table ): void {
		if ( empty( $results ) ) {
			\WP_CLI::success( 'Audit complete. Nothing needs attention!' );
			return;
		}

		$fields = array( 'show', 'tvmaze_status', 'tvmaze_ended', 'character', 'actor', 'role', 'action', 'edit_url' );
		\WP_CLI\Utils\format_items( $this->format, $results, $fields );

		if ( $is_table ) {
			\WP_CLI::success( sprintf( 'Audit complete. %d item(s) need attention.', count( $results ) ) );
		}
	}
}

\WP_CLI::add_command( 'lwtv audit', 'WP_CLI_LWTV_Audit' );
