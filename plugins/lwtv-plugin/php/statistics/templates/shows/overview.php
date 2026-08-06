<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows overview: metric cards, the section index (one hero number per
 * subpage, all from already-cached transforms, each linking through),
 * trope-gap pull-stats, and the library depth band. The old top
 * tropes/genres panels are gone — their headline names live in the
 * index now, and the full ranked lists are one click away.
 *
 * @package LezWatch.TV
 *
 * @var int   $shows_count
 * @var int   $count_tropes
 * @var int   $count_genres
 * @var array $top_tropes    slug => ['name','count', …], top 10 by count.
 * @var array $top_genres    slug => ['name','count', …], top 10 by count.
 * @var int   $trope_buried  count of shows tagged with the buried/dead-queers trope.
 * @var int   $trope_happy   count of shows tagged with the happy-ending trope.
 */

use LWTV\Statistics\Build\Catalog_Depth;
use LWTV\Statistics\Build\Score_Distribution;
use LWTV\Statistics\Build\Scores as Build_Scores;
use LWTV\Statistics\Build\Series_Trend;
use LWTV\Statistics\Build\Star_Podium;
use LWTV\Statistics\Build\Trigger_Levels;
use LWTV\Statistics\Build\We_Love;
use LWTV\Statistics\Build\Worth_It_Grid;

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
require_once plugin_dir_path( __DIR__ ) . 'partials/phrases.php';

// No sparklines on these cards: the term-count cards never had a real
// time series (the old ones were decorative fakes), and the Shows card
// drops its real one so the row reads as a matched set.
$shows_cards = array(
	array(
		'type'    => 'shows',
		'label'   => __( 'Shows', 'lwtv' ),
		'count'   => (int) $shows_count,
		'caption' => __( 'TV series & films', 'lwtv' ),
		'svg'     => 'tv.svg',
		'icon'    => 'svg-television',
	),
	array(
		'type'    => 'characters', // green family (Tropes).
		'label'   => __( 'Tropes', 'lwtv' ),
		'count'   => (int) $count_tropes,
		'caption' => __( 'Distinct tropes tracked', 'lwtv' ),
		'svg'     => 'tag.svg',
		'icon'    => 'svg-tag',
	),
	array(
		'type'    => 'actors', // amber family (Genres).
		'label'   => __( 'Genres', 'lwtv' ),
		'count'   => (int) $count_genres,
		'caption' => __( 'Distinct genres tracked', 'lwtv' ),
		'svg'     => 'theater_masks.svg',
		'icon'    => 'svg-theater-masks',
	),
);

/*
 * The section index: each subpage's best already-cached number as a
 * linked tease. Every figure below comes from the same transforms the
 * subpages themselves run, so this stays in lockstep with them.
 */
$idx_cards = array();

// Formats → the dominant format and its rough share.
$idx_fmt_raw  = lwtv_plugin()->generate_shows_statistics( 'array', 'formats' );
$idx_fmt_data = ( is_array( $idx_fmt_raw ) && ! empty( $idx_fmt_raw ) ) ? (array) reset( $idx_fmt_raw ) : array();
$idx_fmt_top  = reset( $idx_fmt_data );
if ( ! empty( $idx_fmt_top['name'] ) && (int) $shows_count > 0 ) {
	$idx_fmt_pct = round( ( (int) $idx_fmt_top['count'] / (int) $shows_count ) * 100, 1 );

	$idx_cards['formats'] = array(
		'eyebrow' => __( 'Formats', 'lwtv' ),
		'figure'  => $idx_fmt_top['name'],
		/* translators: %s: a fraction phrase, e.g. "Over half", lowercased mid-sentence. */
		'text'    => sprintf( __( 'is the dominant format — %s of all shows.', 'lwtv' ), lcfirst( lwtv_stats_fraction_phrase( $idx_fmt_pct ) ) ),
		'url'     => $baseurl . 'formats/',
	);
}

