<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows → Genres: infographic rework (amber). Shares add up past 100% (multi-value taxonomy).
 *
 * Ports the Tropes rework's Load waffle + Common Pairings pattern onto
 * lez_genres, plus genre-specific additions: a "matchup card" treatment
 * for pairings (per design handoff), an "Uncharted Genres" reframe of the
 * long tail, and a Genre by Decade section (Genre_Trend/Genre_Decade_Buckets)
 * showing each decade's top 3 genres as independent shares of that decade's
 * shows — not a Format Mix by Decade donut port, since genres are
 * multi-value and don't partition to 100%.
 *
 * @package LezWatch.TV
 *
 * @var int $shows_count
 */

$genres_raw  = lwtv_plugin()->generate_shows_statistics( 'array', 'genres' );
$genres_data = ( is_array( $genres_raw ) && ! empty( $genres_raw ) ) ? (array) reset( $genres_raw ) : array();

// Shared WP glue: every published show's genre slugs, one query,
// transient-cached. Feeds Genre Load, the broadest-reach spotlight, and
// Common Genre Pairings below — same map shape Trope Load / Trope Pairings
// already use for lez_tropes.
$genres_slug_map = ( new \LWTV\Statistics\Build\Taxonomy_Optimized() )->get_object_term_slug_map( 'post_type_shows', 'lez_genres' );

// Genre Load: how many genres a show carries, 0 to 4+. lez_genres has no
// "None" placeholder term (unlike lez_tropes), so there is nothing to exclude.
$genres_distribution = \LWTV\Statistics\Build\Term_Count_Distribution::build( $genres_slug_map, (int) $shows_count );
$genres_cells        = \LWTV\Statistics\Build\Term_Count_Distribution::to_cells( $genres_distribution, (int) $shows_count, 100 );

$genres_waffle_segments = array();
foreach ( $genres_distribution as $genres_dist_i => $genres_dist_bucket ) {
	$genres_waffle_segments[] = array(
		'count' => $genres_cells[ $genres_dist_i ],
		'class' => 'b' . $genres_dist_i,
	);
}

// Common Genre Pairings: which two genres most often appear on the same
// show. Counted once here and reused both for the pullstat headline (top 1)
// and the full matchup panel (top 8) below — same pure counting Tropes/
// Intersectionality already use, just aimed at lez_genres. Deliberately
// unlinked: no FacetWP multi-value param is confirmed for lez_genres (same
// conservative call already made for lez_tropes).
$genres_pairs_counted = \LWTV\Statistics\Build\Intersection_Pairs::count_pairs( $genres_slug_map );
$genres_pairs         = \LWTV\Statistics\Build\Intersection_Pairs::top_pairs( $genres_pairs_counted, 8, 2 );

$genres_pair_names = array();
$genres_pair_terms = get_terms(
	array(
		'taxonomy'   => 'lez_genres',
		'hide_empty' => true,
	)
);
// get_terms() can hand back a WP_Error (unregistered taxonomy, DB hiccup);
// iterating that would fatal. The row builders below already fall back to
// slugs when a name is missing, so an empty map is safe.
if ( ! is_wp_error( $genres_pair_terms ) && is_array( $genres_pair_terms ) ) {
	foreach ( $genres_pair_terms as $genres_pair_term ) {
		$genres_pair_names[ $genres_pair_term->slug ] = $genres_pair_term->name;
	}
}

// ---- Pullstats row: average genres/show, share carrying 3+, top pairing ----
// Replaces the old avg/median callout pair — three punchier numbers instead
// of two sentences, per the design handoff.
$genres_stats     = ( new \LWTV\Statistics\Build\Taxonomy_Optimized() )->get_terms_per_object_stats( 'post_type_shows', 'lez_genres' );
$genres_pullstats = array();

if ( (int) $genres_stats['shows'] > 0 ) {
	$genres_pullstats[] = array(
		'icon'   => 'chart-bar.svg',
		'number' => number_format_i18n( (float) $genres_stats['average'], 1 ),
		'label'  => __( 'Number of genres per show, on average.', 'lwtv' ),
	);
}

