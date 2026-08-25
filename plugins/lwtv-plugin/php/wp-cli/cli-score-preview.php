<?php
/*
 * WP CLI Commands for previewing longevity-weighted show scores.
 *
 * READ ONLY. This command writes no post meta and mutates nothing. It exists so
 * the effect of the longevity model can be inspected against real shows before
 * any of it is wired into Calculations::count_queers_all_types().
 *
 * See docs/plans/show-score-longevity.md. The main job here is calibrating
 * SATURATION_K: pick it from the real distribution so the median show's score is
 * roughly unchanged, making the change a re-ranking rather than a mass deflation
 * that slides every letter grade down at once. Use --k to try values.
 *
 * Registered separately from cli-calc.php because WP_CLI_LWTV_Calculate is
 * declared with an __invoke(), so WP-CLI treats it as a single command and it
 * cannot host subcommands.
 */

// Bail if directly accessed
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	die();
}

use LWTV\CPTs\Shows as CPT_Shows;
use LWTV\CPTs\Shows\Airdates;
use LWTV\CPTs\Shows\Calculations as Shows_Calculations;
use LWTV\CPTs\Shows\Longevity;
use LWTV\Queeries\Is_Actor_Queer;
use LWTV\Queeries\Is_Actor_Trans;
use LWTV\Statistics\Build\Score_Distribution;

/**
 * LezWatch.TV command to preview longevity-weighted scores.
 */
class WP_CLI_LWTV_Score_Preview {

	/**
	 * TV Maze API URL. Same constant the audit and calendar commands use.
	 */
	public const TVMAZE_URL = 'https://api.tvmaze.com';

	/**
	 * Pause between TVMaze requests in --all mode, in milliseconds.
	 */
	public const SLEEP_MS = 250;

	/**
	 * Gender terms that mean "not counted as trans" in the existing model.
	 */
	public const NOT_TRANS = array( 'cisgender', 'intersex', 'unknown' );

	/**
	 * "Failing Grades" threshold: score < this.
	 *
	 * Mirrors the $low default of Statistics\Build\Score_Distribution::tails().
	 * Those are function defaults rather than constants, so this cannot reference
	 * them directly -- if they change there, change them here too.
	 */
	public const BAND_FAILING = 20;

	/**
	 * "The 90+ Club" threshold: score >= this. Mirrors tails()'s $high default.
	 */
	public const BAND_TOP = 90;

	/**
	 * Score at which class-grading.php's colour ramp flips red to green.
	 */
	public const COLOUR_INFLECTION = 51;

	/**
	 * Format slug => divisor, exactly as count_queers_all_types() applies them.
	 */
	public const FORMAT_DIVISORS = array(
		'movie'       => 2,
		'mini-series' => 1.5,
		'web-series'  => 1.25,
	);

