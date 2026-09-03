<?php
/**
 * The character component of a show's score: one gather, two models.
 *
 * @package LWTV
 */

namespace LWTV\CPTs\Shows\Scoring;

use LWTV\CPTs\Shows\Airdates;
use LWTV\Queeries\Is_Actor_Queer;
use LWTV\Queeries\Is_Actor_Trans;

// Bail if directly accessed.
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class Character_Score {

	/**
	 * Gender slugs the LEGACY model treats as not-trans.
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
	 * Collect everything either model needs for one show.
	 *
	 * @param int   $show_id Show post ID.
	 * @param array $options aired_override (array) TVMaze years from a caller that
	 *                       fetched them live, empty for stored meta only.
	 *
	 * @return array
	 */
	public static function gather( int $show_id, array $options = array() ): array {
		$aired_override = isset( $options['aired_override'] ) && is_array( $options['aired_override'] )
			? $options['aired_override']
			: array();

		$airdates = Airdates::get( $show_id );
		$now      = (int) gmdate( 'Y' );
		$seasons  = (int) get_post_meta( $show_id, 'lezshows_seasons', true );

		$aired_years = array();
		$why         = '';

		if ( ! empty( $aired_override ) ) {
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
		$all_terms  = self::batch_terms( $characters );

		if ( ! empty( $characters ) ) {
			update_meta_cache( 'post', $characters );
		}

		$appears  = array();
		$credited = array();

		foreach ( $characters as $char_id ) {
			$char_id   = (int) $char_id;
			$years_set = array();
			$role      = '';
			$rows      = get_field( 'lezchars_show_group', $char_id );

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

		$credited       = array_keys( $credited );
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

			// Legacy check.
			$is_trans_old = true;
			foreach ( self::NOT_TRANS as $slug ) {
				if ( isset( $char_terms[ $slug ] ) ) {
					$is_trans_old = false;
					break;
				}
			}

			// New check.
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
			if ( $is_qirl && ! empty( $actor_ids ) ) {
				$primary_queer = ( new Is_Actor_Queer() )->make( reset( $actor_ids ) );
			}

			if ( $primary_queer ) {
				++$out['queer_irl_cast'];
			} elseif ( $is_qirl ) {
				++$out['qirl_failed_primary'];
			}

			// Trans/NB casting, same shape: the primary actor, not the whole cast.
			$actor_class   = 'unknown';
			$primary_actor = 0;
			$primary_slugs = array();
			if ( ! empty( $actor_ids ) ) {
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
	 *  - `flat` is a slug => true map across both taxonomies, which is all the
	 *    legacy counting needs (`isset( $terms['dead'] )`).
	 *  - the per-taxonomy lists preserve which taxonomy a slug came from, which
	 *    classify_gender() needs and a flattened map cannot answer.
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
	 * Arguably we don't add in actors to a show more than once, but IF we did
	 * then this would come into play.
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
	 * Titles come back HTML-encoded (i.e. Sydney &#8220;Syd&#8221; Feldman, Law
	 * &#038; Order) which is right for the web and noise everywhere else.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	public static function display_title( int $post_id ): string {
		return html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' );
	}
}