// 3+ genres = the "3" and "4+" buckets Genre Load already computed above —
// no second query, just add the two percentages Term_Count_Distribution
// already returned.
$genres_3plus_pct = 0.0;
foreach ( $genres_distribution as $genres_dist_bucket ) {
	if ( in_array( $genres_dist_bucket['label'], array( '3', '4+' ), true ) ) {
		$genres_3plus_pct += (float) $genres_dist_bucket['pct'];
	}
}
if ( (int) $shows_count > 0 ) {
	$genres_pullstats[] = array(
		'icon'   => 'chart-pie.svg',
		/* translators: %s: percentage of shows carrying 3 or more genres (one decimal). */
		'number' => sprintf( __( '%s%%', 'lwtv' ), number_format_i18n( $genres_3plus_pct, 1 ) ),
		'label'  => __( 'Percentage of shows with 3 or more genres.', 'lwtv' ),
	);
}

if ( ! empty( $genres_pairs ) ) {
	list( $genres_top_pair_a, $genres_top_pair_b ) = $genres_pairs[0]['slugs'];
	$genres_pullstats[]                            = array(
		'icon'   => 'vest-patches.svg',
		'number' => number_format_i18n( (int) $genres_pairs[0]['count'] ),
		'label'  => sprintf(
			/* translators: 1: genre name, 2: genre name. */
			__( 'Number of shows that pair %1$s with %2$s.', 'lwtv' ),
			$genres_pair_names[ $genres_top_pair_a ] ?? $genres_top_pair_a,
			$genres_pair_names[ $genres_top_pair_b ] ?? $genres_top_pair_b
		),
	);
}

