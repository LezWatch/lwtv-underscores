<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows → Formats: donut of format breakdown (raspberry ramp).
 *
 * @package LezWatch.TV
 *
 * @var int $shows_count
 */

$formats_raw   = lwtv_plugin()->generate_shows_statistics( 'array', 'formats' );
$formats_data  = ( is_array( $formats_raw ) && ! empty( $formats_raw ) ) ? (array) reset( $formats_raw ) : array();
$formats_total = (int) $shows_count;

// Raspberry ramp classes, darkest (largest) first.
$formats_ramp = array( 'dkpink', 'pink', 'mid', 'ltpink', 'ltpink' );

$formats_segments = array();
$formats_i        = 0;
foreach ( $formats_data as $formats_row ) {
	$formats_count      = (int) $formats_row['count'];
	$formats_segments[] = array(
		'label' => $formats_row['name'],
		'count' => $formats_count,
		'pct'   => ( $formats_total > 0 ) ? round( ( $formats_count / $formats_total ) * 100, 1 ) : 0,
		'class' => $formats_ramp[ min( $formats_i, count( $formats_ramp ) - 1 ) ],
	);
	++$formats_i;
}

// Headline from the leading slice.
$formats_lead = $formats_segments[0] ?? array( 'pct' => 0 );
$formats_in10 = ( $formats_lead['pct'] > 0 ) ? (int) round( $formats_lead['pct'] / 10 ) : 0;

// Callout: whichever two formats are smallest (by array position, since
// $formats_segments is already sorted by count desc) — named and summed
// live, never hardcoded, so this stays correct if the ranking ever shifts.
// Sits above the donut, matching Tropes/Genres/Intersectionality convention.
$lwtv_callouts = array();
if ( count( $formats_segments ) >= 2 ) {
	$formats_minor       = array_slice( $formats_segments, -2 );
	$formats_minor_count = array_sum( array_column( $formats_minor, 'count' ) );
	$formats_minor_pct   = ( $formats_total > 0 ) ? round( ( $formats_minor_count / $formats_total ) * 100, 1 ) : 0;
	$formats_minor_names = wp_sprintf_l( '%l', array_column( $formats_minor, 'label' ) );

	$lwtv_callouts[] = array(
		'label' => __( 'The long tail', 'lwtv' ),
		'icon'  => 'chart-pie.svg',
		/* translators: 1: names of the two smallest formats, joined ("Mini-Series and TV Movie"), 2: their combined percentage of all shows (one decimal). */
		'text'  => sprintf( __( '%1$s make up just %2$s%% of everything we track, combined.', 'lwtv' ), $formats_minor_names, number_format_i18n( $formats_minor_pct, 1 ) ),
	);

	// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
	include plugin_dir_path( __DIR__ ) . 'partials/callouts.php';
}

switch ( $formats_lead['label'] ) {
	case 'TV Show':
		$formats_top = 'TV series';
		$description = __( 'TV series include linear (over air, like ABC, NBC, CTV) and streaming (like Netflix).', 'lwtv' );
		break;
	case 'Web Series':
		$formats_top = 'web series';
		$description = __( 'Web-Series are streaming only but not on a distributor, so think YouTube web-series.', 'lwtv' );
		break;
	case 'Mini-Series':
		$formats_top = 'mini-series';
		$description = __( 'Mini-series (or limited release series) are usually found on traditional linear TV, but have been growing on streamers.', 'lwtv' );
		break;
	case 'TV Movie':
		$formats_top = 'made for TV movies';
		$description = __( 'Most Made for TV movies are on traditional linear TV, but have been growing on streamers.', 'lwtv' );
		break;
	default:
		$formats_top = '';
		$description = '';
}