	/**
	 * Preview the longevity-weighted score for one show, or all of them.
	 *
	 * ## OPTIONS
	 *
	 * [<post_id>]
	 * : Show ID to preview. Omit and pass --all for the full comparison.
	 *
	 * [--all]
	 * : Every published show. Pair with --format=csv.
	 *
	 * [--k=<float>]
	 * : Override SATURATION_K for this run. Calibration knob.
	 *
	 * [--tvmaze]
	 * : Fetch season dates from TVMaze for exact aired years. Implied for a
	 *   single show; opt-in for --all because it is one API call per show.
	 *
	 * [--verbose]
	 * : Show the per-character breakdown. Single show only.
	 *
	 * [--format=<format>]
	 * : table (default) or csv.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lwtv score-preview 655 --verbose
	 *     wp lwtv score-preview --all --format=csv > movers.csv
	 *     wp lwtv score-preview --all --k=60
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( $args, $assoc_args = array() ) {
		$format  = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$do_all  = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );
		$verbose = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'verbose', false );
		$tvmaze  = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'tvmaze', false );
		$ceiling = \WP_CLI\Utils\get_flag_value( $assoc_args, 'k', null );
		$ceiling = ( null === $ceiling ) ? null : (float) $ceiling;

		if ( ! $do_all && empty( $args[0] ) ) {
			\WP_CLI::error( 'Pass a show ID, or --all. Try: wp lwtv score-preview 655 --verbose' );
		}

		\WP_CLI::log( 'Read-only preview. No meta is written.' );
		\WP_CLI::log( 'SATURATION_K = ' . ( $ceiling ?? Longevity::SATURATION_K ) . ( null === $ceiling ? ' (default)' : ' (override)' ) );
		\WP_CLI::log( '' );

		if ( $do_all ) {
			$this->preview_all( $format, $ceiling, $tvmaze );
			return;
		}

		$this->preview_one( (int) $args[0], $format, $ceiling, true, $verbose );
	}

	/**
	 * Preview every published show.
	 *
	 * @param string     $format  Output format.
	 * @param float|null $ceiling SATURATION_K override.
	 * @param bool       $tvmaze  Whether to hit the TVMaze API per show.
	 */
	private function preview_all( $format, $ceiling, $tvmaze ): void {
		$show_ids = get_posts(
			array(
				'post_type'      => CPT_Shows::SLUG,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		if ( empty( $show_ids ) ) {
			\WP_CLI::error( 'No published shows found.' );
		}

		$progress = \WP_CLI\Utils\make_progress_bar( 'Scoring ' . count( $show_ids ) . ' shows', count( $show_ids ) );
		$rows     = array();

		foreach ( $show_ids as $show_id ) {
			$row = $this->build_row( (int) $show_id, $ceiling, $tvmaze );
			if ( ! empty( $row ) ) {
				$rows[] = $row;
			}

			if ( $tvmaze ) {
				usleep( self::SLEEP_MS * 1000 );
			}

			$progress->tick();
		}

		$progress->finish();

		// Biggest movers first: that is what needs an editorial eye.
		usort(
			$rows,
			static function ( $a, $b ) {
				return abs( $b['delta'] ) <=> abs( $a['delta'] );
			}
		);

		\WP_CLI\Utils\format_items(
			$format,
			$rows,
			array( 'id', 'show', 'format', 'chars', 'seasons', 'run_years', 'tier', 'chars_no_appears', 'qirl_tagged', 'qirl_no_primary', 'mean_weight', 'char_old_raw', 'char_old', 'char_new_raw', 'char_new', 'score_old', 'score_new', 'score_new_raw', 'delta', 'decile_old', 'decile_new', 'band_old', 'band_new', 'moved' )
		);

		$this->report_distribution( $rows );
	}

	/**
	 * Preview a single show.
	 *
	 * @param int        $show_id Show post ID.
	 * @param string     $format  Output format.
	 * @param float|null $ceiling SATURATION_K override.
	 * @param bool       $tvmaze  Whether to hit the TVMaze API.
	 * @param bool       $verbose Whether to dump the per-character breakdown.
	 */
	private function preview_one( int $show_id, $format, $ceiling, $tvmaze, $verbose ): void {
		if ( CPT_Shows::SLUG !== get_post_type( $show_id ) ) {
			\WP_CLI::error( $show_id . ' is not a show.' );
		}

		$data = $this->gather( $show_id, $tvmaze );

		\WP_CLI::log( \WP_CLI::colorize( '%9' . $this->display_title( $show_id ) . '%n (#' . $show_id . ')' ) );
		\WP_CLI::log( 'Format:      ' . ( $data['format'] ?: 'series' ) . ' (divisor ' . $data['divisor'] . ')' );
		\WP_CLI::log( 'Airdates:    ' . $data['start'] . ' - ' . ( $data['finish'] ?: 'current' ) );
		\WP_CLI::log( 'Seasons:     ' . ( $data['seasons'] ?: 'not recorded' ) );
		\WP_CLI::log( 'Run years:   ' . $data['run_years'] );
		\WP_CLI::log( 'Source:      ' . $data['run_years_source'] );
		// Judged-on counts, not tag counts. The two standards are mutually
		// exclusive now, so "19 queer-irl tagged" would overstate the queer-irl
		// check when 13 of those characters were actually decided by the
		// trans/NB standard instead.
		\WP_CLI::log(
			'Judged on:   ' . $data['judged_on_trans'] . ' trans/NB casting, '
			. $data['judged_on_qirl'] . ' queer casting, '
			. $data['gender_unclassified'] . ' neither (unclassified)'
		);
		\WP_CLI::log(
			'Trans/NB:    ' . $data['trans_new'] . ' characters, '
			. ( $data['trans_new'] - $data['trans_miscast'] - $data['actor_gender_unknown'] ) . ' correctly cast, '
			. $data['trans_miscast'] . ' miscast, '
			. $data['actor_gender_unknown'] . ' actor gender not recorded (scored neutrally)'
		);
		\WP_CLI::log(
			'Queer-IRL:   ' . $data['queer_irl'] . ' tagged overall ('
			. $data['qirl_failed_primary'] . ' fail the primary-actor check) -- note only the '
			. $data['judged_on_qirl'] . ' cis-role characters are scored on this'
		);
		if ( ! empty( $data['aired_years'] ) ) {
			\WP_CLI::log( 'Aired years: ' . implode( ', ', $data['aired_years'] ) );
		}

		// Loud, not silent. An unclassified gender term means
		// Longevity::GENDER_TRANS_OR_NB has not been reconciled with the live
		// taxonomy, and those characters are scoring neutrally in the meantime.
		if ( $data['gender_unclassified'] > 0 ) {
			\WP_CLI::warning(
				$data['gender_unclassified'] . ' character(s) have gender terms missing from '
				. 'Longevity::GENDER_TRANS_OR_NB / GENDER_CIS: '
				. implode( ', ', array_keys( $data['unclassified_slugs'] ) )
				. ' -- they score neutrally until those slugs are triaged.'
			);
		}

		// Actor slugs that fell through to 'unknown'. Includes the five pending
		// an editorial call (gender-non-conforming, demigender, androgynous,
		// no-label, two-spirit) and anything added to the taxonomy since. These
		// score neutrally, so nothing is docked -- but a trans/NB role whose
		// casting cannot be assessed is a gap worth closing.
		if ( ! empty( $data['unknown_actor_slugs'] ) ) {
			\WP_CLI::warning(
				'Primary actors on trans/NB roles with an unclassified gender slug: '
				. implode( ', ', array_keys( $data['unknown_actor_slugs'] ) )
				. ' -- triage into Longevity::ACTOR_GENDER_DIVERSE or ACTOR_CIS.'
			);
		}

		\WP_CLI::log( '' );

		if ( $verbose ) {
			$rows = array();
			foreach ( $data['characters'] as $char ) {
				$rows[] = array(
					'character'    => $char['name'],
					'role'         => $char['role'],
					'years'        => $char['years'],
					'weight'       => number_format( $char['weight'], 3 ),
					'weight_src'   => $char['weight_source'],
					'value'        => number_format( $char['value'], 1 ),
					'contribution' => number_format( $char['contribution'], 2 ),
					'flags'        => $char['flags'],
				);
			}

			// Biggest contributors first.
			usort(
				$rows,
				static function ( $a, $b ) {
					return (float) $b['contribution'] <=> (float) $a['contribution'];
				}
			);

			\WP_CLI\Utils\format_items(
				$format,
				$rows,
				array( 'character', 'role', 'years', 'weight', 'weight_src', 'value', 'contribution', 'flags' )
			);
			\WP_CLI::log( '' );

			// Every place this model docks a show, with the evidence. Check the
			// actor_terms column before believing a miscast: an actor whose
			// gender slug classifies as 'cis' produces a real miscast, but a slug
			// that is simply unrecognised now falls to 'unknown' and scores
			// neutrally -- so anything reaching this table is an explicit cis tag.
			if ( ! empty( $data['miscast_detail'] ) ) {
				\WP_CLI::log( \WP_CLI::colorize( '%3Miscast verdicts -- verify actor_terms before trusting these%n' ) );
				\WP_CLI\Utils\format_items(
					$format,
					$data['miscast_detail'],
					array( 'character', 'gender_terms', 'actor', 'actor_terms' )
				);
				\WP_CLI::log(
					'If an actor_terms value looks trans or non-binary but was not matched, '
					. 'add the slug to Longevity::ACTOR_GENDER_DIVERSE.'
				);
				\WP_CLI::log( '' );
			}
		}

		$scores = $this->score( $data, $ceiling );

		// Where the old character score came from. Printed before the
		// comparison because when a show is clamped, this table is the finding:
		// it shows which single term overran the cap and rendered the rest of
		// the component inert.
		if ( $verbose ) {
			$decomposed = array();
			foreach ( $scores['old_parts'] as $label => $value ) {
				$decomposed[] = array(
					'old score term' => $label,
					'points'         => sprintf( '%+d', $value ),
					'share of raw'   => ( 0 === $scores['char_old_raw'] )
						? '-'
						: number_format( ( abs( $value ) / max( 1, abs( $scores['char_old_raw'] ) ) ) * 100, 1 ) . '%',
				);
			}
			$decomposed[] = array(
				'old score term' => 'UNCAPPED TOTAL',
				'points'         => (string) $scores['char_old_raw'],
				'share of raw'   => $scores['char_old_raw'] > 100
					? sprintf( '%.1fx over the cap', $scores['char_old_raw'] / 100 )
					: 'under the cap',
			);

			\WP_CLI\Utils\format_items( $format, $decomposed, array( 'old score term', 'points', 'share of raw' ) );
			\WP_CLI::log( '' );
		}

		$comparison = array(
			array(
				'component' => 'Show rating',
				'old'       => number_format( $scores['show_rating'], 2 ),
				'new'       => number_format( $scores['show_rating'], 2 ),
				'note'      => 'unchanged',
			),
			array(
				'component' => 'Tropes',
				'old'       => number_format( $scores['tropes'], 2 ),
				'new'       => number_format( $scores['tropes'], 2 ),
				'note'      => 'unchanged',
			),
			array(
				'component' => 'Alive ratio',
				'old'       => number_format( $scores['alive'], 2 ),
				'new'       => number_format( $scores['alive'], 2 ),
				'note'      => $data['dead'] . ' dead of ' . $data['count'],
			),
			array(
				'component' => 'Character score',
				'old'       => number_format( $scores['char_old'], 2 ),
				'new'       => number_format( $scores['char_new'], 2 ),
				'note'      => 'raw X = ' . number_format( $scores['raw'], 2 )
					. ( $scores['char_old'] >= 100 ? ' | OLD WAS CLAMPED AT 100' : '' ),
			),
			array(
				'component' => 'TOTAL',
				'old'       => number_format( $scores['total_old'], 2 ),
				'new'       => number_format( $scores['total_new'], 2 ),
				'note'      => sprintf( '%+.2f', $scores['total_new'] - $scores['total_old'] ),
			),
		);

		\WP_CLI\Utils\format_items( $format, $comparison, array( 'component', 'old', 'new', 'note' ) );

		$stored = get_post_meta( $show_id, 'lezshows_the_score', true );
		if ( '' !== $stored ) {
			\WP_CLI::log( '' );
			\WP_CLI::log( 'Stored lezshows_the_score: ' . $stored );

			// If the recomputed "old" total drifts from the stored value, this
			// preview is not replicating the live model and its "new" number
			// cannot be trusted either.
			if ( abs( (float) $stored - $scores['total_old'] ) > 0.01 ) {
				\WP_CLI::warning( 'Recomputed OLD total does not match stored meta. This preview is not faithfully replicating the live calculation -- investigate before trusting the NEW column.' );
			}
		}
	}

	/**
	 * Build one comparison row for --all.
	 *
	 * @param int        $show_id Show post ID.
	 * @param float|null $ceiling SATURATION_K override.
	 * @param bool       $tvmaze  Whether to hit the TVMaze API.
	 *
	 * @return array
	 */
	private function build_row( int $show_id, $ceiling, $tvmaze ): array {
		$data   = $this->gather( $show_id, $tvmaze );
		$scores = $this->score( $data, $ceiling );

		$weights = wp_list_pluck( $data['characters'], 'weight' );

		return array(
			'id'               => $show_id,
			'show'             => $this->display_title( $show_id ),
			'format'           => $data['format'] ?: 'series',
			'chars'            => $data['count'],
			'seasons'          => $data['seasons'],
			'run_years'        => $data['run_years'],
			'tier'             => $data['tier'],
			'chars_no_appears' => $data['no_appears'],
			'qirl_tagged'      => $data['queer_irl'],
			'qirl_no_primary'  => $data['qirl_failed_primary'],
			'mean_weight'      => empty( $weights ) ? '0.000' : number_format( array_sum( $weights ) / count( $weights ), 3 ),
			'char_old_raw'     => $scores['char_old_raw'],
			'char_old'         => number_format( $scores['char_old'], 2 ),
			'char_new_raw'     => number_format( $scores['raw_divided'], 3 ),
			'char_new'         => number_format( $scores['char_new'], 2 ),
			'score_old'        => number_format( $scores['total_old'], 2 ),
			'score_new'        => number_format( $scores['total_new'], 2 ),
			'score_new_raw'    => number_format( $scores['total_new_true'], 2 ),
			'delta'            => round( $scores['total_new'] - $scores['total_old'], 2 ),
			'decile_old'       => $this->decile_label( $scores['total_old'] ),
			'decile_new'       => $this->decile_label( $scores['total_new'] ),
			'band_old'         => $this->band_label( $scores['total_old'] ),
			'band_new'         => $this->band_label( $scores['total_new'] ),
			'moved'            => $this->movement_flags( $scores['total_old'], $scores['total_new'] ),
		);
	}

	/**
	 * Collect everything both models need for one show.
	 *
	 * @param int  $show_id Show post ID.
	 * @param bool $tvmaze  Whether to hit the TVMaze API.
	 *
	 * @return array
	 */
	private function gather( int $show_id, $tvmaze ): array {
		$airdates = Airdates::get( $show_id );
		$now      = (int) gmdate( 'Y' );

		$aired_years = array();
		$why         = '';
		$seasons     = (int) get_post_meta( $show_id, 'lezshows_seasons', true );

		// Tier 1: exact years, from stored meta if the cron has run, else live.
		$stored = get_post_meta( $show_id, 'lezshows_aired_years', true );
		if ( is_array( $stored ) && ! empty( $stored ) ) {
			$aired_years = array_map( 'intval', $stored );
		} elseif ( $tvmaze ) {
			$aired_years = $this->fetch_aired_years( $show_id, $now, $why );
		} else {
			$why = '--tvmaze not passed';
		}

		$run_years = Longevity::run_years( $aired_years, $seasons, $airdates['start'], $airdates['finish'], $now );

		// Report which tier actually produced the denominator, and when it fell
		// through, why. "tier 4" alone hides whether a show has no TVMaze ID or
		// whether the lookup broke -- those need different fixes.
		//
		// Tier order matches Longevity::run_years(): curated season count first,
		// exact aired years second.
		$still_airing = '' === trim( $airdates['finish'] ) || Airdates::is_still_airing( $airdates['finish'] );
		if ( ! $still_airing && $seasons >= 1 ) {
			$tier   = 1;
			$source = 'season count (tier 1, curated) = ' . $seasons;

			// Worth surfacing even when tier 1 wins: where the curated count and
			// the exact year set disagree, one of them is wrong about this show,
			// and the curated one is the one being used.
			if ( ! empty( $aired_years ) && count( $aired_years ) !== $run_years ) {
				$source .= ' | NOTE exact years say ' . count( $aired_years );
			}
		} elseif ( ! empty( $aired_years ) ) {
			$tier   = 2;
			$source = ( empty( $stored ) ? 'tvmaze seasons' : 'stored aired years' ) . ' (tier 2, exact)'
				. ( $still_airing ? ' | still airing, so season count skipped' : ' | no season count' );
		} else {
			$tier   = 4;
			$source = 'airdate span (tier 4) | no exact years: ' . $why
				. ( $still_airing ? ' | still airing, so season count skipped' : ' | no season count' );
		}

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

		if ( ! empty( $characters ) ) {
			update_meta_cache( 'post', $characters );
			update_object_term_cache( $characters, array( 'lez_cliches', 'lez_gender' ) );
		}

		$out = array(
			'id'                   => $show_id,
			'format'               => $format,
			'divisor'              => $divisor,
			'start'                => $airdates['start'],
			'finish'               => $airdates['finish'],
			'aired_years'          => $aired_years,
			'seasons'              => $seasons,
			'run_years'            => $run_years,
			'run_years_source'     => $source,
			'tier'                 => $tier,
			'count'                => count( $characters ),
			'dead'                 => 0,
			'none'                 => 0,
			'queer_irl'            => 0,
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
			$char_id = (int) $char_id;

			$is_dead = has_term( 'dead', 'lez_cliches', $char_id );
			$is_none = has_term( 'none', 'lez_cliches', $char_id );
			$is_qirl = has_term( 'queer-irl', 'lez_cliches', $char_id );

			// OLD model's trans test: the exclusion check. Kept only so the OLD
			// column still replicates the live calculation.
			$is_trans_old = ! has_term( self::NOT_TRANS, 'lez_gender', $char_id );

			// NEW model: an explicit three-way classification from the actual
			// gender terms, so an untriaged term is reported rather than
			// silently counted as cis.
			$gender_terms = get_the_terms( $char_id, 'lez_gender' );
			$gender_slugs = ( is_array( $gender_terms ) ) ? wp_list_pluck( $gender_terms, 'slug' ) : array();
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

			// The Tambour Takedown, applied to scoring for the first time. The
			// existing implementation in theme/class-show-characters.php sits in
			// an unreachable branch, so the live score has been awarding
			// queer-irl credit without ever checking the actor.
			//
			// Both conditions are required, matching that implementation: the
			// character is tagged queer-irl AND their first-billed actor is
			// actually queer. Actors are stored in billing order, so the
			// primary is simply the first.
			$primary_queer = false;
			if ( $is_qirl && ! empty( $actor_ids ) ) {
				$primary_queer = ( new Is_Actor_Queer() )->make( reset( $actor_ids ) );
			}

			if ( $is_qirl && ! $primary_queer ) {
				++$out['qirl_failed_primary'];
			}

			// Trans/NB casting, same shape: the primary actor, not the whole cast.
			//
			// Read via Longevity::classify_actor_gender() rather than
			// Is_Actor_Trans, which decides with strpos( $slug, 'trans' ) and so
			// cannot see an actor tagged non-binary. Since non-binary CHARACTERS
			// are now held to this standard, using Is_Actor_Trans here would dock
			// every show that cast a non-binary actor correctly.
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

			// One casting decision, one multiplier. Trans/NB roles are judged on
			// trans/NB casting, everyone else on queer casting -- they no longer
			// stack.
			$casting = Longevity::casting_multiplier( $gender_class, $primary_queer, $actor_class );

			// A trans/NB role whose actor's gender we have not recorded scores
			// neutrally rather than as a miscast. Counted separately so the gap
			// is visible instead of hiding inside "correctly cast".
			if ( 'trans-or-nb' === $gender_class && 'unknown' === $actor_class ) {
				++$out['actor_gender_unknown'];
				foreach ( $primary_slugs as $slug ) {
					$out['unknown_actor_slugs'][ $slug ] = true;
				}
			}

			if ( $casting < 1.0 ) {
				++$out['trans_miscast'];

				// A miscast verdict is the only place this model actively DOCKS a
				// show, so it has to be auditable. There is no unclassified
				// bucket on the actor side -- an actor whose gender slug is
				// unrecognised slug now classifies as 'unknown' and scores
				// neutrally, so only an explicit cis tag reaches here. Recording
				// the slugs is what lets that be audited rather than trusted.
				$out['miscast_detail'][] = array(
					'character'    => $this->display_title( $char_id ),
					'gender_terms' => implode( ' ', $gender_slugs ),
					'actor'        => $primary_actor ? $this->display_title( $primary_actor ) : '(no actor listed)',
					'actor_terms'  => empty( $primary_slugs ) ? '(none)' : implode( ' ', $primary_slugs ),
				);
			}

			// Which standard actually decided this character's multiplier. With
			// one combined signal these are mutually exclusive, so counting
			// queer-irl tags overstates what that check is doing.
			if ( 'trans-or-nb' === $gender_class ) {
				++$out['judged_on_trans'];
			} elseif ( 'cis' === $gender_class ) {
				++$out['judged_on_qirl'];
			}

			// Union the character's years across every show-group row pointing
			// at this show, and keep their strongest role. A character with two
			// separate stints is one character, not two -- the existing model
			// counts their role points once per row, which double-counts them.
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

					$role = $this->strongest_role( $role, (string) ( $row['type'] ?? '' ) );

					$appears = $row['appears'] ?? null;
					if ( is_array( $appears ) ) {
						foreach ( $appears as $year ) {
							$year = (int) $year;
							if ( 0 !== $year ) {
								$years_set[ $year ] = true;
							}
						}
					} elseif ( is_numeric( $appears ) ) {
						$years_set[ (int) $appears ] = true;
					}
				}
			}

			$years = Longevity::character_years( array_keys( $years_set ), $aired_years );

			if ( 0 === $years ) {
				++$out['no_appears'];
				$weight        = Longevity::role_proxy_weight( $role );
				$weight_source = 'role proxy';
			} else {
				$weight        = Longevity::weight( $years, $run_years );
				$weight_source = 'appears';
			}

			$value = Longevity::character_value( $role, $casting, $is_none, $is_dead );

			// Flags name which standard was applied, not just what is tagged --
			// with one combined signal, "judged on trans casting" and "judged on
			// queer casting" are mutually exclusive and the reader needs to know
			// which one decided the multiplier.
			$flags = array();
			if ( 'trans-or-nb' === $gender_class ) {
				$flags[] = $primary_trans ? 'trans-cast' : 'trans-MISCAST';
			} elseif ( 'unclassified' === $gender_class ) {
				$flags[] = 'gender-UNCLASSIFIED';
			} elseif ( $primary_queer ) {
				$flags[] = 'qirl';
			} elseif ( $is_qirl ) {
				// Tagged, but the lead role went to a cis/het actor.
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
				'name'          => $this->display_title( $char_id ),
				'role'          => $role ?: '(none)',
				'years'         => $years,
				'weight'        => $weight,
				'weight_source' => $weight_source,
				'value'         => $value,
				'contribution'  => $value * $weight,
				'flags'         => implode( ' ', $flags ),
			);
		}

		return $out;
	}

