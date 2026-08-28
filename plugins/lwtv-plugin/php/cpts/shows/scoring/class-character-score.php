<?php
/**
 * The character component of a show's score: one gather, two models.
 *
 * This class exists to hold exactly ONE copy of a decision that was previously
 * made twice. `Calculations::count_queers_all_types()` computed the live score
 * while `cli-score-preview.php` computed its own replica so it could run before
 * anything was wired in. That was the right call for a preview and it became a
 * divergence risk the moment the preview was used to CALIBRATE the live model:
 * the number in the CSV and the number the site would store could drift apart
 * silently, quietly invalidating the calibration the CSV was used to pick.
 *
 * Two copies of one decision has now caused three bugs in this project -- the
 * transposed denominator-tier labels, the tier column that disagreed with the
 * tier actually used, and this. Hence the shape here: the data is gathered once,
 * each model is a pure function of that data, and the CLI's only remaining job
 * is presentation.
 *
 * ⚠ NO HTTP, EVER. gather() reads meta and taxonomy only. The TVMaze aired-years
 * set arrives as $aired_override, supplied by a CLI that fetched it, or from the
 * `lezshows_aired_years` meta the backfill cron writes. Per-show HTTP inside a
 * recalculation would mean rate limits, timeouts and partial failures leaving
 * some shows on one denominator tier and others on another depending on when the
 * API blinked.
 *
 * @package LWTV
 */

namespace LWTV\CPTs\Shows\Scoring;

use LWTV\Queeries\Is_Actor_Queer;
use LWTV\Queeries\Is_Actor_Trans;

