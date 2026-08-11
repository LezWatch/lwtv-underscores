<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows → Scores: how every show grades out on the 0–100 score.
 *
 * A decile histogram with a median marker, plus pull-stat cards for the
 * median, the 90+ club, and the failing tail. All math lives in the
 * pure Build\Score_Distribution transform.
 *
 * @package LezWatch.TV
 */

use LWTV\Statistics\Build\Scores as Build_Scores;
use LWTV\Statistics\Build\Score_Distribution;

$score_values = ( new Build_Scores() )->get_score_values();
$score_histo  = Score_Distribution::histogram( $score_values );
$score_median = Score_Distribution::median( $score_values );
$score_tails  = Score_Distribution::tails( $score_values );

if ( $score_histo['total'] <= 0 ) {
	return;
}

// The tallest bucket, for bar heights and the highlight.
$score_peak_count = max( 1, max( array_column( $score_histo['buckets'], 'count' ) ) );
?>

<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Show Scores', 'lwtv' ); ?></p>

<div class="lwtv-pullstats lwtv-pullstats--three">
	<div class="lwtv-tropegap card-header shows">
		<div class="lwtv-tropegap-top">
			<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'The Typical Show', 'lwtv' ); ?></span>
		</div>
		<span class="lwtv-tropegap-number" data-count-to="<?php echo (int) round( $score_median ); ?>"><?php echo esc_html( number_format_i18n( (int) round( $score_median ) ) ); ?></span>
		<p class="lwtv-tropegap-desc"><?php esc_html_e( 'Half of all shows grade higher, half lower.', 'lwtv' ); ?></p>
	</div>
	<div class="lwtv-tropegap card-header happy-endings">
		<div class="lwtv-tropegap-top">
			<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'The 90+ Club', 'lwtv' ); ?></span>
		</div>
		<span class="lwtv-tropegap-number" data-count-to="<?php echo (int) $score_tails['high']; ?>"><?php echo esc_html( number_format_i18n( $score_tails['high'] ) ); ?></span>
		<p class="lwtv-tropegap-desc">
			<?php
			printf(
				/* translators: %s: percentage of shows scoring 90 or higher. */
				esc_html__( '%s%% of all shows have ever scored 90 or higher.', 'lwtv' ),
				esc_html( (string) round( ( $score_tails['high'] / $score_histo['total'] ) * 100, 1 ) )
			);
			?>
		</p>
	</div>
	<div class="lwtv-tropegap card-header dead-characters">
		<div class="lwtv-tropegap-top">
			<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Failing Grades', 'lwtv' ); ?></span>
		</div>
		<span class="lwtv-tropegap-number" data-count-to="<?php echo (int) $score_tails['low']; ?>"><?php echo esc_html( number_format_i18n( $score_tails['low'] ) ); ?></span>
		<p class="lwtv-tropegap-desc"><?php esc_html_e( 'Shows that score under 20 are representation in name only.', 'lwtv' ); ?></p>
	</div>
</div>

<section class="lwtv-yearbars-card bg-light">
	<div class="lwtv-yearbars-head">
		<div>
			<h2 class="lwtv-yearbars-headline"><?php esc_html_e( 'How shows grade out', 'lwtv' ); ?></h2>
			<p class="lwtv-yearbars-desc">
				<?php
				printf(
					/* translators: 1: total number of scored shows, 2: the median score (0–100). */
					esc_html__( 'Every one of the %1$s scored shows, bucketed by score. Half of them land under %2$s.', 'lwtv' ),
					esc_html( number_format_i18n( $score_histo['total'] ) ),
					esc_html( number_format_i18n( (int) round( $score_median ) ) )
				);
				?>
			</p>
		</div>
		<div class="lwtv-yearbars-avg">
			<span class="lwtv-yearbars-avg-num" data-count-to="<?php echo (int) round( $score_median ); ?>"><?php echo esc_html( number_format_i18n( (int) round( $score_median ) ) ); ?></span>
			<span class="lwtv-yearbars-avg-sub"><?php esc_html_e( 'median score', 'lwtv' ); ?></span>
		</div>
	</div>

	<div class="lwtv-histo-figure">
		<div class="lwtv-histo" role="img" aria-label="<?php esc_attr_e( 'Histogram of show scores from 0 to 100', 'lwtv' ); ?>">
			<?php
			foreach ( $score_histo['buckets'] as $score_bucket ) {
				$score_label = $score_bucket['floor'] . '–' . $score_bucket['ceiling'];
				$score_h     = round( ( $score_bucket['count'] / $score_peak_count ) * 100, 1 );
				$score_class = ( $score_bucket['count'] === $score_peak_count ) ? ' lwtv-histo-bar--peak' : '';
				$score_class = ( $score_bucket['floor'] >= 90 ) ? ' lwtv-histo-bar--top' : $score_class;
				?>
				<div class="lwtv-histo-col" title="<?php echo esc_attr( $score_label . ': ' . number_format_i18n( $score_bucket['count'] ) . ' (' . $score_bucket['pct'] . '%)' ); ?>">
					<span class="lwtv-histo-val" data-count-to="<?php echo (int) $score_bucket['count']; ?>"><?php echo esc_html( number_format_i18n( $score_bucket['count'] ) ); ?></span>
					<span class="lwtv-histo-bar<?php echo esc_attr( $score_class ); ?>" style="height:<?php echo esc_attr( (string) max( 2, $score_h ) ); ?>%"></span>
					<span class="lwtv-histo-label"><?php echo esc_html( $score_label ); ?></span>
				</div>
				<?php
			}
			?>
			<span class="lwtv-histo-median" style="left:<?php echo esc_attr( (string) round( min( 100, max( 0, $score_median ) ), 1 ) ); ?>%">
				<span class="lwtv-histo-median-label">
					<?php
					printf(
						/* translators: %s: the median score (0–100). */
						esc_html__( 'median %s', 'lwtv' ),
						esc_html( number_format_i18n( (int) round( $score_median ) ) )
					);
					?>
				</span>
			</span>
		</div>
	</div>
</section>
