<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows → We Love It: rarity hero, cohort rail, comparisons, roster.
 *
 * Four sections in two cards: the "1 in N" rarity with a true-scale
 * bar, four cohort callout cards, loved-vs-everything-else paired bars
 * on absolute axes, and the full loved roster (the catalog IS the
 * content at this scale). Every data-dependent sentence is adaptive:
 * the comparison heading only claims what the data supports, each
 * takeaway has directional variants, the "clearest gap" clause is a
 * ranking check, and cohort percentages are never printed bare at
 * n-this-small. Math lives in Build\We_Love_Compare; data acquisition
 * in Build\We_Love.
 *
 * @package LezWatch.TV
 *
 * @var int $shows_count
 */

use LWTV\Statistics\Build\We_Love;
use LWTV\Statistics\Build\We_Love_Compare;

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
require_once plugin_dir_path( __DIR__ ) . 'partials/phrases.php';

$love_glue   = new We_Love();
$love_roster = $love_glue->get_roster();
$love_n      = count( $love_roster );
$love_total  = (int) $shows_count;
?>

<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Shows We Love', 'lwtv' ); ?></p>

<?php
// Zero loved shows: the whole page collapses. One empty state, no cards.
if ( $love_n <= 0 ) {
	?>
	<section class="lwtv-yearbars-card bg-light">
		<h2 class="lwtv-yearbars-headline"><?php esc_html_e( 'No shows currently carry the We Love flag', 'lwtv' ); ?></h2>
		<p class="lwtv-yearbars-desc"><?php esc_html_e( '&#8220;Shows We Love&#8221; is hand-picked, and right now the list is empty.', 'lwtv' ); ?></p>
	</section>
	<?php
	return;
}

$love_rest_n = max( 0, $love_total - $love_n );
$love_pct    = ( $love_total > 0 ) ? round( ( $love_n / $love_total ) * 100, 1 ) : 0.0;
$love_ratio  = ( $love_n > 0 && $love_total > 0 ) ? max( 2, (int) round( $love_total / $love_n ) ) : 0;

$love_cohort = We_Love_Compare::cohort( $love_roster );
$love_versus = We_Love_Compare::versus( We_Love_Compare::loved_totals( $love_roster ), $love_glue->get_archive_totals() );

// Cohort card sentences — counts, never bare percentages, at this n.
if ( $love_cohort['gold'] > 0 ) {
	$love_gold_text = __( 'also carry a gold star, meaning they are not only loved, but made for us specifically.', 'lwtv' );
} else {
	$love_gold_text = __( 'carry a gold star, and earned our love on it\'s own merits.', 'lwtv' );
}

if ( $love_cohort['airing'] > 0 ) {
	$love_airing_text = __( 'are still on the air right now.', 'lwtv' );
} else {
	$love_airing_text = __( 'are still on the air as every loved show has ended.', 'lwtv' );
}

if ( $love_cohort['span_min'] > 0 && $love_cohort['span_max'] > $love_cohort['span_min'] ) {
	$love_span_fig  = $love_cohort['span_min'] . '–' . $love_cohort['span_max'];
	$love_span_text = __( 'from the first loved premiere to the newest.', 'lwtv' );
} elseif ( $love_cohort['span_min'] > 0 ) {
	$love_span_fig  = (string) $love_cohort['span_min'];
	$love_span_text = __( 'every loved show premiered in the same year.', 'lwtv' );
} else {
	$love_span_fig  = '—';
	$love_span_text = __( 'premiere dates are missing for these shows.', 'lwtv' );
}
?>

