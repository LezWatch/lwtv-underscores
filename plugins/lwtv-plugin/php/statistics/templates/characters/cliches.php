<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Characters → Clichés: infographic rework (green, shared with Tropes' family
 * class), porting the Load waffle + Common Pairings pattern already shipped
 * for shows-side taxonomies onto lez_cliches. lez_cliches carries a real
 * "None" placeholder term (characters written with no cliché at all), so
 * Cliché Load/Pairings exclude it exactly the way Trope Load/Pairings
 * exclude lez_tropes' own "none" term — Cliché Load's "0" bucket is
 * therefore a real, meaningful bucket (characters with zero tracked
 * clichés), not absence-of-data, which is why its waffle ramp is a full
 * green gradient rather than Genre/Intersection Load's grey-for-zero
 * treatment.
 *
 * Layout mirrors Genres (no alignment-category split exists for clichés,
 * unlike Tropes' good/maybe/bad/ploy, so there's no second main-column
 * panel to stack): Cliché Load alone in the main (wide) column, Common
 * Pairings alone in the side (narrow) column, and the existing "All
 * Clichés, Ranked" list drops out of that grid to run full width below in
 * a 2-column card, same as .lwtv-genres-breakdown-wrap / .lwtv-tropes-
 * breakdown-wrap / .lwtv-inter-breakdown-wrap.
 *
 * The old average/median callout pair is gone entirely, replaced by a
 * 3-up pullstats banner (average, share with 3+, top pairing) — the same
 * treatment Genres uses in place of its own old callouts.
 *
 * @package LezWatch.TV
 *
 * @var int $character_count
 */

$cliches_raw  = lwtv_plugin()->generate_characters_statistics( 'array', 'cliches' );
$cliches_data = ( is_array( $cliches_raw ) && ! empty( $cliches_raw ) ) ? (array) reset( $cliches_raw ) : array();

// Shared WP glue: every published character's cliché slugs, one query,
// transient-cached. Feeds the pullstats row below, Cliché Load, the
// most-clichéd-character spotlight, and Common Pairings — same map shape
// Genre Load / Trope Load already use for their own taxonomies, just
// scoped to characters instead of shows.
$cliches_slug_map = ( new \LWTV\Statistics\Build\Taxonomy_Optimized() )->get_object_term_slug_map( 'post_type_characters', 'lez_cliches' );

// Cliché Load: how many (real) clichés a character carries, 0 to 4+.
// "none" is excluded — it marks the absence of a cliché, not one of its
// own. Confirmed against live data (audit script, 2026-08-10): every
// published character carries at least one lez_cliches row, so the "0"
// bucket below is exactly the "None"-tagged set, not a mix of that and
// untagged characters — worth labeling as such rather than a bare "0
// clichés", so the legend doesn't read like a data gap.
$cliches_distribution = \LWTV\Statistics\Build\Term_Count_Distribution::build( $cliches_slug_map, (int) $character_count, array( 'none' ) );
$cliches_cells        = \LWTV\Statistics\Build\Term_Count_Distribution::to_cells( $cliches_distribution, (int) $character_count, 100 );

$cliches_waffle_segments = array();
foreach ( $cliches_distribution as $cliches_dist_i => $cliches_dist_bucket ) {
	$cliches_waffle_segments[] = array(
		'count' => $cliches_cells[ $cliches_dist_i ],
		'class' => 'b' . $cliches_dist_i,
	);
}

// Real term name rather than a hardcoded "None" — picks up a rename for
// free and falls back to the slug on the same DB hiccup / WP_Error the
// pair-name lookups below already guard against.
$cliches_none_term = get_term_by( 'slug', 'none', 'lez_cliches' );
$cliches_none_name = ( $cliches_none_term instanceof \WP_Term ) ? $cliches_none_term->name : 'none';

$waffle = array(
	'segments' => $cliches_waffle_segments,
	'total'    => 100,
	'columns'  => 20,
	'radius'   => 6,
	/* translators: %s: the display name of the "None" cliché term. */
	'label'    => sprintf( __( 'Characters grouped by how many clichés each carries, from "%s" to four or more.', 'lwtv' ), $cliches_none_name ),
);

