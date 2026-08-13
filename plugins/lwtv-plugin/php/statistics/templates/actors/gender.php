<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Actors → Gender: donut (grey cisgender + trans/non-binary amber ramp), a
 * pullstats banner, "The Overlap" callout (actors marked Cisgender who are
 * still queer once sexuality/pronouns/romantic orientation are counted), and
 * a most-prolific-actor-per-gender-identity statcard grid — mirrors the
 * Actors → Sexuality page (see that template's docblock for why there's no
 * decade trend or Firsts list here: no data path from an actor to which
 * specific years they were active).
 *
 * @package LezWatch.TV
 *
 * @var int $actor_count
 */

use LWTV\Statistics\Build\Actors as Build_Actors;

$gen_raw   = lwtv_plugin()->generate_actors_statistics( 'array', 'gender' );
$gen_data  = ( is_array( $gen_raw ) && ! empty( $gen_raw ) ) ? (array) reset( $gen_raw ) : array();
$gen_total = (int) $actor_count;

$gen_cis_slugs = array( 'cis-woman', 'cis-man', 'cisgender' );
$gen_cis       = 0;
foreach ( $gen_cis_slugs as $gen_cis_slug ) {
	if ( isset( $gen_data[ $gen_cis_slug ] ) ) {
		$gen_cis += (int) $gen_data[ $gen_cis_slug ]['count'];
		unset( $gen_data[ $gen_cis_slug ] );
	}
}
$gen_unknown = isset( $gen_data['unknown'] ) ? (int) $gen_data['unknown']['count'] : 0;
unset( $gen_data['unknown'] );

// Remaining = trans / non-binary / other tracked identities; rank and ramp
// the top 4, fold the rest into "Other". Amber, not pink — matches the
// "actors" family color used by the pullstats/callout/prolific cards added
// below, so the whole page reads as one color family, same fix already
// applied to the Sexuality donut.
uasort( $gen_data, fn( $a, $b ) => (int) $b['count'] <=> (int) $a['count'] );
$gen_ramp     = array( 'amber', 'medamber', 'midamber', 'paleamber' );
$gen_segments = array(
	array(
		'label' => __( 'Cisgender', 'lwtv' ),
		'count' => $gen_cis,
		'pct'   => ( $gen_total > 0 ) ? round( ( $gen_cis / $gen_total ) * 100, 1 ) : 0,
		'class' => 'grey',
	),
);
$gen_named    = $gen_cis + $gen_unknown;
$gen_i        = 0;
foreach ( $gen_data as $gen_row ) {
	if ( $gen_i >= 4 || (int) $gen_row['count'] <= 0 ) {
		break;
	}
	$gen_count      = (int) $gen_row['count'];
	$gen_named     += $gen_count;
	$gen_segments[] = array(
		'label' => $gen_row['name'],
		'count' => $gen_count,
		'pct'   => ( $gen_total > 0 ) ? round( ( $gen_count / $gen_total ) * 100, 1 ) : 0,
		'class' => $gen_ramp[ $gen_i ],
	);
	++$gen_i;
}
$gen_other = max( 0, $gen_total - $gen_named );
if ( $gen_other > 0 ) {
	$gen_segments[] = array(
		'label' => __( 'Other', 'lwtv' ),
		'count' => $gen_other,
		'pct'   => ( $gen_total > 0 ) ? round( ( $gen_other / $gen_total ) * 100, 1 ) : 0,
		'class' => 'ltamber',
	);
}
$gen_segments[] = array(
	'label' => __( 'Unknown', 'lwtv' ),
	'count' => $gen_unknown,
	'pct'   => ( $gen_total > 0 ) ? round( ( $gen_unknown / $gen_total ) * 100, 1 ) : 0,
	'class' => 'bordergrey',
);

// Headline from the leading slice.
$gen_lead = $gen_segments[0] ?? array( 'pct' => 0 );
$gen_in10 = ( $gen_lead['pct'] > 0 ) ? (int) round( $gen_lead['pct'] / 10 ) : 0;