// Tropes → the most common trope by name.
$idx_top_trope = is_array( $top_tropes ) ? reset( $top_tropes ) : false;
if ( ! empty( $idx_top_trope['name'] ) ) {
	$idx_trope_next = next( $top_tropes );
	$idx_trope_tied = ( ! empty( $idx_trope_next['count'] ) && (int) $idx_trope_next['count'] === (int) $idx_top_trope['count'] );

	$idx_cards['tropes'] = array(
		'eyebrow' => __( 'Tropes', 'lwtv' ),
		'figure'  => $idx_top_trope['name'],
		'text'    => $idx_trope_tied
			/* translators: 1: total number of tropes tracked, 2: shows carrying the top trope. */
			? sprintf( _n( 'ties for the lead among the %1$s tropes we track, on %2$s show.', 'ties for the lead among the %1$s tropes we track, on %2$s shows.', (int) $idx_top_trope['count'], 'lwtv' ), number_format_i18n( (int) $count_tropes ), number_format_i18n( (int) $idx_top_trope['count'] ) )
			/* translators: 1: total number of tropes tracked, 2: shows carrying the top trope. */
			: sprintf( _n( 'leads the %1$s tropes we track, on %2$s show.', 'leads the %1$s tropes we track, on %2$s shows.', (int) $idx_top_trope['count'], 'lwtv' ), number_format_i18n( (int) $count_tropes ), number_format_i18n( (int) $idx_top_trope['count'] ) ),
		'url'     => $baseurl . 'tropes/',
	);
}

// Genres → the dominant genre and its share of all shows.
$idx_top_genre = is_array( $top_genres ) ? reset( $top_genres ) : false;
if ( ! empty( $idx_top_genre['name'] ) && (int) $shows_count > 0 ) {
	$idx_genre_next = next( $top_genres );
	$idx_genre_tied = ( ! empty( $idx_genre_next['count'] ) && (int) $idx_genre_next['count'] === (int) $idx_top_genre['count'] );
	$idx_genre_pct  = number_format_i18n( round( ( (int) $idx_top_genre['count'] / (int) $shows_count ) * 100, 1 ), 1 );

	$idx_cards['genres'] = array(
		'eyebrow' => __( 'Genres', 'lwtv' ),
		'figure'  => $idx_top_genre['name'],
		'text'    => $idx_genre_tied
			/* translators: 1: total number of genres tracked, 2: the top genre's share of all shows (one decimal). */
			? sprintf( __( 'ties at the top of the %1$s genres — %2$s%% of all shows.', 'lwtv' ), number_format_i18n( (int) $count_genres ), $idx_genre_pct )
			/* translators: 1: total number of genres tracked, 2: the top genre's share of all shows (one decimal). */
			: sprintf( __( 'tops the %1$s genres — %2$s%% of all shows.', 'lwtv' ), number_format_i18n( (int) $count_genres ), $idx_genre_pct ),
		'url'     => $baseurl . 'genres/',
	);
}

// Intersectionality → share of shows with at least one intersection.
$idx_inter = ( new \LWTV\Statistics\Build\Taxonomy_Optimized() )->get_terms_per_object_stats( 'post_type_shows', 'lez_intersections' );
if ( (int) ( $idx_inter['shows'] ?? 0 ) > 0 && (int) $shows_count > 0 ) {
	$idx_inter_pct = round( ( (int) $idx_inter['shows'] / (int) $shows_count ) * 100, 1 );

	$idx_cards['intersectionality'] = array(
		'eyebrow' => __( 'Intersectionality', 'lwtv' ),
		'figure'  => ( $idx_inter_pct >= 100 ) ? __( 'Every show', 'lwtv' ) : number_format_i18n( $idx_inter_pct, 1 ) . '%',
		'text'    => ( $idx_inter_pct >= 100 )
			? __( 'carries at least one intersectional identity.', 'lwtv' )
			: __( 'of shows carry at least one intersectional identity.', 'lwtv' ),
		'url'     => $baseurl . 'intersectionality/',
	);
}

