<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows → Tropes: ranked bars (green).
 *
 * @package LezWatch.TV
 *
 * @var int $shows_count
 */

$tropes_raw  = lwtv_plugin()->generate_shows_statistics( 'array', 'tropes' );
$tropes_data = ( is_array( $tropes_raw ) && ! empty( $tropes_raw ) ) ? (array) reset( $tropes_raw ) : array();

// Trope Alignment: how many shows carry at least one good/maybe/bad/ploy
// trope (Trope_Categories — shared with Calculations::show_tropes_score(),
// so this groups tropes exactly the way the score does). A show carrying
// tropes from more than one bucket counts toward each, so these four
// totals are independent, not a partition — don't expect them to sum to
// anything in particular.
$tropes_slug_map  = ( new \LWTV\Statistics\Build\Taxonomy_Optimized() )->get_object_term_slug_map( 'post_type_shows', 'lez_tropes' );
$tropes_alignment = \LWTV\Statistics\Build\Trope_Category_Coverage::count( $tropes_slug_map );

if ( array_sum( $tropes_alignment ) > 0 ) {
	$tropes_alignment_cards = array(
		array(
			'family' => 'good-tropes',
			'label'  => __( 'Good', 'lwtv' ),
			'svg'    => 'heart-circle.svg',
			'icon'   => 'svg-heart-circle',
			'count'  => $tropes_alignment['good'],
			'desc'   => __( 'Shows that carry a clearly positive trope.', 'lwtv' ),
		),
		array(
			'family' => 'maybe-tropes',
			'label'  => __( 'Maybe', 'lwtv' ),
			'svg'    => 'question-square.svg',
			'icon'   => 'svg-question-square',
			'count'  => $tropes_alignment['maybe'],
			'desc'   => __( 'Shows that carry a trope which is only good depending on context.', 'lwtv' ),
		),
		array(
			'family' => 'bad-tropes',
			'label'  => __( 'Bad', 'lwtv' ),
			'svg'    => 'warning.svg',
			'icon'   => 'svg-warning',
			'count'  => $tropes_alignment['bad'],
			'desc'   => __( 'Shows carry an actively harmful trope.', 'lwtv' ),
		),
		array(
			'family' => 'ploy-tropes',
			'label'  => __( 'Ploy', 'lwtv' ),
			'svg'    => 'jason-mask.svg',
			'icon'   => 'svg-jason-mask',
			'count'  => $tropes_alignment['ploy'],
			'desc'   => __( 'Shows with token representation.', 'lwtv' ),
		),
	);
	?>
	<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Trope Alignment', 'lwtv' ); ?></p>
	<div class="lwtv-pullstats lwtv-pullstats--four">
		<?php foreach ( $tropes_alignment_cards as $tropes_align_card ) : ?>
			<div class="lwtv-tropegap lwtv-tropegap--tint card-header <?php echo esc_attr( $tropes_align_card['family'] ); ?>">
				<div class="lwtv-tropegap-top">
					<span class="lwtv-stats-eyebrow"><?php echo esc_html( $tropes_align_card['label'] ); ?></span>
					<span class="lwtv-tropegap-icon">
						<?php echo lwtv_plugin()->get_symbolicon( svg: $tropes_align_card['svg'], icon: $tropes_align_card['icon'], max_size: '22' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</span>
				</div>
				<span class="lwtv-tropegap-number" data-count-to="<?php echo (int) $tropes_align_card['count']; ?>"><?php echo esc_html( number_format_i18n( $tropes_align_card['count'] ) ); ?></span>
				<p class="lwtv-tropegap-desc"><?php echo esc_html( $tropes_align_card['desc'] ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
}

// Trope Load: what share of shows carry 0, 1, 2, 3, or 4+ tropes. Replaces a
// plain average/median pair — those collapse to the same "2" when the data
// clusters tightly around the middle, which tells a reader nothing about
// the spread. Reuses the same slug map as Trope Alignment above; $shows_count
// is the true denominator since the map only lists shows with >=1 trope
// relationship row, so shows with none never appear in it at all.
$tropes_distribution = \LWTV\Statistics\Build\Term_Count_Distribution::build( $tropes_slug_map, (int) $shows_count, array( 'none' ) );

// Rendered as a 100-dot waffle colored by bucket rather than another
// ranked-bar list — Trope Breakdown (now its own full-width section below
// this whole layout) is already that shape, so Trope Load needs to look
// like a different kind of fact, not a second copy of the same chart.
// to_cells() apportions the
// 100 dots by largest remainder so they always sum to exactly 100, even
// though the buckets' raw pcts (each independently rounded to 1 decimal)
// don't necessarily add up to 100 themselves.
$tropes_cells           = \LWTV\Statistics\Build\Term_Count_Distribution::to_cells( $tropes_distribution, (int) $shows_count, 100 );
$tropes_waffle_segments = array();
foreach ( $tropes_distribution as $tropes_dist_i => $tropes_dist_bucket ) {
	$tropes_waffle_segments[] = array(
		'count' => $tropes_cells[ $tropes_dist_i ],
		'class' => 'b' . $tropes_dist_i,
	);
}

$waffle = array(
	'segments' => $tropes_waffle_segments,
	'total'    => 100,
	'columns'  => 20,
	'radius'   => 6,
	'label'    => __( 'Shows grouped by how many tropes each carries, from none to four or more.', 'lwtv' ),
);

// Spotlight the single most trope-loaded show as a small footer strip on
// the panel (below the waffle+legend row, not squeezed into it — a third
// flex item there would just force an awkward wrap). Ties are common once
// counts get this small, so
// top_object() reports how many shows shared the top spot and the caption
// below hedges accordingly rather than implying the pictured show is
// uniquely the most trope-heavy.
$tropes_top       = \LWTV\Statistics\Build\Term_Count_Distribution::top_object( $tropes_slug_map, array( 'none' ) );
$tropes_top_media = '';
if ( $tropes_top['id'] > 0 && has_post_thumbnail( $tropes_top['id'] ) ) {
	$tropes_top_media = get_the_post_thumbnail(
		$tropes_top['id'],
		'medium',
		array(
			'class'   => 'lwtv-tropeload-poster-img',
			'loading' => 'lazy',
			'alt'     => get_the_title( $tropes_top['id'] ),
		)
	);
}
?>
<div class="lwtv-tropes-columns">
	<div class="lwtv-tropes-col lwtv-tropes-col--main">
		<section class="lwtv-panel bg-light lwtv-tropeload">
			<header class="lwtv-panel-head">
				<span class="lwtv-panel-icon tropes">
					<?php echo lwtv_plugin()->get_symbolicon( svg: 'chart-bar.svg', icon: 'svg-chart-bar', max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<div>
					<h2 class="lwtv-panel-title"><?php esc_html_e( 'Trope Load', 'lwtv' ); ?></h2>
					<p class="lwtv-panel-sub"><?php esc_html_e( 'How many tropes a show carries, by share of all shows', 'lwtv' ); ?></p>
				</div>
			</header>
			<div class="lwtv-tropeload-row">
				<div class="lwtv-tropeload-figure">
					<?php // phpcs:ignore PEAR.Files.IncludingFile.UseRequire ?>
					<?php include plugin_dir_path( __DIR__ ) . 'partials/waffle.php'; ?>
				</div>
				<ul class="lwtv-legend lwtv-tropeload-legend">
					<?php foreach ( $tropes_distribution as $tropes_dist_bucket ) : ?>
						<li class="lwtv-legend-row">
							<span class="lwtv-legend-dot"></span>
							<span class="lwtv-legend-name">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: number of tropes (or "4+"). */
										_n( '%s trope', '%s tropes', ( '1' === $tropes_dist_bucket['label'] ) ? 1 : 2, 'lwtv' ),
										$tropes_dist_bucket['label']
									)
								);
								?>
							</span>
							<span class="lwtv-legend-pct"><?php echo esc_html( number_format_i18n( $tropes_dist_bucket['pct'], 1 ) ); ?>%</span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php if ( '' !== $tropes_top_media ) : ?>
				<figure class="lwtv-tropeload-poster">
					<a href="<?php echo esc_url( get_permalink( $tropes_top['id'] ) ); ?>">
						<?php echo $tropes_top_media; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_the_post_thumbnail() returns safe markup. ?>
					</a>
					<figcaption class="lwtv-tropeload-poster-cap">
						<?php
						if ( $tropes_top['tied'] > 1 ) {
							printf(
								/* translators: 1: show name, 2: number of tropes, 3: number of shows tied for the most. */
								esc_html__( '%1$s is tied with %3$s other shows for the most tropes (%2$s).', 'lwtv' ),
								esc_html( get_the_title( $tropes_top['id'] ) ),
								esc_html( number_format_i18n( $tropes_top['count'] ) ),
								esc_html( number_format_i18n( $tropes_top['tied'] - 1 ) )
							);
						} else {
							printf(
								/* translators: 1: show name, 2: number of tropes. */
								esc_html__( '%1$s carries the most tropes of any show (%2$s).', 'lwtv' ),
								esc_html( get_the_title( $tropes_top['id'] ) ),
								esc_html( number_format_i18n( $tropes_top['count'] ) )
							);
						}
						?>
					</figcaption>
				</figure>
			<?php endif; ?>
		</section>
		<?php
		// Mixed Alignment: shows that carry tropes from more than one
		// alignment category at once (e.g. both a "good" and a "bad" trope
		// on the same show) versus shows that stay in exactly one bucket.
		// category_sets() keeps each show's categories together (unlike
		// Trope_Category_Coverage::count() above, which tallies them
		// independently), and its output is the same [ id => [ slug, … ] ]
		// shape Intersection_Pairs already knows how to pair up — just with
		// category names standing in for trope slugs, so the "most common
		// pairing" sentence below is free, not a new algorithm.
		$tropes_category_sets = \LWTV\Statistics\Build\Trope_Category_Coverage::category_sets( $tropes_slug_map );
		$tropes_align_split   = \LWTV\Statistics\Build\Trope_Category_Coverage::alignment_split( $tropes_category_sets );

		if ( $tropes_align_split['pure'] + $tropes_align_split['mixed'] > 0 ) {
			$tropes_category_labels = array(
				'good'  => __( 'Good', 'lwtv' ),
				'maybe' => __( 'Maybe', 'lwtv' ),
				'bad'   => __( 'Bad', 'lwtv' ),
				'ploy'  => __( 'Ploy', 'lwtv' ),
			);

			$tropes_top_category_pair = \LWTV\Statistics\Build\Intersection_Pairs::top_pairs(
				\LWTV\Statistics\Build\Intersection_Pairs::count_pairs( $tropes_category_sets ),
				1,
				1
			);

			$tropes_align_denom = $tropes_align_split['pure'] + $tropes_align_split['mixed'];
			$tropes_pure_pct    = ( $tropes_align_denom > 0 ) ? round( ( $tropes_align_split['pure'] / $tropes_align_denom ) * 100, 1 ) : 0.0;
			?>
			<section class="lwtv-panel bg-light lwtv-mixed-alignment">
				<header class="lwtv-panel-head">
					<span class="lwtv-panel-icon tropes">
						<?php echo lwtv_plugin()->get_symbolicon( svg: 'scales.svg', icon: 'svg-scales', max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</span>
					<div>
						<h2 class="lwtv-panel-title"><?php esc_html_e( 'Mixed Alignment', 'lwtv' ); ?></h2>
					</div>
				</header>
				<p class="lwtv-panel-sub">
					<?php
					if ( $tropes_align_split['mixed'] > 0 && ! empty( $tropes_top_category_pair ) ) {
						list( $tropes_pair_cat_a, $tropes_pair_cat_b ) = $tropes_top_category_pair[0]['slugs'];
						printf(
							/* translators: 1: percentage of shows with a categorized trope that are mixed, 2: alignment category name, 3: alignment category name. */
							esc_html__( '%1$s%% of shows with a categorized trope carry more than one alignment at once, most often pairing a %2$s trope with a %3$s one.', 'lwtv' ),
							esc_html( number_format_i18n( $tropes_align_split['mixed_pct'], 1 ) ),
							esc_html( $tropes_category_labels[ $tropes_pair_cat_a ] ?? $tropes_pair_cat_a ),
							esc_html( $tropes_category_labels[ $tropes_pair_cat_b ] ?? $tropes_pair_cat_b )
						);
					} else {
						esc_html_e( 'None of the shows with a categorized trope carry more than one alignment at once — alignment here is all-or-nothing.', 'lwtv' );
					}
					?>
				</p>
				<div class="lwtv-mixed-alignment-row">
					<div class="lwtv-donut-figure">
						<svg class="lwtv-donut" viewBox="0 0 120 120" role="img" aria-label="<?php esc_attr_e( 'Pure versus mixed alignment shows', 'lwtv' ); ?>">
							<g transform="rotate(-90 60 60)">
								<circle class="lwtv-donut-track" cx="60" cy="60" r="50" fill="none" stroke-width="15" pathLength="100" />
								<circle class="lwtv-donut-seg lwtv-donut-seg--green" cx="60" cy="60" r="50" fill="none" stroke-width="15" pathLength="100" stroke-dasharray="<?php echo esc_attr( (string) $tropes_align_split['mixed_pct'] ); ?> <?php echo esc_attr( (string) ( 100 - $tropes_align_split['mixed_pct'] ) ); ?>" stroke-dashoffset="0" />
							</g>
						</svg>
						<div class="lwtv-donut-center">
							<span class="lwtv-donut-center-num" data-count-to="<?php echo (int) round( $tropes_align_split['mixed_pct'] ); ?>" data-count-suffix="%"><?php echo esc_html( number_format_i18n( (int) round( $tropes_align_split['mixed_pct'] ) ) ); ?>%</span>
							<span class="lwtv-donut-center-sub"><?php esc_html_e( 'mixed', 'lwtv' ); ?></span>
						</div>
					</div>
					<ul class="lwtv-donut-legend lwtv-donut-legend--compact">
						<li class="lwtv-donut-legend-row">
							<span class="lwtv-donut-dot lwtv-donut-seg--bordergrey"></span>
							<span class="lwtv-donut-legend-name"><?php esc_html_e( 'Pure (one alignment)', 'lwtv' ); ?></span>
							<span class="lwtv-donut-legend-val"><?php echo esc_html( number_format_i18n( $tropes_align_split['pure'] ) . ' · ' . number_format_i18n( $tropes_pure_pct, 1 ) . '%' ); ?></span>
						</li>
						<li class="lwtv-donut-legend-row">
							<span class="lwtv-donut-dot lwtv-donut-seg--green"></span>
							<span class="lwtv-donut-legend-name"><?php esc_html_e( 'Mixed (2+ alignments)', 'lwtv' ); ?></span>
							<span class="lwtv-donut-legend-val"><?php echo esc_html( number_format_i18n( $tropes_align_split['mixed'] ) . ' · ' . number_format_i18n( $tropes_align_split['mixed_pct'], 1 ) . '%' ); ?></span>
						</li>
					</ul>
				</div>
			</section>
			<?php
		}
		?>
	</div>
	<div class="lwtv-tropes-col lwtv-tropes-col--side">
		<?php
		// Common pairings: which tropes appear together on the same show.
		// Pure counting lives in Build\Intersection_Pairs (already
		// unit-tested for the Intersectionality page — the co-occurrence
		// math doesn't care which taxonomy it's counting); the term names
		// and links here are the WP glue. No FacetWP multi-value param is
		// confirmed for lez_tropes (unlike lez_intersections'
		// fwp_show_intersectionality), so rows don't link anywhere yet —
		// same conservative call made for the Trope Alignment cards.
		// Now its own column (previously stacked below Trope Load in the
		// same side column) since Trope Load moved into the main column
		// alongside Mixed Alignment — see the layout comment on
		// .lwtv-tropes-columns in _stats.scss for the full "why".
		$tropes_pairs = \LWTV\Statistics\Build\Intersection_Pairs::top_pairs(
			\LWTV\Statistics\Build\Intersection_Pairs::count_pairs( $tropes_slug_map ),
			8,
			2
		);

		if ( ! empty( $tropes_pairs ) ) {
			$tropes_pair_names = array();
			$tropes_pair_terms = get_terms(
				array(
					'taxonomy'   => 'lez_tropes',
					'hide_empty' => true,
				)
			);
			// get_terms() can hand back a WP_Error (unregistered taxonomy,
			// DB hiccup); iterating that would fatal. The row builder
			// below already falls back to slugs when a name is missing,
			// so an empty map is safe.
			if ( ! is_wp_error( $tropes_pair_terms ) && is_array( $tropes_pair_terms ) ) {
				foreach ( $tropes_pair_terms as $tropes_pair_term ) {
					$tropes_pair_names[ $tropes_pair_term->slug ] = $tropes_pair_term->name;
				}
			}

			// Matchup rows, matching Genres' Common Pairings treatment — a
			// leading count + "A + B" on one compact line instead of the
			// lollipop-list bars used elsewhere on this page.
			$tropes_matchup_items = array();
			foreach ( $tropes_pairs as $tropes_pair ) {
				list( $tropes_pair_a, $tropes_pair_b ) = $tropes_pair['slugs'];
				$tropes_matchup_items[]                = array(
					'a'     => $tropes_pair_names[ $tropes_pair_a ] ?? $tropes_pair_a,
					'b'     => $tropes_pair_names[ $tropes_pair_b ] ?? $tropes_pair_b,
					'count' => (int) $tropes_pair['count'],
				);
			}

			// Shows with only one trope never appear in the grid above — a
			// pairing needs 2+ distinct tropes — so this footnotes the count
			// that's missing from it. Reuses the "1" bucket from Trope
			// Load's distribution above; no new query.
			$tropes_single_count = 0;
			foreach ( $tropes_distribution as $tropes_dist_bucket ) {
				if ( '1' === $tropes_dist_bucket['label'] ) {
					$tropes_single_count = (int) $tropes_dist_bucket['count'];
					break;
				}
			}

			$matchup = array(
				'items'  => $tropes_matchup_items,
				'family' => 'tropes',
				'svg'    => 'vest-patches.svg',
				'icon'   => 'svg-vest-patches',
				'title'  => __( 'Common Pairings', 'lwtv' ),
				'sub'    => __( 'Tropes that appear together on the same show, by number of shows', 'lwtv' ),
				'unit'   => __( 'shows together', 'lwtv' ),
				'footer' => array(
					'title'  => __( 'Shows with only one Trope', 'lwtv' ),
					'number' => number_format_i18n( $tropes_single_count ),
				),
			);
			// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
			include plugin_dir_path( __DIR__ ) . 'partials/matchup-cards.php';
		}
		?>
	</div>
</div>

<?php
// Trope Breakdown: full width now, like Genre Breakdown — it no longer
// shares the grid with Trope Load/Mixed Alignment/Common Pairings, so it
// gets the full page to spread its own internal 2 columns across instead
// of squeezing a 2-col list into one half of a narrower split.
?>
<div class="lwtv-tropes-breakdown-wrap">
	<?php
	$ranked = array(
		'rows'   => $tropes_data,
		'total'  => (int) $shows_count,
		'family' => 'characters',
		'svg'    => 'tag.svg',
		'icon'   => 'svg-tag',
		'title'  => __( 'Trope Breakdown', 'lwtv' ),
		/* translators: %s: number of tropes. */
		'sub'    => sprintf( __( '%s tropes, by number of shows', 'lwtv' ), number_format_i18n( count( $tropes_data ) ) ),
		'base'   => '/trope/',
	);
	// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
	include plugin_dir_path( __DIR__ ) . 'partials/ranked-bars.php';
	?>
</div>