$donut = array(
	'segments'    => $formats_segments,
	'center'      => $formats_total,
	'center_sub'  => __( 'shows', 'lwtv' ),
	'eyebrow'     => __( 'Format Breakdown', 'lwtv' ),
	// translators: %1$1d is "N in ten" figure for the leading format, %2$2s is the leading format
	'headline'    => ( $formats_in10 > 0 ) ? sprintf( __( '%1$1d in 10 are %2$2s', 'lwtv' ), $formats_in10, $formats_top ) : __( 'Format breakdown', 'lwtv' ),
	'description' => $description,
	// Visual echo of the headline's "N in 10" framing — same ratio, as a
	// dot grid instead of a sentence. Colored pink-deep in _stats.scss
	// (.lwtv-donut-waffle), matching this ramp's lead segment; a future
	// caller with a different ramp would need its own color rule.
	'waffle'      => ( $formats_lead['pct'] > 0 ) ? array(
		'filled'  => (int) round( $formats_lead['pct'] ),
		'total'   => 100,
		'columns' => 20,
		'radius'  => 6,
		/* translators: 1: percentage (whole number), 2: the leading format's name. */
		'label'   => sprintf( __( '%1$s%% of all shows are %2$s.', 'lwtv' ), number_format_i18n( round( $formats_lead['pct'] ) ), $formats_lead['label'] ),
	) : array(),
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';

// Format mix by decade: small compact donuts, oldest to newest. The pure
// bucketing (decade rollup + folding sparse early decades into one leading
// bucket) lives in Format_Decade_Buckets — see that class for why 1950s,
// 1960s, and 1970s combine into one "Before 1980s" tile today. Each tile
// reuses the same segment/ramp shape as the main donut above, rank-based
// (the biggest slice in THIS bucket gets dkpink), so a color can mean a
// different format from one tile to the next — same convention the main
// donut already uses, just applied per-bucket instead of catalogue-wide.
$decade_buckets = ( new \LWTV\Statistics\Build\Format_Trend() )->generate( 20 );

if ( ! empty( $decade_buckets ) ) :
	?>
	<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Format Mix by Decade', 'lwtv' ); ?></p>
	<div class="lwtv-decade-row">
		<?php foreach ( $decade_buckets as $decade_bucket ) : ?>
			<?php
			$decade_formats = $decade_bucket['formats'];
			arsort( $decade_formats );

			$decade_segments = array();
			$decade_i        = 0;
			foreach ( $decade_formats as $decade_format_name => $decade_format_count ) {
				$decade_segments[] = array(
					'label' => $decade_format_name,
					'count' => $decade_format_count,
					'pct'   => $decade_bucket['pcts'][ $decade_format_name ] ?? 0,
					'class' => $formats_ramp[ min( $decade_i, count( $formats_ramp ) - 1 ) ],
				);
				++$decade_i;
			}

			// The trailing "s" is wrapped in .lwtv-decade-suffix so it can be
			// forced lowercase — this label renders inside an
			// uppercase-transformed eyebrow (see partials/donut.php's compact
			// branch), which would otherwise turn "1980s" into "1980S".
			if ( 'before' === $decade_bucket['type'] ) {
				$decade_label = $decade_bucket['to']
					/* translators: %d: the decade this bucket ends before, e.g. "Before 1980s". */
					? sprintf( __( 'Before %1$d<span class="lwtv-decade-suffix">s</span>', 'lwtv' ), $decade_bucket['to'] )
					: __( 'Earliest years', 'lwtv' );
			} else {
				/* translators: %d: a decade, e.g. "1980s". */
				$decade_label = sprintf( __( '%1$d<span class="lwtv-decade-suffix">s</span>', 'lwtv' ), $decade_bucket['from'] );
			}

			$donut = array(
				'layout'        => 'compact',
				'segments'      => $decade_segments,
				'eyebrow'       => $decade_label,
				'center_pct'    => (int) round( $decade_bucket['lead_pct'] ),
				'center_family' => $decade_segments[0]['class'] ?? 'dkpink',
				'center_sub'    => $decade_bucket['lead_format'],
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
						/* translators: %s: number of shows that premiered in this bucket. */
						esc_html__( '%s shows', 'lwtv' ),
						esc_html( number_format_i18n( $decade_bucket['total'] ) )
					);
					?>
				</p>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
endif;
