<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows → Worth It: hundred-square grid + verdict list + score bars.
 *
 * Worth It is an ordinal verdict (Yes beats Meh beats No), so the donut
 * is replaced by a 10×10 grid — one square per percent of the archive —
 * where all four verdicts, including a tiny TBD, render at true size.
 * Below, the average show score per verdict on an absolute 0–100 axis.
 * The "verdict tracks the score" heading is a claim about the data and
 * is only made while Worth_It_Grid::tracks_score() says it holds. Math
 * lives in the pure Build\Worth_It_Grid transform.
 *
 * @package LezWatch.TV
 *
 * @var int $shows_count
 */

use LWTV\Statistics\Build\Scores as Build_Scores;
use LWTV\Statistics\Build\Worth_It_Grid;

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
require_once plugin_dir_path( __DIR__ ) . 'partials/phrases.php';

$worth_raw  = lwtv_plugin()->generate_shows_statistics( 'array', 'worth-it' );
$worth_data = ( is_array( $worth_raw ) && ! empty( $worth_raw ) ) ? (array) reset( $worth_raw ) : array();

$worth_counts = array();
foreach ( Worth_It_Grid::ORDER as $worth_verdict ) {
	$worth_counts[ $worth_verdict ] = isset( $worth_data[ $worth_verdict ] ) ? (int) $worth_data[ $worth_verdict ]['count'] : 0;
}
$worth_sum = array_sum( $worth_counts );

if ( $worth_sum <= 0 ) {
	return;
}

$worth_squares  = Worth_It_Grid::squares( $worth_counts );
$worth_averages = Worth_It_Grid::averages( ( new Build_Scores() )->get_scores_by_worthit() );

$worth_meta = array(
	'yes' => __( 'Yes', 'lwtv' ),
	'meh' => __( 'Meh', 'lwtv' ),
	'no'  => __( 'No', 'lwtv' ),
	'tbd' => __( 'TBD', 'lwtv' ),
);

// Shares of the rated total, one decimal — same denominator as the grid.
$worth_shares = array();
foreach ( $worth_counts as $worth_verdict => $worth_count ) {
	$worth_shares[ $worth_verdict ] = round( ( $worth_count / $worth_sum ) * 100, 1 );
}

// Headline: the Yes share, phrased from the ladder ("Nearly two thirds…").
if ( $worth_counts['yes'] > 0 ) {
	$worth_headline = sprintf(
		/* translators: 1: a fraction phrase, e.g. "Nearly two thirds", 2: the Yes share (one decimal). */
		__( '%1$s (%2$s%%) are a clear yes', 'lwtv' ),
		lwtv_stats_fraction_phrase( $worth_shares['yes'] ),
		number_format_i18n( $worth_shares['yes'], 1 )
	);
} else {
	$worth_headline = __( 'How our editors call it', 'lwtv' );
}

// Deck: the hard-no ratio, phrased ("one in 8"), with a graceful zero.
$worth_no_ratio = lwtv_stats_ratio_phrase( $worth_shares['no'] );
if ( '' !== $worth_no_ratio ) {
	$worth_deck = sprintf(
		/* translators: %s: a "one in N" ratio phrase, e.g. "one in 8". */
		__( 'Our editors rate every show. About %s is a hard &#8220;no&#8221;. The rest sit somewhere in the middle or await review.', 'lwtv' ),
		$worth_no_ratio
	);
} else {
	$worth_deck = __( 'Our editors rate every show. Almost none are a hard &#8220;no&#8221; — the rest sit somewhere in the middle or await review.', 'lwtv' );
}

$worth_grid_label = sprintf(
	/* translators: 1-4: Yes/Meh/No/TBD counts, 5: total rated shows. */
	__( 'Hundred-dot grid: %1$s yes, %2$s meh, %3$s no, %4$s TBD, of %5$s rated shows.', 'lwtv' ),
	number_format_i18n( $worth_counts['yes'] ),
	number_format_i18n( $worth_counts['meh'] ),
	number_format_i18n( $worth_counts['no'] ),
	number_format_i18n( $worth_counts['tbd'] ),
	number_format_i18n( $worth_sum )
);
?>

<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Worth It Ratings', 'lwtv' ); ?></p>

