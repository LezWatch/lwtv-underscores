<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows → Triggers: callout rail + true-scale bar, magnified.
 *
 * Two bars tell it honestly: the top one refuses to exaggerate the
 * flagged sliver of the archive, the bottom one magnifies just the
 * flagged shows so the three levels become readable. Beside them, a
 * rail of solid stat cards (scarcity, weight, the floor) and an
 * adaptive balance footnote. Level descriptions come from the
 * lez_triggers term descriptions — never hardcoded. Math lives in the
 * pure Build\Trigger_Levels transform.
 *
 * @package LezWatch.TV
 *
 * @var int $shows_count
 */

use LWTV\Statistics\Build\Trigger_Levels;

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
require_once plugin_dir_path( __DIR__ ) . 'partials/phrases.php';

$trig_raw  = lwtv_plugin()->generate_shows_statistics( 'array', 'triggers' );
$trig_data = ( is_array( $trig_raw ) && ! empty( $trig_raw ) ) ? (array) reset( $trig_raw ) : array();

$trig_counts = array();
foreach ( Trigger_Levels::ORDER as $trig_level ) {
	$trig_counts[ $trig_level ] = isset( $trig_data[ $trig_level ] ) ? (int) $trig_data[ $trig_level ]['count'] : 0;
}

$trig_facts = Trigger_Levels::facts( $trig_counts, (int) $shows_count );

// Level meta: label + live taxonomy term description (styled, not split —
// the leading NOTICE/CAUTION/WARNING arrives as a <strong> from the editor).
$trig_levels = array(
	'low'    => array( 'label' => __( 'Low', 'lwtv' ) ),
	'medium' => array( 'label' => __( 'Medium', 'lwtv' ) ),
	'high'   => array( 'label' => __( 'High', 'lwtv' ) ),
);
foreach ( $trig_levels as $trig_level => $trig_meta ) {
	$trig_term = get_term_by( 'slug', $trig_level, 'lez_triggers' );

	$trig_levels[ $trig_level ]['desc'] = ( $trig_term instanceof WP_Term ) ? term_description( $trig_term ) : '';
}
?>

<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Trigger Warnings', 'lwtv' ); ?></p>

<?php if ( $trig_facts['flagged'] <= 0 ) : ?>
	<section class="lwtv-yearbars-card bg-light">
		<h2 class="lwtv-yearbars-headline"><?php esc_html_e( 'No show carries a content warning yet', 'lwtv' ); ?></h2>
		<p class="lwtv-yearbars-desc">
			<?php
			printf(
				/* translators: %s: total number of shows. */
				esc_html__( 'All %s tracked shows are currently unflagged.', 'lwtv' ),
				esc_html( number_format_i18n( (int) $shows_count ) )
			);
			?>
		</p>
	</section>
	<?php return; ?>
<?php endif; ?>