if ( ! empty( $genres_pullstats ) ) :
	?>
	<div class="lwtv-pullstats lwtv-pullstats--three lwtv-statcards">
		<?php foreach ( $genres_pullstats as $genres_pullstat ) : ?>
			<div class="lwtv-statcard">
				<span class="lwtv-statcard-icon">
					<?php echo lwtv_plugin()->get_symbolicon( svg: $genres_pullstat['icon'], icon: 'svg-' . str_replace( '.svg', '', $genres_pullstat['icon'] ), max_size: '18' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<span class="lwtv-statcard-number"><?php echo esc_html( $genres_pullstat['number'] ); ?></span>
				<p class="lwtv-statcard-label"><?php echo esc_html( $genres_pullstat['label'] ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
endif;

// ---- Genre Load (waffle) + broadest-reach spotlight, and Common Pairings ----
// Asymmetric 2/3 + 1/3 layout: Load is the taller panel (waffle + legend +
// poster strip), so it takes the wide column; Pairings' matchup cards sit
// beside it.
$genres_top       = \LWTV\Statistics\Build\Term_Count_Distribution::top_object( $genres_slug_map );
$genres_top_media = '';
if ( $genres_top['id'] > 0 && has_post_thumbnail( $genres_top['id'] ) ) {
	$genres_top_media = get_the_post_thumbnail(
		$genres_top['id'],
		'medium',
		array(
			'class'   => 'lwtv-genreload-poster-img',
			'loading' => 'lazy',
			'alt'     => get_the_title( $genres_top['id'] ),
		)
	);
}

$waffle = array(
	'segments' => $genres_waffle_segments,
	'total'    => 100,
	'columns'  => 20,
	'radius'   => 6,
	'label'    => __( 'Shows grouped by how many genres each carries, from none to four or more.', 'lwtv' ),
);
?>
<div class="lwtv-genres-columns">
	<div class="lwtv-genres-col lwtv-genres-col--main">
		<section class="lwtv-panel bg-light lwtv-genreload">
			<header class="lwtv-panel-head">
				<span class="lwtv-panel-icon genres">
					<?php echo lwtv_plugin()->get_symbolicon( svg: 'theater_masks.svg', icon: 'svg-theater-masks', max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<div>
					<h2 class="lwtv-panel-title"><?php esc_html_e( 'Genre Load', 'lwtv' ); ?></h2>
					<p class="lwtv-panel-sub"><?php esc_html_e( 'How many genres a show carries, by share of all shows', 'lwtv' ); ?></p>
				</div>
			</header>
			<div class="lwtv-genreload-row">
				<div class="lwtv-genreload-figure">
					<?php // phpcs:ignore PEAR.Files.IncludingFile.UseRequire ?>
					<?php include plugin_dir_path( __DIR__ ) . 'partials/waffle.php'; ?>
				</div>
				<ul class="lwtv-legend lwtv-genreload-legend">
					<?php foreach ( $genres_distribution as $genres_dist_i => $genres_dist_bucket ) : ?>
						<?php if ( (int) $genres_dist_bucket['count'] <= 0 ) : ?>
							<?php continue; // No shows in this bucket (e.g. every show carries at least one genre) — nothing to show a % of. ?>
						<?php endif; ?>
						<li class="lwtv-legend-row">
							<span class="lwtv-legend-dot lwtv-legend-dot--b<?php echo (int) $genres_dist_i; ?>"></span>
							<span class="lwtv-legend-name">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: number of genres (or "4+"). */
										_n( '%s genre', '%s genres', ( '1' === $genres_dist_bucket['label'] ) ? 1 : 2, 'lwtv' ),
										$genres_dist_bucket['label']
									)
								);
								?>
							</span>
							<span class="lwtv-legend-pct"><?php echo esc_html( number_format_i18n( $genres_dist_bucket['pct'], 1 ) ); ?>%</span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php if ( '' !== $genres_top_media ) : ?>
				<figure class="lwtv-genreload-poster">
					<a href="<?php echo esc_url( get_permalink( $genres_top['id'] ) ); ?>">
						<?php echo $genres_top_media; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_the_post_thumbnail() returns safe markup. ?>
					</a>
					<figcaption class="lwtv-genreload-poster-cap">
						<span class="lwtv-genreload-poster-eyebrow"><?php esc_html_e( 'Most genre-loaded show', 'lwtv' ); ?></span>
						<?php
						if ( $genres_top['tied'] > 1 ) {
							printf(
								/* translators: 1: show name, 2: number of genres, 3: number of shows tied for the most. */
								esc_html__( '%1$s spans %2$s genres, tied with %3$s other shows for the most.', 'lwtv' ),
								esc_html( get_the_title( $genres_top['id'] ) ),
								esc_html( number_format_i18n( $genres_top['count'] ) ),
								esc_html( number_format_i18n( $genres_top['tied'] - 1 ) )
							);
						} else {
							printf(
								/* translators: 1: show name, 2: number of genres. */
								esc_html__( '%1$s spans %2$s genres, the most of any show.', 'lwtv' ),
								esc_html( get_the_title( $genres_top['id'] ) ),
								esc_html( number_format_i18n( $genres_top['count'] ) )
							);
						}
						?>
					</figcaption>
				</figure>
			<?php endif; ?>
		</section>
	</div>
	<div class="lwtv-genres-col lwtv-genres-col--side">
		<?php
		if ( ! empty( $genres_pairs ) ) {
			$genres_matchup_items = array();
			foreach ( $genres_pairs as $genres_pair ) {
				list( $genres_pair_a, $genres_pair_b ) = $genres_pair['slugs'];
				$genres_matchup_items[]                = array(
					'a'     => $genres_pair_names[ $genres_pair_a ] ?? $genres_pair_a,
					'b'     => $genres_pair_names[ $genres_pair_b ] ?? $genres_pair_b,
					'count' => (int) $genres_pair['count'],
				);
			}

			$matchup = array(
				'items'  => $genres_matchup_items,
				'family' => 'genres',
				'svg'    => 'vest-patches.svg',
				'icon'   => 'svg-vest-patches',
				'title'  => __( 'Common Pairings', 'lwtv' ),
				'sub'    => __( 'Genres that appear together on the same show, by number of shows', 'lwtv' ),
				'unit'   => __( 'shows together', 'lwtv' ),
			);
			// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
			include plugin_dir_path( __DIR__ ) . 'partials/matchup-cards.php';
		}
		?>
	</div>
</div>

<?php
// ---- Still Largely Uncharted: long-tail reframed around representation ----
// Same $genres_data already computed above — display layer only, no new
// query. Framing constraint: the database only contains queer shows, so
// "few shows carry this genre" means queer TV hasn't reached it yet, never
// a claim about representation within the genre generally (there is no
// non-queer denominator to compare against).
$genres_by_count = $genres_data;
uasort( $genres_by_count, fn( $a, $b ) => (int) ( $a['count'] ?? 0 ) <=> (int) ( $b['count'] ?? 0 ) );
$genres_by_count = array_filter( $genres_by_count, fn( $g ) => (int) ( $g['count'] ?? 0 ) > 0 );

$genres_uncharted_n = min( 4, count( $genres_by_count ) );

if ( $genres_uncharted_n > 0 && (int) $shows_count > 0 ) {
	$genres_uncharted       = array_slice( $genres_by_count, 0, $genres_uncharted_n, true );
	$genres_uncharted_total = array_sum( array_column( $genres_uncharted, 'count' ) );
	$genres_uncharted_pct   = round( ( $genres_uncharted_total / (int) $shows_count ) * 100, 1 );
	$genres_uncharted_names = wp_sprintf_l( '%l', array_column( $genres_uncharted, 'name' ) );
	/* translators: %s: combined percentage of all shows the least-explored genres account for (one decimal). */
	$genres_uncharted_gauge_label = sprintf( __( 'The least-explored genres combined account for just %s%% of shows.', 'lwtv' ), number_format_i18n( $genres_uncharted_pct, 1 ) );
	?>
	<section class="lwtv-panel bg-light lwtv-uncharted">
		<header class="lwtv-panel-head">
			<span class="lwtv-panel-icon genres">
				<?php echo lwtv_plugin()->get_symbolicon( svg: 'search.svg', icon: 'svg-search', max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</span>
			<div>
				<h2 class="lwtv-panel-title"><?php esc_html_e( 'Still Largely Uncharted', 'lwtv' ); ?></h2>
				<p class="lwtv-panel-sub"><?php esc_html_e( 'Genres queer TV has barely explored yet', 'lwtv' ); ?></p>
			</div>
		</header>
		<div class="lwtv-uncharted-gauge" role="img" aria-label="<?php echo esc_attr( $genres_uncharted_gauge_label ); ?>">
			<div class="lwtv-uncharted-gauge-fill" style="width:0" data-grow-to="<?php echo esc_attr( (string) $genres_uncharted_pct ); ?>"></div>
		</div>
		<p class="lwtv-uncharted-sentence">
			<?php
			printf(
				/* translators: 1: comma-and-"and"-joined list of the least-explored genre names, 2: their combined percentage of all shows (one decimal). */
				esc_html__( '%1$s combined account for just %2$s%% of shows.', 'lwtv' ),
				esc_html( $genres_uncharted_names ),
				esc_html( number_format_i18n( $genres_uncharted_pct, 1 ) )
			);
			?>
		</p>
		<div class="lwtv-uncharted-tiles">
			<?php foreach ( $genres_uncharted as $genres_uncharted_slug => $genres_uncharted_genre ) : ?>
				<?php
				$genres_uncharted_genre_pct = ( (int) $shows_count > 0 ) ? round( ( (int) $genres_uncharted_genre['count'] / (int) $shows_count ) * 100, 1 ) : 0.0;
				?>
				<a class="lwtv-uncharted-tile" href="<?php echo esc_url( site_url( '/genre/' . $genres_uncharted_slug ) ); ?>">
					<span class="lwtv-uncharted-tile-name"><?php echo esc_html( $genres_uncharted_genre['name'] ?? $genres_uncharted_slug ); ?></span>
					<span class="lwtv-uncharted-tile-stat">
						<?php
						printf(
							/* translators: 1: number of shows, 2: percentage of all shows (one decimal). */
							esc_html__( '%1$s shows · %2$s%%', 'lwtv' ),
							esc_html( number_format_i18n( (int) $genres_uncharted_genre['count'] ) ),
							esc_html( number_format_i18n( $genres_uncharted_genre_pct, 1 ) )
						);
						?>
					</span>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}
?>

<?php
// ---- Genre by Decade ----
// lez_genres is multi-value (a show can carry several genres at once), so
// this can't be Format Mix by Decade's donut — those segments partition
// 100% of a bucket because format is single-value; genre shares don't and
// aren't meant to. Genre_Trend/Genre_Decade_Buckets track each bucket's
// distinct show count separately from its genre tag counts, and this
// renders the top 3 genres per decade as their own "% of shows that
// decade" bars — each row true on its own terms, with no implied total.
$genres_decade_buckets = ( new \LWTV\Statistics\Build\Genre_Trend() )->generate( 20, 3 );

if ( ! empty( $genres_decade_buckets ) ) :
	?>
	<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Genre Mix by Decade', 'lwtv' ); ?></p>
	<p class="lwtv-decade-tile-note"><?php esc_html_e( 'Top 3 genres per decade, each as its own share of shows that premiered in that decade. As shows often carry more than one genre, the three don\'t add up to 100%.', 'lwtv' ); ?></p>
	<div class="lwtv-decade-tile-grid">
		<?php foreach ( $genres_decade_buckets as $genres_decade_bucket ) : ?>
			<?php
			// Mirrors Format Mix by Decade's label construction: the
			// trailing "s" is wrapped so it can be forced lowercase inside
			// an uppercase-transformed eyebrow.
			if ( 'before' === $genres_decade_bucket['type'] ) {
				$genres_decade_label = $genres_decade_bucket['to']
					/* translators: %d: the decade this bucket ends before, e.g. "Before 1980s". */
					? sprintf( __( 'Before %1$d<span class="lwtv-decade-suffix">s</span>', 'lwtv' ), $genres_decade_bucket['to'] )
					: __( 'Earliest years', 'lwtv' );
			} else {
				/* translators: %d: a decade, e.g. "1980s". */
				$genres_decade_label = sprintf( __( '%1$d<span class="lwtv-decade-suffix">s</span>', 'lwtv' ), $genres_decade_bucket['from'] );
			}
			?>
			<div class="lwtv-decade-tile">
				<div class="lwtv-decade-tile-head">
					<span class="lwtv-decade-tile-label"><?php echo wp_kses( $genres_decade_label, array( 'span' => array( 'class' => array() ) ) ); ?></span>
					<span class="lwtv-decade-tile-count">
						<?php
						printf(
							/* translators: %s: number of shows that premiered in this bucket. */
							esc_html__( '%s shows', 'lwtv' ),
							esc_html( number_format_i18n( $genres_decade_bucket['shows'] ) )
						);
						?>
					</span>
				</div>
				<?php if ( empty( $genres_decade_bucket['top'] ) ) : ?>
					<p class="lwtv-decade-tile-empty"><?php esc_html_e( 'No genres tracked yet.', 'lwtv' ); ?></p>
				<?php else : ?>
					<div class="lwtv-decade-tile-rows lwtv-bars--genres">
						<?php foreach ( $genres_decade_bucket['top'] as $genres_decade_row ) : ?>
							<div class="lwtv-decade-tile-row">
								<div class="lwtv-decade-tile-row-head">
									<a class="lwtv-decade-tile-name" href="<?php echo esc_url( site_url( '/genre/' . $genres_decade_row['slug'] ) ); ?>"><?php echo esc_html( $genres_decade_row['name'] ); ?></a>
									<span class="lwtv-decade-tile-value"><?php echo esc_html( number_format_i18n( $genres_decade_row['pct'], 1 ) . '%' ); ?></span>
								</div>
								<div class="progress lwtv-decade-tile-track">
									<div class="progress-bar" role="progressbar" style="width:0" data-grow-to="<?php echo esc_attr( (string) $genres_decade_row['pct'] ); ?>" aria-valuenow="<?php echo esc_attr( (string) $genres_decade_row['count'] ); ?>" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( (string) $genres_decade_bucket['shows'] ); ?>"></div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
endif;
?>

<!-- Genre Breakdown: unchanged data/query, just a denser 2-col layout -->
<div class="lwtv-genres-breakdown-wrap">
	<?php
	$ranked = array(
		'rows'   => $genres_data,
		'total'  => (int) $shows_count,
		'family' => 'genres',
		'svg'    => 'theater_masks.svg',
		'icon'   => 'svg-theater-masks',
		'title'  => __( 'Genre Breakdown', 'lwtv' ),
		/* translators: %s: number of genres. */
		'sub'    => sprintf( __( '%s genres, by number of shows', 'lwtv' ), number_format_i18n( count( $genres_data ) ) ),
		'base'   => '/genre/',
	);
	// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
	include plugin_dir_path( __DIR__ ) . 'partials/ranked-bars.php';
	?>
</div>
