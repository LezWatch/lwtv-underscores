<?php
/*
 * WP CLI Commands for previewing longevity-weighted show scores.
 *
 * READ ONLY. This command writes no post meta and mutates nothing. It exists so
 * the effect of the longevity model can be inspected against real shows before
 * it is switched on.
 *
 * ⚠ Both models come from LWTV\CPTs\Shows\Character_Score, which is also what
 * Calculations::count_queers_all_types() calls. That is the point: this command
 * was used to CALIBRATE the new model, so a private replica of the maths here
 * could have drifted from what ships and quietly invalidated the calibration it
 * was used to pick. If you are tempted to compute a score in this file, don't --
 * add it to Character_Score and read it from there.
 *
 * See docs/plans/show-score-longevity.md. The main job here is calibrating
 * SATURATION_K, and NOT by holding the median total steady -- that advice used to
 * live here and it is backwards, because the old total median only sat where it
 * did while the character component was stuck on the floor. Calibrate on that
 * component's own distribution instead. Use --k to try values, though a sweep
 * needs no re-run: see the note printed after the distribution summary.
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
use LWTV\CPTs\Shows\Calculations as Shows_Calculations;
use LWTV\CPTs\Shows\Character_Score;
use LWTV\CPTs\Shows\Longevity;
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
	 * What each aired-years rejection means, appended to the tier-4 explanation.
	 *
	 * The verdict slug alone says which check fired but not what it implies about
	 * the data, and the two rejections need different follow-up: a seasons or
	 * coverage rejection means TVMaze is missing seasons for that show, while a
	 * late-start rejection can also mean our own start year is wrong.
	 */
	public const VERDICT_NOTES = array(
		Longevity::VERDICT_SEASONS    => ' (fewer dated years than recorded seasons)',
		Longevity::VERDICT_LATE_START => ' (dated years begin well after the recorded start)',
		Longevity::VERDICT_COVERAGE   => ' (cannot account for the years characters are credited in)',
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
			array( 'id', 'show', 'format', 'chars', 'seasons', 'run_years', 'tier', 'floored', 'aired_rejected', 'aired_verdict', 'coverage', 'credited_years', 'aired_set', 'disc_outside', 'disc_hole', 'chars_no_appears', 'qirl_tagged', 'qirl_no_primary', 'mean_weight', 'char_old_raw', 'char_old', 'char_new_raw', 'char_new', 'score_old', 'score_new', 'score_new_raw', 'delta', 'decile_old', 'decile_new', 'band_old', 'band_new', 'moved' )
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

		// Coverage is the evidence behind a tier-2 accept or reject, so show it
		// wherever a TVMaze set existed -- including when it was accepted. A
		// borderline accept is exactly the case worth eyeballing, and it is
		// invisible if the number only prints on rejection.
		if ( Longevity::VERDICT_NONE !== $data['aired_verdict'] ) {
			$thin = $data['credited_years'] < Longevity::COVERAGE_MIN_EVIDENCE;

			\WP_CLI::log(
				'Coverage:    ' . number_format( $data['coverage'], 3 )
				. ' -- ' . $data['credited_years'] . ' credited years vs a ' . $data['aired_set'] . '-year TVMaze set'
				. ( $thin ? ' (below the evidence floor, so not judged on it)' : '' )
			);

			if ( $data['disc_outside'] > 0 || $data['disc_hole'] > 0 ) {
				\WP_CLI::log(
					'Discarded:   ' . $data['disc_hole'] . ' credited years fall in a gap inside the set, '
					. $data['disc_outside'] . ' fall outside its range entirely'
				);
			}
		}
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
			// The floor turned out to fire on 292 shows, not the one it was built
			// for, so it needs to be visible in bulk output rather than inferred
			// from run_years > seasons.
			'floored'          => $data['run_years_floored'] ? 'yes' : '-',
			'aired_rejected'   => $data['aired_rejected'] ? 'yes' : '-',
			'aired_verdict'    => $data['aired_verdict'],
			// A show with no TVMaze set has nothing to measure coverage against,
			// and appearance_coverage() reports 0.0 for it. Printing that would
			// read as catastrophic coverage on 1,855 shows when it means "not
			// measurable" -- so it is blanked rather than shown as a number.
			'coverage'         => ( Longevity::VERDICT_NONE === $data['aired_verdict'] )
				? '-'
				: number_format( $data['coverage'], 3 ),
			'credited_years'   => $data['credited_years'],
			// What a rejection actually rests on. |A| and |C| side by side make
			// the verdict checkable by hand, and the outside/hole split shows
			// whether the missing years sit beyond the set's range (a harder
			// fact) or inside a gap in it.
			'aired_set'        => $data['aired_set'],
			'disc_outside'     => $data['disc_outside'],
			'disc_hole'        => $data['disc_hole'],
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
	 * Collect one show's data, then add the CLI's own explanation of it.
	 *
	 * The gathering itself lives in Character_Score so this command and the live
	 * calculation cannot disagree -- that shared implementation is the whole point
	 * of the class. What stays here is the two things only a CLI wants: the
	 * optional live TVMaze fetch (which must never enter the scoring path) and the
	 * prose explaining which denominator tier was used and why.
	 *
	 * @param int  $show_id Show post ID.
	 * @param bool $tvmaze  Whether to hit the TVMaze API for missing aired years.
	 *
	 * @return array
	 */
	private function gather( int $show_id, $tvmaze ): array {
		$aired_override = array();
		$why            = '--tvmaze not passed';
		$stored         = get_post_meta( $show_id, 'lezshows_aired_years', true );
		$has_stored     = is_array( $stored ) && ! empty( $stored );

		if ( $has_stored ) {
			$why = '';
		} elseif ( $tvmaze ) {
			$aired_override = $this->fetch_aired_years( $show_id, (int) gmdate( 'Y' ), $why );
		}

		// Both gates forced on regardless of the live flags. A preview whose
		// contents depended on whether the feature was already enabled would be
		// useless for deciding whether to enable it -- and with the flags off,
		// gather() would return no weights at all and every NEW column would read
		// zero, which looks like a result rather than a missing input.
		$data = Character_Score::gather(
			$show_id,
			array(
				'aired_override' => $aired_override,
				'longevity'      => true,
				'actor_check'    => true,
			)
		);

		$data['has_stored']       = $has_stored;
		$data['run_years_source'] = $this->explain_tier( $data, $why );

		return $data;
	}

	/**
	 * Why this show's denominator came from the tier it did.
	 *
	 * "tier 4" alone hides whether a show has no TVMaze ID or whether the lookup
	 * broke, and those need different fixes -- one is data entry, the other is an
	 * outage or a bad ID.
	 *
	 * @param array  $data Output of Character_Score::gather().
	 * @param string $why  Why no aired years were available, if they were not.
	 *
	 * @return string
	 */
	private function explain_tier( array $data, string $why ): string {
		$airing = $data['still_airing']
			? ' | still airing, so season count skipped'
			: ' | no season count';

		$floored = ! empty( $data['run_years_floored'] )
			? ' | RAISED to ' . $data['run_years'] . ', the number of years characters are credited in'
			: '';

		if ( 1 === $data['tier'] ) {
			$source = 'season count (tier 1, curated) = ' . $data['seasons'];

			// Worth surfacing even when tier 1 wins: where the curated count and
			// the exact year set disagree, one of them is wrong about this show,
			// and the curated one is the one being used.
			if ( ! empty( $data['aired_years'] ) && count( $data['aired_years'] ) !== $data['run_years'] ) {
				$source .= ' | NOTE exact years say ' . count( $data['aired_years'] );
			}

			return $source . $floored;
		}

		if ( 2 === $data['tier'] ) {
			return $data['aired_source'] . ' (tier 2, exact)' . $airing;
		}

		if ( $data['aired_rejected'] ) {
			return 'airdate span (tier ' . $data['tier'] . ') | TVMaze aired years REJECTED as implausible, signal: '
				. $data['aired_verdict'] . self::VERDICT_NOTES[ $data['aired_verdict'] ];
		}

		return 'airdate span (tier ' . $data['tier'] . ') | no exact years: '
			. ( $why ?: $data['aired_why'] ) . $airing . $floored;
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

		// Both models come from Character_Score, which is also what the live
		// calculation calls. That is deliberate and it is the point of the
		// refactor: this command was used to CALIBRATE the new model, so a
		// separate replica here could have drifted from the shipping maths and
		// quietly invalidated the calibration it was used to pick.
		//
		// $legacy['score'] is therefore not "the preview's idea of the old score"
		// but literally the number count_queers_all_types() returns -- which is
		// what makes the stored-meta divergence check below meaningful.
		$legacy = Character_Score::legacy( $data );
		$new    = Character_Score::longevity( $data, $ceiling );

		// $divided, not $raw, is what saturate() consumed -- so it is the value a
		// K sweep needs. Reported as its own column because back-deriving it from
		// a two-decimal char_new is lossy, and doing that by hand is exactly the
		// gap this column closes.
		$total_old = ( $show_rating + $tropes + $alive + $legacy['score'] ) / 4;
		$total_new = ( $show_rating + $tropes + $alive + $new['score'] ) / 4;

		return array(
			'show_rating'    => $show_rating,
			'tropes'         => $tropes,
			'alive'          => $alive,
			'raw'            => $new['raw'],
			'raw_divided'    => $new['divided'],
			'old_parts'      => $legacy['parts'],
			'char_old_raw'   => $legacy['raw'],
			'char_old'       => $legacy['score'],
			'char_new'       => $new['score'],
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
	 * Delegates so there is one decode, not two. Titles come back HTML-encoded --
	 * Sydney &#8220;Syd&#8221; Feldman, Law &#038; Order -- which is right for the
	 * web and noise in a CLI table.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	private function display_title( int $post_id ): string {
		return Character_Score::display_title( $post_id );
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
			3 => 0,
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
		$rejected  = 0;
		$verdicts  = array();
		$histogram = array_fill( 0, 11, 0 );
		$judged    = 0;

		foreach ( $rows as $row ) {
			if ( 'yes' === ( $row['aired_rejected'] ?? '-' ) ) {
				++$rejected;

				$verdict              = (string) ( $row['aired_verdict'] ?? '?' );
				$verdicts[ $verdict ] = ( $verdicts[ $verdict ] ?? 0 ) + 1;
			}

			// The coverage histogram exists to calibrate COVERAGE_MIN, so it must
			// only count shows the signal can actually judge: one that had no
			// TVMaze set at all measures 0.0 for want of anything to compare,
			// and one below the evidence floor is deliberately not judged.
			// Including either would invent a cluster at the bottom of the chart.
			if ( Longevity::VERDICT_NONE === ( $row['aired_verdict'] ?? '' )
				|| (int) ( $row['credited_years'] ?? 0 ) < Longevity::COVERAGE_MIN_EVIDENCE ) {
				continue;
			}

			++$judged;
			++$histogram[ (int) floor( (float) $row['coverage'] * 10 ) ];
		}

		$floored = 0;
		foreach ( $rows as $row ) {
			if ( 'yes' === ( $row['floored'] ?? '-' ) ) {
				++$floored;
			}
		}

		\WP_CLI::log( 'Denominator tier:' );
		\WP_CLI::log( '  tier 1 season count:   ' . $tiers[1] . ' (curated)' );
		\WP_CLI::log( '  tier 2 exact years:    ' . $tiers[2] . ' (TVMaze; needs --tvmaze or stored lezshows_aired_years)' );
		\WP_CLI::log( '  tier 3 span - hiatus:  ' . $tiers[3] . ' (no hiatus data exists yet, so expect 0)' );
		\WP_CLI::log( '  tier 4 airdate span:   ' . $tiers[4] . ' (nothing improved for these)' );
		\WP_CLI::log( '    of which rejected:   ' . $rejected . ' had TVMaze aired years but they looked' );
		\WP_CLI::log( '                         implausible, so the span was used instead' );
		\WP_CLI::log( '' );
		\WP_CLI::log( 'Denominator raised to the credited-years floor: ' . $floored . ' shows' );
		\WP_CLI::log( '  Each of these had a denominator SMALLER than the number of years its own' );
		\WP_CLI::log( '  characters are credited in, which cannot be right. Read as a data signal:' );
		\WP_CLI::log( '  that many shows have a lezshows_seasons value materially below the calendar' );
		\WP_CLI::log( '  years they actually aired across.' );

		if ( ! empty( $verdicts ) ) {
			\WP_CLI::log( '' );
			\WP_CLI::log( 'Which signal rejected them:' );
			foreach ( $verdicts as $verdict => $total ) {
				\WP_CLI::log( '  ' . str_pad( $verdict, 22 ) . $total );
			}
		}

		// The calibration table. COVERAGE_MIN is currently a provisional guess,
		// and this is what replaces the guess: if incomplete sets are a distinct
		// population there will be a sparse band between the pile at 1.0 and the
		// broken ones, and the threshold belongs in that gap. If instead the
		// distribution is smooth, there is no natural cut point and the signal
		// needs rethinking rather than tuning -- so a boring histogram is a real
		// answer, not a failed measurement.
		if ( $judged > 0 ) {
			\WP_CLI::log( '' );
			\WP_CLI::log( 'Appearance coverage, for the ' . $judged . ' shows the signal can judge:' );
			\WP_CLI::log( '  (had a TVMaze set, and at least ' . Longevity::COVERAGE_MIN_EVIDENCE . ' credited years to check it against)' );

			$widest = max( $histogram );
			foreach ( $histogram as $bucket => $total ) {
				$label = ( 10 === $bucket )
					? '     1.00 (exact)'
					: sprintf( '%.2f - %.2f    ', $bucket / 10, ( $bucket + 1 ) / 10 );

				\WP_CLI::log(
					'  ' . $label . ' ' . str_pad( (string) $total, 6, ' ', STR_PAD_LEFT ) . '  '
					. str_repeat( '#', (int) round( ( $total / max( 1, $widest ) ) * 40 ) )
				);
			}

			\WP_CLI::log( '  current COVERAGE_MIN = ' . Longevity::COVERAGE_MIN . ' (provisional -- set it from the gap above)' );
		}

		\WP_CLI::log( '' );
		\WP_CLI::log( 'Uncapped NEW totals above 100: ' . $over_100 . ' shows' . ( $over_100 > 0 ? ', highest ' . number_format( $worst, 2 ) : '' ) );
		\WP_CLI::log( '  ^ this is what a display-only cap would preserve. Theoretical max is' );
		\WP_CLI::log( '    103.75, since only show_score() (max 115) is unclamped.' );
		\WP_CLI::log( '' );
		// This used to read "adjust --k until median NEW is close to median OLD",
		// which is backwards and is corrected here rather than deleted, because
		// it is the intuitive thing to try and the reason it fails is not obvious.
		\WP_CLI::log( 'Calibration: do NOT tune --k to match the old median.' );
		\WP_CLI::log( '  The character score is one term of a four-way average whose other three' );
		\WP_CLI::log( '  have a median of ~69. The old character median was 10 -- a scale error, not' );
		\WP_CLI::log( '  a judgment. Matching the old total median needs K=40, which puts the' );
		\WP_CLI::log( '  character median at 11.8 and keeps the error. Tune on the character' );
		\WP_CLI::log( '  component\'s own distribution, and expect most totals to rise.' );
		\WP_CLI::log( '' );
		\WP_CLI::log( '  No re-run is needed to try other values: char_new_raw IS the X that K' );
		\WP_CLI::log( '  divides, and the rest of the total is 4 * score_new_raw - char_new, so one' );
		\WP_CLI::log( '  CSV can be swept offline for every candidate K at once.' );
	}
}

\WP_CLI::add_command( 'lwtv score-preview', 'WP_CLI_LWTV_Score_Preview' );
