<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shared Death → Nations / Death → Stations highlights: total on-screen
 * queer deaths, the share of tracked networks/countries with any recorded
 * death, and the single deadliest-by-rate network/country (linked).
 *
 * Replaces two near-identical highlight blocks that would otherwise be
 * copy-pasted across death/nations.php and death/stations.php — one
 * include, parameterized by taxonomy, matching the earlier
 * taxonomy-facet.php precedent for the same two pages.
 *
 * @package LezWatch.TV
 *
 * @var string $dtx_taxonomy    'lez_country' | 'lez_stations'.
 * @var string $dtx_url_base    '/country/' | '/station/' — matches the
 *                               existing ranked-bars link base on each page.
 * @var string $dtx_noun_plural 'countries' | 'networks' — for the pullstat
 *                               copy (passed pre-pluralized rather than
 *                               built here, since "country" doesn't just
 *                               take an "s").
 */

use LWTV\Statistics\Build\Taxonomy_Death_Leaders;

/**
 * total_terms comes from the same published-shows query terms_with_death
 * does — not wp_count_terms(), which (with its default hide_empty=false)
 * counts every term row regardless of post status, including ones sitting
 * on drafts only or never assigned to a show at all. Using that as the
 * denominator would let unpublished/unused terms silently drag the
 * percentage down without ever being able to appear in terms_with_death.
 */

$dtx_leaders          = ( new Taxonomy_Death_Leaders( $dtx_taxonomy ) )->generate();
$dtx_total_terms      = (int) ( $dtx_leaders['total_terms'] ?? 0 );
$dtx_terms_with_death = (int) ( $dtx_leaders['terms_with_death'] ?? 0 );
$dtx_deadliest        = $dtx_leaders['deadliest'] ?? null;
$dtx_total_dead       = (int) lwtv_plugin()->generate_total_dead( 'characters' );

$dtx_highlights = array();

if ( $dtx_total_dead > 0 ) {
	$dtx_highlights[] = array(
		'icon'   => 'skull.svg',
		'number' => number_format_i18n( $dtx_total_dead ),
		'label'  => __( 'On-screen queer deaths recorded across every network and country.', 'lwtv' ),
	);
}

if ( $dtx_total_terms > 0 ) {
	$dtx_death_pct = round( ( $dtx_terms_with_death / $dtx_total_terms ) * 100, 1 );

	$dtx_highlights[] = array(
		'icon'   => 'chart-pie.svg',
		/* translators: %s: percentage of tracked terms with a recorded death (one decimal). */
		'number' => sprintf( __( '%s%%', 'lwtv' ), number_format_i18n( $dtx_death_pct, 1 ) ),
		/* translators: 1: how many have a recorded death, 2: total tracked, 3: 'countries' or 'networks'. */
		'label'  => sprintf( __( 'Of %3$s have at least one recorded on-screen death (%1$s of %2$s).', 'lwtv' ), number_format_i18n( $dtx_terms_with_death ), number_format_i18n( $dtx_total_terms ), $dtx_noun_plural ),
	);
}

if ( ! empty( $dtx_deadliest ) ) {
	$dtx_highlights[] = array(
		'icon'   => 'chart-bar.svg',
		/* translators: %s: the highest death rate found among qualifying terms (one decimal). */
		'number' => sprintf( __( '%s%%', 'lwtv' ), number_format_i18n( $dtx_deadliest['pct'], 1 ) ),
		/* translators: 1: 'countries' or 'networks', 2: minimum tracked-character count to qualify. */
		'label'  => sprintf( __( 'Of characters die — the highest death rate among %1$s with %2$d+ characters:', 'lwtv' ), $dtx_noun_plural, \LWTV\Statistics\Build\Taxonomy_Death_Leaders::MIN_CHARS_FOR_RATE ),
		'url'    => site_url( $dtx_url_base . $dtx_deadliest['slug'] ),
		'name'   => $dtx_deadliest['name'],
	);
}

if ( ! empty( $dtx_highlights ) ) {
	?>
	<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Standout Numbers', 'lwtv' ); ?></p>
	<div class="lwtv-pullstats lwtv-pullstats--three lwtv-statcards lwtv-bars--death">
		<?php
		foreach ( $dtx_highlights as $dtx_highlight ) {
			?>
			<div class="lwtv-statcard">
				<span class="lwtv-statcard-icon">
					<?php echo lwtv_plugin()->get_symbolicon( svg: $dtx_highlight['icon'], icon: 'svg-' . str_replace( '.svg', '', $dtx_highlight['icon'] ), max_size: '18' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<span class="lwtv-statcard-number"><?php echo esc_html( $dtx_highlight['number'] ); ?></span>
				<p class="lwtv-statcard-label">
					<?php echo esc_html( $dtx_highlight['label'] ); ?>
					<?php if ( isset( $dtx_highlight['url'] ) ) { ?>
						<a href="<?php echo esc_url( $dtx_highlight['url'] ); ?>"><?php echo esc_html( $dtx_highlight['name'] ); ?></a>
					<?php } ?>
				</p>
			</div>
			<?php
		}
		?>
	</div>
	<hr>
	<?php
}
?>