<div class="lwtv-wi bg-light">
	<div class="lwtv-wi-layout">

		<div class="lwtv-wi-gridwrap">
			<div class="lwtv-wi-grid" role="img" aria-label="<?php echo esc_attr( $worth_grid_label ); ?>">
				<?php
				foreach ( Worth_It_Grid::ORDER as $worth_verdict ) {
					$worth_sq = (int) ( $worth_squares[ $worth_verdict ] ?? 0 );
					for ( $worth_i = 0; $worth_i < $worth_sq; $worth_i++ ) {
						printf( '<span class="lwtv-wi-square lwtv-wi-square--%s" aria-hidden="true"></span>', esc_attr( $worth_verdict ) );
					}
				}
				?>
			</div>
			<p class="lwtv-wi-caption">
				<?php
				printf(
					/* translators: %s: roughly how many shows one grid dot represents. */
					esc_html__( 'Each dot is 1%% of the archive — about %s shows.', 'lwtv' ),
					esc_html( number_format_i18n( max( 1, (int) round( $worth_sum / 100 ) ) ) )
				);
				?>
			</p>
		</div>

		<div class="lwtv-wi-list">
			<h2 class="lwtv-yearbars-headline"><?php echo esc_html( $worth_headline ); ?></h2>
			<p class="lwtv-wi-deck"><?php echo esc_html( html_entity_decode( $worth_deck, ENT_QUOTES, 'UTF-8' ) ); ?></p>

			<?php
			$worth_rows = array_keys( array_filter( $worth_counts ) );
			$worth_last = end( $worth_rows );
			foreach ( $worth_rows as $worth_verdict ) :
				?>
				<div class="lwtv-wi-row<?php echo ( $worth_verdict === $worth_last ) ? ' lwtv-wi-row--last' : ''; ?>">
					<span class="lwtv-wi-swatch lwtv-wi-swatch--<?php echo esc_attr( $worth_verdict ); ?>" aria-hidden="true"></span>
					<span class="lwtv-wi-verdict"><?php echo esc_html( $worth_meta[ $worth_verdict ] ); ?></span>
					<span class="lwtv-wi-count" data-count-to="<?php echo (int) $worth_counts[ $worth_verdict ]; ?>"><?php echo esc_html( number_format_i18n( $worth_counts[ $worth_verdict ] ) ); ?></span>
					<span class="lwtv-wi-share"><?php echo esc_html( number_format_i18n( $worth_shares[ $worth_verdict ], 1 ) . '%' ); ?></span>
				</div>
			<?php endforeach; ?>

			<?php if ( ! empty( $worth_squares['tbd'] ) && 1 === (int) $worth_squares['tbd'] ) : ?>
				<p class="lwtv-wi-note">
					<?php
					printf(
						/* translators: %s: the TBD share of shows (one decimal). */
						esc_html__( 'The grid is the one form where TBD is visible without being exaggerated: it is a single dot, which is what %s%% looks like.', 'lwtv' ),
						esc_html( number_format_i18n( $worth_shares['tbd'], 1 ) )
					);
					?>
				</p>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $worth_averages ) ) : ?>
			<div class="lwtv-wi-scores">
				<div class="lwtv-wi-scores-head">
					<div>
						<span class="lwtv-wi-eyebrow"><?php esc_html_e( 'Average Score by Verdict', 'lwtv' ); ?></span>
						<h3 class="lwtv-wi-scores-headline">
							<?php
							// A claim about the data, only made while the data supports it.
							if ( Worth_It_Grid::tracks_score( $worth_averages ) ) {
								esc_html_e( 'The verdict tracks the score', 'lwtv' );
							} else {
								esc_html_e( 'Average score, verdict by verdict', 'lwtv' );
							}
							?>
						</h3>
					</div>
					<p class="lwtv-wi-caveat">
						<?php
						if ( isset( $worth_averages['tbd'] ) && (int) $worth_averages['tbd']['count'] < 30 ) {
							printf(
								/* translators: %s: number of TBD shows. */
								esc_html__( 'Mean of each show&#8217;s score, out of 100. TBD is only %s shows, so treat its average as a small sample.', 'lwtv' ),
								esc_html( number_format_i18n( $worth_averages['tbd']['count'] ) )
							);
						} else {
							esc_html_e( 'Mean of each show&#8217;s score, out of 100.', 'lwtv' );
						}
						?>
					</p>
				</div>

				<div class="lwtv-wi-score-rows">
					<?php
					foreach ( Worth_It_Grid::ORDER as $worth_verdict ) :
						if ( ! isset( $worth_averages[ $worth_verdict ] ) ) {
							continue;
						}
						$worth_avg = (int) $worth_averages[ $worth_verdict ]['average'];
						?>
						<div class="lwtv-wi-score-row">
							<span class="lwtv-wi-score-label">
								<span class="lwtv-wi-swatch lwtv-wi-swatch--sm lwtv-wi-swatch--<?php echo esc_attr( $worth_verdict ); ?>" aria-hidden="true"></span>
								<span class="lwtv-wi-verdict lwtv-wi-verdict--sm"><?php echo esc_html( $worth_meta[ $worth_verdict ] ); ?></span>
							</span>
							<span class="lwtv-wi-score-track" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: verdict name, 2: average score out of 100. */ __( '%1$s: average score %2$s out of 100.', 'lwtv' ), $worth_meta[ $worth_verdict ], number_format_i18n( $worth_avg ) ) ); ?>">
								<span class="lwtv-wi-score-fill lwtv-wi-score-fill--<?php echo esc_attr( $worth_verdict ); ?>" style="width:<?php echo (int) $worth_avg; ?>%" aria-hidden="true"></span>
							</span>
							<span class="lwtv-wi-score-num" data-count-to="<?php echo (int) $worth_avg; ?>"><?php echo esc_html( number_format_i18n( $worth_avg ) ); ?></span>
						</div>
					<?php endforeach; ?>

					<div class="lwtv-wi-axis" aria-hidden="true">
						<span></span>
						<span class="lwtv-wi-axis-ticks"><span>0</span><span>25</span><span>50</span><span>75</span><span>100</span></span>
						<span></span>
					</div>
				</div>
			</div>
		<?php endif; ?>

	</div>
</div>