<div class="lwtv-tw bg-light">
	<div class="lwtv-tw-layout">

		<div class="lwtv-tw-rail">
			<div class="lwtv-tw-stat lwtv-tw-stat--scarcity">
				<div class="lwtv-tw-stat-top">
					<span class="lwtv-tw-stat-eyebrow"><?php esc_html_e( 'Scarcity', 'lwtv' ); ?></span>
					<span class="lwtv-tw-stat-icon"><?php echo lwtv_plugin()->get_symbolicon( svg: 'warning.svg', icon: 'svg-warning', max_size: '15' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</div>
				<span class="lwtv-tw-stat-figure">
					<?php
					printf(
						/* translators: %s: the "1 in N" denominator for flagged shows. */
						esc_html__( '1 in %s', 'lwtv' ),
						esc_html( number_format_i18n( $trig_facts['scarcity_ratio'] ) )
					);
					?>
				</span>
				<p class="lwtv-tw-stat-body">
					<?php
					printf(
						/* translators: 1: number of flagged shows, 2: total number of shows. */
						esc_html__( 'shows carries a warning of any kind: %1$s of %2$s.', 'lwtv' ),
						esc_html( number_format_i18n( $trig_facts['flagged'] ) ),
						esc_html( number_format_i18n( (int) $shows_count ) )
					);
					?>
				</p>
			</div>

			<div class="lwtv-tw-stat lwtv-tw-stat--weight">
				<div class="lwtv-tw-stat-top">
					<span class="lwtv-tw-stat-eyebrow"><?php esc_html_e( 'Weight', 'lwtv' ); ?></span>
					<span class="lwtv-tw-stat-icon"><?php echo lwtv_plugin()->get_symbolicon( svg: 'scales.svg', icon: 'svg-scales', max_size: '15' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</div>
				<span class="lwtv-tw-stat-figure"><?php echo esc_html( number_format_i18n( $trig_facts['heavy_pct'], 1 ) . '%' ); ?></span>
				<p class="lwtv-tw-stat-body">
					<?php
					if ( $trig_facts['heavy'] > 0 ) {
						printf(
							/* translators: %s: number of medium + high warning shows. */
							esc_html__( 'of warnings are medium or high: %s shows in all.', 'lwtv' ),
							esc_html( number_format_i18n( $trig_facts['heavy'] ) )
						);
					} else {
						esc_html_e( 'of warnings are medium or high — every flagged show sits at low.', 'lwtv' );
					}
					?>
				</p>
			</div>

			<div class="lwtv-tw-stat lwtv-tw-stat--floor">
				<div class="lwtv-tw-stat-top">
					<span class="lwtv-tw-stat-eyebrow"><?php esc_html_e( 'The Floor', 'lwtv' ); ?></span>
					<span class="lwtv-tw-stat-icon"><?php echo lwtv_plugin()->get_symbolicon( svg: 'graph-line.svg', icon: 'svg-graph-line', max_size: '15' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</div>
				<span class="lwtv-tw-stat-figure" data-count-to="<?php echo (int) $trig_facts['levels']['high']['count']; ?>"><?php echo esc_html( number_format_i18n( $trig_facts['levels']['high']['count'] ) ); ?></span>
				<p class="lwtv-tw-stat-body">
					<?php
					if ( $trig_facts['floor_ratio'] > 0 ) {
						printf(
							/* translators: %s: the "1 in N" denominator for high-warning shows. */
							esc_html__( 'shows carry a high warning. That&#8217;s 1 in every %s.', 'lwtv' ),
							esc_html( number_format_i18n( $trig_facts['floor_ratio'] ) )
						);
					} else {
						esc_html_e( 'shows carry a high warning right now — the heaviest flag is unused.', 'lwtv' );
					}
					?>
				</p>
			</div>

			<?php
			// Balance footnote — named honestly: "nearly 2 to 1" only while true.
			$trig_bal      = Trigger_Levels::balance( $trig_facts['levels']['low']['count'], $trig_facts['levels']['high']['count'] );
			$trig_bal_text = '';
			if ( 'even' === $trig_bal['mode'] ) {
				$trig_bal_text = sprintf(
					/* translators: 1: low-warning count, 2: high-warning count. */
					__( 'low and high warnings are nearly even — %1$s against %2$s.', 'lwtv' ),
					number_format_i18n( $trig_facts['levels']['low']['count'] ),
					number_format_i18n( $trig_facts['levels']['high']['count'] )
				);
			} elseif ( 'low-leads' === $trig_bal['mode'] || 'high-leads' === $trig_bal['mode'] ) {
				$trig_bal_variants = array(
					'low-leads'  => array(
						/* translators: 1: rounded ratio, 2: low-warning count, 3: high-warning count. */
						'nearly'    => __( 'low warnings outnumber high ones nearly %1$s to 1 (%2$s against %3$s).', 'lwtv' ),
						/* translators: 1: rounded ratio, 2: low-warning count, 3: high-warning count. */
						'more-than' => __( 'low warnings outnumber high ones more than %1$s to 1 (%2$s against %3$s).', 'lwtv' ),
						/* translators: 1: rounded ratio, 2: low-warning count, 3: high-warning count. */
						'exactly'   => __( 'low warnings outnumber high ones %1$s to 1 (%2$s against %3$s).', 'lwtv' ),
					),
					'high-leads' => array(
						/* translators: 1: rounded ratio, 2: high-warning count, 3: low-warning count. */
						'nearly'    => __( 'high warnings outnumber low ones nearly %1$s to 1 (%2$s against %3$s).', 'lwtv' ),
						/* translators: 1: rounded ratio, 2: high-warning count, 3: low-warning count. */
						'more-than' => __( 'high warnings outnumber low ones more than %1$s to 1 (%2$s against %3$s).', 'lwtv' ),
						/* translators: 1: rounded ratio, 2: high-warning count, 3: low-warning count. */
						'exactly'   => __( 'high warnings outnumber low ones %1$s to 1 (%2$s against %3$s).', 'lwtv' ),
					),
				);

				$trig_bal_first  = ( 'low-leads' === $trig_bal['mode'] ) ? 'low' : 'high';
				$trig_bal_second = ( 'low-leads' === $trig_bal['mode'] ) ? 'high' : 'low';
				$trig_bal_text   = sprintf(
					$trig_bal_variants[ $trig_bal['mode'] ][ $trig_bal['qualifier'] ], // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Chosen from the translated variants above.
					number_format_i18n( $trig_bal['ratio'] ),
					number_format_i18n( $trig_facts['levels'][ $trig_bal_first ]['count'] ),
					number_format_i18n( $trig_facts['levels'][ $trig_bal_second ]['count'] )
				);
			}
			if ( '' !== $trig_bal_text ) :
				?>
				<p class="lwtv-tw-footnote"><strong><?php esc_html_e( 'Balance:', 'lwtv' ); ?></strong> <?php echo esc_html( $trig_bal_text ); ?></p>
			<?php endif; ?>
		</div>

		<div class="lwtv-tw-chart">
			<h2 class="lwtv-yearbars-headline">
				<?php
				if ( $trig_facts['flagged_pct'] < 25 ) {
					esc_html_e( 'A thin sliver of the archive, opened up', 'lwtv' );
				} else {
					printf(
						/* translators: %s: a fraction phrase, e.g. "Over a quarter". */
						esc_html__( '%s of the archive, opened up', 'lwtv' ),
						esc_html( lwtv_stats_fraction_phrase( $trig_facts['flagged_pct'] ) )
					);
				}
				?>
			</h2>
			<p class="lwtv-tw-deck">
				<?php
				printf(
					/* translators: 1: total number of shows, 2: number of flagged shows. */
					esc_html__( 'Of the %1$s shows we track, the %2$s flagged ones sit at the right-hand end.', 'lwtv' ),
					esc_html( number_format_i18n( (int) $shows_count ) ),
					esc_html( number_format_i18n( $trig_facts['flagged'] ) )
				);
				?>
			</p>

			<?php
			$trig_scale_label = sprintf(
				/* translators: 1: unflagged count, 2: low count, 3: medium count, 4: high count, 5: total shows. */
				__( 'True-scale bar: %1$s shows with no warning, then %2$s low, %3$s medium, and %4$s high, of %5$s shows.', 'lwtv' ),
				number_format_i18n( $trig_facts['none'] ),
				number_format_i18n( $trig_facts['levels']['low']['count'] ),
				number_format_i18n( $trig_facts['levels']['medium']['count'] ),
				number_format_i18n( $trig_facts['levels']['high']['count'] ),
				number_format_i18n( (int) $shows_count )
			);
			?>
			<div class="lwtv-tw-bar" role="img" aria-label="<?php echo esc_attr( $trig_scale_label ); ?>">
				<div class="lwtv-tw-bar-none" style="width:<?php echo esc_attr( (string) $trig_facts['none_pct'] ); ?>%" aria-hidden="true">
					<span class="lwtv-tw-bar-none-label">
						<?php
						printf(
							/* translators: %s: number of shows without a warning. */
							esc_html( _n( '%s show carries no warning', '%s shows carry no warning', $trig_facts['none'], 'lwtv' ) ),
							esc_html( number_format_i18n( $trig_facts['none'] ) )
						);
						?>
					</span>
				</div>
				<?php foreach ( Trigger_Levels::ORDER as $trig_level ) : ?>
					<div class="lwtv-tw-bar-seg lwtv-tw-bar-seg--<?php echo esc_attr( $trig_level ); ?>" style="width:<?php echo esc_attr( (string) $trig_facts['levels'][ $trig_level ]['share_total'] ); ?>%" aria-hidden="true"></div>
				<?php endforeach; ?>
			</div>

			<div class="lwtv-tw-bracket" aria-hidden="true">
				<div class="lwtv-tw-bracket-spacer" style="width:<?php echo esc_attr( (string) $trig_facts['none_pct'] ); ?>%"></div>
				<div class="lwtv-tw-bracket-span" style="width:<?php echo esc_attr( (string) $trig_facts['flagged_pct'] ); ?>%"><span class="lwtv-tw-bracket-stem"></span></div>
			</div>

			<div class="lwtv-tw-panel">
				<div class="lwtv-tw-panel-head">
					<span class="lwtv-tw-panel-num" data-count-to="<?php echo (int) $trig_facts['flagged']; ?>"><?php echo esc_html( number_format_i18n( $trig_facts['flagged'] ) ); ?></span>
					<span class="lwtv-tw-panel-sub"><?php esc_html_e( 'flagged shows', 'lwtv' ); ?></span>
				</div>

				<?php
				$trig_mag_label = sprintf(
					/* translators: 1: low count, 2: medium count, 3: high count, 4: flagged total. */
					__( 'Magnified bar of the %4$s flagged shows: %1$s low, %2$s medium, %3$s high.', 'lwtv' ),
					number_format_i18n( $trig_facts['levels']['low']['count'] ),
					number_format_i18n( $trig_facts['levels']['medium']['count'] ),
					number_format_i18n( $trig_facts['levels']['high']['count'] ),
					number_format_i18n( $trig_facts['flagged'] )
				);
				?>
				<div class="lwtv-tw-magbar" role="img" aria-label="<?php echo esc_attr( $trig_mag_label ); ?>">
					<?php
					foreach ( Trigger_Levels::ORDER as $trig_level ) {
						$trig_share = $trig_facts['levels'][ $trig_level ]['share_flagged'];
						if ( $trig_share <= 0 ) {
							continue;
						}
						?>
						<div class="lwtv-tw-magbar-seg lwtv-tw-magbar-seg--<?php echo esc_attr( $trig_level ); ?>" style="width:<?php echo esc_attr( (string) $trig_share ); ?>%" aria-hidden="true">
							<?php if ( $trig_share >= 10 ) : ?>
								<span class="lwtv-tw-magbar-label"><?php echo esc_html( $trig_levels[ $trig_level ]['label'] . ' · ' . number_format_i18n( $trig_facts['levels'][ $trig_level ]['count'] ) ); ?></span>
							<?php endif; ?>
						</div>
						<?php
					}
					?>
				</div>

				<div class="lwtv-tw-legend">
					<?php foreach ( Trigger_Levels::ORDER as $trig_level ) : ?>
						<div class="lwtv-tw-legend-item lwtv-tw-legend-item--<?php echo esc_attr( $trig_level ); ?>">
							<span class="lwtv-tw-legend-name"><?php echo esc_html( $trig_levels[ $trig_level ]['label'] ); ?></span>
							<?php if ( '' !== $trig_levels[ $trig_level ]['desc'] ) : ?>
								<div class="lwtv-tw-legend-desc">
									<?php
									echo wp_kses(
										$trig_levels[ $trig_level ]['desc'],
										array(
											'p'      => array(),
											'strong' => array(),
											'em'     => array(),
											'br'     => array(),
										)
									);
									?>
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>

	</div>
</div>
