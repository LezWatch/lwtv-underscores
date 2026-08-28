<?php
/**
 * Name: Show Calculations
 * Description: Calculate various data points for shows
 */

namespace LWTV\CPTs\Shows;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\_Components\Grading;
use LWTV\CPTs\Shows as CPT_Shows;
use LWTV\CPTs\Shows\Scoring\Character_Score;
use LWTV\CPTs\Shows\Scoring\Show_Rating;
use LWTV\CPTs\Shows\Scoring\Show_Tropes;
use LWTV\Theme\Show_Characters;

class Calculations {

	/**
	 * Per-request cache for taxonomy term scaffolding used by show_character_data().
	 * Populated once on first call; reused for every subsequent show in the same request.
	 *
	 * @var array<string, array<string, int>>|null
	 */
	private static ?array $tax_scaffold = null;

	/**
	 * Calculate show rating.
	 *
	 * @param int  $post_id Post ID
	 * @return int          Show score
	 */
	public function show_score( $post_id ) {

		// If this is not a valid show post type, we skip.
		if ( ! isset( $post_id ) || CPT_Shows::SLUG !== get_post_type( $post_id ) ) {
			return;
		}

		// Get all meta fields at once to reduce database queries
		$meta_fields = $this->get_show_meta_fields( $post_id );

		// Get all taxonomy terms at once to reduce database queries
		$taxonomy_terms = $this->get_show_taxonomy_terms( $post_id );

		return Show_Rating::score(
			(int) $meta_fields['lezshows_realness_rating'],
			(int) $meta_fields['lezshows_quality_rating'],
			(int) $meta_fields['lezshows_screentime_rating'],
			(string) $meta_fields['lezshows_worthit_rating'],
			(string) ( $taxonomy_terms['lez_stars'] ?? $meta_fields['lez_stars'] ),
			(string) ( $taxonomy_terms['lez_triggers'] ?? $meta_fields['lezshows_triggerwarning'] ),
			'on' === $meta_fields['lezshows_worthit_show_we_love']
		);
	}

	/**
	 * Get all show meta fields in a single database query
	 *
	 * @param int $post_id The post ID
	 * @return array Array of meta field values
	 */
	private function get_show_meta_fields( $post_id ) {
		$meta_keys = array(
			'lezshows_realness_rating',
			'lezshows_quality_rating',
			'lezshows_screentime_rating',
			'lezshows_worthit_rating',
			'lez_stars',
			'lezshows_triggerwarning',
			'lezshows_worthit_show_we_love',
		);

		$meta_fields = array();
		foreach ( $meta_keys as $key ) {
			$meta_fields[ $key ] = get_post_meta( $post_id, $key, true );
		}

		return $meta_fields;
	}

	/**
	 * Get all show taxonomy terms in batch queries
	 *
	 * @param int $post_id The post ID
	 * @return array Array of taxonomy terms by taxonomy name
	 */
	private function get_show_taxonomy_terms( $post_id ) {
		$taxonomies = array( 'lez_stars', 'lez_triggers' );
		$terms      = array();

		foreach ( $taxonomies as $taxonomy ) {
			$tax_terms = get_the_terms( $post_id, $taxonomy );
			if ( ! empty( $tax_terms ) && ! is_wp_error( $tax_terms ) ) {
				$terms[ $taxonomy ] = $tax_terms[0]->slug;
			}
		}

		return $terms;
	}

	/*
	 * Count Queers
	 *
	 * This will update the metakeys on save
	 *
	 * @param  int $post_id  The post ID.
	 * @return int           The number of queers
	 */
	public function count_queers( $post_id, $type = 'count' ) {

		if ( ! isset( $post_id ) || CPT_Shows::SLUG !== get_post_type( $post_id ) ) {
			return;
		}

		$type_array = array( 'count', 'none', 'dead', 'queer-irl', 'score' );

		// If this isn't one of the above types, return.
		if ( ! in_array( $type, $type_array, true ) ) {
			return;
		}

		// Get all character counts in single pass to avoid redundant queries
		$all_counts = $this->count_queers_all_types( $post_id );

		return $all_counts[ $type ] ?? 0;
	}