	/**
	 * Score a gathered show under both models.
	 *
	 * @param array      $data    Output of gather().
	 * @param float|null $ceiling SATURATION_K override.
	 *
	 * @return array
	 */
	private function score( array $data, $ceiling ): array {
		$calc = new Shows_Calculations();

		// Unchanged components, read from the live calculation.
		$show_rating = (float) ( $calc->show_score( (int) $data['id'] ) ?? 0 );
		$tropes      = (float) ( $calc->show_tropes_score( (int) $data['id'] ) ?? 0 );

		$alive = 0.0;
		if ( 0 !== $data['count'] ) {
			$alive = ( ( $data['count'] - $data['dead'] ) / $data['count'] ) * 100;
		}

		// The OLD show-level trans aggregate. Retained ONLY for the OLD column,
		// to keep it a faithful replication of the live calculation.
		//
		// The new model has no equivalent: trans casting is now a per-character
		// multiplier already folded into each contribution, so adding this here
		// too would double-count it. It also used the exclusion gender test,
		// where the new path uses an explicit classification.
		$trans_score = ( $data['trans_irl'] < $data['trans'] )
			? ( ( $data['trans'] - $data['trans_irl'] ) * -5 )
			: ( $data['trans'] * 10 );

		// OLD: unbounded sum, format divisor, hard clamp at 100.
		//
		// Decomposed rather than summed in one expression, because which term
		// blew past the cap is the interesting part. On Transparent the
		// queer-irl bonus alone is 190 against a cap of 100, so roles, deaths
		// and everything else are noise below the clamp -- the stored character
		// score carries no information at all.
		$roles = get_post_meta( (int) $data['id'], 'lezshows_char_roles', true );
		$roles = is_array( $roles ) ? $roles : array();

		$parts = array(
			'base (roles)'     => ( ( $roles['regular'] ?? 0 ) * 5 ) + ( ( $roles['recurring'] ?? 0 ) * 2 ) + ( $roles['guest'] ?? 0 ),
			'queer-irl bonus'  => $data['queer_irl'] * 10,
			'no-cliches bonus' => $data['none'] * 5,
			'dead penalty'     => $data['dead'] * -5,
			'trans adjustment' => $trans_score,
		);

		$char_old_raw = array_sum( $parts );
		$char_old     = ( 0 !== $char_old_raw ) ? ( $char_old_raw / $data['divisor'] ) : 0;
		$char_old     = min( 100, $char_old );

		// NEW: longevity-weighted sum, format divisor, saturating ceiling.
		//
		// Every modifier -- role, queer casting, trans casting, clichés, death --
		// is already inside each character's contribution. Nothing is added at
		// the show level any more, so the per-character table now accounts for
		// the whole of X rather than leaving a third of it invisible.
		$raw = 0.0;
		foreach ( $data['characters'] as $char ) {
			$raw += $char['contribution'];
		}

		$divided  = ( 0.0 !== $raw ) ? ( $raw / $data['divisor'] ) : 0.0;
		$char_new = Longevity::saturate( (float) $divided, $ceiling );

		// $divided, not $raw, is what saturate() consumed -- so it is the value a
		// K sweep needs. Reported as its own column because back-deriving it from
		// a two-decimal char_new is lossy, and doing that by hand is exactly the
		// gap this column closes.

		$total_old = ( $show_rating + $tropes + $alive + $char_old ) / 4;
		$total_new = ( $show_rating + $tropes + $alive + $char_new ) / 4;

		return array(
			'show_rating'    => $show_rating,
			'tropes'         => $tropes,
			'alive'          => $alive,
			'raw'            => $raw,
			'raw_divided'    => (float) $divided,
			'old_parts'      => $parts,
			'char_old_raw'   => $char_old_raw,
			'char_old'       => (float) $char_old,
			'char_new'       => $char_new,
			// Capped for comparability with the stored meta, which is capped
			// today. The uncapped values are returned alongside so the display
			// -only cap question can be answered from real numbers.
			'total_old'      => max( 0, min( 100, $total_old ) ),
			'total_new'      => max( 0, min( 100, $total_new ) ),
			'total_old_true' => $total_old,
			'total_new_true' => $total_new,
		);
	}

