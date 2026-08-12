<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Characters → Queer IRL: a single waffle chart (dot grid + headline +
 * compact legend), replacing the earlier donut-plus-two-progress-bars card
 * that showed the same 24%/76% split three times over. Per the design
 * handoff: no new data — same queer-vs-not counts the donut card already
 * read, just one visualization instead of three.
 *
 * Colors reuse Casting Gap's exact choice for this identical metric
 * (characters/overview.php's "Who Plays Queer Characters" section) rather
 * than the handoff mockup's own literal hex values — $lwtv-pink /
 * $lwtv-aro-grey, so the same statistic reads as the same colors wherever
 * it appears on the site, and per CLAUDE.md's real-token-over-hardcoded-hex
 * rule.
 *
 * @package LezWatch.TV
 *
 * @var int $character_count
 */

use LWTV\Statistics\Build\Character_Queer_Cast_Firsts;

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
require_once plugin_dir_path( __DIR__ ) . 'partials/phrases.php';

$qirl_raw  = lwtv_plugin()->generate_characters_statistics( 'array', 'queer-irl' );
$qirl_data = ( is_array( $qirl_raw ) && ! empty( $qirl_raw ) ) ? (array) reset( $qirl_raw ) : array();

$qirl_yes = isset( $qirl_data['queer'] ) ? (int) $qirl_data['queer']['count'] : 0;
$qirl_no  = isset( $qirl_data['not_queer'] ) ? (int) $qirl_data['not_queer']['count'] : 0;
$qirl_tot = $qirl_yes + $qirl_no;
$qirl_pct = ( $qirl_tot > 0 ) ? round( ( $qirl_yes / $qirl_tot ) * 100, 1 ) : 0.0;

// 50 dots, each worth 2% — the closest whole-dot match to the real
// percentage, same rounding the design handoff calls out (24.2% → 12 dots).
$qirl_dots_yes = ( $qirl_tot > 0 ) ? (int) round( ( $qirl_yes / $qirl_tot ) * 50 ) : 0;
$qirl_dots_yes = max( 0, min( 50, $qirl_dots_yes ) );
$qirl_dots_no  = 50 - $qirl_dots_yes;

$waffle = array(
	'segments' => array(
		array(
			'count' => $qirl_dots_yes,
			'class' => 'queer',
		),
		array(
			'count' => $qirl_dots_no,
			'class' => 'cis',
		),
	),
	'total'    => 50,
	'columns'  => 10,
	'radius'   => 8,
	/* translators: 1: percentage played by a queer actor, 2: percentage played by a straight or cis actor. */
	'label'    => sprintf( __( '%1$s%% of characters are played by a queer actor; %2$s%% by a straight or cis actor.', 'lwtv' ), number_format_i18n( $qirl_pct, 1 ), number_format_i18n( round( 100 - $qirl_pct, 1 ), 1 ) ),
);
?>
<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Queer IRL', 'lwtv' ); ?></p>

<section class="lwtv-panel bg-light lwtv-qirl-card">
	<div class="lwtv-qirl-row">
		<div class="lwtv-qirl-waffle">
			<?php
			// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
			include plugin_dir_path( __DIR__ ) . 'partials/waffle.php';
			?>
		</div>
		<div class="lwtv-qirl-body">
			<h2 class="lwtv-donut-headline">
				<?php
				printf(
					/* translators: %s: a shortfall phrase like "Fewer than a quarter". */
					esc_html__( '%s are played by queer actors', 'lwtv' ),
					esc_html( lwtv_stats_shortfall_phrase( $qirl_pct ) )
				);
				?>
			</h2>
			<p class="lwtv-donut-desc"><?php esc_html_e( 'Most queer and trans characters are still played by straight or cisgender actors.', 'lwtv' ); ?></p>
			<ul class="lwtv-donut-legend lwtv-donut-legend--compact">
				<li class="lwtv-donut-legend-row">
					<span class="lwtv-donut-dot lwtv-donut-seg--pink"></span>
					<span class="lwtv-donut-legend-name"><?php esc_html_e( 'Played by queer actors', 'lwtv' ); ?></span>
					<span class="lwtv-donut-legend-val"><?php echo esc_html( number_format_i18n( $qirl_yes ) . ' · ' . number_format_i18n( $qirl_pct, 1 ) . '%' ); ?></span>
				</li>
				<li class="lwtv-donut-legend-row">
					<span class="lwtv-donut-dot lwtv-donut-seg--grey"></span>
					<span class="lwtv-donut-legend-name"><?php esc_html_e( 'Straight or cis actors', 'lwtv' ); ?></span>
					<span class="lwtv-donut-legend-val"><?php echo esc_html( number_format_i18n( $qirl_no ) . ' · ' . number_format_i18n( round( 100 - $qirl_pct, 1 ), 1 ) . '%' ); ?></span>
				</li>
			</ul>
		</div>
	</div>
	<p class="lwtv-qirl-footnote">
		<?php
		printf(
			/* translators: %s: total number of queer characters tracked. */
			esc_html__( 'Each dot is roughly 2%% of the %s queer characters we track.', 'lwtv' ),
			esc_html( number_format_i18n( $qirl_tot ) )
		);
		?>
	</p>
</section>

<?php
// ---- Firsts: oldest/newest played by a queer actor, oldest played by a trans actor ----
// "Oldest"/"newest" is each character's own earliest on-screen year (same
// appears-based mechanism the Gender/Sexuality Firsts lists use), not a
// judgment about the character's fictional age.
$qirl_cast_firsts  = new Character_Queer_Cast_Firsts();
$qirl_queer_firsts = $qirl_cast_firsts->generate_queer_actor_firsts();
$qirl_trans_oldest = $qirl_cast_firsts->generate_trans_actor_oldest();

$qirl_firsts_rows = array();
if ( ! empty( $qirl_queer_firsts['oldest'] ) ) {
	$qirl_firsts_rows[] = array(
		'term' => __( 'Oldest, played by a queer actor', 'lwtv' ),
		'row'  => $qirl_queer_firsts['oldest'],
	);
}
if ( ! empty( $qirl_queer_firsts['newest'] ) ) {
	$qirl_firsts_rows[] = array(
		'term' => __( 'Newest, played by a queer actor', 'lwtv' ),
		'row'  => $qirl_queer_firsts['newest'],
	);
}
if ( ! empty( $qirl_trans_oldest ) ) {
	$qirl_firsts_rows[] = array(
		'term' => __( 'Oldest, played by a trans actor', 'lwtv' ),
		'row'  => $qirl_trans_oldest,
	);
}

if ( ! empty( $qirl_firsts_rows ) ) :
	?>
	<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Firsts', 'lwtv' ); ?></p>
	<div class="lwtv-pullstats lwtv-pullstats--three lwtv-statcards lwtv-bars--queer-irl">
		<?php foreach ( $qirl_firsts_rows as $qirl_firsts_row ) : ?>
			<div class="lwtv-statcard">
				<span class="lwtv-statcard-icon">
					<?php echo lwtv_plugin()->get_symbolicon( svg: 'calendar-alt.svg', icon: 'svg-calendar-alt', max_size: '18' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<span class="lwtv-statcard-number"><?php echo esc_html( (string) $qirl_firsts_row['row']['year'] ); ?></span>
				<p class="lwtv-statcard-label">
					<?php echo esc_html( $qirl_firsts_row['term'] ); ?>:
					<a href="<?php echo esc_url( $qirl_firsts_row['row']['url'] ); ?>"><?php echo esc_html( $qirl_firsts_row['row']['name'] ); ?></a>
				</p>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