// Scores → median + the 90+ club.
$idx_score_values = ( new Build_Scores() )->get_score_values();
if ( ! empty( $idx_score_values ) ) {
	$idx_median = (int) round( Score_Distribution::median( $idx_score_values ) );
	$idx_high   = (int) Score_Distribution::tails( $idx_score_values )['high'];

	$idx_cards['scores'] = array(
		'eyebrow' => __( 'Scores', 'lwtv' ),
		'figure'  => number_format_i18n( $idx_median ),
		'text'    => ( $idx_high <= 50 )
			/* translators: %s: number of shows scoring 90 or higher. */
			? sprintf( _n( 'is the median show score; only %s show has ever hit 90+.', 'is the median show score; only %s shows have ever hit 90+.', $idx_high, 'lwtv' ), number_format_i18n( $idx_high ) )
			/* translators: %s: number of shows scoring 90 or higher. */
			: sprintf( __( 'is the median show score; %s shows have hit 90+.', 'lwtv' ), number_format_i18n( $idx_high ) ),
		'url'     => $baseurl . 'scores/',
	);
}

// Triggers → the scarcity ratio.
$idx_trig_raw    = lwtv_plugin()->generate_shows_statistics( 'array', 'triggers' );
$idx_trig_data   = ( is_array( $idx_trig_raw ) && ! empty( $idx_trig_raw ) ) ? (array) reset( $idx_trig_raw ) : array();
$idx_trig_counts = array();
foreach ( Trigger_Levels::ORDER as $idx_level ) {
	$idx_trig_counts[ $idx_level ] = isset( $idx_trig_data[ $idx_level ] ) ? (int) $idx_trig_data[ $idx_level ]['count'] : 0;
}
$idx_trig = Trigger_Levels::facts( $idx_trig_counts, (int) $shows_count );
if ( $idx_trig['flagged'] > 0 ) {
	$idx_cards['triggers'] = array(
		'eyebrow' => __( 'Triggers', 'lwtv' ),
		/* translators: %s: the "1 in N" denominator for flagged shows. */
		'figure'  => sprintf( __( '1 in %s', 'lwtv' ), number_format_i18n( $idx_trig['scarcity_ratio'] ) ),
		/* translators: %s: number of flagged shows. */
		'text'    => sprintf( __( 'shows carries a content warning — %s in all.', 'lwtv' ), number_format_i18n( $idx_trig['flagged'] ) ),
		'url'     => $baseurl . 'triggers/',
	);
}

// Stars → the leading tier and its share of all stars.
$idx_star_raw    = lwtv_plugin()->generate_shows_statistics( 'array', 'stars' );
$idx_star_data   = ( is_array( $idx_star_raw ) && ! empty( $idx_star_raw ) ) ? (array) reset( $idx_star_raw ) : array();
$idx_star_counts = array();
foreach ( array( 'gold', 'silver', 'bronze', 'anti' ) as $idx_tier ) {
	$idx_star_counts[ $idx_tier ] = isset( $idx_star_data[ $idx_tier ] ) ? (int) $idx_star_data[ $idx_tier ]['count'] : 0;
}
$idx_star_facts  = Star_Podium::facts( $idx_star_counts, (int) $shows_count );
$idx_star_labels = array(
	'gold'   => __( 'Gold', 'lwtv' ),
	'silver' => __( 'Silver', 'lwtv' ),
	'bronze' => __( 'Bronze', 'lwtv' ),
);
if ( '' !== $idx_star_facts['leader'] ) {
	$idx_cards['stars'] = array(
		'eyebrow' => __( 'Stars', 'lwtv' ),
		'figure'  => $idx_star_labels[ $idx_star_facts['leader'] ],
		/* translators: %s: the leading tier's share of all stars (whole percent). */
		'text'    => sprintf( __( 'leads the medal count, with %s%% of all stars awarded.', 'lwtv' ), number_format_i18n( $idx_star_facts['leader_share_pct'] ) ),
		'url'     => $baseurl . 'stars/',
	);
}