	/**
	 * A post title fit for terminal output.
	 *
	 * Titles come back HTML-encoded -- Sydney &#8220;Syd&#8221; Feldman, Law
	 * &#038; Order -- which is right for the web and noise in a CLI table. Same
	 * decode cli-audit.php uses.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	private function display_title( int $post_id ): string {
		return html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Decile bucket label for a score, matching the stats histogram exactly.
	 *
	 * Deliberately reproduces Score_Distribution's arithmetic rather than
	 * approximating it: bucket index is min( BUCKETS - 1, floor( score / 10 ) ),
	 * and the top bucket runs 90-100 inclusive rather than 90-99, so a show on
	 * exactly 100 lands in it.
	 *
	 * @param float $score A 0-100 score.
	 *
	 * @return string e.g. '50-59', '90-100'.
	 */
	private function decile_label( float $score ): string {
		$width = (int) ( Score_Distribution::SCORE_MAX / Score_Distribution::BUCKETS );
		$index = min( Score_Distribution::BUCKETS - 1, (int) floor( $score / $width ) );
		$index = max( 0, $index );
		$floor = $index * $width;
		$top   = ( Score_Distribution::BUCKETS - 1 === $index )
			? Score_Distribution::SCORE_MAX
			: ( $floor + $width - 1 );

		return $floor . '-' . $top;
	}