// Common Cliché Pairings: which two clichés most often appear on the same
// character. Counted once here and reused both for the pullstat headline
// (top 1) below and the full matchup panel (top 8) further down — same
// pure counting Genres/Tropes/Intersectionality already use, just aimed
// at lez_cliches. Deliberately unlinked: no FacetWP multi-value param is
// confirmed for lez_cliches (same conservative call already made for
// lez_genres/lez_tropes). Passed the raw slug map with no "none"
// pre-filter, same as Trope Pairings — "none" is designed to be exclusive
// of real clichés on the same character, so it never actually surfaces as
// a pairing partner in practice.
$cliches_pairs_counted = \LWTV\Statistics\Build\Intersection_Pairs::count_pairs( $cliches_slug_map );
$cliches_pairs         = \LWTV\Statistics\Build\Intersection_Pairs::top_pairs( $cliches_pairs_counted, 8, 2 );

$cliches_pair_names = array();
$cliches_pair_terms = get_terms(
	array(
		'taxonomy'   => 'lez_cliches',
		'hide_empty' => true,
	)
);
// get_terms() can hand back a WP_Error (unregistered taxonomy, DB hiccup);
// iterating that would fatal. The row builders below already fall back to
// slugs when a name is missing, so an empty map is safe.
if ( ! is_wp_error( $cliches_pair_terms ) && is_array( $cliches_pair_terms ) ) {
	foreach ( $cliches_pair_terms as $cliches_pair_term ) {
		$cliches_pair_names[ $cliches_pair_term->slug ] = $cliches_pair_term->name;
	}
}

// ---- Pullstats row: average clichés/character, share carrying 3+, top pairing ----
// Replaces the old average/median callout pair — three punchier numbers,
// same treatment as the Genres/Tropes pullstats row. The average is
// measured across characters that carry at least one real cliché (Taxonomy_
// Optimized excludes "None"-only characters from that denominator entirely,
// same scope the old callout used) — a different, narrower denominator than
// the 3+ share below, which is a % of every published character.
$cliches_stats     = ( new \LWTV\Statistics\Build\Taxonomy_Optimized() )->get_terms_per_object_stats( 'post_type_characters', 'lez_cliches', array( 'none' ) );
$cliches_pullstats = array();

if ( (int) $cliches_stats['shows'] > 0 ) {
	$cliches_pullstats[] = array(
		'icon'   => 'chart-bar.svg',
		'number' => number_format_i18n( (float) $cliches_stats['average'], 1 ),
		'label'  => __( 'Number of clichés per character, on average.', 'lwtv' ),
	);
}

// 3+ clichés = the "3" and "4+" buckets Cliché Load already computed above
// — no second query, just add the two percentages Term_Count_Distribution
// already returned.
$cliches_3plus_pct = 0.0;
foreach ( $cliches_distribution as $cliches_dist_bucket ) {
	if ( in_array( $cliches_dist_bucket['label'], array( '3', '4+' ), true ) ) {
		$cliches_3plus_pct += (float) $cliches_dist_bucket['pct'];
	}
}
if ( (int) $character_count > 0 ) {
	$cliches_pullstats[] = array(
		'icon'   => 'chart-pie.svg',
		/* translators: %s: percentage of characters carrying 3 or more clichés (one decimal). */
		'number' => sprintf( __( '%s%%', 'lwtv' ), number_format_i18n( $cliches_3plus_pct, 1 ) ),
		'label'  => __( 'Percentage of characters with 3 or more clichés.', 'lwtv' ),
	);
}

if ( ! empty( $cliches_pairs ) ) {
	list( $cliches_top_pair_a, $cliches_top_pair_b ) = $cliches_pairs[0]['slugs'];
	$cliches_pullstats[]                             = array(
		'icon'   => 'vest-patches.svg',
		'number' => number_format_i18n( (int) $cliches_pairs[0]['count'] ),
		'label'  => sprintf(
			/* translators: 1: cliché name, 2: cliché name. */
			__( 'Number of characters who pair %1$s with %2$s.', 'lwtv' ),
			$cliches_pair_names[ $cliches_top_pair_a ] ?? $cliches_top_pair_a,
			$cliches_pair_names[ $cliches_top_pair_b ] ?? $cliches_top_pair_b
		),
	);
}

