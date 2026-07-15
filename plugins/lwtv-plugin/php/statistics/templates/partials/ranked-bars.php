<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Reusable ranked horizontal-bar list (full-width panel).
 *
 * @package LezWatch.TV
 *
 * @var array  $ranked {
 *   @type array  $rows    slug => ['name','count','url'(optional)].
 *   @type int    $total   Denominator for pct (all shows).
 *   @type string $family  Color family: characters|actors|shows.
 *   @type string $eyebrow Section eyebrow.
 *   @type string $base    URL base for row links (e.g. '/trope/'); '' to use row 'url'.
 * }
 */

$ranked_rows = $ranked['rows'] ?? array();
uasort( $ranked_rows, fn( $a, $b ) => (int) $b['count'] <=> (int) $a['count'] );
$ranked_top   = ! empty( $ranked_rows ) ? max( array_map( fn( $r ) => (int) $r['count'], $ranked_rows ) ) : 0;
$ranked_total = (int) ( $ranked['total'] ?? 0 );
?>
<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php echo esc_html( $ranked['eyebrow'] ?? '' ); ?></p>

<section class="lwtv-panel bg-light">
	<div class="lwtv-bars lwtv-bars--<?php echo esc_attr( $ranked['family'] ?? 'shows' ); ?>">
		<?php
		foreach ( $ranked_rows as $ranked_slug => $ranked_row ) {
			$ranked_count = (int) $ranked_row['count'];
			// Skip terms with no shows — they add empty "0 · 0.0%" rows.
			if ( $ranked_count <= 0 ) {
				continue;
			}
			$ranked_pct   = ( $ranked_total > 0 ) ? round( ( $ranked_count / $ranked_total ) * 100, 1 ) : 0;
			$ranked_width = ( $ranked_top > 0 ) ? round( ( $ranked_count / $ranked_top ) * 100, 1 ) : 0;
			$ranked_href  = ( ! empty( $ranked['base'] ) ) ? site_url( $ranked['base'] . $ranked_slug ) : ( $ranked_row['url'] ?? '#' );
			?>
			<div class="lwtv-bar-row">
				<a class="lwtv-bar-name" href="<?php echo esc_url( $ranked_href ); ?>"><?php echo esc_html( $ranked_row['name'] ); ?></a>
				<div class="progress lwtv-bar-track">
					<div class="progress-bar" role="progressbar" style="width:0" data-grow-to="<?php echo esc_attr( (string) $ranked_width ); ?>" aria-valuenow="<?php echo esc_attr( (string) $ranked_count ); ?>" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( (string) $ranked_top ); ?>"></div>
				</div>
				<span class="lwtv-bar-label"><?php echo esc_html( number_format_i18n( $ranked_count ) . ' · ' . $ranked_pct . '%' ); ?></span>
			</div>
			<?php
		}
		?>
	</div>
</section>