	/**
	 * Named display band for a score, or '-' when it is in neither tail.
	 *
	 * @param float $score A 0-100 score.
	 *
	 * @return string
	 */
	private function band_label( float $score ): string {
		if ( $score < self::BAND_FAILING ) {
			return 'failing';
		}

		if ( $score >= self::BAND_TOP ) {
			return '90+club';
		}

		return '-';
	}

	/**
	 * Which display boundaries a show crosses, as a sortable flag string.
	 *
	 * The point of the column: these are the only score changes a reader can
	 * actually see. A show shifting 57 to 59 is invisible; one crossing 20, 90 or
	 * the colour inflection is not.
	 *
	 * @param float $before Total under the current model.
	 * @param float $after  Total under the new model.
	 *
	 * @return string Space-separated flags, or '-'.
	 */
	private function movement_flags( float $before, float $after ): string {
		$flags = array();

		if ( $this->decile_label( $before ) !== $this->decile_label( $after ) ) {
			$flags[] = 'decile';
		}

		if ( $this->band_label( $before ) !== $this->band_label( $after ) ) {
			$flags[] = 'band';
		}

		if ( ( $before < self::COLOUR_INFLECTION ) !== ( $after < self::COLOUR_INFLECTION ) ) {
			$flags[] = 'colour';
		}

		return empty( $flags ) ? '-' : implode( ' ', $flags );
	}