	/**
	 * Calculate all character counts in a single pass for performance
	 *
	 * @param int $post_id The post ID
	 * @return array Array of counts for all types
	 */
	private function count_queers_all_types( $post_id ) {
		$post_id = (int) $post_id;

		// Memoised because this is called twice per do_the_math() -- once via
		// count_queers() and once from show_character_score() -- and each call
		// used to redo the full character traversal, the batched term queries and
		// every get_field( 'lezchars_actor' ).
		if ( isset( self::$counts_memo[ $post_id ] ) ) {
			return self::$counts_memo[ $post_id ];
		}

		$counts = array(
			'count'     => 0,
			'dead'      => 0,
			'none'      => 0,
			'queer-irl' => 0,
			'trans'     => 0,
			'trans-irl' => 0,
			'score'     => 0,
		);

		$data = Character_Score::gather( $post_id );

		if ( 0 === $data['count'] ) {
			self::$counts_memo[ $post_id ] = $counts;

			return $counts;
		}

		// Key names are the historical ones and are deliberately unchanged: they
		// are the public shape of count_queers(), which is called from outside
		// this class.
		$counts['count'] = $data['count'];
		$counts['dead']  = $data['dead'];
		$counts['none']  = $data['none'];
		$counts['trans'] = $data['trans'];

		// This becomes lezshows_queer_irl_count, whose only reader is the "actors"
		// column of the Shows We Love comparison. With the actor check on it is the
		// count of characters whose first-billed actor is actually queer, which is
		// what that column has always claimed to show and has never contained.
		$counts['queer-irl'] = $data['queer_irl_scored'];
		$counts['trans-irl'] = $data['trans_irl'];

		$counts['score'] = Character_Score::longevity( $data )['score'];

		self::$counts_memo[ $post_id ] = $counts;

		return $counts;
	}

	/**
	 * Per-show memo for count_queers_all_types().
	 *
	 * Static, and cleared by do_the_math() at the top of a recalculation, mirroring
	 * how Show_Characters::flush_cache() is handled -- a long-running WP-CLI
	 * process must not serve a stale score after the data underneath it changed.
	 *
	 * @var array<int, array>
	 */
	private static array $counts_memo = array();

	/**
	 * Drop the memoised counts for one show, or all of them.
	 *
	 * @param int|null $post_id Show to forget, or null for everything.
	 */
	public static function flush_counts( $post_id = null ): void {
		if ( null === $post_id ) {
			self::$counts_memo = array();

			return;
		}

		unset( self::$counts_memo[ (int) $post_id ] );
	}

	/**
	 * Prime the WordPress post meta and term object caches for every character
	 * belonging to a show before either character loop runs.
	 *
	 * show_character_data() and count_queers_all_types() each iterate over the
	 * same character list and call get_the_terms() / get_post_meta() per character.
	 * Without priming, those are individual DB hits. After this method runs, every
	 * subsequent call for these objects + taxonomies is served from the in-memory
	 * object cache — no extra DB round-trips regardless of character count.
	 *
	 * @param int $post_id Show post ID.
	 */
	private function prime_character_caches( int $post_id ): void {
		$characters = lwtv_plugin()->get_characters_list( $post_id, 'query' );
		if ( empty( $characters ) || ! is_array( $characters ) ) {
			return;
		}

		// One SELECT…IN loads all post meta for every character into the WP cache.
		update_meta_cache( 'post', $characters );

		// One query per taxonomy (batched across all character IDs) primes the term
		// object cache used by get_the_terms() and wp_get_object_terms().
		// Covers all taxonomies consumed by show_character_data() and by
		// Character_Score::batch_terms().
		update_object_term_cache(
			$characters,
			array( 'lez_cliches', 'lez_gender', 'lez_sexuality', 'lez_romantic' )
		);
	}

