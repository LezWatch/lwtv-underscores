<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Characters → Sexuality: donut (raspberry ramp), a pullstats banner, a
 * decade-by-decade trend (small donut tiles, mirroring Format Mix by
 * Decade), and a Firsts list — the earliest-recorded character for each
 * tracked orientation. lez_sexuality is a single-value taxonomy on
 * Characters (an ACF "select" field wraps it, so a character carries
 * exactly one term) — the trend/firsts data comes from
 * Character_Identity_Trend, which anchors each character to their own
 * earliest on-screen year (from the show-group repeater's `appears`
 * sub-field) since characters have no premiere-year field of their own.
 *
 * @package LezWatch.TV
 *
 * @var int $character_count
 */

use LWTV\Statistics\Build\Character_Identity_Trend;

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
require_once plugin_dir_path( __DIR__ ) . 'partials/phrases.php';

$sex_raw   = lwtv_plugin()->generate_characters_statistics( 'array', 'sexuality' );
$sex_data  = ( is_array( $sex_raw ) && ! empty( $sex_raw ) ) ? (array) reset( $sex_raw ) : array();
$sex_total = (int) $character_count;

// Every tracked-and-populated orientation, counted before the segment loop
// below trims $sex_data display to the top 5 + Other.
$sex_tracked_count = count( array_filter( $sex_data, static fn( $row ) => (int) $row['count'] > 0 ) );

// Lesbian ("homosexual" slug) + bisexual share, for the headline.
$sex_lesbi     = (int) ( $sex_data['homosexual']['count'] ?? 0 ) + (int) ( $sex_data['bisexual']['count'] ?? 0 );
$sex_lesbi_pct = ( $sex_total > 0 ) ? round( ( $sex_lesbi / $sex_total ) * 100, 1 ) : 0;

uasort( $sex_data, fn( $a, $b ) => (int) $b['count'] <=> (int) $a['count'] );
// Green ramp (was a raspberry/pink ramp), matching Gender — same five
// classes gender.php uses, just all five slotted into the main ramp here
// since Sexuality has no separate "cisgender"-style baseline segment to
// carve out first.
$sex_ramp     = array( 'green', 'medgreen', 'midgreen', 'ltgreen', 'palegreen' );
$sex_segments = array();
$sex_named    = 0;
$sex_i        = 0;
foreach ( $sex_data as $sex_row ) {
	if ( $sex_i >= 5 || (int) $sex_row['count'] <= 0 ) {
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
		'class' => 'grey',
	);
}

