<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shared Sexuality / Gender / Formats / Tropes facet for a single Nation or
 * Station page — donut (via Donut_Segments) + a pullstats banner + a
 * facet-specific "Most Prolific"-style section (see Taxonomy_Profile's
 * class docblock for why each facet's version means something different).
 *
 * Replaces four near-identical ~35-line blocks that used to be duplicated
 * across nations/single.php and stations/single.php — one include per
 * facet, parameterized by taxonomy instead of copy-pasted per page.
 *
 * @package LezWatch.TV
 *
 * @var string $facet_view       'sexuality' | 'gender' | 'formats' | 'tropes'.
 * @var string $facet_taxonomy   'lez_country' | 'lez_stations'.
 * @var string $facet_term_slug  Nation or station term slug (unprefixed).
 * @var array  $facet_raw        Raw [ { 'name', 'count', 'url'[, 'slug'] } ] list
 *                                already fetched via generate_nation_statistics()
 *                                / generate_station_statistics().
 */

use LWTV\Statistics\Build\Donut_Segments;
use LWTV\Statistics\Build\Taxonomy_Profile;

$facet_list = ( is_array( $facet_raw ) ) ? $facet_raw : array();

// Per-facet config: donut ramp depth, the item pulled into its own grey
// slot first (Gender's "Cisgender"), and the copy around it.
switch ( $facet_view ) {
	case 'gender':
		$facet_topn        = 4;
		$facet_grey_match  = 'cisgender';
		$facet_eyebrow     = __( 'Character Gender', 'lwtv' );
		$facet_headline    = __( 'Gender identities', 'lwtv' );
		$facet_sub         = __( 'characters', 'lwtv' );
		$facet_tracked_str = __( 'Distinct gender identities tracked.', 'lwtv' );
		break;
	case 'formats':
		$facet_topn        = 5;
		$facet_grey_match  = '';
		$facet_eyebrow     = __( 'Show Formats', 'lwtv' );
		$facet_headline    = __( 'How these shows are made', 'lwtv' );
		$facet_sub         = __( 'shows', 'lwtv' );
		$facet_tracked_str = __( 'Distinct formats tracked.', 'lwtv' );
		break;
	case 'tropes':
		$facet_topn        = 5;
		$facet_grey_match  = '';
		$facet_eyebrow     = __( 'Common Tropes', 'lwtv' );
		$facet_headline    = __( 'Most common tropes', 'lwtv' );
		$facet_sub         = __( 'tagged appearances', 'lwtv' );
		$facet_tracked_str = __( 'Distinct tropes tracked.', 'lwtv' );
		break;
	default: // sexuality.
		$facet_topn        = 5;
		$facet_grey_match  = '';
		$facet_eyebrow     = __( 'Character Sexual Orientation', 'lwtv' );
		$facet_headline    = __( 'Sexual orientations', 'lwtv' );
		$facet_sub         = __( 'characters', 'lwtv' );
		$facet_tracked_str = __( 'Distinct sexual orientations tracked.', 'lwtv' );
		break;
}

list( $facet_segments, $facet_total ) = Donut_Segments::build( $facet_list, $facet_topn, $facet_grey_match );

// Tropes' segments are shares of total *taggings*, not of total shows — a
// show can carry several tropes at once (see Taxonomy_Profile's class
// docblock), so this total is intentionally the sum of tag instances, not
// the nation/station's show count.
$facet_description = ( 'tropes' === $facet_view )
	? __( 'Shows can carry several tropes, so this is a share of total tags, not of the shows themselves.', 'lwtv' )
	: '';

$donut = array(
	'segments'    => $facet_segments,
	'center'      => $facet_total,
	'center_sub'  => $facet_sub,
	'eyebrow'     => $facet_eyebrow,
	'headline'    => $facet_headline,
	'description' => $facet_description,
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include __DIR__ . '/donut.php';

// ---- Pullstats: distinct terms tracked, leading term's share, (tropes only) most trope-heavy show ----
$facet_profile = new Taxonomy_Profile( $facet_taxonomy, $facet_term_slug );

$facet_tracked_count = count( array_filter( $facet_list, static fn( $row ) => (int) $row['count'] > 0 ) );

$facet_pullstats = array();

if ( $facet_tracked_count > 0 ) {
	$facet_pullstats[] = array(
		'icon'   => 'tag.svg',
		'number' => number_format_i18n( $facet_tracked_count ),
		'label'  => $facet_tracked_str,
	);
}

// When a grey_match forced a segment (e.g. Gender's "Cisgender") into slot
// 0 regardless of rank, that slot is never the interesting fact — skip to
// slot 1 for the real leader, same as Actors' Sexuality/Gender pages do for
// their own forced-first Straight/Cisgender segment.
$facet_lead_index = ( '' !== $facet_grey_match ) ? 1 : 0;
$facet_lead       = $facet_segments[ $facet_lead_index ] ?? null;

if ( null !== $facet_lead && ! in_array( $facet_lead['label'], array( __( 'Other', 'lwtv' ) ), true ) ) {
	$facet_pullstats[] = array(
		'icon'   => 'chart-pie.svg',
		/* translators: %s: the leading segment's share of the total (one decimal). */
		'number' => sprintf( __( '%s%%', 'lwtv' ), number_format_i18n( $facet_lead['pct'], 1 ) ),
		/* translators: %s: the leading segment's name. */
		'label'  => sprintf( __( 'Are %s, the most common.', 'lwtv' ), lcfirst( $facet_lead['label'] ) ),
	);
}

// Tropes only has two pullstats above, so its "Most Prolific" analog folds
// into this same row as a third, linked card instead of a separate
// full-width callout underneath — with just two other cards, a lone callout
// below wasted the row a third column would otherwise fill.
if ( 'tropes' === $facet_view ) {
	$facet_trope_leader = $facet_profile->generate_most_trope_heavy_show();
	if ( ! empty( $facet_trope_leader ) ) {
		$facet_pullstats[] = array(
			'icon'   => 'tag.svg',
			'number' => number_format_i18n( $facet_trope_leader['count'] ),
			'label'  => __( 'Most trope-heavy show:', 'lwtv' ),
			'url'    => $facet_trope_leader['url'],
			'name'   => $facet_trope_leader['name'],
		);
	}
}

if ( ! empty( $facet_pullstats ) ) :
	?>
	<div class="lwtv-pullstats lwtv-pullstats--three lwtv-statcards lwtv-bars--geo">
		<?php foreach ( $facet_pullstats as $facet_pullstat ) : ?>
			<div class="lwtv-statcard<?php echo isset( $facet_pullstat['url'] ) ? ' lwtv-statcard--firsts' : ''; ?>">
				<span class="lwtv-statcard-icon">
					<?php echo lwtv_plugin()->get_symbolicon( svg: $facet_pullstat['icon'], icon: 'svg-' . str_replace( '.svg', '', $facet_pullstat['icon'] ), max_size: '18' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<span class="lwtv-statcard-number"><?php echo esc_html( $facet_pullstat['number'] ); ?></span>
				<p class="lwtv-statcard-label">
					<?php echo esc_html( $facet_pullstat['label'] ); ?>
					<?php if ( isset( $facet_pullstat['url'] ) ) : ?>
						<a href="<?php echo esc_url( $facet_pullstat['url'] ); ?>"><?php echo esc_html( $facet_pullstat['name'] ); ?></a>
					<?php endif; ?>
				</p>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
endif;

// ---- Most Prolific — a different shape per facet; see Taxonomy_Profile's docblock ----
// Tropes has no branch here — it was folded into the pullstats row above.
if ( 'formats' === $facet_view ) {
	$facet_prolific = $facet_profile->generate_top_rated_by_format();
	if ( ! empty( $facet_prolific ) ) {
		$facet_prolific_slugs = array_keys( $facet_prolific );
		?>
		<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Top-Rated by Format', 'lwtv' ); ?></p>
		<div class="lwtv-pullstats lwtv-pullstats--three lwtv-statcards lwtv-bars--geo">
			<?php foreach ( $facet_prolific_slugs as $facet_prolific_slug ) : ?>
				<?php $facet_prolific_row = $facet_prolific[ $facet_prolific_slug ]; ?>
				<div class="lwtv-statcard lwtv-statcard--firsts">
					<span class="lwtv-statcard-icon">
						<?php echo lwtv_plugin()->get_symbolicon( svg: 'star.svg', icon: 'svg-star', max_size: '18' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</span>
					<span class="lwtv-statcard-number"><?php echo esc_html( number_format_i18n( $facet_prolific_row['score'], 1 ) ); ?></span>
					<p class="lwtv-statcard-label">
						<?php echo esc_html( $facet_prolific_row['term_name'] ); ?>:
						<a href="<?php echo esc_url( $facet_prolific_row['url'] ); ?>"><?php echo esc_html( $facet_prolific_row['name'] ); ?></a>
					</p>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}
} elseif ( in_array( $facet_view, array( 'sexuality', 'gender' ), true ) ) {
	$facet_prolific = $facet_profile->generate_prolific_show( $facet_view );
	if ( ! empty( $facet_prolific ) ) {
		// Same display order the donut/ramp above uses: whatever order the
		// raw list already carries (count-descending, since $facet_list came
		// straight off generate_nation_statistics()/generate_station_statistics()).
		$facet_prolific_slugs = array();
		foreach ( $facet_list as $facet_row ) {
			if ( isset( $facet_row['slug'] ) ) {
				$facet_prolific_slugs[] = $facet_row['slug'];
			}
		}
		?>
		<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Most Prolific Show', 'lwtv' ); ?></p>
		<div class="lwtv-pullstats lwtv-pullstats--three lwtv-statcards lwtv-bars--geo">
			<?php
			foreach ( $facet_prolific_slugs as $facet_prolific_slug ) {
				if ( ! isset( $facet_prolific[ $facet_prolific_slug ] ) ) {
					continue;
				}
				$facet_prolific_row = $facet_prolific[ $facet_prolific_slug ];
				?>
				<div class="lwtv-statcard lwtv-statcard--firsts">
					<span class="lwtv-statcard-icon">
						<?php echo lwtv_plugin()->get_symbolicon( svg: 'trophy.svg', icon: 'svg-trophy', max_size: '18' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</span>
					<span class="lwtv-statcard-number"><?php echo esc_html( number_format_i18n( $facet_prolific_row['count'] ) ); ?></span>
					<p class="lwtv-statcard-label">
						<?php echo esc_html( $facet_prolific_row['term_name'] ); ?>:
						<a href="<?php echo esc_url( $facet_prolific_row['url'] ); ?>"><?php echo esc_html( $facet_prolific_row['name'] ); ?></a>
					</p>
				</div>
				<?php
			}
			?>
		</div>
		<?php
	}
}