// Bail if directly accessed.
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class Character_Score {

	/**
	 * Gender slugs the LEGACY model treats as not-trans.
	 *
	 * Preserved exactly as `count_queers_all_types()` had it, because the legacy
	 * path must stay a faithful replica while it is still the live one. It is an
	 * exclusion test -- anything not on this list counts as trans -- which is why
	 * a character with no gender term at all counted as trans. The new model uses
	 * Longevity::classify_gender() instead, an explicit three-way classification.
	 */
	public const NOT_TRANS = array( 'cisgender', 'intersex', 'unknown' );

	/**
	 * Format slug => divisor. Applies to both models identically.
	 */
	public const FORMAT_DIVISORS = array(
		'movie'       => 2,
		'mini-series' => 1.5,
		'web-series'  => 1.25,
	);

	/**
	 * The gather options implied by the current flags.
	 *
	 * One place, so a caller cannot gather less than the active model needs (a
	 * silently wrong score) or more than it needs (a silent slowdown).
	 *
	 * @return array
	 */
	public static function options_from_flags(): array {
		return array(
			'longevity'   => true,
			'actor_check' => true,
		);
	}

	/**
	 * Collect everything either model needs for one show.
	 *
	 * Ordering here is load-bearing and not obvious: the characters must be read
	 * and reduced to their credited years BEFORE the aired-years set is vetted,
	 * because the vet's strongest signal is whether the set can account for those
	 * years. The vet's verdict then decides the denominator every weight is
	 * measured against. So it is characters, then vet, then denominator -- not the
	 * other way round.
	 *
	 * The two gathering gates are PERFORMANCE gates, not feature flags, and they
	 * exist because each unlocks work the other models never read. Across ~2,300
	 * shows and ~30,000 characters that difference is thousands of queries:
	 *
	 * | gate          | unlocks                                                   |
	 * |---------------|-----------------------------------------------------------|
	 * | `longevity`   | `lezchars_show_group` per character (credited years), the  |
	 * |               | airdates, the season count, the aired-years vetting, and   |
	 * |               | the primary actor's gender terms                          |
	 * | `actor_check` | Is_Actor_Queer on the primary actor of each character      |
	 * |               | TAGGED queer-irl -- far cheaper, since most are not        |
	 *
	 * With both false, gather() does exactly the work count_queers_all_types() did
	 * before this class existed. Callers pass what their flags actually turned on
	 * rather than taking the defaults, so a disabled model costs nothing.
	 *
	 * @param int   $show_id Show post ID.
	 * @param array $options aired_override (array) TVMaze years from a caller that
	 *                       fetched them live, empty for stored meta only;
	 *                       longevity (bool) gather longevity inputs;
	 *                       actor_check (bool) gather primary-actor queerness.
	 *
	 * @return array
	 */
	public static function gather( int $show_id, array $options = array() ): array {
		$aired_override = isset( $options['aired_override'] ) && is_array( $options['aired_override'] )
			? $options['aired_override']
			: array();
		$with_longevity = (bool) ( $options['longevity'] ?? true );
		$with_actor     = (bool) ( $options['actor_check'] ?? true );

		// The longevity model folds queer casting into casting_multiplier(), so it
		// cannot score without the actor check regardless of that flag.
		$with_actor = $with_actor || $with_longevity;

		$airdates = $with_longevity
			? Airdates::get( $show_id )
			: array(
				'start'  => '',
				'finish' => '',
			);
		$now      = (int) gmdate( 'Y' );
		$seasons  = $with_longevity ? (int) get_post_meta( $show_id, 'lezshows_seasons', true ) : 0;

		$aired_years = array();
		$why         = '';

		if ( ! $with_longevity ) {
			$why = 'longevity data not gathered';
		} elseif ( ! empty( $aired_override ) ) {
			$aired_years = array_map( 'intval', $aired_override );
		} else {
			$stored = get_post_meta( $show_id, 'lezshows_aired_years', true );
			if ( is_array( $stored ) && ! empty( $stored ) ) {
				$aired_years = array_map( 'intval', $stored );
			} else {
				$why = 'no stored lezshows_aired_years';
			}
		}

		$source_label = empty( $aired_override ) ? 'stored aired years' : 'tvmaze seasons';

		// Format divisor.
		$format  = '';
		$divisor = 1;
		foreach ( self::FORMAT_DIVISORS as $slug => $value ) {
			if ( has_term( $slug, 'lez_formats', $show_id ) ) {
				$format  = $slug;
				$divisor = $value;
				break;
			}
		}

		$characters = lwtv_plugin()->get_characters_list( $show_id, 'query' );
		$characters = is_array( $characters ) ? $characters : array();

		// Batched, not per-character. The live path used one wp_get_object_terms()
		// across every character while the preview used has_term() per character
		// with primed caches; sharing the slower of the two would have made a full
		// recalculation worse, which is the one thing this project must not do.
		$all_terms = self::batch_terms( $characters );

		if ( ! empty( $characters ) ) {
			update_meta_cache( 'post', $characters );
		}

		// Pre-pass: reduce each character's show-group rows to the union of years
		// they are credited on THIS show, plus their strongest role. Read once;
		// the scoring pass below consumes this rather than re-reading ACF.
		$appears  = array();
		$credited = array();

		foreach ( $characters as $char_id ) {
			$char_id   = (int) $char_id;
			$years_set = array();
			$role      = '';
			$rows      = $with_longevity ? get_field( 'lezchars_show_group', $char_id ) : null;

			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					$row_show = is_array( $row['show'] ?? null ) ? ( $row['show'][0] ?? 0 ) : ( $row['show'] ?? 0 );

					// phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual
					if ( $row_show != $show_id ) {
						continue;
					}

					$role = self::strongest_role( $role, (string) ( $row['type'] ?? '' ) );

					$years = $row['appears'] ?? null;
					if ( is_array( $years ) ) {
						foreach ( $years as $year ) {
							$year = (int) $year;
							if ( 0 !== $year ) {
								$years_set[ $year ] = true;
							}
						}
					} elseif ( is_numeric( $years ) ) {
						$years_set[ (int) $years ] = true;
					}
				}
			}

			$appears[ $char_id ] = array(
				'years' => array_keys( $years_set ),
				'role'  => $role,
			);

			$credited += $years_set;
		}

		$credited = array_keys( $credited );

		// Vet the aired-years set before it is used for anything. It feeds both
		// the denominator and the appears-year intersection, so a set missing
		// seasons has to be dropped for BOTH or the guard achieves nothing --
		// rejecting it from run_years while still intersecting against it would
		// keep the exact damage the guard exists to prevent.
		$coverage       = Longevity::appearance_coverage( $aired_years, $credited );
		$discarded      = Longevity::discarded_years( $aired_years, $credited );
		$aired_set      = count( array_unique( array_filter( array_map( 'intval', $aired_years ) ) ) );
		$verdict        = Longevity::aired_years_verdict( $aired_years, $seasons, $airdates['start'], $credited );
		$aired_years    = Longevity::usable_aired_years( $aired_years, $seasons, $airdates['start'], $credited );
		$aired_rejected = ! in_array( $verdict, array( Longevity::VERDICT_NONE, Longevity::VERDICT_OK ), true );

		// The tier comes from the function that chose it, never re-derived here.
		// The credited count goes in as the denominator's floor: a show cannot have
		// run for fewer calendar years than its own characters were on screen for.
		$denominator = Longevity::run_years_detail(
			$aired_years,
			$seasons,
			$airdates['start'],
			$airdates['finish'],
			$now,
			array(),
			count( $credited )
		);

		$out = array(
			'id'                   => $show_id,
			'format'               => $format,
			'divisor'              => $divisor,
			'start'                => $airdates['start'],
			'finish'               => $airdates['finish'],
			'seasons'              => $seasons,
			'aired_years'          => $aired_years,
			'aired_set'            => $aired_set,
			'aired_rejected'       => $aired_rejected,
			'aired_verdict'        => $verdict,
			'aired_why'            => $why,
			'aired_source'         => $source_label,
			'coverage'             => $coverage,
			'credited_years'       => count( $credited ),
			'disc_outside'         => $discarded['outside'],
			'disc_hole'            => $discarded['hole'],
			'run_years'            => $denominator['years'],
			'run_years_floored'    => $denominator['floored'],
			'tier'                 => $denominator['tier'],
			'still_airing'         => $denominator['still_airing'],
			'span'                 => $denominator['span'],
			'count'                => count( $characters ),
			// Read here rather than inside legacy() so that model stays pure and
			// therefore unit-testable.
			'char_roles'           => get_post_meta( $show_id, 'lezshows_char_roles', true ),
			'dead'                 => 0,
			'none'                 => 0,
			'queer_irl'            => 0,
			'queer_irl_cast'       => 0,
			'qirl_failed_primary'  => 0,
			'trans'                => 0,
			'trans_irl'            => 0,
			'trans_new'            => 0,
			'trans_miscast'        => 0,
			'miscast_detail'       => array(),
			'judged_on_trans'      => 0,
			'judged_on_qirl'       => 0,
			'actor_gender_unknown' => 0,
			'unknown_actor_slugs'  => array(),
			'gender_unclassified'  => 0,
			'unclassified_slugs'   => array(),
			'no_appears'           => 0,
			'characters'           => array(),
		);

		foreach ( $characters as $char_id ) {
			$char_id    = (int) $char_id;
			$char_terms = $all_terms[ $char_id ]['flat'] ?? array();

			$is_dead = isset( $char_terms['dead'] );
			$is_none = isset( $char_terms['none'] );
			$is_qirl = isset( $char_terms['queer-irl'] );

			// LEGACY trans test: the exclusion check. Kept so the legacy model
			// stays a faithful replica of what the site stores today.
			$is_trans_old = true;
			foreach ( self::NOT_TRANS as $slug ) {
				if ( isset( $char_terms[ $slug ] ) ) {
					$is_trans_old = false;
					break;
				}
			}

			// NEW model: an explicit three-way classification from the actual
			// gender terms, so an untriaged term is reported rather than silently
			// counted as cis.
			$gender_slugs = $all_terms[ $char_id ]['lez_gender'] ?? array();
			$gender_class = Longevity::classify_gender( $gender_slugs );

			if ( 'unclassified' === $gender_class ) {
				++$out['gender_unclassified'];
				foreach ( $gender_slugs as $slug ) {
					$out['unclassified_slugs'][ $slug ] = true;
				}
			}

			$out['dead']      += $is_dead ? 1 : 0;
			$out['none']      += $is_none ? 1 : 0;
			$out['queer_irl'] += $is_qirl ? 1 : 0;
			$out['trans']     += $is_trans_old ? 1 : 0;

			if ( 'trans-or-nb' === $gender_class ) {
				++$out['trans_new'];
			}

			$actor_ids = get_field( 'lezchars_actor', $char_id ) ?: array();
			foreach ( $actor_ids as $actor ) {
				if ( ( new Is_Actor_Trans() )->make( $actor ) ) {
					++$out['trans_irl'];
					break;
				}
			}

			// The Tambor Takedown. Both conditions are required, matching the
			// implementation in theme/class-show-characters.php that nothing has
			// ever reached: the character is tagged queer-irl AND their
			// first-billed actor is actually queer. Actors are stored in billing
			// order, so the primary is simply the first.
			$primary_queer = false;
			if ( $with_actor && $is_qirl && ! empty( $actor_ids ) ) {
				$primary_queer = ( new Is_Actor_Queer() )->make( reset( $actor_ids ) );
			}

			if ( $primary_queer ) {
				++$out['queer_irl_cast'];
			} elseif ( $is_qirl ) {
				++$out['qirl_failed_primary'];
			}

			// Trans/NB casting, same shape: the primary actor, not the whole cast.
			// Read via Longevity::classify_actor_gender() rather than
			// Is_Actor_Trans, which decides with strpos( $slug, 'trans' ) and so
			// cannot see an actor tagged non-binary.
			$actor_class   = 'unknown';
			$primary_actor = 0;
			$primary_slugs = array();
			if ( $with_longevity && ! empty( $actor_ids ) ) {
				$primary_actor = (int) reset( $actor_ids );
				$actor_terms   = get_the_terms( $primary_actor, 'lez_actor_gender' );
				$primary_slugs = ( is_array( $actor_terms ) ) ? wp_list_pluck( $actor_terms, 'slug' ) : array();
				$actor_class   = Longevity::classify_actor_gender( $primary_slugs );
			}

			$primary_trans = ( 'trans-or-nb' === $actor_class );
			$casting       = Longevity::casting_multiplier( $gender_class, $primary_queer, $actor_class );

			if ( 'trans-or-nb' === $gender_class && 'unknown' === $actor_class ) {
				++$out['actor_gender_unknown'];
				foreach ( $primary_slugs as $slug ) {
					$out['unknown_actor_slugs'][ $slug ] = true;
				}
			}

			if ( $casting < 1.0 ) {
				++$out['trans_miscast'];

				// A miscast verdict is the only place this model actively DOCKS a
				// show, so it has to be auditable rather than trusted.
				$out['miscast_detail'][] = array(
					'character'    => self::display_title( $char_id ),
					'gender_terms' => implode( ' ', $gender_slugs ),
					'actor'        => $primary_actor ? self::display_title( $primary_actor ) : '(no actor listed)',
					'actor_terms'  => empty( $primary_slugs ) ? '(none)' : implode( ' ', $primary_slugs ),
				);
			}

			// Which standard decided this character's multiplier. With one
			// combined signal these are mutually exclusive, so counting queer-irl
			// tags overstates what that check is doing.
			if ( 'trans-or-nb' === $gender_class ) {
				++$out['judged_on_trans'];
			} elseif ( 'cis' === $gender_class ) {
				++$out['judged_on_qirl'];
			}

			$role  = $appears[ $char_id ]['role'];
			$years = Longevity::character_years( $appears[ $char_id ]['years'], $aired_years );

			if ( 0 === $years ) {
				++$out['no_appears'];
				$weight        = Longevity::role_proxy_weight( $role );
				$weight_source = 'role proxy';
			} else {
				$weight        = Longevity::weight( $years, $denominator['years'] );
				$weight_source = 'appears';
			}

			$value = Longevity::character_value( $role, $casting, $is_none, $is_dead );

			$flags = array();
			if ( 'trans-or-nb' === $gender_class ) {
				$flags[] = $primary_trans ? 'trans-cast' : 'trans-MISCAST';
			} elseif ( 'unclassified' === $gender_class ) {
				$flags[] = 'gender-UNCLASSIFIED';
			} elseif ( $primary_queer ) {
				$flags[] = 'qirl';
			} elseif ( $is_qirl ) {
				$flags[] = 'qirl-TAG-ONLY';
			}
			if ( $is_none ) {
				$flags[] = 'no-cliche';
			}
			if ( $is_dead ) {
				$flags[] = 'dead';
			}

			$out['characters'][] = array(
				'id'            => $char_id,
				'name'          => self::display_title( $char_id ),
				'role'          => $role ?: '(none)',
				'years'         => $years,
				'weight'        => $weight,
				'weight_source' => $weight_source,
				'value'         => $value,
				'contribution'  => $value * $weight,
				'flags'         => implode( ' ', $flags ),
			);
		}

		$out['queer_irl_scored'] = $out['queer_irl_cast'];

		return $out;
	}

	/**
	 * The LONGEVITY character score: weighted sum, format divisor, saturating.
	 *
	 * Every modifier -- role, queer casting, trans casting, clichés, death -- is
	 * already inside each character's contribution. Nothing is added at the show
	 * level, so there is no equivalent of the legacy trans aggregate; adding one
	 * would double-count what casting_multiplier() already applied.
	 *
	 * @param array      $data    Output of gather().
	 * @param float|null $ceiling SATURATION_K override, for calibration sweeps.
	 *
	 * @return array{raw:float,divided:float,score:float}
	 */
	public static function longevity( array $data, ?float $ceiling = null ): array {
		$raw = 0.0;
		foreach ( $data['characters'] as $char ) {
			$raw += $char['contribution'];
		}

		$divided = ( 0.0 !== $raw ) ? ( $raw / $data['divisor'] ) : 0.0;

		return array(
			'raw'     => $raw,
			'divided' => (float) $divided,
			'score'   => Longevity::saturate( (float) $divided, $ceiling ),
		);
	}

	/**
	 * Taxonomy terms for many characters in two queries rather than 2N.
	 *
	 * Keeps the terms BOTH flattened and split by taxonomy, because the two
	 * models want different things from them and neither should pay for a re-read:
	 *
	 *  - `flat` is a slug => true map across both taxonomies, which is all the
	 *    legacy counting needs (`isset( $terms['dead'] )`).
	 *  - the per-taxonomy lists preserve which taxonomy a slug came from, which
	 *    classify_gender() needs and a flattened map cannot answer.
	 *
	 * An earlier revision flattened only, then called get_the_terms() per
	 * character to recover the gender slugs -- correct, cache-warm, and still one
	 * function call per character for data already in hand.
	 *
	 * @param array $characters Character post IDs.
	 *
	 * @return array<int, array{flat:array<string,bool>,lez_gender:array,lez_cliches:array}>
	 */
	public static function batch_terms( array $characters ): array {
		$taxonomies = array( 'lez_cliches', 'lez_gender' );
		$all_terms  = array();

		foreach ( $characters as $char_id ) {
			$all_terms[ (int) $char_id ] = array_merge(
				array( 'flat' => array() ),
				array_fill_keys( $taxonomies, array() )
			);
		}

		if ( empty( $characters ) ) {
			return $all_terms;
		}

		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_object_terms( $characters, $taxonomy, array( 'fields' => 'all_with_object_id' ) );

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				$all_terms[ $term->object_id ]['flat'][ $term->slug ] = true;
				$all_terms[ $term->object_id ][ $taxonomy ][]         = $term->slug;
			}
		}

		return $all_terms;
	}

	/**
	 * The stronger of two role slugs.
	 *
	 * A character with two show-group rows on one show -- a guest stint and a
	 * later regular run -- is one character in one role, the better of the two.
	 *
	 * Ranked by the POINTS each role is worth rather than by their position in
	 * ROLE_POINTS, so reordering that array cannot silently invert the hierarchy.
	 * An unrecognised slug scores 0 and therefore never wins, matching the
	 * behaviour this replaced.
	 *
	 * @param string $current Role held so far.
	 * @param string $found   Role from this row.
	 *
	 * @return string
	 */
	public static function strongest_role( string $current, string $found ): string {
		$current_points = Longevity::ROLE_POINTS[ $current ] ?? 0;
		$found_points   = Longevity::ROLE_POINTS[ $found ] ?? 0;

		return ( $found_points > $current_points ) ? $found : $current;
	}

	/**
	 * A post title fit for terminal output and for comparison.
	 *
	 * Titles come back HTML-encoded -- Sydney &#8220;Syd&#8221; Feldman, Law
	 * &#038; Order -- which is right for the web and noise everywhere else.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	public static function display_title( int $post_id ): string {
		return html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' );
	}
}