$donut = array(
	'segments'    => $sex_segments,
	'center'      => $sex_total,
	'center_sub'  => __( 'characters', 'lwtv' ),
	'eyebrow'     => __( 'Sexual Orientation', 'lwtv' ),
	/* translators: %s: a fraction phrase like "Over three quarters". */
	'headline'    => sprintf( __( '%s are lesbian or bisexual', 'lwtv' ), lwtv_stats_fraction_phrase( $sex_lesbi_pct ) ),
	'description' => __( 'Lesbian and bisexual characters make up the bulk of the characters.', 'lwtv' ),
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';

// ---- Pullstats: orientations tracked, long-tail share, rarest orientation's first ----
$sex_identity_trend = new Character_Identity_Trend();
$sex_firsts         = $sex_identity_trend->generate_firsts( 'lez_sexuality' );

$sex_pullstats = array();

if ( $sex_tracked_count > 0 ) {
	$sex_pullstats[] = array(
		'icon'   => 'tag.svg',
		'number' => number_format_i18n( $sex_tracked_count ),
		'label'  => __( 'Distinct sexual orientations tracked.', 'lwtv' ),
	);
}

if ( $sex_total > 0 ) {
	$sex_longtail_pct = round( 100 - $sex_lesbi_pct, 1 );
	$sex_pullstats[]  = array(
		'icon'   => 'chart-pie.svg',
		/* translators: %s: percentage of characters whose orientation is not lesbian or bisexual (one decimal). */
		'number' => sprintf( __( '%s%%', 'lwtv' ), number_format_i18n( $sex_longtail_pct, 1 ) ),
		'label'  => __( 'Percentage with an orientation other than lesbian or bisexual.', 'lwtv' ),
	);
}

// The rarest tracked orientation (with at least one character) and how far
// back its earliest recorded character goes — a single highlight pulled
// from the same "firsts" data the full list renders below.
$sex_rarest_slug  = '';
$sex_rarest_count = null;
foreach ( $sex_data as $sex_rarest_candidate_slug => $sex_rarest_row ) {
	if ( (int) $sex_rarest_row['count'] <= 0 ) {
		continue;
	}
	if ( null === $sex_rarest_count || (int) $sex_rarest_row['count'] < $sex_rarest_count ) {
		$sex_rarest_count = (int) $sex_rarest_row['count'];
		$sex_rarest_slug  = (string) $sex_rarest_candidate_slug;
	}
}
if ( '' !== $sex_rarest_slug && isset( $sex_firsts[ $sex_rarest_slug ] ) ) {
	$sex_pullstats[] = array(
		'icon'   => 'calendar-alt.svg',
		'number' => (string) $sex_firsts[ $sex_rarest_slug ]['year'],
		/* translators: %s: the rarest tracked orientation's name. */
		'label'  => sprintf( __( 'How far back our rarest tracked orientation (%s) goes.', 'lwtv' ), $sex_firsts[ $sex_rarest_slug ]['name'] ),
	);
}

if ( ! empty( $sex_pullstats ) ) :
	?>
	<div class="lwtv-pullstats lwtv-pullstats--three lwtv-statcards lwtv-bars--characters">
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

// ---- Sexuality Mix by Decade: small compact donuts, oldest to newest ----
// Mirrors Format Mix by Decade (shows/formats.php) exactly, just anchored to
// each character's own earliest on-screen year instead of a show's premiere
// year — see Character_Identity_Trend's docblock for why. Colors are
// rank-based per bucket, same convention Format's tiles use.
$sex_decade_buckets = $sex_identity_trend->generate_decades( 'lez_sexuality', 20 );

if ( ! empty( $sex_decade_buckets ) ) :
	?>
	<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Sexuality Mix by Decade', 'lwtv' ); ?></p>
	<div class="lwtv-decade-row">
		<?php foreach ( $sex_decade_buckets as $sex_decade_bucket ) : ?>
			<?php
			$sex_decade_terms = $sex_decade_bucket['terms'];
			arsort( $sex_decade_terms );

			$sex_decade_segments = array();
			$sex_decade_i        = 0;
			foreach ( $sex_decade_terms as $sex_decade_term_name => $sex_decade_term_count ) {
				$sex_decade_segments[] = array(
					'label' => $sex_decade_term_name,
					'count' => $sex_decade_term_count,
					'pct'   => $sex_decade_bucket['pcts'][ $sex_decade_term_name ] ?? 0,
					'class' => $sex_ramp[ min( $sex_decade_i, count( $sex_ramp ) - 1 ) ],
				);
				++$sex_decade_i;
			}

			// The trailing "s" is wrapped in .lwtv-decade-suffix so it can be
			// forced lowercase — this label renders inside an
			// uppercase-transformed eyebrow (see partials/donut.php's compact
			// branch), which would otherwise turn "1980s" into "1980S".
			if ( 'before' === $sex_decade_bucket['type'] ) {
				$sex_decade_label = $sex_decade_bucket['to']
					/* translators: %d: the decade this bucket ends before, e.g. "Before 1980s". */
					? sprintf( __( 'Before %1$d<span class="lwtv-decade-suffix">s</span>', 'lwtv' ), $sex_decade_bucket['to'] )
					: __( 'Earliest years', 'lwtv' );
			} else {
				/* translators: %d: a decade, e.g. "1980s". */
				$sex_decade_label = sprintf( __( '%1$d<span class="lwtv-decade-suffix">s</span>', 'lwtv' ), $sex_decade_bucket['from'] );
			}

			$donut = array(
				'layout'        => 'compact',
				'segments'      => $sex_decade_segments,
				'eyebrow'       => $sex_decade_label,
				'center_pct'    => (int) round( $sex_decade_bucket['lead_pct'] ),
				'center_family' => $sex_decade_segments[0]['class'] ?? 'green',
				'center_sub'    => $sex_decade_bucket['lead_term'],
			);
			?>
			<div class="lwtv-decade-tile">
				<?php
				// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
				include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
				?>
				<p class="lwtv-decade-count">
					<?php
					printf(
						/* translators: %s: number of characters first on screen in this bucket. */
						esc_html__( '%s characters', 'lwtv' ),
						esc_html( number_format_i18n( $sex_decade_bucket['total'] ) )
					);
					?>
				</p>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
endif;

// ---- Firsts: earliest-recorded character per tracked orientation ----
if ( ! empty( $sex_firsts ) ) :
	// Ordered oldest first, matching the decade trend's own flow.
	$sex_firsts_sorted = $sex_firsts;
	uasort( $sex_firsts_sorted, static fn( $a, $b ) => $a['year'] <=> $b['year'] );
	?>
	<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Firsts', 'lwtv' ); ?></p>
	<ul class="lwtv-identity-firsts">
		<?php foreach ( $sex_firsts_sorted as $sex_first ) : ?>
			<li class="lwtv-identity-firsts-row">
				<span class="lwtv-identity-firsts-term"><?php echo esc_html( $sex_first['name'] ); ?></span>
				<a class="lwtv-identity-firsts-name" href="<?php echo esc_url( $sex_first['url'] ); ?>"><?php echo esc_html( $sex_first['char_name'] ); ?></a>
				<span class="lwtv-identity-firsts-year"><?php echo esc_html( (string) $sex_first['year'] ); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