// Worth It → the Yes share of rated shows.
$idx_worth_raw  = lwtv_plugin()->generate_shows_statistics( 'array', 'worth-it' );
$idx_worth_data = ( is_array( $idx_worth_raw ) && ! empty( $idx_worth_raw ) ) ? (array) reset( $idx_worth_raw ) : array();
$idx_worth_sum  = 0;
$idx_worth_yes  = 0;
foreach ( Worth_It_Grid::ORDER as $idx_verdict ) {
	$idx_worth_count = isset( $idx_worth_data[ $idx_verdict ] ) ? (int) $idx_worth_data[ $idx_verdict ]['count'] : 0;
	$idx_worth_sum  += $idx_worth_count;
	if ( 'yes' === $idx_verdict ) {
		$idx_worth_yes = $idx_worth_count;
	}
}
if ( $idx_worth_sum > 0 && $idx_worth_yes > 0 ) {
	$idx_worth_pct = round( ( $idx_worth_yes / $idx_worth_sum ) * 100, 1 );

	$idx_cards['worth-it'] = array(
		'eyebrow' => __( 'Worth It', 'lwtv' ),
		'figure'  => number_format_i18n( $idx_worth_pct, 1 ) . '%',
		/* translators: %s: a fraction phrase, e.g. "Nearly two thirds", lowercased mid-sentence. */
		'text'    => sprintf( __( 'of rated shows are a clear yes — %s of the catalogue.', 'lwtv' ), lcfirst( lwtv_stats_fraction_phrase( $idx_worth_pct ) ) ),
		'url'     => $baseurl . 'worth-it/',
	);
}

// We Love → the rarity ratio.
$idx_loved_n = count( ( new We_Love() )->get_roster() );
if ( $idx_loved_n > 0 && (int) $shows_count > 0 ) {
	$idx_cards['we-love-it'] = array(
		'eyebrow' => __( 'We Love It', 'lwtv' ),
		/* translators: %s: the "1 in N" denominator for loved shows. */
		'figure'  => sprintf( __( '1 in %s', 'lwtv' ), number_format_i18n( max( 2, (int) round( (int) $shows_count / $idx_loved_n ) ) ) ),
		'count'   => 0,
		/* translators: %s: number of loved shows. */
		'text'    => sprintf( _n( 'shows earns the We Love flag — %s so far.', 'shows earns the We Love flag — %s so far.', $idx_loved_n, 'lwtv' ), number_format_i18n( $idx_loved_n ) ),
		'url'     => $baseurl . 'we-love-it/',
	);
}

// On Air → the adaptive trend state.
$idx_oa_raw  = lwtv_plugin()->generate_shows_statistics( 'array', 'on-air' );
$idx_oa_data = ( is_array( $idx_oa_raw ) && ! empty( $idx_oa_raw ) ) ? (array) reset( $idx_oa_raw ) : array();
$idx_oa_pts  = array();
foreach ( $idx_oa_data as $idx_oa_row ) {
	$idx_oa_pts[] = array(
		'year'  => (int) ( $idx_oa_row['name'] ?? 0 ),
		'count' => (int) ( $idx_oa_row['count'] ?? 0 ),
	);
}
$idx_oa = Series_Trend::classify( $idx_oa_pts, (int) gmdate( 'Y' ) );
if ( ! empty( $idx_oa ) ) {
	switch ( $idx_oa['state'] ) {
		case 'at-peak':
			/* translators: %s: the latest complete year. */
			$idx_oa_text = sprintf( __( 'shows on air in %s — the most ever recorded.', 'lwtv' ), (string) $idx_oa['latest_year'] );
			break;
		case 'recovering':
			/* translators: 1: the latest complete year, 2: the peak year. */
			$idx_oa_text = sprintf( __( 'shows on air in %1$s, climbing again after the %2$s peak.', 'lwtv' ), (string) $idx_oa['latest_year'], (string) $idx_oa['peak_year'] );
			break;
		case 'receding':
			/* translators: 1: the latest complete year, 2: the peak year. */
			$idx_oa_text = sprintf( __( 'shows on air in %1$s, down from the %2$s peak.', 'lwtv' ), (string) $idx_oa['latest_year'], (string) $idx_oa['peak_year'] );
			break;
		default:
			/* translators: 1: the latest complete year, 2: the peak year. */
			$idx_oa_text = sprintf( __( 'shows on air in %1$s, holding below the %2$s peak.', 'lwtv' ), (string) $idx_oa['latest_year'], (string) $idx_oa['peak_year'] );
			break;
	}

	$idx_cards['on-air'] = array(
		'eyebrow' => __( 'On Air', 'lwtv' ),
		'figure'  => number_format_i18n( $idx_oa['latest_count'] ),
		'count'   => (int) $idx_oa['latest_count'],
		'text'    => $idx_oa_text,
		'url'     => $baseurl . 'on-air/',
	);
}

