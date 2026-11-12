<?php
/**
 * TMDB Watch Providers Task
 *
 * Keeps lezshows_watch_providers fresh on Action Scheduler, so a show page never
 * makes a request. There is no queue: the work list is "shows with a TMDB ID whose
 * lezshows_watch_providers_checked is missing or older than their tier's TTL",
 * read from meta each run. Tiers (on air weekly, ended monthly, no finish date
 * never) are in Watch_Providers_Tiers. See docs/architecture/scheduling.md#stale-sweep-watch-providers.
 *
 * @package lwtv-plugin
 */

namespace LWTV\Schedulers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\_Components\CPTs;
use LWTV\_Helpers\Tmdb_Providers;
use LWTV\CPTs\Shows as CPT_Shows;
use LWTV\CPTs\Shows\Airdates;

/**
 * Class Watch_Providers_Task
 */
class Watch_Providers_Task {

	/**
	 * Action Scheduler hook name.
	 */
	const AS_HOOK = 'lwtv_watch_providers_task';

	/**
	 * Action Scheduler group name.
	 */
	const AS_GROUP = 'lwtv';

	/**
	 * Meta keys.
	 */
	const META_DATA    = 'lezshows_watch_providers';
	const META_CHECKED = 'lezshows_watch_providers_checked';

	/**
	 * Shows per run.
	 */
	const BATCH_SIZE = 30;

	/**
	 * Pause between requests, in microseconds.
	 */
	const DELAY_US = 250000;

	/**
	 * Wall-clock budget per run, in seconds. Checked before each request, so a
	 * run stops before one that could overshoot it.
	 * See docs/architecture/scheduling.md#time-budgets.
	 */
	const BUDGET = 120;

	/**
	 * Whether the hooks are attached. The CLI and cron make their own instances,
	 * and a second process() on the hook would fetch every show twice.
	 *
	 * @var bool
	 */
	private static $hooked = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( self::$hooked ) {
			return;
		}

		self::$hooked = true;

