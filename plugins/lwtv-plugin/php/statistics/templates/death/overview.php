<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Death → Overview: the toll (3 counters) + the year chart.
 *
 * @package LezWatch.TV
 *
 * @var int    $deadchars
 * @var int    $deadchar_percent
 * @var int    $deadshows
 * @var int    $deadshow_percent
 * @var int    $allchars
 * @var int    $allshows
 * @var string $dead_years_average
 * @var array  $dead_years_series
 */

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
require_once plugin_dir_path( __DIR__ ) . 'partials/phrases.php';

$death_cards = array(
	array(
		'label'   => __( 'Characters Who Die', 'lwtv' ),
		'value'   => $deadchar_percent . '%',
		'count'   => (int) $deadchar_percent,
		'suffix'  => '%',
		/* translators: 1: dead, 2: total characters. */
		'caption' => sprintf( __( '%1$s of %2$s queer characters', 'lwtv' ), number_format_i18n( $deadchars ), number_format_i18n( $allchars ) ),
		'svg'     => 'skull.svg',
		'icon'    => 'svg-skull',
	),
	array(
		'label'   => __( 'Shows That Kill', 'lwtv' ),
		'count'   => (int) $deadshow_percent,
		'suffix'  => '%',
		/* translators: 1: dead shows, 2: total shows. */
		'caption' => sprintf( __( '%1$s of %2$s shows kill a queer character', 'lwtv' ), number_format_i18n( $deadshows ), number_format_i18n( $allshows ) ),
		'svg'     => 'tv.svg',
		'icon'    => 'svg-tv',
	),
	array(
		'label'   => __( 'Deaths Per Year', 'lwtv' ),
		'count'   => (int) round( (float) $dead_years_average ),
		'suffix'  => '',
		'caption' => __( 'On average, including quiet years', 'lwtv' ),
		'svg'     => 'calendar-alt.svg',
		'icon'    => 'svg-calendar',
	),
);
?>
<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'The Toll', 'lwtv' ); ?></p>
<div class="lwtv-metric-grid lwtv-metric-grid--3">
	<?php
	foreach ( $death_cards as $death_card ) {
		?>
		<div class="lwtv-metric-card bg-light card-header dead-characters">
			<div class="lwtv-metric-top">
				<span class="lwtv-stats-eyebrow"><?php echo esc_html( $death_card['label'] ); ?></span>
				<span class="lwtv-metric-icon dead"><?php echo lwtv_plugin()->get_symbolicon( svg: $death_card['svg'], icon: $death_card['icon'], max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			</div>
			<span class="lwtv-metric-number" data-count-to="<?php echo (int) $death_card['count']; ?>" data-count-suffix="<?php echo esc_attr( $death_card['suffix'] ); ?>"><?php echo esc_html( number_format_i18n( $death_card['count'] ) . $death_card['suffix'] ); ?></span>
			<span class="lwtv-metric-caption"><?php echo esc_html( $death_card['caption'] ); ?></span>
		</div>
		<?php
	}
	?>
</div>

<?php
$death_ys      = lwtv_stats_year_series( $dead_years_series );
$death_py_year = (string) $death_ys['peak_year'];
$yearbars      = array(
	'rows'        => $death_ys['rows'],
	'peak_year'   => $death_ys['peak_year'],
	'peak_count'  => $death_ys['peak_count'],
	'eyebrow'     => __( 'Deaths By Year', 'lwtv' ),
	/* translators: %s: the deadliest year (4-digit, not a quantity — never thousands-formatted). */
	'headline'    => sprintf( __( 'Deaths peaked in %s — and have fallen since', 'lwtv' ), $death_py_year ),
	/* translators: %s: the deadliest year (4-digit, not a quantity — never thousands-formatted). */
	'description' => sprintf( __( '%s was the deadliest year on record for queer women on TV.', 'lwtv' ), $death_py_year ),
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/year-bars.php';
