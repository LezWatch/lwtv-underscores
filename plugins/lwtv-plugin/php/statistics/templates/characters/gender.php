<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Characters → Gender: a pullstats banner, donut (grey cisgender + green-ramp
 * minorities), a decade-by-decade trend (small donut tiles, mirroring Format
 * Mix by Decade), and a Firsts list — the earliest-recorded character for
 * each tracked identity. lez_gender is a single-value taxonomy on Characters
 * (an ACF "select" field wraps it, so a character carries exactly one term)
 * — the trend/firsts data comes from Character_Identity_Trend, which anchors
 * each character to their own earliest on-screen year (from the show-group
 * repeater's `appears` sub-field) since characters have no premiere-year
 * field of their own.
 *
 * Pullstats render before the donut (moved up per request) — the two are
 * otherwise independent, so the swap is just render order.
 *
 * @package LezWatch.TV
 *
 * @var int $character_count
 */

use LWTV\Statistics\Build\Character_Identity_Trend;

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
require_once plugin_dir_path( __DIR__ ) . 'partials/phrases.php';

$gen_raw   = lwtv_plugin()->generate_characters_statistics( 'array', 'gender' );
$gen_data  = ( is_array( $gen_raw ) && ! empty( $gen_raw ) ) ? (array) reset( $gen_raw ) : array();
$gen_total = (int) $character_count;

$gen_cis      = isset( $gen_data['cisgender'] ) ? (int) $gen_data['cisgender']['count'] : 0;
$gen_cis_name = isset( $gen_data['cisgender'] ) ? $gen_data['cisgender']['name'] : __( 'Cisgender', 'lwtv' );
unset( $gen_data['cisgender'] );

// Every tracked-and-populated identity besides cisgender, +1 for cisgender
// itself — counted before the segment loop below trims $gen_data display
// to the top 4 + Other, so this reflects every identity with at least one
// character, not just the ones that made the donut.
$gen_tracked_count = 1 + count( array_filter( $gen_data, static fn( $row ) => (int) $row['count'] > 0 ) );

// ---- Pullstats: identities tracked, non-cisgender share, rarest identity's first ----
$gen_identity_trend = new Character_Identity_Trend();
$gen_firsts         = $gen_identity_trend->generate_firsts( 'lez_gender' );

$gen_pullstats = array();

if ( $gen_tracked_count > 0 ) {
	$gen_pullstats[] = array(
		'icon'   => 'tag.svg',
		'number' => number_format_i18n( $gen_tracked_count ),
		'label'  => __( 'Distinct gender identities tracked.', 'lwtv' ),
	);
}

if ( $gen_total > 0 ) {
	$gen_noncis_pct  = round( ( ( $gen_total - $gen_cis ) / $gen_total ) * 100, 1 );
	$gen_pullstats[] = array(
		'icon'   => 'venus-double.svg',
		/* translators: %s: percentage of characters who are not cisgender (one decimal). */
		'number' => sprintf( __( '%s%%', 'lwtv' ), number_format_i18n( $gen_noncis_pct, 1 ) ),
		'label'  => __( 'Percentage of characters who are not cisgender.', 'lwtv' ),
	);
}

// The rarest tracked identity (excluding cisgender, and only ones that
// actually have at least one character) and how far back its earliest
// recorded character goes — a single highlight pulled from the same
// "firsts" data the full list renders below. $gen_data hasn't been sorted
// or trimmed yet at this point, so every tracked identity is still in play.
$gen_rarest_slug  = '';
$gen_rarest_count = null;
foreach ( $gen_data as $gen_rarest_candidate_slug => $gen_rarest_row ) {
	if ( (int) $gen_rarest_row['count'] <= 0 ) {
		continue;
	}
	if ( null === $gen_rarest_count || (int) $gen_rarest_row['count'] < $gen_rarest_count ) {
		$gen_rarest_count = (int) $gen_rarest_row['count'];
		$gen_rarest_slug  = (string) $gen_rarest_candidate_slug;
	}
}
if ( '' !== $gen_rarest_slug && isset( $gen_firsts[ $gen_rarest_slug ] ) ) {
	$gen_pullstats[] = array(
		'icon'   => 'calendar-alt.svg',
		'number' => (string) $gen_firsts[ $gen_rarest_slug ]['year'],
		/* translators: %s: the rarest tracked gender identity's name. */
		'label'  => sprintf( __( 'How far back our rarest tracked identity (%s) goes.', 'lwtv' ), $gen_firsts[ $gen_rarest_slug ]['name'] ),
	);
}

if ( ! empty( $gen_pullstats ) ) :
	?>
	<div class="lwtv-pullstats lwtv-pullstats--three lwtv-statcards lwtv-bars--characters">
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

// ---- Donut: grey cisgender + green-ramp minorities ----
$gen_segments = array(
	array(
		'label' => $gen_cis_name,
		'count' => $gen_cis,
		'pct'   => ( $gen_total > 0 ) ? round( ( $gen_cis / $gen_total ) * 100, 1 ) : 0,
		'class' => 'grey',
	),
);

uasort( $gen_data, fn( $a, $b ) => (int) $b['count'] <=> (int) $a['count'] );
// Green ramp (was a raspberry/pink ramp) — --green reuses the existing solid
// dark-green segment class rather than duplicating its value under a new
// name, so it keeps that class's dark-mode swap to green-light for free.
$gen_ramp  = array( 'green', 'medgreen', 'midgreen', 'ltgreen' );
$gen_named = $gen_cis;
$gen_i     = 0;
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
		'class' => 'palegreen',
	);
}

