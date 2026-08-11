<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows → Intersectionality: ranked bars (royal-blue), plus the same
 * infographic pattern already shipped for Genres/Tropes — an Intersection
 * Load waffle with a Most Intersectional Show spotlight, a Single vs
 * Multiple donut (mirrors Mixed Alignment), the existing Breakdown/Common
 * Pairings lists, and an Intersections by Decade tile grid. lez_intersections
 * is multi-value, so the decade section reuses Genre_Decade_Buckets'
 * top-3-per-decade shape (via Intersection_Trend) rather than a Format Mix
 * by Decade donut port — see genres.php's docblock for why.
 *
 * Layout mirrors Tropes: Intersection Load + Single vs Multiple stack in
 * the main (wide) column, Common Pairings sits alone in the side (narrow)
 * column beside them (as matchup-cards.php rows — lez_intersections is the
 * one taxonomy here with a confirmed FacetWP multi-value param,
 * fwp_show_intersectionality, so unlike Genres'/Tropes' matchup cards these
 * rows link out), and the Breakdown ranked list drops out of that grid to
 * run full width below in a 2-column card, same as
 * .lwtv-genres-breakdown-wrap / .lwtv-tropes-breakdown-wrap. Intersections
 * by Decade stays full width at the very end, unchanged.
 *
 * @package LezWatch.TV
 *
 * @var int $shows_count
 */

$inter_raw  = lwtv_plugin()->generate_shows_statistics( 'array', 'intersections' );
$inter_data = ( is_array( $inter_raw ) && ! empty( $inter_raw ) ) ? (array) reset( $inter_raw ) : array();

// Shared WP glue: every published show's intersection slugs, one query,
// transient-cached. Feeds Intersection Load, the Single vs Multiple split,
// the Most Intersectional Show spotlight, and Common Pairings below — same
// map shape Genre Load / Trope Load already use for their own taxonomies.
$inter_slug_map = ( new \LWTV\Statistics\Build\Taxonomy_Optimized() )->get_object_term_slug_map( 'post_type_shows', 'lez_intersections' );

// Intersection Load: how many intersections a show carries, 0 to 4+.
// lez_intersections has no "None" placeholder term, so there is nothing to exclude.
$inter_distribution = \LWTV\Statistics\Build\Term_Count_Distribution::build( $inter_slug_map, (int) $shows_count );
$inter_cells        = \LWTV\Statistics\Build\Term_Count_Distribution::to_cells( $inter_distribution, (int) $shows_count, 100 );

$inter_waffle_segments = array();
foreach ( $inter_distribution as $inter_dist_i => $inter_dist_bucket ) {
	$inter_waffle_segments[] = array(
		'count' => $inter_cells[ $inter_dist_i ],
		'class' => 'b' . $inter_dist_i,
	);
}

// Callouts: coverage, then average + median intersections per show (across shows that have at least one).
$inter_stats   = ( new \LWTV\Statistics\Build\Taxonomy_Optimized() )->get_terms_per_object_stats( 'post_type_shows', 'lez_intersections' );
$lwtv_callouts = array();
if ( (int) $inter_stats['shows'] > 0 && (int) $shows_count > 0 ) {
	$inter_with    = (int) $inter_stats['shows'];
	$inter_pct     = round( ( $inter_with / (int) $shows_count ) * 100, 1 );
	$inter_avg     = (float) $inter_stats['average'];
	$inter_all_avg = ( (int) $shows_count > 0 ) ? ( (int) $inter_stats['total'] / (int) $shows_count ) : 0.0;

	$lwtv_callouts[] = array(
		'label'  => __( 'Shows with intersections', 'lwtv' ),
		'icon'   => 'chart-pie.svg',
		/* translators: %s: percentage of all shows carrying at least one intersection (one decimal). */
		'text'   => sprintf( __( '%s%% of all shows carry at least one intersection.', 'lwtv' ), number_format_i18n( $inter_pct, 1 ) ),
		'family' => 'intersections',
	);

	$lwtv_callouts[] = array(
		'label'  => __( 'Average per show', 'lwtv' ),
		'icon'   => 'chart-bar.svg',
		/* translators: %s: average number of intersections per show that has at least one (one decimal). */
		'text'   => sprintf( __( 'Shows with intersections span %s of them on average.', 'lwtv' ), number_format_i18n( $inter_avg, 1 ) ),
		'family' => 'intersections',
	);

	// Same 'total' (every intersection tag, catalogue-wide) as the average
	// above, but divided by every show — including the ones with none —
	// so this one measures density across the whole catalogue rather than
	// just the subset that already carries at least one.
	$lwtv_callouts[] = array(
		'label'  => __( 'Average across all shows', 'lwtv' ),
		'icon'   => 'scales.svg',
		/* translators: %s: average number of intersections per show, counting every show including those with none (two decimals). */
		'text'   => sprintf( __( 'Counting every show we track, that average drops to %s.', 'lwtv' ), number_format_i18n( $inter_all_avg, 2 ) ),
		'family' => 'intersections',
	);

	// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
	include plugin_dir_path( __DIR__ ) . 'partials/callouts.php';
}