// translators: %1$1d is the X-in-10 number for the largest Gender Demographic, %2$2s is the name of the gender.
$headline = ( $gen_in10 > 0 ) ? sprintf( __( '%1$1d in 10 actors are %2$2s', 'lwtv' ), $gen_in10, lcfirst( $gen_lead['label'] ) ) : __( 'Gender breakdown', 'lwtv' );

$donut = array(
	'segments'    => $gen_segments,
	'center'      => $gen_total,
	'center_sub'  => __( 'actors', 'lwtv' ),
	'eyebrow'     => __( 'Actor Gender Identity', 'lwtv' ),
	'headline'    => $headline,
	'description' => __( 'Trans and non-binary actors remain a small share of the total.', 'lwtv' ),
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';

// ---- Pullstats: identities tracked, non-cis share, leading non-cis identity ----
// "Distinct gender identities tracked" counts Cisgender back in (it's a
// real tracked identity, just excluded from the ramp above as one merged
// bucket) but leaves out Unknown, same convention Sexuality's pullstats use.
$gen_tracked_count = count( array_filter( $gen_data, static fn( $row ) => (int) $row['count'] > 0 ) ) + ( ( $gen_cis > 0 ) ? 1 : 0 );

$gen_pullstats = array();

if ( $gen_tracked_count > 0 ) {
	$gen_pullstats[] = array(
		'icon'   => 'tag.svg',
		'number' => number_format_i18n( $gen_tracked_count ),
		'label'  => __( 'Distinct gender identities tracked.', 'lwtv' ),
	);
}

if ( $gen_total > 0 ) {
	$gen_noncis_pct  = round( max( 0, $gen_total - $gen_cis - $gen_unknown ) / $gen_total * 100, 1 );
	$gen_pullstats[] = array(
		'icon'   => 'chart-pie.svg',
		/* translators: %s: percentage of actors with a gender identity other than cisgender (one decimal). */
		'number' => sprintf( __( '%s%%', 'lwtv' ), number_format_i18n( $gen_noncis_pct, 1 ) ),
		'label'  => __( 'Percentage with a gender identity other than cisgender.', 'lwtv' ),
	);
}

// The single highest-count non-cis identity: segments[0] is always forced to
// "Cisgender" above regardless of rank, so segments[1] (when it isn't the
// Other/Unknown catch-alls) is the real leader.
if ( isset( $gen_segments[1] ) && ! in_array( $gen_segments[1]['label'], array( __( 'Other', 'lwtv' ), __( 'Unknown', 'lwtv' ) ), true ) ) {
	$gen_pullstats[] = array(
		'icon'   => 'heart.svg',
		/* translators: %s is the leading non-cisgender identity's share. */
		'number' => sprintf( __( '%s%%', 'lwtv' ), number_format_i18n( $gen_segments[1]['pct'], 1 ) ),
		/* translators: %s: the leading non-cisgender identity's name. */
		'label'  => sprintf( __( 'Are %s, the most common non-cisgender identity.', 'lwtv' ), lcfirst( $gen_segments[1]['label'] ) ),
	);
}

if ( ! empty( $gen_pullstats ) ) :
	?>
	<div class="lwtv-pullstats lwtv-pullstats--three lwtv-statcards lwtv-bars--actors">
		<?php foreach ( $gen_pullstats as $gen_pullstat ) : ?>
			<div class="lwtv-statcard">
				<span class="lwtv-statcard-icon">
					<?php echo lwtv_plugin()->get_symbolicon( svg: $gen_pullstat['icon'], icon: 'svg-' . str_replace( '.svg', '', $gen_pullstat['icon'] ), max_size: '18' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<span class="lwtv-statcard-number"><?php echo esc_html( $gen_pullstat['number'] ); ?></span>
				<p class="lwtv-statcard-label"><?php echo esc_html( $gen_pullstat['label'] ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
endif;

// ---- The Overlap: actors marked Cisgender who are still queer overall ----
$gen_gap = ( new Build_Actors() )->generate_cis_queer_gap();
if ( ! empty( $gen_gap['cis_total'] ) && $gen_gap['queer_anyway'] > 0 ) :
	$gen_gap_pct   = round( ( $gen_gap['queer_anyway'] / $gen_gap['cis_total'] ) * 100, 1 );
	$lwtv_callouts = array(
		array(
			'label' => __( 'The Overlap', 'lwtv' ),
			'icon'  => 'user-heart.svg',
			/* translators: %s: percentage of Cisgender-tagged actors who are still queer overall (one decimal). */
			'text'  => sprintf( __( '%s%% of actors marked Cisgender by gender identity are still counted as queer once sexuality, pronouns, or romantic orientation are factored in.', 'lwtv' ), number_format_i18n( $gen_gap_pct, 1 ) ),
		),
	);
	// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
	include plugin_dir_path( __DIR__ ) . 'partials/callouts.php';
endif;

// ---- Most prolific actor per gender identity ----
// generate_prolific_by_gender() returns one entry per raw taxonomy term
// (cis-woman/cis-man/cisgender kept separate); merge those three into a
// single "Cisgender" card here, matching the donut's own merged bucket. The
// max of a union's subgroup maxes is always the union's max, so picking
// whichever of the three per-slug leaders has the highest count is a safe,
// correct merge without re-querying.
$gen_prolific_raw = ( new Build_Actors() )->generate_prolific_by_gender();
$gen_prolific     = array();
$gen_cis_leader   = null;
foreach ( $gen_cis_slugs as $gen_cis_slug ) {
	if ( ! isset( $gen_prolific_raw[ $gen_cis_slug ] ) ) {
		continue;
	}
	if ( null === $gen_cis_leader || $gen_prolific_raw[ $gen_cis_slug ]['count'] > $gen_cis_leader['count'] ) {
		$gen_cis_leader = $gen_prolific_raw[ $gen_cis_slug ];
	}
}
if ( null !== $gen_cis_leader ) {
	$gen_cis_leader['term_name'] = __( 'Cisgender', 'lwtv' );
	$gen_prolific['cisgender']   = $gen_cis_leader;
}
foreach ( $gen_prolific_raw as $gen_prolific_slug => $gen_prolific_row ) {
	if ( in_array( $gen_prolific_slug, $gen_cis_slugs, true ) ) {
		continue;
	}
	$gen_prolific[ $gen_prolific_slug ] = $gen_prolific_row;
}

if ( ! empty( $gen_prolific ) ) :
	// Same display order the donut/ramp above uses: Cisgender first, then
	// every other tracked identity in $gen_data's existing count-descending
	// order. Slugs with no prolific entry are simply skipped below.
	$gen_prolific_slugs = array_merge( array( 'cisgender' ), array_keys( $gen_data ) );
	?>
	<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Most Prolific by Gender Identity', 'lwtv' ); ?></p>
	<div class="lwtv-pullstats lwtv-pullstats--three lwtv-statcards lwtv-bars--actors">
		<?php
		foreach ( $gen_prolific_slugs as $gen_prolific_slug ) :
			if ( ! isset( $gen_prolific[ $gen_prolific_slug ] ) ) :
				continue;
			endif;
			$gen_prolific_row = $gen_prolific[ $gen_prolific_slug ];
			?>
			<div class="lwtv-statcard lwtv-statcard--firsts">
				<span class="lwtv-statcard-icon">
					<?php echo lwtv_plugin()->get_symbolicon( svg: 'trophy.svg', icon: 'svg-trophy', max_size: '18' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<span class="lwtv-statcard-number"><?php echo esc_html( number_format_i18n( $gen_prolific_row['count'] ) ); ?></span>
				<p class="lwtv-statcard-label">
					<?php echo esc_html( $gen_prolific_row['term_name'] ); ?>:
					<a href="<?php echo esc_url( $gen_prolific_row['url'] ); ?>"><?php echo esc_html( $gen_prolific_row['name'] ); ?></a>
				</p>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