		add_action( self::AS_HOOK, array( $this, 'process' ) );
		add_action( 'added_post_meta', array( $this, 'tmdb_id_changed' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'tmdb_id_changed' ), 10, 4 );
		add_action( 'deleted_post_meta', array( $this, 'tmdb_id_deleted' ), 10, 3 );
		add_action( 'updated_post_meta', array( $this, 'finish_changed' ), 10, 3 );
	}

	/**
	 * A show's finish date changed (ended, or came back): fetch it again now
	 * rather than when its old tier's TTL runs out. Core only fires
	 * updated_post_meta when the value actually changes, so a plain re-save of an
	 * unchanged show does nothing. No HTTP here; this can fire inside save_post.
	 *
	 * @param int    $meta_id  Unused.
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 * @return void
	 */
	public function finish_changed( $meta_id, $post_id, $meta_key ): void {
		if ( Airdates::META_FINISH !== $meta_key || '' === (string) get_post_meta( (int) $post_id, 'lezshows_tmdb_id', true ) ) {
			return;
		}

		delete_post_meta( (int) $post_id, self::META_CHECKED );
		$this->schedule();
	}

	/**
	 * A show gained, changed or cleared its TMDB ID.
	 *
	 * Gained or changed: deleting the checked time is all it takes, because the
	 * sweep reads staleness from meta. Cleared (ACF saves '' rather than deleting):
	 * the sweep skips shows without an ID, so the stored answer would never be
	 * replaced; drop it so the page falls back to the curated links.
	 * No HTTP here; this can fire inside save_post.
	 *
	 * @param int    $meta_id    Unused.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value New value.
	 * @return void
	 */
	public function tmdb_id_changed( $meta_id, $post_id, $meta_key, $meta_value ): void {
		if ( 'lezshows_tmdb_id' !== $meta_key ) {
			return;
		}

		if ( empty( $meta_value ) ) {
			$this->forget( (int) $post_id );
			return;
		}

		delete_post_meta( (int) $post_id, self::META_CHECKED );
		$this->schedule();
	}

	/**
	 * A show's TMDB ID meta was deleted outright (WP-CLI, delete_post_meta()):
	 * drop the stored answer, as for a cleared ID.
	 *
	 * @param array<int, int> $meta_ids Unused.
	 * @param int             $post_id  Post ID.
	 * @param string          $meta_key Meta key.
	 * @return void
	 */
	public function tmdb_id_deleted( $meta_ids, $post_id, $meta_key ): void {
		if ( 'lezshows_tmdb_id' !== $meta_key ) {
			return;
		}

		$this->forget( (int) $post_id );
	}

	/**
	 * Remove a show's stored providers and checked time.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function forget( int $post_id ): void {
		delete_post_meta( $post_id, self::META_DATA );
		delete_post_meta( $post_id, self::META_CHECKED );
	}

	/**
	 * Schedule a run unless one is pending. Safe to call from cron every day.
	 *
	 * @param int $delay Seconds from now.
	 * @return bool Whether a run was scheduled by this call.
	 */
	public function schedule( int $delay = 60 ): bool {
		if ( ! function_exists( 'as_schedule_single_action' ) || ! function_exists( 'as_next_scheduled_action' ) || as_next_scheduled_action( self::AS_HOOK ) ) {
			return false;
		}

		as_schedule_single_action( time() + $delay, self::AS_HOOK, array(), self::AS_GROUP );
		return true;
	}

	/**
	 * Refresh a batch of stale shows (Action Scheduler handler).
	 *
	 * Reschedules while stale shows remain, but only if this run got at least one
	 * answer. A transport error leaves a show stale so it is retried, so without
	 * that check a TMDB outage would reschedule every minute until it ended. The
	 * next daily kick tries again.
	 *
	 * @return void
	 */
	public function process(): void {
		$start   = time();
		$batch   = $this->stale_ids( self::BATCH_SIZE );
		$answers = 0;
		$errors  = 0;

		foreach ( $batch as $index => $post_id ) {
			if ( time() - $start >= self::BUDGET ) {
				break;
			}

			if ( 'error' === $this->refresh_post( $post_id ) ) {
				++$errors;
			} else {
				++$answers;
			}

			if ( $index < count( $batch ) - 1 ) {
				usleep( self::DELAY_US );
			}
		}

		lwtv_plugin()->debug_log( 'tmdb', 'Watch providers: ' . $answers . ' refreshed, ' . $errors . ' errors, of ' . count( $batch ) . ' stale' );

		// Not schedule(): inside this handler as_next_scheduled_action() counts the
		// running action as pending, so the idempotency check would always refuse.
		if ( $answers > 0 && function_exists( 'as_schedule_single_action' ) && ! empty( $this->stale_ids( 1 ) ) ) {
			as_schedule_single_action( time() + 60, self::AS_HOOK, array(), self::AS_GROUP );
		}
	}

	/**
	 * Fetch and store one show's providers.
	 *
	 * @param int $post_id Show ID.
	 * @return string 'stored' (something to show), 'empty' (an answer with nothing displayable), or 'error' (not stored, retried later).
	 */
	public function refresh_post( int $post_id ): string {
		$response = ( new CPTs() )->get_tmdb_watch_providers( $post_id );

		if ( false === $response ) {
			return 'error';
		}

		$data = Tmdb_Providers::normalise( $response );

		// update_post_meta() unslashes, which would eat the backslashes in JSON escapes.
		update_post_meta( $post_id, self::META_DATA, wp_slash( (string) wp_json_encode( (object) $data ) ) );
		update_post_meta( $post_id, self::META_CHECKED, time() );

		return '' === Tmdb_Providers::default_country( $data, Tmdb_Providers::DISPLAY_TYPES ) ? 'empty' : 'stored';
	}

	/**
	 * Stored providers for a show, as normalise() returned them.
	 *
	 * @param int $post_id Show ID.
	 * @return array<string, array<string, mixed>> Empty when never fetched or TMDB had nothing.
	 */
	public static function stored( int $post_id ): array {
		$data = json_decode( (string) get_post_meta( $post_id, self::META_DATA, true ), true );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Per-tier cutoffs: a show is due when its checked time is below its tier's.
	 *
	 * @param bool $all Ignore the TTLs: every tier, including NONE, is due.
	 * @return array{year: int, on_air: int, ended: int, none: int}
	 */
	private function cutoffs( bool $all = false ): array {
		$now = time();

		return array(
			'year'   => (int) wp_date( 'Y', $now ),
			'on_air' => $all ? PHP_INT_MAX : $now - Watch_Providers_Tiers::TTLS[ Watch_Providers_Tiers::ON_AIR ],
			'ended'  => $all ? PHP_INT_MAX : $now - Watch_Providers_Tiers::TTLS[ Watch_Providers_Tiers::ENDED ],
			// No finish date: never due on time. `< 0` is never true for an unsigned cast.
			'none'   => $all ? PHP_INT_MAX : 0,
		);
	}

	/**
	 * Published shows with a TMDB ID that are due, never-checked first, then oldest.
	 *
	 * The CASE mirrors Watch_Providers_Tiers::tier(): 'current' or a year at or
	 * after this one is on air, an earlier year is ended, anything else is none.
	 * Keep the two in step.
	 *
	 * @param int  $limit How many.
	 * @param bool $all   Ignore the TTLs: every show with a TMDB ID.
	 * @return array<int, int> Post IDs.
	 */
	public function stale_ids( int $limit, bool $all = false ): array {
		global $wpdb;

		$cut = $this->cutoffs( $all );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- always wants the live answer.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} t ON t.post_id = p.ID AND t.meta_key = 'lezshows_tmdb_id' AND t.meta_value <> ''
				LEFT JOIN {$wpdb->postmeta} f ON f.post_id = p.ID AND f.meta_key = %s
				LEFT JOIN {$wpdb->postmeta} c ON c.post_id = p.ID AND c.meta_key = %s
				WHERE p.post_type = %s AND p.post_status = 'publish'
				AND ( c.meta_value IS NULL OR CAST( c.meta_value AS UNSIGNED ) < CASE
					WHEN LOWER( TRIM( f.meta_value ) ) = 'current'
						OR ( TRIM( f.meta_value ) REGEXP '^[0-9]{4}\$' AND CAST( TRIM( f.meta_value ) AS UNSIGNED ) >= %d ) THEN %d
					WHEN TRIM( f.meta_value ) REGEXP '^[0-9]{4}\$' THEN %d
					ELSE %d END )
				GROUP BY p.ID
				ORDER BY MIN( CAST( c.meta_value AS UNSIGNED ) ) IS NOT NULL, MIN( CAST( c.meta_value AS UNSIGNED ) ), p.ID
				LIMIT %d",
				Airdates::META_FINISH,
				self::META_CHECKED,
				CPT_Shows::SLUG,
				$cut['year'],
				$cut['on_air'],
				$cut['ended'],
				$cut['none'],
				$limit
			)
		);

		return array_map( 'intval', $ids );
	}

	/**
	 * Counts for the CLI, one row per tier.
	 *
	 * `due` is what the sweep would fetch now. In the NONE tier that is only the
	 * never-checked shows, which are fetched once and then left alone.
	 *
	 * @return array{tiers: array<int, array<string, int|string>>, next_scheduled: int|bool}
	 */
	public function get_status(): array {
		global $wpdb;

		$cut = $this->cutoffs();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- CLI report.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.tier, COUNT(*) AS shows,
					SUM( s.checked IS NULL ) AS never,
					SUM( s.checked >= CASE s.tier WHEN 'on_air' THEN %d WHEN 'ended' THEN %d ELSE 0 END ) AS fresh
				FROM (
					SELECT p.ID,
						CASE
							WHEN LOWER( TRIM( MAX( f.meta_value ) ) ) = 'current'
								OR ( TRIM( MAX( f.meta_value ) ) REGEXP '^[0-9]{4}\$' AND CAST( TRIM( MAX( f.meta_value ) ) AS UNSIGNED ) >= %d ) THEN 'on_air'
							WHEN TRIM( MAX( f.meta_value ) ) REGEXP '^[0-9]{4}\$' THEN 'ended'
							ELSE 'none'
						END AS tier,
						MAX( CAST( c.meta_value AS UNSIGNED ) ) AS checked
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} t ON t.post_id = p.ID AND t.meta_key = 'lezshows_tmdb_id' AND t.meta_value <> ''
					LEFT JOIN {$wpdb->postmeta} f ON f.post_id = p.ID AND f.meta_key = %s
					LEFT JOIN {$wpdb->postmeta} c ON c.post_id = p.ID AND c.meta_key = %s
					WHERE p.post_type = %s AND p.post_status = 'publish'
					GROUP BY p.ID
				) s
				GROUP BY s.tier",
				$cut['on_air'],
				$cut['ended'],
				$cut['year'],
				Airdates::META_FINISH,
				self::META_CHECKED,
				CPT_Shows::SLUG
			),
			OBJECT_K
		);

		$tiers  = array();
		$labels = array(
			Watch_Providers_Tiers::ON_AIR => 'weekly',
			Watch_Providers_Tiers::ENDED  => 'monthly',
			Watch_Providers_Tiers::NONE   => 'never (CLI only)',
		);

		foreach ( $labels as $tier => $refresh ) {
			$shows   = (int) ( $rows[ $tier ]->shows ?? 0 );
			$fresh   = (int) ( $rows[ $tier ]->fresh ?? 0 );
			$tiers[] = array(
				'tier'          => $tier,
				'refresh'       => $refresh,
				'shows'         => $shows,
				'fresh'         => $fresh,
				'due'           => $shows - $fresh,
				'never_checked' => (int) ( $rows[ $tier ]->never ?? 0 ),
			);
		}

		return array(
			'tiers'          => $tiers,
			'next_scheduled' => function_exists( 'as_next_scheduled_action' ) ? as_next_scheduled_action( self::AS_HOOK ) : false,
		);
	}
}
