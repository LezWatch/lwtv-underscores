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
use LWTV\Queeries\Is_Actor_Trans;
use LWTV\CPTs\Shows as CPT_Shows;

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

		// Set initial score:
		$score = 0;

		// Base Ratings: Multiply by 3 for a max of 30
		$realness   = min( (int) $meta_fields['lezshows_realness_rating'], 5 );
		$quality    = min( (int) $meta_fields['lezshows_quality_rating'], 5 );
		$screentime = min( (int) $meta_fields['lezshows_screentime_rating'], 5 );
		$score     += ( $realness + $quality + $screentime ) * 3;

		// Thumb Score Rating: 10, 5, 0, -10
		$worth_it    = $meta_fields['lezshows_worthit_rating'];
		$worth_score = array(
			'Yes' => 10,
			'Meh' => 5,
			'No'  => -10,
			'TBD' => 0,
		);
		$score      += ( key_exists( $worth_it, $worth_score ) ) ? $worth_score[ $worth_it ] : 0;

		// Star Rating: 20, 10, 5, -15
		$stars      = $taxonomy_terms['lez_stars'] ?? $meta_fields['lez_stars'];
		$star_score = array(
			'gold'   => 20,
			'silver' => 10,
			'bronze' => 5,
			'anti'   => -15,
		);
		$score     += ( key_exists( $stars, $star_score ) ) ? $star_score[ $stars ] : 0;

		// Trigger Warning: -5, -10, -15
		$trigger       = $taxonomy_terms['lez_triggers'] ?? $meta_fields['lezshows_triggerwarning'];
		$trigger_score = array(
			'on'     => 15,
			'high'   => 15,
			'med'    => 10,
			'medium' => 10,
			'low'    => 5,
		);
		$score        += ( key_exists( $trigger, $trigger_score ) ) ? $trigger_score[ $trigger ] : 0;

		// Shows We Love: 40 points
		if ( 'on' === $meta_fields['lezshows_worthit_show_we_love'] ) {
			$score += 40;
		}

		return $score;
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
		// Initialize counts
		$counts = array(
			'count'     => 0,
			'dead'      => 0,
			'none'      => 0,
			'queer-irl' => 0,
			'trans'     => 0,
			'trans-irl' => 0,
			'score'     => 0,
		);

		// Get character list once
		$characters = lwtv_plugin()->get_characters_list( $post_id, 'query' );
		if ( empty( $characters ) || ! is_array( $characters ) ) {
			return $counts;
		}

		$counts['count'] = count( $characters );

		// Get character roles for score calculation
		$char_roles      = get_post_meta( $post_id, 'lezshows_char_roles', true );
		$chars_regular   = $char_roles['regular'] ?? 0;
		$chars_recurring = $char_roles['recurring'] ?? 0;
		$chars_guest     = $char_roles['guest'] ?? 0;

		// Points: Regular = 5; Recurring = 2; Guests = 1
		$base_score = ( $chars_regular * 5 ) + ( $chars_recurring * 2 ) + $chars_guest;

		// Batch get all taxonomy terms for all characters at once
		$all_terms = $this->get_batch_character_terms( $characters );

		// Prime the post meta cache for all characters in one query so the
		// per-character get_post_meta( 'lezchars_actor' ) calls below are cache hits.
		update_meta_cache( 'post', $characters );

		// Process each character once
		foreach ( $characters as $char_id ) {
			$char_terms = $all_terms[ $char_id ] ?? array();

			// Count different character types
			if ( isset( $char_terms['dead'] ) ) {
				++$counts['dead'];
			}
			if ( isset( $char_terms['none'] ) ) {
				++$counts['none'];
			}
			if ( isset( $char_terms['queer-irl'] ) ) {
				++$counts['queer-irl'];
			}
			if ( ! isset( $char_terms['cisgender'] ) && ! isset( $char_terms['intersex'] ) && ! isset( $char_terms['unknown'] ) ) {
				++$counts['trans'];
			}

			// Check for trans actors (trans-irl)
			$actors_ids = get_post_meta( $char_id, 'lezchars_actor', true );
			if ( ! is_array( $actors_ids ) ) {
				$actors_ids = array( $actors_ids );
			}
			foreach ( $actors_ids as $actor ) {
				if ( ( new Is_Actor_Trans() )->make( $actor ) ) {
					++$counts['trans-irl'];
					break; // Only count once per character
				}
			}
		}

		// Calculate score components
		$queer_irl_score  = $counts['queer-irl'] * 10;
		$no_cliches_score = $counts['none'] * 5;
		$the_dead_score   = $counts['dead'] * -5;
		$trans_score      = 0;

		if ( $counts['trans-irl'] < $counts['trans'] ) {
			$trans_score = ( ( $counts['trans'] - $counts['trans-irl'] ) * -5 );
		} else {
			$trans_score = $counts['trans'] * 10;
		}

		// Add it all together
		$counts['score'] = $base_score + $queer_irl_score + $no_cliches_score + $the_dead_score + $trans_score;

		// Adjust scores based on type of series
		if ( 0 !== $counts['score'] ) {
			if ( has_term( 'movie', 'lez_formats', $post_id ) ) {
				// Movies have a low bar, since they have low stakes
				$counts['score'] = ( $counts['score'] / 2 );
			} elseif ( has_term( 'mini-series', 'lez_formats', $post_id ) ) {
				// Mini-Series similarly have a small run
				$counts['score'] = ( $counts['score'] / 1.5 );
			} elseif ( has_term( 'web-series', 'lez_formats', $post_id ) ) {
				// WebSeries tend to be more daring, but again, low stakes.
				$counts['score'] = ( $counts['score'] / 1.25 );
			}
		}

		// Finally make sure we're between 0 and 100
		$counts['score'] = ( $counts['score'] > 100 ) ? 100 : $counts['score'];

		return $counts;
	}

	/**
	 * Get taxonomy terms for multiple characters in batch queries
	 *
	 * @param array $characters Array of character IDs
	 * @return array Array of character terms organized by character ID
	 */
	private function get_batch_character_terms( $characters ) {
		$all_terms = array();

		// Initialize array for each character
		foreach ( $characters as $char_id ) {
			$all_terms[ $char_id ] = array();
		}

		// Get all terms for all characters in fewer queries
		$taxonomies = array( 'lez_cliches', 'lez_gender' );

		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_object_terms(
				$characters,
				$taxonomy,
				array(
					'fields' => 'all_with_object_id',
				)
			);

			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				foreach ( $terms as $term ) {
					$all_terms[ $term->object_id ][ $term->slug ] = true;
				}
			}
		}

		return $all_terms;
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
		// Covers all taxonomies consumed by show_character_data() and
		// count_queers_all_types() / get_batch_character_terms().
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

		$score        = 0;
		$tropes       = wp_get_post_terms( $post_id, 'lez_tropes' );
		$count_tropes = ( $tropes ) ? count( $tropes ) : 0;

		// Get all trope slugs for efficient processing
		$trope_slugs = wp_list_pluck( $tropes, 'slug' );

		// Check specific tropes efficiently using array operations
		$has_dead     = in_array( 'dead-queers', $trope_slugs, true );
		$is_happy_end = in_array( 'happy-ending', $trope_slugs, true );

		// Death Override Checker.
		$override = get_post_meta( $post_id, 'lezshows_byq_override', true );
		if ( ! empty( $override ) ) {
			$has_dead = false;
		}

		// Good tropes are always good.
		// Maybe tropes are only good IF there isn't Queer-for-Ratings
		$good_tropes  = array( 'happy-ending', 'everyones-queer' );
		$maybe_tropes = array( 'big-queer-wedding', 'coming-out', 'subtext' );
		$bad_tropes   = array( 'queerbashing', 'in-prison', 'queerbaiting', 'big-bad-queers' );
		$ploy_tropes  = array( 'queer-for-ratings', 'queer-laughs', 'happy-then-not', 'erasure', 'subtext', 'queer-of-the-week', 'background-queers' );

		// If there a no tropes, we have a default of 80.
		if ( ( 0 === $count_tropes ) || in_array( 'none', $trope_slugs, true ) ) {
			// No tropes: 80
			$score = 80;
		} else {
			// Calculate all trope counts efficiently using array operations
			$has_tropes = array(
				'good'  => count( array_intersect( $trope_slugs, $good_tropes ) ),
				'maybe' => count( array_intersect( $trope_slugs, $maybe_tropes ) ),
				'bad'   => count( array_intersect( $trope_slugs, $bad_tropes ) ),
				'ploy'  => count( array_intersect( $trope_slugs, $ploy_tropes ) ),
			);

			// Calculate total tropes counted
			$has_tropes['any'] = $has_tropes['good'] + $has_tropes['maybe'] + $has_tropes['bad'] + $has_tropes['ploy'];

			// Pause for C Shows
			if ( 0 === $has_tropes['any'] ) {
				// If a show has NO good/maybe/bad/ploy tropes, it gets a C
				$score = 70;
			} else {
				// Most shows need math!
				$base_score     = ( $has_tropes['good'] + $has_tropes['maybe'] - $has_tropes['ploy'] - $has_tropes['bad'] );
				$counted_tropes = $has_tropes['any'];

				if ( $base_score > 0 ) {
					$score = ( ( $base_score / $counted_tropes ) * 100 );
				} else {
					$score = 0;
				}
			}
		}

		// Add Intersectionality Bonus
		// If you do good with intersectionality you can have more points up to 15
		$count_inters = 0;
		$intersection = get_the_terms( $post_id, 'lez_intersections' );
		if ( is_array( $intersection ) ) {
			$count_inters = count( $intersection );
			$score       += min( ( $count_inters * 3 ), 15 );
		}

		// Sanity Check: Below 0?
		$score = ( $score < 0 ) ? 0 : $score;

		// Death Deductions
		if ( 0 !== $score && $has_dead && ! $is_happy_end ) {
			// If there are dead WITHOUT happy-ending, drop by a third.
			$score = ( $score * .66 );
		} elseif ( 0 !== $score && $has_dead && $is_happy_end ) {
			// If there are dead WITH happy-ending, drop by a quarter
			$score = ( $score * .75 );
		}

		// Sanity Check: Still above 100?
		$score = ( $score > 100 ) ? 100 : $score;

		return $score;
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
				$shows_array = get_post_meta( $char_id, 'lezchars_show_group', true );

				if ( is_array( $shows_array ) && ! empty( $shows_array ) ) {
					foreach ( $shows_array as $char_show ) {
						// Remove the array if it's there.
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
			delete_post_meta( $post_id, 'lezshows_on_air' );
			delete_post_meta( $post_id, 'lezshows_on_air_score' );
			delete_post_meta( $post_id, 'lezshows_score' );
			return;
		}

		// Prime post meta and term object caches for all characters before either
		// loop runs, so show_character_data() and count_queers_all_types() are cache hits.
		$this->prime_character_caches( $post_id );

		// Generate character data
		self::show_character_data( $post_id );

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

		// Keep it between 0 and 100
		$calculate = ( $calculate > 100 ) ? 100 : $calculate;
		$calculate = ( $calculate < 0 ) ? 0 : $calculate;

		// Update the score meta. The rest are done.
		update_post_meta( $post_id, 'lezshows_the_score', $calculate );

		// Update 3rd party scores
		( new Grading() )->update_scores( $post_id );

		// Invalidate score-related caches
		lwtv_plugin()->invalidate_statistics_cache( 'score', $post_id );

		// Cheat and update the show 'on-air' ness.
		$on_air   = 'no';
		$airdates = get_post_meta( $post_id, 'lezshows_airdates', true );

		// If there is no finish date, or the finish date is current, it's on air.
		if ( ! isset( $airdates['finish'] ) || 'current' === lcfirst( $airdates['finish'] ) ) {
			$on_air = 'yes';
		}

		// If there is a finish date and it's in the future, it's on air.
		if ( isset( $airdates['finish'] ) && $airdates['finish'] >= gmdate( 'Y' ) ) {
			$on_air = 'yes';
		}

		update_post_meta( $post_id, 'lezshows_on_air', $on_air );
	}
}
