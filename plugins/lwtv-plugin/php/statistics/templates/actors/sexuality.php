<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Actors → Sexuality: donut (grey straight + queer ramp + unknown), a
 * pullstats banner, "The Overlap" callout (actors marked Straight who are
 * still queer once gender/pronouns/romantic orientation are counted), and
 * a most-prolific-actor-per-orientation statcard grid.
 *
 * No decade trend or Firsts list here, unlike Characters' Gender/Sexuality
 * pages — there's no data path from an actor to which specific years they
 * were active (that lives on the character's show-group repeater, and a
 * recast actor has no per-year attribution — see Build_Actors::
 * generate_active_this_year()'s docblock for the same wrinkle).
 *
 * @package LezWatch.TV
 *
 * @var int $actor_count
 */

use LWTV\Statistics\Build\Actors as Build_Actors;

$sex_raw   = lwtv_plugin()->generate_actors_statistics( 'array', 'sexuality' );
$sex_data  = ( is_array( $sex_raw ) && ! empty( $sex_raw ) ) ? (array) reset( $sex_raw ) : array();
$sex_total = (int) $actor_count;

$sex_straight = isset( $sex_data['heterosexual'] ) ? (int) $sex_data['heterosexual']['count'] : 0;
$sex_unknown  = isset( $sex_data['unknown'] ) ? (int) $sex_data['unknown']['count'] : 0;
unset( $sex_data['heterosexual'], $sex_data['unknown'] );

// Remaining = queer orientations; rank and ramp the top 4, fold the rest into "Other".
// Amber, not pink — matches the "actors" family color used by the
// pullstats/callout/prolific cards added below, so the whole page reads as
// one color family instead of the donut being a leftover from before those
// existed.
uasort( $sex_data, fn( $a, $b ) => (int) $b['count'] <=> (int) $a['count'] );
$sex_ramp     = array( 'amber', 'medamber', 'midamber', 'paleamber' );
$sex_segments = array(
	array(
		'label' => __( 'Straight', 'lwtv' ),
		'count' => $sex_straight,
		'pct'   => ( $sex_total > 0 ) ? round( ( $sex_straight / $sex_total ) * 100, 1 ) : 0,
		'class' => 'grey',
	),
);
$sex_named    = $sex_straight + $sex_unknown;
$sex_i        = 0;
foreach ( $sex_data as $sex_row ) {
	if ( $sex_i >= 4 || (int) $sex_row['count'] <= 0 ) {
		break;
	}
	$sex_count      = (int) $sex_row['count'];
	$sex_named     += $sex_count;
	$sex_segments[] = array(
		'label' => $sex_row['name'],
		'count' => $sex_count,
		'pct'   => ( $sex_total > 0 ) ? round( ( $sex_count / $sex_total ) * 100, 1 ) : 0,
		'class' => $sex_ramp[ $sex_i ],
	);
	++$sex_i;
}
$sex_other = max( 0, $sex_total - $sex_named );
if ( $sex_other > 0 ) {
	$sex_segments[] = array(
		'label' => __( 'Other', 'lwtv' ),
		'count' => $sex_other,
		'pct'   => ( $sex_total > 0 ) ? round( ( $sex_other / $sex_total ) * 100, 1 ) : 0,
		'class' => 'ltamber',
	);
}
$sex_segments[] = array(
	'label' => __( 'Unknown', 'lwtv' ),
	'count' => $sex_unknown,
	'pct'   => ( $sex_total > 0 ) ? round( ( $sex_unknown / $sex_total ) * 100, 1 ) : 0,
	'class' => 'bordergrey',
);

// Headline from the leading slice.
$sex_lead = $sex_segments[0] ?? array( 'pct' => 0 );
$sex_in10 = ( $sex_lead['pct'] > 0 ) ? (int) round( $sex_lead['pct'] / 10 ) : 0;

// translators: %1$1d is the X-in-10 number for the largest Gender Demographic, %2$2s is the name of the gender.
$headline = ( $sex_in10 > 0 ) ? sprintf( __( '%1$1d in 10 actors are %2$2s', 'lwtv' ), $sex_in10, lcfirst( $sex_lead['label'] ) ) : __( 'Sexuality Breakdown:', 'lwtv' );

$donut = array(
	'segments'    => $sex_segments,
	'center'      => $sex_total,
	'center_sub'  => __( 'actors', 'lwtv' ),
	'eyebrow'     => __( 'Actor Sexual Orientation', 'lwtv' ),
	'headline'    => $headline,
	'description' => __( 'Queer roles are still mostly played by straight actors.', 'lwtv' ),
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';

// ---- Pullstats: orientations tracked, non-straight share, leading non-straight orientation ----
// "Distinct orientations tracked" counts Straight back in (it's a real
// tracked orientation, just excluded from the ramp above) but leaves out
// Unknown, the same way "None" is excluded from cliché counts elsewhere —
// it isn't a real orientation.
$sex_tracked_count = count( array_filter( $sex_data, static fn( $row ) => (int) $row['count'] > 0 ) ) + ( ( $sex_straight > 0 ) ? 1 : 0 );

$sex_pullstats = array();

if ( $sex_tracked_count > 0 ) {
	$sex_pullstats[] = array(
		'icon'   => 'tag.svg',
		'number' => number_format_i18n( $sex_tracked_count ),
		'label'  => __( 'Distinct sexual orientations tracked.', 'lwtv' ),
	);
}

if ( $sex_total > 0 ) {
	$sex_nonstraight_pct = round( max( 0, $sex_total - $sex_straight - $sex_unknown ) / $sex_total * 100, 1 );
	$sex_pullstats[]     = array(
		'icon'   => 'chart-pie.svg',
		/* translators: %s: percentage of actors with an orientation other than straight (one decimal). */
		'number' => sprintf( __( '%s%%', 'lwtv' ), number_format_i18n( $sex_nonstraight_pct, 1 ) ),
		'label'  => __( 'Percentage with an orientation other than straight.', 'lwtv' ),
	);
}

// The single highest-count non-straight orientation: segments[0] is always
// forced to "Straight" above regardless of rank, so segments[1] (when it
// isn't the Other/Unknown catch-alls) is the real leader.
if ( isset( $sex_segments[1] ) && ! in_array( $sex_segments[1]['label'], array( __( 'Other', 'lwtv' ), __( 'Unknown', 'lwtv' ) ), true ) ) {
	$sex_pullstats[] = array(
		'icon'   => 'heart.svg',
		/* translators: %s is the name of the segment */
		'number' => sprintf( __( '%s%%', 'lwtv' ), number_format_i18n( $sex_segments[1]['pct'], 1 ) ),
		/* translators: %s: the leading non-straight orientation's name. */
		'label'  => sprintf( __( 'Are %s, the most common non-straight orientation.', 'lwtv' ), lcfirst( $sex_segments[1]['label'] ) ),
	);
}

if ( ! empty( $sex_pullstats ) ) :
	?>
	<div class="lwtv-pullstats lwtv-pullstats--three lwtv-statcards lwtv-bars--actors">
		<?php foreach ( $sex_pullstats as $sex_pullstat ) : ?>
			<div class="lwtv-statcard">
				<span class="lwtv-statcard-icon">
					<?php echo lwtv_plugin()->get_symbolicon( svg: $sex_pullstat['icon'], icon: 'svg-' . str_replace( '.svg', '', $sex_pullstat['icon'] ), max_size: '18' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<span class="lwtv-statcard-number"><?php echo esc_html( $sex_pullstat['number'] ); ?></span>
				<p class="lwtv-statcard-label"><?php echo esc_html( $sex_pullstat['label'] ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
endif;

// ---- The Overlap: actors marked Straight who are still queer overall ----
$sex_gap = ( new Build_Actors() )->generate_straight_queer_gap();
if ( ! empty( $sex_gap['straight_total'] ) && $sex_gap['queer_anyway'] > 0 ) :
	$sex_gap_pct   = round( ( $sex_gap['queer_anyway'] / $sex_gap['straight_total'] ) * 100, 1 );
	$lwtv_callouts = array(
		array(
			'label' => __( 'The Overlap', 'lwtv' ),
			'icon'  => 'user-heart.svg',
			/* translators: %s: percentage of Straight-tagged actors who are still queer overall (one decimal). */
			'text'  => sprintf( __( '%s%% of actors marked Straight by orientation are still counted as queer once gender, pronouns, or romantic orientation are factored in.', 'lwtv' ), number_format_i18n( $sex_gap_pct, 1 ) ),
		),
	);
	// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
	include plugin_dir_path( __DIR__ ) . 'partials/callouts.php';
endif;

// ---- Most prolific actor per orientation ----
$sex_prolific = ( new Build_Actors() )->generate_prolific_by_orientation();
if ( ! empty( $sex_prolific ) ) :
	// Same display order the donut/ramp above uses: Straight first, then
	// every other tracked orientation in $sex_data's existing
	// count-descending order. Slugs with no prolific entry (no actor of
	// that orientation has any characters) are simply skipped below.
	$sex_prolific_slugs = array_merge( array( 'heterosexual' ), array_keys( $sex_data ) );
	?>
	<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Most Prolific by Orientation', 'lwtv' ); ?></p>
	<div class="lwtv-pullstats lwtv-pullstats--three lwtv-statcards lwtv-bars--actors">
		<?php
		foreach ( $sex_prolific_slugs as $sex_prolific_slug ) :
			if ( ! isset( $sex_prolific[ $sex_prolific_slug ] ) ) :
				continue;
			endif;
			$sex_prolific_row = $sex_prolific[ $sex_prolific_slug ];
			?>
			<div class="lwtv-statcard lwtv-statcard--firsts">
				<span class="lwtv-statcard-icon">
					<?php echo lwtv_plugin()->get_symbolicon( svg: 'trophy.svg', icon: 'svg-trophy', max_size: '18' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<span class="lwtv-statcard-number"><?php echo esc_html( number_format_i18n( $sex_prolific_row['count'] ) ); ?></span>
				<p class="lwtv-statcard-label">
					<?php echo esc_html( $sex_prolific_row['term_name'] ); ?>:
					<a href="<?php echo esc_url( $sex_prolific_row['url'] ); ?>"><?php echo esc_html( $sex_prolific_row['name'] ); ?></a>
				</p>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