// ---- Intersection Load (waffle) + Most Intersectional Show spotlight ----
// Same Term_Count_Distribution + top_object() pairing Genre Load already
// uses — both are pure, taxonomy-agnostic, so lez_intersections reuses them
// as-is rather than needing new query classes.
$inter_top       = \LWTV\Statistics\Build\Term_Count_Distribution::top_object( $inter_slug_map );
$inter_top_media = '';
if ( $inter_top['id'] > 0 && has_post_thumbnail( $inter_top['id'] ) ) {
	$inter_top_media = get_the_post_thumbnail(
		$inter_top['id'],
		'medium',
		array(
			'class'   => 'lwtv-interload-poster-img',
			'loading' => 'lazy',
			'alt'     => get_the_title( $inter_top['id'] ),
		)
	);
}

$waffle = array(
	'segments' => $inter_waffle_segments,
	'total'    => 100,
	'columns'  => 20,
	'radius'   => 6,
	'label'    => __( 'Shows grouped by how many intersections each carries, from none to four or more.', 'lwtv' ),
);
?>
<div class="lwtv-inter-columns">
	<div class="lwtv-inter-col lwtv-inter-col--main">
		<section class="lwtv-panel bg-light lwtv-interload">
			<header class="lwtv-panel-head">
				<span class="lwtv-panel-icon intersections">
					<?php echo lwtv_plugin()->get_symbolicon( svg: 'escalator.svg', icon: 'svg-escalator', max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<div>
					<h2 class="lwtv-panel-title"><?php esc_html_e( 'Intersection Load', 'lwtv' ); ?></h2>
					<p class="lwtv-panel-sub"><?php esc_html_e( 'How many intersections a show carries, by share of all shows', 'lwtv' ); ?></p>
				</div>
			</header>
			<div class="lwtv-interload-row">
				<div class="lwtv-interload-figure">
					<?php // phpcs:ignore PEAR.Files.IncludingFile.UseRequire ?>
					<?php include plugin_dir_path( __DIR__ ) . 'partials/waffle.php'; ?>
				</div>
				<ul class="lwtv-legend lwtv-interload-legend">
					<?php foreach ( $inter_distribution as $inter_dist_i => $inter_dist_bucket ) : ?>
						<?php if ( (int) $inter_dist_bucket['count'] <= 0 ) : ?>
							<?php continue; // No shows in this bucket — nothing to show a % of. ?>
						<?php endif; ?>
						<li class="lwtv-legend-row">
							<span class="lwtv-legend-dot lwtv-legend-dot--b<?php echo (int) $inter_dist_i; ?>"></span>
							<span class="lwtv-legend-name">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: number of intersections (or "4+"). */
										_n( '%s intersection', '%s intersections', ( '1' === $inter_dist_bucket['label'] ) ? 1 : 2, 'lwtv' ),
										$inter_dist_bucket['label']
									)
								);
								?>
							</span>
							<span class="lwtv-legend-pct"><?php echo esc_html( number_format_i18n( $inter_dist_bucket['pct'], 1 ) ); ?>%</span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php if ( '' !== $inter_top_media ) : ?>
				<figure class="lwtv-interload-poster">
					<a href="<?php echo esc_url( get_permalink( $inter_top['id'] ) ); ?>">
						<?php echo $inter_top_media; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_the_post_thumbnail() returns safe markup. ?>
					</a>
					<figcaption class="lwtv-interload-poster-cap">
						<span class="lwtv-interload-poster-eyebrow"><?php esc_html_e( 'Most intersectional show', 'lwtv' ); ?></span>
						<?php
						if ( $inter_top['tied'] > 1 ) {
							printf(
								/* translators: 1: show name, 2: number of intersections, 3: number of shows tied for the most. */
								esc_html__( '%1$s spans %2$s intersections, tied with %3$s other shows for the most.', 'lwtv' ),
								esc_html( get_the_title( $inter_top['id'] ) ),
								esc_html( number_format_i18n( $inter_top['count'] ) ),
								esc_html( number_format_i18n( $inter_top['tied'] - 1 ) )
							);
						} else {
							printf(
								/* translators: 1: show name, 2: number of intersections. */
								esc_html__( '%1$s spans %2$s intersections, the most of any show.', 'lwtv' ),
								esc_html( get_the_title( $inter_top['id'] ) ),
								esc_html( number_format_i18n( $inter_top['count'] ) )
							);
						}
						?>
					</figcaption>
				</figure>
			<?php endif; ?>
		</section>
	</div>
	<div class="lwtv-inter-col lwtv-inter-col--side">
		<?php
		// Common pairings: which intersections appear together on the same show.
		// Pure counting lives in Build\Intersection_Pairs (unit-tested); reuses the
		// same $inter_slug_map fetched at the top of this file. Rendered as
		// matchup-cards.php rows (not the ranked-bars lollipop list Genres/Tropes
		// use for this) because lez_intersections has a confirmed FacetWP
		// multi-value param — matchup-cards.php's optional per-item 'url' turns
		// each row into a link to that filtered shows archive, same destination
		// this section always linked to.
		$pairs = \LWTV\Statistics\Build\Intersection_Pairs::top_pairs(
			\LWTV\Statistics\Build\Intersection_Pairs::count_pairs( $inter_slug_map ),
			8,
			2
		);

		if ( ! empty( $pairs ) ) {
			$pair_names = array();
			$pair_terms = get_terms(
				array(
					'taxonomy'   => 'lez_intersections',
					'hide_empty' => true,
				)
			);
			// get_terms() can hand back a WP_Error (unregistered taxonomy, DB
			// hiccup); iterating that would fatal. The row builder below already
			// falls back to slugs when a name is missing, so an empty map is safe.
			if ( ! is_wp_error( $pair_terms ) && is_array( $pair_terms ) ) {
				foreach ( $pair_terms as $pair_term ) {
					$pair_names[ $pair_term->slug ] = $pair_term->name;
				}
			}

			$inter_matchup_items = array();
			foreach ( $pairs as $pair ) {
				list( $pair_a, $pair_b ) = $pair['slugs'];
				// Link each pairing to the shows archive with both facet values selected.
				$inter_matchup_items[] = array(
					'a'     => $pair_names[ $pair_a ] ?? $pair_a,
					'b'     => $pair_names[ $pair_b ] ?? $pair_b,
					'count' => (int) $pair['count'],
					'url'   => site_url( '/shows/?fwp_show_intersectionality=' . rawurlencode( $pair_a . ',' . $pair_b ) ),
				);
			}

			$matchup = array(
				'items'  => $inter_matchup_items,
				'family' => 'intersections',
				'svg'    => 'vest-patches.svg',
				'icon'   => 'svg-vest-patches',
				'title'  => __( 'Common Pairings', 'lwtv' ),
				'sub'    => __( 'Intersections that appear together on the same show, by number of shows', 'lwtv' ),
				'unit'   => __( 'shows together', 'lwtv' ),
			);
			// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
			include plugin_dir_path( __DIR__ ) . 'partials/matchup-cards.php';
		}
		?>
	</div>