// Mirror the subnav order, so the band doubles as a visual table of contents.
$idx_order  = array( 'formats', 'tropes', 'genres', 'intersectionality', 'stars', 'scores', 'triggers', 'worth-it', 'we-love-it', 'on-air' );
$idx_sorted = array();
foreach ( $idx_order as $idx_key ) {
	if ( isset( $idx_cards[ $idx_key ] ) ) {
		$idx_sorted[ $idx_key ] = $idx_cards[ $idx_key ];
	}
}
$idx_cards = $idx_sorted;

// Promote one stat to the lead plate (editorial choice, with a documented
// fallback order) and remove it from the rail so it never appears twice.
$idx_lead = array();
foreach ( array( 'on-air', 'we-love-it', 'worth-it' ) as $idx_lead_key ) {
	if ( isset( $idx_cards[ $idx_lead_key ] ) ) {
		$idx_lead = $idx_cards[ $idx_lead_key ];
		unset( $idx_cards[ $idx_lead_key ] );
		break;
	}
}

// The library: seasons/episodes totals, only when coverage can carry them.
$idx_depth    = ( new Catalog_Depth() )->get_totals();
$idx_depth_ok = ( ! empty( $idx_depth['n'] ) && $idx_depth['with_episodes'] >= 0.8 * $idx_depth['n'] && $idx_depth['episodes_sum'] > 0 );
?>

<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Shows at a Glance', 'lwtv' ); ?></p>