$donut = array(
	'segments'    => $gen_segments,
	'center'      => $gen_cis,
	'center_sub'  => __( 'cisgender', 'lwtv' ),
	'eyebrow'     => __( 'Gender Identity', 'lwtv' ),
	/* translators: %s: a fraction phrase like "Over three quarters". */
	'headline'    => sprintf( __( '%s are cisgender', 'lwtv' ), lwtv_stats_fraction_phrase( ( $gen_total > 0 ) ? round( ( $gen_cis / $gen_total ) * 100, 1 ) : 0 ) ),
	'description' => __( 'Cisgender characters dominate, but the database tracks a growing range of trans, non-binary and genderqueer identities.', 'lwtv' ),
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';

// ---- Gender Mix by Decade: small compact donuts, oldest to newest ----
// Mirrors Format Mix by Decade (shows/formats.php) exactly, just anchored to
// each character's own earliest on-screen year instead of a show's premiere
// year — see Character_Identity_Trend's docblock for why. Colors are
// rank-based per bucket (same convention Format's tiles use) rather than
// keeping cisgender specially grey in every tile; with cisgender the
// majority in nearly every decade, rank-based green already lands on it
// almost everywhere anyway.
$gen_decade_buckets = $gen_identity_trend->generate_decades( 'lez_gender', 20 );

if ( ! empty( $gen_decade_buckets ) ) :
	?>
	<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Gender Mix by Decade', 'lwtv' ); ?></p>
	<div class="lwtv-decade-row">
		<?php foreach ( $gen_decade_buckets as $gen_decade_bucket ) : ?>
			<?php
			$gen_decade_terms = $gen_decade_bucket['terms'];
			arsort( $gen_decade_terms );

			$gen_decade_segments = array();
			$gen_decade_i        = 0;
			foreach ( $gen_decade_terms as $gen_decade_term_name => $gen_decade_term_count ) {
				$gen_decade_segments[] = array(
					'label' => $gen_decade_term_name,
					'count' => $gen_decade_term_count,
					'pct'   => $gen_decade_bucket['pcts'][ $gen_decade_term_name ] ?? 0,
					'class' => $gen_ramp[ min( $gen_decade_i, count( $gen_ramp ) - 1 ) ],
				);
				++$gen_decade_i;
			}

			// The trailing "s" is wrapped in .lwtv-decade-suffix so it can be
			// forced lowercase — this label renders inside an
			// uppercase-transformed eyebrow (see partials/donut.php's compact
			// branch), which would otherwise turn "1980s" into "1980S".
			if ( 'before' === $gen_decade_bucket['type'] ) {
				$gen_decade_label = $gen_decade_bucket['to']
					/* translators: %d: the decade this bucket ends before, e.g. "Before 1980s". */
					? sprintf( __( 'Before %1$d<span class="lwtv-decade-suffix">s</span>', 'lwtv' ), $gen_decade_bucket['to'] )
					: __( 'Earliest years', 'lwtv' );
			} else {
				/* translators: %d: a decade, e.g. "1980s". */
				$gen_decade_label = sprintf( __( '%1$d<span class="lwtv-decade-suffix">s</span>', 'lwtv' ), $gen_decade_bucket['from'] );
			}

			$donut = array(
				'layout'        => 'compact',
				'segments'      => $gen_decade_segments,
				'eyebrow'       => $gen_decade_label,
				'center_pct'    => (int) round( $gen_decade_bucket['lead_pct'] ),
				'center_family' => $gen_decade_segments[0]['class'] ?? 'green',
				'center_sub'    => $gen_decade_bucket['lead_term'],
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
						esc_html( number_format_i18n( $gen_decade_bucket['total'] ) )
					);
					?>
				</p>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
endif;

// ---- Firsts: earliest-recorded character per tracked identity ----
if ( ! empty( $gen_firsts ) ) :
	// Ordered oldest first, matching the decade trend's own flow.
	$gen_firsts_sorted = $gen_firsts;
	uasort( $gen_firsts_sorted, static fn( $a, $b ) => $a['year'] <=> $b['year'] );
	?>
	<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Firsts', 'lwtv' ); ?></p>
	<div class="lwtv-pullstats lwtv-pullstats--three lwtv-statcards lwtv-bars--characters">
		<?php foreach ( $gen_firsts_sorted as $gen_first ) : ?>
			<div class="lwtv-statcard lwtv-statcard--firsts">
				<span class="lwtv-statcard-icon">
					<?php echo lwtv_plugin()->get_symbolicon( svg: 'calendar-alt.svg', icon: 'svg-calendar-alt', max_size: '18' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<span class="lwtv-statcard-number"><?php echo esc_html( (string) $gen_first['year'] ); ?></span>
				<p class="lwtv-statcard-label">
					<?php echo esc_html( $gen_first['name'] ); ?>:
					<a href="<?php echo esc_url( $gen_first['url'] ); ?>"><?php echo esc_html( $gen_first['char_name'] ); ?></a>
				</p>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