</div>

<div class="lwtv-inter-columns">
	<div class="lwtv-inter-col lwtv-inter-col--main">
		<?php
		// ---- Intersections by Decade ----
		// Same reasoning as Genres' decade section: lez_intersections is
		// multi-value, so this reuses Genre_Decade_Buckets' top-3-per-decade tile
		// grid as-is (via Intersection_Trend, the taxonomy-specific WP glue) rather
		// than a Format Mix by Decade donut port.
		$inter_decade_buckets = ( new \LWTV\Statistics\Build\Intersection_Trend() )->generate( 20, 3 );

		if ( ! empty( $inter_decade_buckets ) ) :
			?>
			<section class="lwtv-panel bg-light lwtv-inter-decades">
				<header class="lwtv-panel-head">
					<span class="lwtv-panel-icon intersections">
						<?php echo lwtv_plugin()->get_symbolicon( svg: 'calendar-15.svg', icon: 'svg-calendar-15', max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</span>
					<div>
						<h2 class="lwtv-panel-title"><?php esc_html_e( 'Intersection by Decade', 'lwtv' ); ?></h2>
						<p class="lwtv-panel-sub"><?php esc_html_e( 'Top 3 intersections per decade, each as its own share of shows that premiered in that decade. As shows often carry more than one intersection, the three don\'t add up to 100%.', 'lwtv' ); ?></p>
					</div>
				</header>
				<div class="lwtv-decade-tile-grid">
					<?php foreach ( $inter_decade_buckets as $inter_decade_bucket ) : ?>
						<?php
						// Mirrors Genres'/Format Mix by Decade's label construction: the
						// trailing "s" is wrapped so it can be forced lowercase inside
						// an uppercase-transformed eyebrow.
						if ( 'before' === $inter_decade_bucket['type'] ) {
							$inter_decade_label = $inter_decade_bucket['to']
								/* translators: %d: the decade this bucket ends before, e.g. "Before 1980s". */
								? sprintf( __( 'Before %1$d<span class="lwtv-decade-suffix">s</span>', 'lwtv' ), $inter_decade_bucket['to'] )
								: __( 'Earliest years', 'lwtv' );
						} else {
							/* translators: %d: a decade, e.g. "1980s". */
							$inter_decade_label = sprintf( __( '%1$d<span class="lwtv-decade-suffix">s</span>', 'lwtv' ), $inter_decade_bucket['from'] );
						}
						?>
						<div class="lwtv-decade-tile">
							<div class="lwtv-decade-tile-head">
								<span class="lwtv-decade-tile-label"><?php echo wp_kses( $inter_decade_label, array( 'span' => array( 'class' => array() ) ) ); ?></span>
								<span class="lwtv-decade-tile-count">
									<?php
									printf(
										/* translators: %s: number of shows that premiered in this bucket. */
										esc_html__( '%s shows', 'lwtv' ),
										esc_html( number_format_i18n( $inter_decade_bucket['shows'] ) )
									);
									?>
								</span>
							</div>
							<?php if ( empty( $inter_decade_bucket['top'] ) ) : ?>
								<p class="lwtv-decade-tile-empty"><?php esc_html_e( 'No intersections tracked yet.', 'lwtv' ); ?></p>
							<?php else : ?>
								<div class="lwtv-decade-tile-rows lwtv-bars--intersections">
									<?php foreach ( $inter_decade_bucket['top'] as $inter_decade_row ) : ?>
										<div class="lwtv-decade-tile-row">
											<div class="lwtv-decade-tile-row-head">
												<a class="lwtv-decade-tile-name" href="<?php echo esc_url( site_url( '/intersection/' . $inter_decade_row['slug'] ) ); ?>"><?php echo esc_html( $inter_decade_row['name'] ); ?></a>
												<span class="lwtv-decade-tile-value"><?php echo esc_html( number_format_i18n( $inter_decade_row['pct'], 1 ) . '%' ); ?></span>
											</div>
											<div class="progress lwtv-decade-tile-track">
												<div class="progress-bar" role="progressbar" style="width:0" data-grow-to="<?php echo esc_attr( (string) $inter_decade_row['pct'] ); ?>" aria-valuenow="<?php echo esc_attr( (string) $inter_decade_row['count'] ); ?>" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( (string) $inter_decade_bucket['shows'] ); ?>"></div>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
			<?php
		endif;
		?>
	</div>
	<div class="lwtv-inter-col lwtv-inter-col--side">
		<?php
		// ---- Single vs Multiple Intersections ----
		// A simpler cut of the same Intersection Load distribution: of shows that
		// carry at least one intersection, what share stop at exactly one versus
		// carry two or more at once. Mirrors Tropes' Pure/Mixed Alignment donut.
		$inter_single_count = 0;
		$inter_multi_count  = 0;
		foreach ( $inter_distribution as $inter_dist_bucket ) {
			if ( '1' === $inter_dist_bucket['label'] ) {
				$inter_single_count = (int) $inter_dist_bucket['count'];
			} elseif ( '0' !== $inter_dist_bucket['label'] ) {
				$inter_multi_count += (int) $inter_dist_bucket['count'];
			}
		}
		$inter_split_total = $inter_single_count + $inter_multi_count;

		if ( $inter_split_total > 0 ) :
			$inter_multi_pct  = round( ( $inter_multi_count / $inter_split_total ) * 100, 1 );
			$inter_single_pct = round( ( $inter_single_count / $inter_split_total ) * 100, 1 );
			?>
			<section class="lwtv-panel bg-light lwtv-inter-split">
				<header class="lwtv-panel-head">
					<span class="lwtv-panel-icon intersections">
						<?php echo lwtv_plugin()->get_symbolicon( svg: 'scales.svg', icon: 'svg-scales', max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</span>
					<div>
						<h2 class="lwtv-panel-title"><?php esc_html_e( 'Single vs Multiple Intersections', 'lwtv' ); ?></h2>
						<p class="lwtv-panel-sub">
							<?php
							printf(
								/* translators: %s: percentage of shows with at least one intersection that carry two or more at once (one decimal). */
								esc_html__( '%s%% of shows with an intersection carry more than one at once.', 'lwtv' ),
								esc_html( number_format_i18n( $inter_multi_pct, 1 ) )
							);
							?>
						</p>
					</div>
				</header>
				<div class="lwtv-inter-split-row">
					<div class="lwtv-donut-figure">
						<svg class="lwtv-donut" viewBox="0 0 120 120" role="img" aria-label="<?php esc_attr_e( 'Single versus multiple intersections', 'lwtv' ); ?>">
							<g transform="rotate(-90 60 60)">
								<circle class="lwtv-donut-track" cx="60" cy="60" r="50" fill="none" stroke-width="15" pathLength="100" />
								<circle class="lwtv-donut-seg lwtv-donut-seg--royal-blue" cx="60" cy="60" r="50" fill="none" stroke-width="15" pathLength="100" stroke-dasharray="<?php echo esc_attr( (string) $inter_multi_pct ); ?> <?php echo esc_attr( (string) ( 100 - $inter_multi_pct ) ); ?>" stroke-dashoffset="0" />
							</g>
						</svg>
						<div class="lwtv-donut-center">
							<span class="lwtv-donut-center-num" data-count-to="<?php echo (int) round( $inter_multi_pct ); ?>" data-count-suffix="%"><?php echo esc_html( number_format_i18n( (int) round( $inter_multi_pct ) ) ); ?>%</span>
							<span class="lwtv-donut-center-sub"><?php esc_html_e( 'multiple', 'lwtv' ); ?></span>
						</div>
					</div>
					<ul class="lwtv-donut-legend lwtv-donut-legend--compact">
						<li class="lwtv-donut-legend-row">
							<span class="lwtv-donut-dot lwtv-donut-seg--bordergrey"></span>
							<span class="lwtv-donut-legend-name"><?php esc_html_e( '1 intersection', 'lwtv' ); ?></span>
							<br /><span class="lwtv-donut-legend-val"><?php echo esc_html( number_format_i18n( $inter_single_count ) . ' · ' . number_format_i18n( $inter_single_pct, 1 ) . '%' ); ?></span>
						</li>
						<li class="lwtv-donut-legend-row">
							<span class="lwtv-donut-dot lwtv-donut-seg--royal-blue"></span>
							<span class="lwtv-donut-legend-name"><?php esc_html_e( '2+ intersections', 'lwtv' ); ?></span>
							<br /><span class="lwtv-donut-legend-val"><?php echo esc_html( number_format_i18n( $inter_multi_count ) . ' · ' . number_format_i18n( $inter_multi_pct, 1 ) . '%' ); ?></span>
						</li>
					</ul>
				</div>
			</section>
		<?php endif; ?>
	</div>
</div>

<div class="lwtv-inter-breakdown-wrap">
<?php
$ranked = array(
	'rows'   => $inter_data,
	'total'  => (int) $shows_count,
	'family' => 'intersections',
	'svg'    => 'statue-of-liberty.svg',
	'icon'   => 'svg-statue-of-liberty',
	'title'  => __( 'Intersectionality Breakdown', 'lwtv' ),
	/* translators: %s: number of intersections. */
	'sub'    => sprintf( __( '%s intersections, by number of shows', 'lwtv' ), number_format_i18n( count( $inter_data ) ) ),
	'base'   => '',
	'mode'   => 'lollipop',
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/ranked-bars.php';
?>
</div>