<div class="lwtv-metric-grid lwtv-metric-grid--3">
	<?php
	foreach ( $shows_cards as $shows_card ) {
		?>
		<div class="lwtv-metric-card card-header <?php echo esc_attr( $shows_card['type'] ); ?>">
			<div class="lwtv-metric-top">
				<span class="lwtv-stats-eyebrow"><?php echo esc_html( $shows_card['label'] ); ?></span>
				<span class="lwtv-metric-icon <?php echo esc_attr( $shows_card['type'] ); ?>">
					<?php echo lwtv_plugin()->get_symbolicon( svg: $shows_card['svg'], icon: $shows_card['icon'], max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
			</div>
			<span class="lwtv-metric-number" data-count-to="<?php echo (int) $shows_card['count']; ?>"><?php echo esc_html( number_format_i18n( $shows_card['count'] ) ); ?></span>
			<span class="lwtv-metric-caption"><?php echo esc_html( $shows_card['caption'] ); ?></span>
		</div>
		<?php
	}
	?>
</div>

<?php
// Fewer than four stats with data would render a lead plus a stub — skip
// the whole module instead (per the handoff's edge-case rule).
$idx_total = count( $idx_cards ) + ( empty( $idx_lead ) ? 0 : 1 );
?>
<?php if ( $idx_total >= 4 ) : ?>
	<section class="lwtv-hl-section" aria-labelledby="lwtv-hl-heading">
		<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section" id="lwtv-hl-heading"><?php esc_html_e( 'The Headlines', 'lwtv' ); ?></p>
		<div class="lwtv-hl bg-light">
			<?php if ( ! empty( $idx_lead ) ) : ?>
				<a class="lwtv-hl-lead" href="<?php echo esc_url( $idx_lead['url'] ); ?>">
					<span class="lwtv-hl-lead-figwrap">
						<span class="lwtv-hl-lead-label"><?php echo esc_html( $idx_lead['eyebrow'] ); ?></span>
						<span class="lwtv-hl-lead-fig"><?php echo esc_html( $idx_lead['figure'] ); ?></span>
					</span>
					<span class="lwtv-hl-lead-text"><?php echo esc_html( $idx_lead['text'] ); ?> <span class="lwtv-hl-arrow" aria-hidden="true">&#8599;</span></span>
				</a>
			<?php endif; ?>
			<ul class="lwtv-hl-rail">
				<?php foreach ( $idx_cards as $idx_key => $idx_card ) : ?>
					<li>
						<a class="lwtv-hl-item lwtv-hl-item--<?php echo esc_attr( $idx_key ); ?>" href="<?php echo esc_url( $idx_card['url'] ); ?>">
							<span class="lwtv-hl-spine" aria-hidden="true"></span>
							<span class="lwtv-hl-body">
								<span class="lwtv-hl-head">
									<span class="lwtv-hl-label"><?php echo esc_html( $idx_card['eyebrow'] ); ?></span>
									<span class="lwtv-hl-arrow" aria-hidden="true">&#8599;</span>
								</span>
								<span class="lwtv-hl-fig"><?php echo esc_html( $idx_card['figure'] ); ?></span>
								<span class="lwtv-hl-text"><?php echo esc_html( $idx_card['text'] ); ?></span>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>
<?php endif; ?>

<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'The Trope Gap', 'lwtv' ); ?></p>
<div class="lwtv-pullstats">
	<div class="lwtv-tropegap card-header dead-characters">
		<div class="lwtv-tropegap-top">
			<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Bury Your Queers', 'lwtv' ); ?></span>
			<span class="lwtv-tropegap-icon byq"><?php echo lwtv_plugin()->get_symbolicon( svg: 'hand-holding-skull.svg', icon: 'svg-skull', max_size: '22' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</div>
		<span class="lwtv-tropegap-number" data-count-to="<?php echo (int) $trope_buried; ?>"><?php echo esc_html( number_format_i18n( $trope_buried ) ); ?></span>
		<p class="lwtv-tropegap-desc"><?php esc_html_e( 'shows kill off a queer character — the most common harmful trope in the catalogue.', 'lwtv' ); ?></p>
		<a role="button" class="btn lwtv-tropegap-link" href="<?php echo esc_url( site_url( '/trope/dead-queers/' ) ); ?>"><?php esc_html_e( 'See these shows', 'lwtv' ); ?> <span aria-hidden="true">&#8599;</span></a>
	</div>
	<div class="lwtv-tropegap card-header happy-endings">
		<div class="lwtv-tropegap-top">
			<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Happy Endings', 'lwtv' ); ?></span>
			<span class="lwtv-tropegap-icon he"><?php echo lwtv_plugin()->get_symbolicon( svg: 'heart-circle.svg', icon: 'svg-heart', max_size: '22' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</div>
		<span class="lwtv-tropegap-number" data-count-to="<?php echo (int) $trope_happy; ?>"><?php echo esc_html( number_format_i18n( $trope_happy ) ); ?></span>
		<p class="lwtv-tropegap-desc"><?php esc_html_e( 'shows give their queer characters a happy ending.', 'lwtv' ); ?></p>
		<a role="button" class="btn lwtv-tropegap-link" href="<?php echo esc_url( site_url( '/trope/happy-ending/' ) ); ?>"><?php esc_html_e( 'See these shows', 'lwtv' ); ?> <span aria-hidden="true">&#8599;</span></a>
	</div>
</div>

<?php if ( $idx_depth_ok ) : ?>
	<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'The Library', 'lwtv' ); ?></p>
	<div class="lwtv-lib bg-light">
		<div class="lwtv-lib-figures">
			<div class="lwtv-lib-figure">
				<span class="lwtv-lib-num" data-count-to="<?php echo (int) $idx_depth['seasons_sum']; ?>"><?php echo esc_html( number_format_i18n( $idx_depth['seasons_sum'] ) ); ?></span>
				<span class="lwtv-lib-sub"><?php esc_html_e( 'seasons', 'lwtv' ); ?></span>
			</div>
			<div class="lwtv-lib-figure">
				<span class="lwtv-lib-num" data-count-to="<?php echo (int) $idx_depth['episodes_sum']; ?>"><?php echo esc_html( number_format_i18n( $idx_depth['episodes_sum'] ) ); ?></span>
				<span class="lwtv-lib-sub"><?php esc_html_e( 'episodes', 'lwtv' ); ?></span>
			</div>
		</div>
		<p class="lwtv-lib-caption">
			<?php
			printf(
				/* translators: %s: total number of shows. */
				esc_html__( 'of queer TV documented across %s shows — every one watched, catalogued, and argued over.', 'lwtv' ),
				esc_html( number_format_i18n( (int) $shows_count ) )
			);
			?>
		</p>
	</div>
<?php endif; ?>