	/**
	 * Calculate show tropes score.
	 */
	public function show_tropes_score( $post_id ) {

		if ( ! isset( $post_id ) || CPT_Shows::SLUG !== get_post_type( $post_id ) ) {
			return;
		}

		$tropes      = wp_get_post_terms( $post_id, 'lez_tropes' );
		$trope_slugs = wp_list_pluck( $tropes, 'slug' );

		// Death Override Checker.
		$override = get_post_meta( $post_id, 'lezshows_byq_override', true );

		// Add Intersectionality Bonus
		$intersection       = get_the_terms( $post_id, 'lez_intersections' );
		$intersection_count = is_array( $intersection ) ? count( $intersection ) : 0;

		return Show_Tropes::score( $trope_slugs, ! empty( $override ), $intersection_count );
	}

	/**
	 * Calculate show character score.
	 *
	 * NO MATTER WHAT YOU THINK the post counts HAVE to be two separate meta fields.
	 * Otherwise you get weird issues with FacetWP.
	 *
	 * Attempts: 4
	 */
	public function show_character_score( $post_id ) {

		if ( ! isset( $post_id ) || CPT_Shows::SLUG !== get_post_type( $post_id ) ) {
			return;
		}

		// Base Score
		$score = array(
			'alive' => 0,
			'score' => 0,
		);

		// Get all character counts in single pass to avoid redundant queries
		$all_counts   = $this->count_queers_all_types( $post_id );
		$number_chars = max( 0, $all_counts['count'] );
		$number_dead  = max( 0, $all_counts['dead'] );

		// If there are no chars, the score will be zero, so bail early.
		if ( 0 !== $number_chars ) {
			$score['alive'] = ( ( ( $number_chars - $number_dead ) / $number_chars ) * 100 );
			$score['score'] = $all_counts['score'];
		}

		// Update post meta for counts (NO YOU CANNOT MAKE THIS AN ARRAY)
		update_post_meta( $post_id, 'lezshows_char_count', $number_chars );
		update_post_meta( $post_id, 'lezshows_dead_count', $number_dead );
		update_post_meta( $post_id, 'lezshows_queer_irl_count', (int) $all_counts['queer-irl'] );

		return $score;
	}

	/**
	 * Calculate show character data.
	 */
	public function show_character_data( $show_id ) {

		if ( ! isset( $show_id ) || CPT_Shows::SLUG !== get_post_type( $show_id ) ) {
			return;
		}

		// What role each character has
		$role_data = array(
			'regular'   => 0,
			'recurring' => 0,
			'guest'     => 0,
		);

		// Create a massive array of all the terms we care about...
		$valid_taxes = array(
			'gender'    => 'lez_gender',
			'sexuality' => 'lez_sexuality',
			'romantic'  => 'lez_romantic',
		);
		// Build the zero-initialised slug scaffold once per request and reuse it.
		// get_terms() hits the DB (or term cache) on every call; during a bulk
		// recalculation this would repeat for every show even though the term
		// list never changes within a single request.
		if ( null === self::$tax_scaffold ) {
			self::$tax_scaffold = array();
			foreach ( $valid_taxes as $title => $taxonomy ) {
				$terms = get_terms( $taxonomy );
				if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
					self::$tax_scaffold[ $title ] = array();
					foreach ( $terms as $term ) {
						self::$tax_scaffold[ $title ][ $term->slug ] = 0;
					}
				}
			}
		}

		// Start with the cached scaffold; each show gets its own copy to count into.
		$tax_data = self::$tax_scaffold;

		// Get array of characters (by ID)
		$characters = lwtv_plugin()->get_characters_list( $show_id, 'query' );