if ( ! empty( $cliches_pullstats ) ) :
	?>
	<div class="lwtv-pullstats lwtv-pullstats--three lwtv-statcards lwtv-bars--characters">
		<?php foreach ( $cliches_pullstats as $cliches_pullstat ) : ?>
			<div class="lwtv-statcard">
				<span class="lwtv-statcard-icon">
					<?php echo lwtv_plugin()->get_symbolicon( svg: $cliches_pullstat['icon'], icon: 'svg-' . str_replace( '.svg', '', $cliches_pullstat['icon'] ), max_size: '18' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<span class="lwtv-statcard-number"><?php echo esc_html( $cliches_pullstat['number'] ); ?></span>
				<p class="lwtv-statcard-label"><?php echo esc_html( $cliches_pullstat['label'] ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
endif;

// Spotlight the single most-clichéd character as a small footer strip on
// the panel — same treatment Genre/Trope Load use for their own "most
// loaded" entity. This overlaps the #1 slot of the separate "Most Clichés"
// leaderboard subpage by design (per direction): that page is the full
// top-25 list, this is just the headline fact for this panel.
$cliches_top       = \LWTV\Statistics\Build\Term_Count_Distribution::top_object( $cliches_slug_map, array( 'none' ) );
$cliches_top_media = '';
if ( $cliches_top['id'] > 0 && has_post_thumbnail( $cliches_top['id'] ) ) {
	$cliches_top_media = get_the_post_thumbnail(
		$cliches_top['id'],
		'medium',
		array(
			'class'   => 'lwtv-clicheload-poster-img',
			'loading' => 'lazy',
			'alt'     => get_the_title( $cliches_top['id'] ),
		)
	);
}
?>
<div class="lwtv-cliches-columns">
	<div class="lwtv-cliches-col lwtv-cliches-col--main">
	<section class="lwtv-panel bg-light lwtv-clicheload">
		<header class="lwtv-panel-head">
			<span class="lwtv-panel-icon characters">
				<?php echo lwtv_plugin()->get_symbolicon( svg: 'tag.svg', icon: 'svg-tag', max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</span>
			<div>
				<h2 class="lwtv-panel-title"><?php esc_html_e( 'Cliché Load', 'lwtv' ); ?></h2>
				<p class="lwtv-panel-sub"><?php esc_html_e( 'How many clichés a character carries, by share of all characters', 'lwtv' ); ?></p>
			</div>
		</header>
		<div class="lwtv-clicheload-row">
			<div class="lwtv-clicheload-figure">
				<?php // phpcs:ignore PEAR.Files.IncludingFile.UseRequire ?>
				<?php include plugin_dir_path( __DIR__ ) . 'partials/waffle.php'; ?>
			</div>
			<ul class="lwtv-legend lwtv-clicheload-legend">
				<?php foreach ( $cliches_distribution as $cliches_dist_i => $cliches_dist_bucket ) : ?>
					<?php if ( (int) $cliches_dist_bucket['count'] <= 0 ) : ?>
						<?php continue; // No characters in this bucket — nothing to show a % of. ?>
					<?php endif; ?>
					<li class="lwtv-legend-row">
						<span class="lwtv-legend-dot lwtv-legend-dot--b<?php echo (int) $cliches_dist_i; ?>"></span>
						<span class="lwtv-legend-name">
							<?php
							if ( '0' === $cliches_dist_bucket['label'] ) {
								// The "0" bucket is exactly the "None"-tagged characters
								// (confirmed against live data, see the comment above
								// $cliches_distribution) — label it as that cliché by
								// name instead of a bare "0 clichés", which reads like
								// a data gap rather than a deliberate tag.
								echo esc_html( $cliches_none_name );
							} else {
								echo esc_html(
									sprintf(
										/* translators: %s: number of clichés (or "4+"). */
										_n( '%s cliché', '%s clichés', ( '1' === $cliches_dist_bucket['label'] ) ? 1 : 2, 'lwtv' ),
										$cliches_dist_bucket['label']
									)
								);
							}
							?>
						</span>
						<span class="lwtv-legend-pct"><?php echo esc_html( number_format_i18n( $cliches_dist_bucket['pct'], 1 ) ); ?>%</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php if ( '' !== $cliches_top_media ) : ?>
			<figure class="lwtv-clicheload-poster">
				<a href="<?php echo esc_url( get_permalink( $cliches_top['id'] ) ); ?>">
					<?php echo $cliches_top_media; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_the_post_thumbnail() returns safe markup. ?>
				</a>
				<figcaption class="lwtv-clicheload-poster-cap">
					<span class="lwtv-clicheload-poster-eyebrow"><?php esc_html_e( 'Most clichéd character', 'lwtv' ); ?></span>
					<?php
					if ( $cliches_top['tied'] > 1 ) {
						printf(
							/* translators: 1: character name, 2: number of clichés, 3: number of characters tied for the most. */
							esc_html__( '%1$s carries %2$s clichés, tied with %3$s other characters for the most.', 'lwtv' ),
							esc_html( get_the_title( $cliches_top['id'] ) ),
							esc_html( number_format_i18n( $cliches_top['count'] ) ),
							esc_html( number_format_i18n( $cliches_top['tied'] - 1 ) )
						);
					} else {
						printf(
							/* translators: 1: character name, 2: number of clichés. */
							esc_html__( '%1$s carries %2$s clichés, the most of any character.', 'lwtv' ),
							esc_html( get_the_title( $cliches_top['id'] ) ),
							esc_html( number_format_i18n( $cliches_top['count'] ) )
						);
					}
					?>
				</figcaption>
			</figure>
		<?php endif; ?>
	</section>
	</div>
	<div class="lwtv-cliches-col lwtv-cliches-col--side">
		<?php
		// Common pairings: which clichés appear together on the same
		// character. $cliches_pairs/$cliches_pair_names were already
		// computed above (reused for the pullstat headline), so this just
		// builds the matchup rows — no re-query. No FacetWP multi-value
		// param is confirmed for lez_cliches (same conservative call
		// already made for lez_genres/lez_tropes), so rows don't link
		// anywhere yet.
		if ( ! empty( $cliches_pairs ) ) {
			$cliches_matchup_items = array();
			foreach ( $cliches_pairs as $cliches_pair ) {
				list( $cliches_pair_a, $cliches_pair_b ) = $cliches_pair['slugs'];
				$cliches_matchup_items[]                 = array(
					'a'     => $cliches_pair_names[ $cliches_pair_a ] ?? $cliches_pair_a,
					'b'     => $cliches_pair_names[ $cliches_pair_b ] ?? $cliches_pair_b,
					'count' => (int) $cliches_pair['count'],
				);
			}

			// Characters with only one cliché never appear in the grid
			// above — a pairing needs 2+ distinct clichés — so this
			// footnotes the count that's missing from it. Reuses the "1"
			// bucket from Cliché Load's distribution above, same footer
			// Trope Pairings adds for the same reason; no new query.
			$cliches_single_count = 0;
			foreach ( $cliches_distribution as $cliches_dist_bucket ) {
				if ( '1' === $cliches_dist_bucket['label'] ) {
					$cliches_single_count = (int) $cliches_dist_bucket['count'];
					break;
				}
			}

			$matchup = array(
				'items'  => $cliches_matchup_items,
				'family' => 'characters',
				'svg'    => 'vest-patches.svg',
				'icon'   => 'svg-vest-patches',
				'title'  => __( 'Common Pairings', 'lwtv' ),
				'sub'    => __( 'Clichés that appear together on the same character, by number of characters', 'lwtv' ),
				'unit'   => __( 'characters together', 'lwtv' ),
				'footer' => array(
					'title'  => __( 'Characters with only one Cliché', 'lwtv' ),
					'number' => number_format_i18n( $cliches_single_count ),
				),
			);
			// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
			include plugin_dir_path( __DIR__ ) . 'partials/matchup-cards.php';
		}
		?>
	</div>
</div>

<!-- Cliché Breakdown: unchanged data/query, just a denser 2-col layout -->
<div class="lwtv-cliches-breakdown-wrap">
	<?php
	$ranked = array(
		'rows'   => $cliches_data,
		'total'  => (int) $character_count,
		'family' => 'characters',
		'svg'    => 'tag.svg',
		'icon'   => 'svg-tag',
		'title'  => __( 'All Clichés, Ranked', 'lwtv' ),
		/* translators: %s: number of clichés. */
		'sub'    => sprintf( __( '%s clichés, by number of characters. A character can carry several, so shares add up past 100%%.', 'lwtv' ), number_format_i18n( count( $cliches_data ) ) ),
		'base'   => '/cliche/',
	);
	// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
	include plugin_dir_path( __DIR__ ) . 'partials/ranked-bars.php';
	?>
</div>