<div class="lwtv-love bg-light">

	<div class="lwtv-love-hero">
		<div class="lwtv-love-hero-figure">
			<span class="lwtv-love-ratio">
				<?php
				printf(
					/* translators: %s: the "1 in N" denominator for loved shows. */
					esc_html__( '1 in %s', 'lwtv' ),
					esc_html( number_format_i18n( $love_ratio ) )
				);
				?>
			</span>
			<span class="lwtv-love-ratio-sub"><?php esc_html_e( 'shows earns the flag', 'lwtv' ); ?></span>
			<span class="lwtv-love-ratio-meta">
				<?php
				printf(
					/* translators: 1: loved count, 2: total shows, 3: loved share (one decimal). */
					esc_html__( '%1$s of %2$s · %3$s%%', 'lwtv' ),
					esc_html( number_format_i18n( $love_n ) ),
					esc_html( number_format_i18n( $love_total ) ),
					esc_html( number_format_i18n( $love_pct, 1 ) )
				);
				?>
			</span>
		</div>
		<div class="lwtv-love-hero-body">
			<h2 class="lwtv-yearbars-headline"><?php esc_html_e( 'A rare and deliberate honor', 'lwtv' ); ?></h2>
			<p class="lwtv-love-hero-desc"><?php echo esc_html( html_entity_decode( __( '&#8220;Shows We Love&#8221; is a hand-picked, carefully curated list, so it&#8217;s a fraction of the whole database.', 'lwtv' ), ENT_QUOTES, 'UTF-8' ) ); ?></p>
			<div class="lwtv-love-sliver" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: loved count, 2: everything-else count. */ __( 'True-scale bar: %1$s loved shows against %2$s everything else.', 'lwtv' ), number_format_i18n( $love_n ), number_format_i18n( $love_rest_n ) ) ); ?>">
				<span class="lwtv-love-sliver-fill" style="width:<?php echo esc_attr( (string) max( 0.1, $love_pct ) ); ?>%" aria-hidden="true"></span>
			</div>
			<div class="lwtv-love-sliver-caption" aria-hidden="true">
				<span>
					<?php
					printf(
						/* translators: %s: loved count. */
						esc_html__( '%s shows we love', 'lwtv' ),
						esc_html( number_format_i18n( $love_n ) )
					);
					?>
				</span>
				<span>
					<?php
					printf(
						/* translators: %s: everything-else count. */
						esc_html__( '%s everything else', 'lwtv' ),
						esc_html( number_format_i18n( $love_rest_n ) )
					);
					?>
				</span>
			</div>
		</div>
	</div>

	<div class="lwtv-love-section">
		<span class="lwtv-love-eyebrow"><?php esc_html_e( 'The Cohort', 'lwtv' ); ?></span>
		<h3 class="lwtv-love-h3"><?php esc_html_e( 'Who the loved shows are', 'lwtv' ); ?></h3>
		<div class="lwtv-love-rail">
			<div class="lwtv-love-callout lwtv-love-callout--pink">
				<div class="lwtv-love-callout-top">
					<span class="lwtv-love-callout-eyebrow"><?php esc_html_e( 'Also Gold', 'lwtv' ); ?></span>
					<span class="lwtv-love-callout-chip"><?php echo lwtv_plugin()->get_symbolicon( svg: 'star.svg', icon: 'svg-star', max_size: '15' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</div>
				<span class="lwtv-love-callout-num">
					<?php
					printf(
						/* translators: 1: gold-overlap count, 2: loved total. */
						esc_html__( '%1$s of %2$s', 'lwtv' ),
						esc_html( number_format_i18n( $love_cohort['gold'] ) ),
						esc_html( number_format_i18n( $love_n ) )
					);
					?>
				</span>
				<p class="lwtv-love-callout-text"><?php echo esc_html( $love_gold_text ); ?></p>
			</div>
			<div class="lwtv-love-callout lwtv-love-callout--pinkdeep">
				<div class="lwtv-love-callout-top">
					<span class="lwtv-love-callout-eyebrow"><?php esc_html_e( 'Still Airing', 'lwtv' ); ?></span>
					<span class="lwtv-love-callout-chip"><?php echo lwtv_plugin()->get_symbolicon( svg: 'tv.svg', icon: 'svg-tv', max_size: '15' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</div>
				<span class="lwtv-love-callout-num">
					<?php
					printf(
						/* translators: 1: still-airing count, 2: loved total. */
						esc_html__( '%1$s of %2$s', 'lwtv' ),
						esc_html( number_format_i18n( $love_cohort['airing'] ) ),
						esc_html( number_format_i18n( $love_n ) )
					);
					?>
				</span>
				<p class="lwtv-love-callout-text"><?php echo esc_html( $love_airing_text ); ?></p>
			</div>
			<div class="lwtv-love-callout lwtv-love-callout--purple">
				<div class="lwtv-love-callout-top">
					<span class="lwtv-love-callout-eyebrow"><?php esc_html_e( 'Span', 'lwtv' ); ?></span>
					<span class="lwtv-love-callout-chip"><?php echo lwtv_plugin()->get_symbolicon( svg: 'calendar-alt.svg', icon: 'svg-calendar', max_size: '15' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</div>
				<span class="lwtv-love-callout-num"><?php echo esc_html( $love_span_fig ); ?></span>
				<p class="lwtv-love-callout-text"><?php echo esc_html( $love_span_text ); ?></p>
			</div>
			<div class="lwtv-love-callout lwtv-love-callout--teal">
				<div class="lwtv-love-callout-top">
					<span class="lwtv-love-callout-eyebrow"><?php esc_html_e( 'Reach', 'lwtv' ); ?></span>
					<span class="lwtv-love-callout-chip"><?php echo lwtv_plugin()->get_symbolicon( svg: 'globe.svg', icon: 'svg-globe', max_size: '15' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</div>
				<span class="lwtv-love-callout-num" data-count-to="<?php echo (int) $love_cohort['countries']; ?>"><?php echo esc_html( number_format_i18n( $love_cohort['countries'] ) ); ?></span>
				<p class="lwtv-love-callout-text">
					<?php
					echo esc_html(
						_n( 'country has produced a loved show.', 'countries have produced loved shows.', $love_cohort['countries'], 'lwtv' )
					);
					?>
				</p>
			</div>
		</div>
	</div>

	<div class="lwtv-love-section">
		<?php if ( $love_n < 10 || empty( $love_versus ) ) : ?>
			<span class="lwtv-love-eyebrow"><?php esc_html_e( 'Loved vs. Everything Else', 'lwtv' ); ?></span>
			<p class="lwtv-love-deck">
				<?php
				printf(
					/* translators: %s: number of loved shows. */
					esc_html__( 'With only %s loved shows, group comparisons would be an average of almost nothing. The roster below is the honest view.', 'lwtv' ),
					esc_html( number_format_i18n( $love_n ) )
				);
				?>
			</p>
		<?php else : ?>
			<div class="lwtv-love-vs-head">
				<div>
					<span class="lwtv-love-eyebrow"><?php esc_html_e( 'Loved vs. Everything Else', 'lwtv' ); ?></span>
					<h3 class="lwtv-love-h3">
						<?php
						// The claim heading asserts three leads at once; fall back when any slips.
						if ( ! empty( $love_versus['leads_all'] ) ) {
							esc_html_e( 'Bigger casts, queerer casts, happier endings', 'lwtv' );
						} else {
							esc_html_e( 'How the loved shows compare', 'lwtv' );
						}
						?>
					</h3>
				</div>
				<div class="lwtv-love-legend" aria-hidden="true">
					<span class="lwtv-love-legend-item">
						<span class="lwtv-love-legend-stack">
							<span class="lwtv-love-legend-swatch lwtv-love-legend-swatch--chars"></span>
							<span class="lwtv-love-legend-swatch lwtv-love-legend-swatch--actors"></span>
							<span class="lwtv-love-legend-swatch lwtv-love-legend-swatch--happy"></span>
							<span class="lwtv-love-legend-swatch lwtv-love-legend-swatch--deaths"></span>
						</span>
						<?php
						printf(
							/* translators: %s: loved count. */
							esc_html__( 'The %s loved', 'lwtv' ),
							esc_html( number_format_i18n( $love_n ) )
						);
						?>
					</span>
					<span class="lwtv-love-legend-item">
						<span class="lwtv-love-legend-swatch lwtv-love-legend-swatch--rest"></span>
						<?php
						printf(
							/* translators: %s: everything-else count. */
							esc_html__( 'The other %s', 'lwtv' ),
							esc_html( number_format_i18n( $love_rest_n ) )
						);
						?>
					</span>
				</div>
			</div>
			<p class="lwtv-love-deck"><?php esc_html_e( 'The top pair are averages per show on a 0–10 axis; the bottom pair are shares of each group on a 0–100% axis.', 'lwtv' ); ?></p>

			<?php
			// Takeaway sentences, all directional.
			$love_take = array();

			switch ( $love_versus['chars']['mode'] ) {
				case 'multiple':
					$love_take['chars'] = ( 2 === $love_versus['chars']['times'] )
						? __( 'More than twice as many queer characters per loved show.', 'lwtv' )
						/* translators: %s: a whole multiple, 3 or more. */
						: sprintf( __( '%s times as many queer characters per loved show.', 'lwtv' ), number_format_i18n( $love_versus['chars']['times'] ) );
					break;
				case 'more':
					$love_take['chars'] = __( 'More queer characters per loved show than elsewhere.', 'lwtv' );
					break;
				case 'fewer':
					$love_take['chars'] = __( 'Loved shows actually run smaller queer casts than the rest.', 'lwtv' );
					break;
				default:
					$love_take['chars'] = __( 'About as many queer characters as everywhere else.', 'lwtv' );
					break;
			}

			switch ( $love_versus['actors']['mode'] ) {
				case 'multiple':
					$love_take['actors'] = ( 2 === $love_versus['actors']['times'] )
						? __( 'More than twice the queer casting.', 'lwtv' )
						/* translators: %s: a whole multiple, 3 or more. */
						: sprintf( __( '%s times the queer casting.', 'lwtv' ), number_format_i18n( $love_versus['actors']['times'] ) );
					break;
				case 'more':
					$love_take['actors'] = __( 'More queer actors in the cast than elsewhere.', 'lwtv' );
					break;
				case 'fewer':
					$love_take['actors'] = __( 'Loved shows actually cast fewer queer actors.', 'lwtv' );
					break;
				default:
					$love_take['actors'] = __( 'About the same queer casting as everywhere else.', 'lwtv' );
					break;
			}

			switch ( $love_versus['happy']['mode'] ) {
				case 'multiple':
					$love_take['happy'] = ( 2 === $love_versus['happy']['times'] )
						? __( 'A loved show is well over twice as likely to end happily.', 'lwtv' )
						/* translators: %s: a whole multiple, 3 or more. */
						: sprintf( __( 'A loved show is %s times as likely to end happily.', 'lwtv' ), number_format_i18n( $love_versus['happy']['times'] ) );
					break;
				case 'more':
					$love_take['happy'] = __( 'A loved show is more likely to end happily.', 'lwtv' );
					break;
				case 'fewer':
					$love_take['happy'] = __( 'Loved shows are actually less likely to end happily.', 'lwtv' );
					break;
				default:
					$love_take['happy'] = __( 'Loved shows end happily about as often as the rest.', 'lwtv' );
					break;
			}
			// "The clearest gap on the page" is a ranking claim — only when true.
			if ( 'happy' === $love_versus['largest_gap'] && in_array( $love_versus['happy']['mode'], array( 'multiple', 'more' ), true ) ) {
				$love_take['happy'] .= ' ' . __( 'The clearest gap on the page.', 'lwtv' );
			}

			switch ( $love_versus['deaths']['direction'] ) {
				case 'higher':
					$love_take['deaths'] = __( 'A loved show is no less likely to kill a queer character. In fact, queer death is slightly more likely. Being loved is not the same as being safe.', 'lwtv' );
					break;
				case 'lower':
					$love_take['deaths'] = ( ( $love_versus['deaths']['rest_pct'] - $love_versus['deaths']['loved_pct'] ) > 10 )
						? __( 'Loved shows are the clearly less deadly group.', 'lwtv' )
						: __( 'Loved shows are the less deadly group, though not by much. Read it as a small edge, not a promise.', 'lwtv' );
					break;
				default:
					$love_take['deaths'] = __( 'Loved shows kill queer characters at about the same rate as everything else. The flag says nothing either way.', 'lwtv' );
					break;
			}

			// Row definitions: counts on the 0–10 axis, shares on 0–100%.
			$love_rows = array(
				'chars'  => array(
					'name'      => __( 'Queer characters', 'lwtv' ),
					'sub'       => __( 'Average per show', 'lwtv' ),
					'loved_fig' => number_format_i18n( $love_versus['chars']['loved'], 1 ),
					'rest_fig'  => number_format_i18n( $love_versus['chars']['rest'], 1 ),
					'loved_w'   => min( 100, $love_versus['chars']['loved'] * 10 ),
					'rest_w'    => min( 100, $love_versus['chars']['rest'] * 10 ),
				),
				'actors' => array(
					'name'      => __( 'Queer actors', 'lwtv' ),
					'sub'       => __( 'Average per show', 'lwtv' ),
					'loved_fig' => number_format_i18n( $love_versus['actors']['loved'], 1 ),
					'rest_fig'  => number_format_i18n( $love_versus['actors']['rest'], 1 ),
					'loved_w'   => min( 100, $love_versus['actors']['loved'] * 10 ),
					'rest_w'    => min( 100, $love_versus['actors']['rest'] * 10 ),
				),
				'happy'  => array(
					'name'      => __( 'Happy ending', 'lwtv' ),
					'sub'       => __( 'Carry the trope', 'lwtv' ),
					/* translators: 1: loved shows with the trait, 2: loved total. */
					'loved_fig' => sprintf( __( '%1$s of %2$s', 'lwtv' ), number_format_i18n( $love_versus['happy']['loved_count'] ), number_format_i18n( $love_n ) ),
					'rest_fig'  => number_format_i18n( $love_versus['happy']['rest_pct'], 1 ) . '%',
					'loved_w'   => $love_versus['happy']['loved_pct'],
					'rest_w'    => $love_versus['happy']['rest_pct'],
				),
				'deaths' => array(
					'name'      => __( 'Kills a queer character', 'lwtv' ),
					'sub'       => __( 'At least one death', 'lwtv' ),
					/* translators: 1: loved shows with the trait, 2: loved total. */
					'loved_fig' => sprintf( __( '%1$s of %2$s', 'lwtv' ), number_format_i18n( $love_versus['deaths']['loved_count'] ), number_format_i18n( $love_n ) ),
					'rest_fig'  => number_format_i18n( $love_versus['deaths']['rest_pct'], 1 ) . '%',
					'loved_w'   => $love_versus['deaths']['loved_pct'],
					'rest_w'    => $love_versus['deaths']['rest_pct'],
				),
			);
			?>
			<div class="lwtv-love-vs">
				<?php
				foreach ( $love_rows as $love_key => $love_row ) :
					$love_is_share = in_array( $love_key, array( 'happy', 'deaths' ), true );
					?>
					<div class="lwtv-love-metric<?php echo ( 'happy' === $love_key ) ? ' lwtv-love-metric--axisbreak' : ''; ?>">
						<div class="lwtv-love-metric-label">
							<span class="lwtv-love-metric-name lwtv-love-metric-name--<?php echo esc_attr( $love_key ); ?>"><?php echo esc_html( $love_row['name'] ); ?></span>
							<span class="lwtv-love-metric-sub"><?php echo esc_html( $love_row['sub'] ); ?></span>
						</div>
						<div class="lwtv-love-metric-bars">
							<div class="lwtv-love-bar-row<?php echo $love_is_share ? ' lwtv-love-bar-row--share' : ''; ?>">
								<span class="lwtv-love-track" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: metric name, 2: loved figure. */ __( '%1$s, the loved shows: %2$s.', 'lwtv' ), $love_row['name'], $love_row['loved_fig'] ) ); ?>">
									<span class="lwtv-love-fill lwtv-love-fill--<?php echo esc_attr( $love_key ); ?>" style="width:<?php echo esc_attr( (string) $love_row['loved_w'] ); ?>%" aria-hidden="true"></span>
								</span>
								<span class="lwtv-love-fig"><?php echo esc_html( $love_row['loved_fig'] ); ?></span>
							</div>
							<div class="lwtv-love-bar-row<?php echo $love_is_share ? ' lwtv-love-bar-row--share' : ''; ?>">
								<span class="lwtv-love-track" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: metric name, 2: everything-else figure. */ __( '%1$s, everything else: %2$s.', 'lwtv' ), $love_row['name'], $love_row['rest_fig'] ) ); ?>">
									<span class="lwtv-love-fill lwtv-love-fill--rest" style="width:<?php echo esc_attr( (string) $love_row['rest_w'] ); ?>%" aria-hidden="true"></span>
								</span>
								<span class="lwtv-love-fig lwtv-love-fig--rest"><?php echo esc_html( $love_row['rest_fig'] ); ?></span>
							</div>
							<p class="lwtv-love-takeaway"><?php echo esc_html( $love_take[ $love_key ] ); ?></p>
						</div>
					</div>
					<?php if ( 'actors' === $love_key ) : ?>
						<div class="lwtv-love-ticks" aria-hidden="true">
							<span></span>
							<span class="lwtv-love-ticks-row"><span>0</span><span>2</span><span>4</span><span>6</span><span>8</span><span>10</span></span>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
				<div class="lwtv-love-ticks lwtv-love-ticks--share" aria-hidden="true">
					<span></span>
					<span class="lwtv-love-ticks-row"><span>0</span><span>25%</span><span>50%</span><span>75%</span><span>100%</span></span>
				</div>
			</div>
		<?php endif; ?>
	</div>