		if ( is_array( $characters ) ) {
			foreach ( $characters as $char_id ) {
				$shows_array = get_field( 'lezchars_show_group', $char_id );

				if ( is_array( $shows_array ) && ! empty( $shows_array ) ) {
					foreach ( $shows_array as $char_show ) {
						// Remove the array (pre-migration CMB2 data).
						if ( is_array( $char_show['show'] ) ) {
							$char_show['show'] = $char_show['show'][0];
						}

						// phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
						if ( $char_show['show'] == $show_id ) {
							// Bump the array for this role
							++$role_data[ $char_show['type'] ];

							// Now we'll sort gender and stuff...
							foreach ( $valid_taxes as $title => $taxonomy ) {
								$this_term = get_the_terms( $char_id, $taxonomy );
								if ( $this_term && ! is_wp_error( $this_term ) ) {
									foreach ( $this_term as $term ) {
										++$tax_data[ $title ][ $term->slug ];
									}
								}
							}
						}
					}
				}
			}
		}

		// Update the roles scores
		update_post_meta( $show_id, 'lezshows_char_roles', $role_data );

		/**
		 * Update the taxonomies
		 *  - lezshows_char_sexuality
		 *  - lezshows_char_gender
		 *  - lezshows_char_romantic
		 */
		foreach ( $valid_taxes as $title => $taxonomy ) {
			update_post_meta( $show_id, 'lezshows_char_' . $title, $tax_data[ $title ] );
		}
	}

	/**
	 * do_the_math function.
	 *
	 * This will update the following metakeys on save:
	 *  - lezshows_char_count      Number of characters
	 *  - lezshows_dead_count      Number of dead characters
	 *  - lezshows_the_score       Score of show data
	 *
	 * @access public
	 * @param  int  $post_id
	 * @param  bool $force    Force the calculation to run
	 * @return void
	 */
	public function do_the_math( $post_id, $force = false ): void {

		// If force is true, destroy any cached data before recalculation
		if ( $force ) {
			lwtv_plugin()->invalidate_statistics_cache( 'score', $post_id );
			lwtv_plugin()->invalidate_statistics_cache( 'post_type_shows', $post_id );
		}

		if ( ! isset( $post_id ) || CPT_Shows::SLUG !== get_post_type( $post_id ) ) {
			// delete the meta fields
			delete_post_meta( $post_id, 'lezshows_char_roles' );
			delete_post_meta( $post_id, 'lezshows_char_gender' );
			delete_post_meta( $post_id, 'lezshows_char_sexuality' );
			delete_post_meta( $post_id, 'lezshows_char_romantic' );
			delete_post_meta( $post_id, 'lezshows_char_count' );
			delete_post_meta( $post_id, 'lezshows_dead_count' );
			delete_post_meta( $post_id, 'lezshows_the_score' );
			delete_post_meta( $post_id, 'lezshows_the_score_uncapped' );
			delete_post_meta( $post_id, 'lezshows_on_air' );
			delete_post_meta( $post_id, 'lezshows_on_air_score' );
			delete_post_meta( $post_id, 'lezshows_score' );
			return;
		}

		// Start this show's calculation from a clean memo. Show_Characters caches
		// resolved character lists per request, which collapses the three
		// identical lookups below into one -- but a recalculation must never be
		// served a list built before whatever prompted it. Flushing per show also
		// stops the memo growing across a bulk run over every show.
		Show_Characters::flush_cache( $post_id );
		self::flush_counts( $post_id );

		// Prime post meta and term object caches for all characters before either
		// loop runs, so show_character_data() and count_queers_all_types() are cache hits.
		$this->prime_character_caches( $post_id );

		// Generate character data
		self::show_character_data( $post_id );

		// show_character_data() has just rewritten lezshows_char_roles, which is an
		// INPUT to the legacy character score. count_queers() is public and can be
		// called from anywhere, so a memo taken before this write would pin a score
		// built on the previous run's role counts. Flushing here rather than
		// reordering keeps the dependency visible instead of implicit.
		self::flush_counts( $post_id );

		// Get the ratings
		$score_show_rating_raw = self::show_score( $post_id );
		$score_show_tropes_raw = self::show_tropes_score( $post_id );
		$score_chars_total     = self::show_character_score( $post_id );

		// Guard against null returns from sub-calculations before doing arithmetic.
		// Any null here in PHP 8.1+ causes a fatal TypeError that halts execution
		// before update_post_meta runs, leaving the show with no score.
		if ( null === $score_show_rating_raw || null === $score_show_tropes_raw || ! is_array( $score_chars_total ) ) {
			lwtv_plugin()->debug_log(
				'show-score',
				sprintf(
					'Score calculation incomplete for show %d — show_rating=%s tropes=%s chars=%s',
					$post_id,
					wp_json_encode( $score_show_rating_raw ),
					wp_json_encode( $score_show_tropes_raw ),
					wp_json_encode( $score_chars_total )
				)
			);
		}

		$score_show_rating = (float) ( $score_show_rating_raw ?? 0 );
		$score_show_tropes = (float) ( $score_show_tropes_raw ?? 0 );
		$score_chars_alive = (float) ( $score_chars_total['alive'] ?? 0 );
		$score_chars_score = (float) ( $score_chars_total['score'] ?? 0 );

		// Calculate the full score
		$calculate = ( $score_show_rating + $score_show_tropes + $score_chars_alive + $score_chars_score ) / 4;

		// Keep the true value before clamping.
		//
		// The clamp used to be the only thing stored, which threw away the one
		// piece of information that distinguishes shows at the ceiling from each
		// other -- the same mistake, one level up, as the old character score
		// pinning 38 shows at exactly 100 with no way to rank them. Today only one
		// show clears 100, so this buys little; the point is that it cannot start
		// creating ties again as the data improves.
		//
		// lezshows_the_score stays clamped, deliberately. Everything reads it --
		// display, the stats SQL, Grading, of-the-day, the taxonomy queries -- and
		// none of that should have to learn about a 0-115 range.
		update_post_meta( $post_id, 'lezshows_the_score_uncapped', $calculate );

		// Keep it between 0 and 100
		$calculate = ( $calculate > 100 ) ? 100 : $calculate;
		$calculate = ( $calculate < 0 ) ? 0 : $calculate;

		// Update the score meta. The rest are done.
		update_post_meta( $post_id, 'lezshows_the_score', $calculate );

		/**
		 * Whether to refresh this show's third-party (TMDB / TVMaze) scores.
		 *
		 * Filterable because Grading\TVMaze::update_scores() makes a live
		 * wp_remote_get() on a transient miss, so a bulk recalculation over the
		 * whole corpus would fire thousands of unthrottled requests -- well past
		 * TVMaze's documented 20-calls-per-10-seconds, and the resulting 429s get
		 * written into lezshows_3rd_scores as if they were data.
		 *
		 * A recalculation triggered by a change to OUR scoring has no reason to
		 * refetch somebody else's, so `wp lwtv calc --all` turns this off and lets
		 * the on-save path and the daily cron refresh them at their own pace.
		 *
		 * @param bool $refresh Whether to update third-party scores.
		 * @param int  $post_id Show post ID.
		 */
		if ( apply_filters( 'lwtv_recalculate_third_party_scores', true, $post_id ) ) {
			( new Grading() )->update_scores( $post_id );
		}

		// Invalidate score-related caches
		lwtv_plugin()->invalidate_statistics_cache( 'score', $post_id );

		// Cheat and update the show 'on-air' ness.
		$on_air = 'no';
		$finish = get_post_meta( $post_id, 'lezshows_airdates_finish', true );
		if ( empty( $finish ) ) {
			$legacy = get_post_meta( $post_id, 'lezshows_airdates', true );
			$finish = is_array( $legacy ) ? ( $legacy['finish'] ?? '' ) : '';
		}

		// If there is no finish date, or the finish date is current, it's on air.
		if ( empty( $finish ) || 'current' === lcfirst( $finish ) ) {
			$on_air = 'yes';
		}

		// If there is a finish date and it's in the future, it's on air.
		if ( ! empty( $finish ) && $finish >= gmdate( 'Y' ) ) {
			$on_air = 'yes';
		}

		update_post_meta( $post_id, 'lezshows_on_air', $on_air );
	}
}
