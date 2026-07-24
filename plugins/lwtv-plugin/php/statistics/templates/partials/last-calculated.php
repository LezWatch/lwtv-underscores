<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Reusable "freshness" note for the statistics pages.
 *
 * Two variants:
 *  - 'volatile' — used for the *current* year, where numbers move as the
 *    database is updated. Shows an absolute "last recalculated" timestamp
 *    (never a relative "x ago", which would freeze into a lie inside the
 *    full-page cache). Requires $lwtv_lastcalc_time.
 *  - 'daily' (default) — the settled pages, which regenerate on a daily cache.
 *
 * @package LezWatch.TV
 *
 * @var string   $lwtv_lastcalc_variant 'volatile' or 'daily'. Defaults to 'daily'.
 * @var int|null $lwtv_lastcalc_time    Unix timestamp of the last rebuild (volatile only).
 */

$lwtv_lastcalc_variant = $lwtv_lastcalc_variant ?? 'daily';
$lwtv_lastcalc_time    = $lwtv_lastcalc_time ?? null;
?>
<div class="lwtv-stats-lastcalc d-flex justify-content-center">
	<p class="justify-content-center">
	<?php
	if ( 'volatile' === $lwtv_lastcalc_variant && ! empty( $lwtv_lastcalc_time ) ) {
		printf(
			/* translators: %s: absolute date and time the statistics were last recalculated. */
			esc_html__( 'This year is still in progress, and data will change as we continue to document everything. Last recalculated %s.', 'lwtv' ),
			esc_html( wp_date( get_option( 'date_format' ) . ' \a\t ' . get_option( 'time_format' ), $lwtv_lastcalc_time ) )
		);
	} else {
		esc_html_e( 'Statistics are updated daily.', 'lwtv' );
	}
	?>
	</p>
</div>