</div>

<div class="lwtv-love-roster bg-light">
	<div class="lwtv-love-roster-head">
		<div>
			<span class="lwtv-love-eyebrow"><?php esc_html_e( 'The Roster', 'lwtv' ); ?></span>
			<h3 class="lwtv-love-h3">
				<?php
				printf(
					/* translators: %s: number of loved shows. */
					esc_html__( 'All %s, in one place', 'lwtv' ),
					esc_html( number_format_i18n( $love_n ) )
				);
				?>
			</h3>
		</div>
		<p class="lwtv-love-roster-note">
			<?php
			if ( $love_cohort['gold'] > 0 ) {
				esc_html_e( 'A star marks the shows that also carry a gold star. Sorted newest premiere first.', 'lwtv' );
			} else {
				esc_html_e( 'Sorted newest premiere first.', 'lwtv' );
			}
			?>
		</p>
	</div>
	<div class="lwtv-love-grid">
		<?php
		foreach ( $love_roster as $love_show ) :
			if ( $love_show['airing'] ) {
				/* translators: %s: the year the show started airing. */
				$love_years = sprintf( __( 'Since %s', 'lwtv' ), (string) $love_show['start'] );
			} elseif ( $love_show['start'] > 0 && $love_show['finish'] > $love_show['start'] ) {
				$love_years = $love_show['start'] . '–' . $love_show['finish'];
			} elseif ( $love_show['start'] > 0 ) {
				$love_years = (string) $love_show['start'];
			} else {
				$love_years = __( 'Date unknown', 'lwtv' );
			}

			$love_bits = array();
			/* translators: %s: number of queer characters. */
			$love_bits[] = sprintf( _n( '%s queer character', '%s queer characters', $love_show['chars'], 'lwtv' ), number_format_i18n( $love_show['chars'] ) );
			if ( 0 === $love_show['dead'] ) {
				$love_bits[] = __( 'no deaths', 'lwtv' );
			} else {
				/* translators: %s: number of character deaths. */
				$love_bits[] = sprintf( _n( '%s death', '%s deaths', $love_show['dead'], 'lwtv' ), number_format_i18n( $love_show['dead'] ) );
			}
			?>
			<div class="lwtv-love-card">
				<div class="lwtv-love-card-top">
					<a class="lwtv-love-card-title" href="<?php echo esc_url( $love_show['url'] ); ?>"><?php echo esc_html( $love_show['title'] ); ?></a>
					<?php if ( $love_show['gold'] ) : ?>
						<span class="lwtv-love-card-star" role="img" aria-label="<?php esc_attr_e( 'Also a gold star show', 'lwtv' ); ?>"><?php echo lwtv_plugin()->get_symbolicon( svg: 'star.svg', icon: 'svg-star', max_size: '14' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<?php endif; ?>
				</div>
				<span class="lwtv-love-card-years"><?php echo esc_html( $love_years ); ?></span>
				<span class="lwtv-love-card-meta"><?php echo esc_html( implode( ' · ', $love_bits ) ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>
</div>