	/**
	 * Which of two role slugs represents the larger presence.
	 *
	 * @param string $current Role held so far.
	 * @param string $next    Role from the row being read.
	 *
	 * @return string
	 */
	private function strongest_role( string $current, string $next ): string {
		$rank = array(
			''          => 0,
			'guest'     => 1,
			'recurring' => 2,
			'regular'   => 3,
		);

		$current_rank = $rank[ $current ] ?? 0;
		$next_rank    = $rank[ $next ] ?? 0;

		return ( $next_rank > $current_rank ) ? $next : $current;
	}

	/**
	 * Fetch a show's aired years from the TVMaze seasons endpoint.
	 *
	 * Sets $reason to why it came back empty, so a tier 3 fallback can say
	 * whether the show has no TVMaze ID at all or the lookup failed. Those need
	 * different fixes -- one is data entry, the other is an outage or a bad ID --
	 * and reporting both as "tier 3" hides which.
	 *
	 * @param int    $show_id      Show post ID.
	 * @param int    $current_year The current year.
	 * @param string $reason       Set by reference to explain an empty result.
	 *
	 * @return array Empty when there is no TVMaze ID or the call fails.
	 */
	private function fetch_aired_years( int $show_id, int $current_year, &$reason ): array {
		$tvmaze_id = get_post_meta( $show_id, 'lezshows_tvmaze_id', true );
		if ( empty( $tvmaze_id ) ) {
			$reason = 'no lezshows_tvmaze_id';
			return array();
		}

		$response = wp_remote_get( self::TVMAZE_URL . '/shows/' . (int) $tvmaze_id . '/seasons' );

		if ( is_wp_error( $response ) ) {
			$reason = 'tvmaze request failed: ' . $response->get_error_message();
			return array();
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$reason = 'tvmaze returned HTTP ' . $code . ' for id ' . $tvmaze_id;
			return array();
		}

		$seasons = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $seasons ) ) {
			$reason = 'tvmaze response was not valid JSON';
			return array();
		}

		$years = Longevity::aired_years_from_seasons( $seasons, $current_year );
		if ( empty( $years ) ) {
			$reason = 'tvmaze listed ' . count( $seasons ) . ' seasons but none had a usable premiere date';
		}

		return $years;
	}

	/**
	 * Summarise the distribution, which is what SATURATION_K is calibrated from.
	 *
	 * @param array $rows Comparison rows.
	 */
	private function report_distribution( array $rows ): void {
		$old = array_map( static fn( $r ) => (float) $r['score_old'], $rows );
		$new = array_map( static fn( $r ) => (float) $r['score_new'], $rows );

		sort( $old );
		sort( $new );

		$median = static function ( array $set ) {
			$count = count( $set );
			if ( 0 === $count ) {
				return 0.0;
			}
			$middle = (int) floor( ( $count - 1 ) / 2 );
			return ( 0 === $count % 2 ) ? ( ( $set[ $middle ] + $set[ $middle + 1 ] ) / 2 ) : $set[ $middle ];
		};

		$clamped  = 0;
		$tiers    = array(
			1 => 0,
			2 => 0,
			4 => 0,
		);
		$over_100 = 0;
		$worst    = 0.0;

		foreach ( $rows as $row ) {
			if ( (float) $row['char_old'] >= 100 ) {
				++$clamped;
			}

			$tier = (int) $row['tier'];
			if ( isset( $tiers[ $tier ] ) ) {
				++$tiers[ $tier ];
			}

			// How much a display-only cap would actually preserve. The new
			// character score is asymptotic below 100 and tropes and alive are
			// both capped, so only show_score() (max 115, unclamped) can push a
			// total past 100 -- a ceiling of 103.75 in the best case.
			if ( (float) $row['score_new_raw'] > 100 ) {
				++$over_100;
				$worst = max( $worst, (float) $row['score_new_raw'] );
			}
		}

		\WP_CLI::log( '' );
		\WP_CLI::log( '--- distribution ---' );
		\WP_CLI::log( 'Shows:                   ' . count( $rows ) );
		\WP_CLI::log( 'Median score OLD:        ' . number_format( $median( $old ), 2 ) );
		\WP_CLI::log( 'Median score NEW:        ' . number_format( $median( $new ), 2 ) );
		\WP_CLI::log( 'Mean score OLD:          ' . number_format( array_sum( $old ) / max( 1, count( $old ) ), 2 ) );
		\WP_CLI::log( 'Mean score NEW:          ' . number_format( array_sum( $new ) / max( 1, count( $new ) ), 2 ) );
		\WP_CLI::log( 'Char score clamped OLD:  ' . $clamped . ' shows pinned at exactly 100 (no ranking info)' );
		\WP_CLI::log( '' );
		// Labels must match the order in Longevity::run_years(): curated season
		// count is tier 1, exact aired years is tier 2. These were transposed in
		// an earlier revision, which reported 1813 shows as using "exact years"
		// when not one of them did.
		\WP_CLI::log( 'Denominator tier:' );
		\WP_CLI::log( '  tier 1 season count:   ' . $tiers[1] . ' (curated)' );
		\WP_CLI::log( '  tier 2 exact years:    ' . $tiers[2] . ' (TVMaze; needs --tvmaze or stored lezshows_aired_years)' );
		\WP_CLI::log( '  tier 4 airdate span:   ' . $tiers[4] . ' (nothing improved for these)' );
		\WP_CLI::log( '' );
		\WP_CLI::log( 'Uncapped NEW totals above 100: ' . $over_100 . ' shows' . ( $over_100 > 0 ? ', highest ' . number_format( $worst, 2 ) : '' ) );
		\WP_CLI::log( '  ^ this is what a display-only cap would preserve. Theoretical max is' );
		\WP_CLI::log( '    103.75, since only show_score() (max 115) is unclamped.' );
		\WP_CLI::log( '' );
		\WP_CLI::log( 'Calibration: adjust --k until median NEW is close to median OLD.' );
	}
}

\WP_CLI::add_command( 'lwtv score-preview', 'WP_CLI_LWTV_Score_Preview' );
