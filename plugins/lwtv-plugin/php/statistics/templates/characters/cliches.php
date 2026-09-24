<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Characters → Clichés (green): pullstats, Cliché Load waffle, Common
 * Pairings and the full ranked list, excluding the "none" term.
 * See docs/statistics/pages.md#load-and-pairings-pages.
 *
 * @package LezWatch.TV
 *
 * @var int $character_count
 */

$cliches_raw  = lwtv_plugin()->generate_characters_statistics( 'array', 'cliches' );
$cliches_data = ( is_array( $cliches_raw ) && ! empty( $cliches_raw ) ) ? (array) reset( $cliches_raw ) : array();

// Every published character's cliché slugs, one cached query, shared by
// the pullstats, Cliché Load, the spotlight and Common Pairings.
$cliches_slug_map = ( new \LWTV\Statistics\Build\Taxonomy_Optimized() )->get_object_term_slug_map( 'post_type_characters', 'lez_cliches' );

// Cliché Load: real clichés per character, 0 to 4+, with "none" excluded,
// so bucket 0 is the None-tagged set. See docs/statistics/data-model.md#none-terms.
$cliches_distribution = \LWTV\Statistics\Build\Term_Count_Distribution::build( $cliches_slug_map, (int) $character_count, array( 'none' ) );
$cliches_cells        = \LWTV\Statistics\Build\Term_Count_Distribution::to_cells( $cliches_distribution, (int) $character_count, 100 );

$cliches_waffle_segments = array();
foreach ( $cliches_distribution as $cliches_dist_i => $cliches_dist_bucket ) {
	$cliches_waffle_segments[] = array(
		'count' => $cliches_cells[ $cliches_dist_i ],
		'class' => 'b' . $cliches_dist_i,
	);
}

// Real term name for bucket 0 (picks up a rename), falling back to the slug.
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

// Common Cliché Pairings, counted once for the pullstat (top 1) and the
// panel (top 8). Unlinked: see docs/statistics/pages.md#facetwp-links.
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
// The average excludes None-only characters; the 3+ share is of every character.
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

// Spotlight the most-clichéd character as a footer strip (it deliberately
// repeats the #1 of Characters → Most).
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

			// Footnote the one-cliché characters a pairing can't include,
			// from Cliché Load's "1" bucket.
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

<!-- Cliché Breakdown: full width, 2-col layout -->
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
