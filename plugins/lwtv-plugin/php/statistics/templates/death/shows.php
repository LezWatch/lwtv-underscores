<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Death → Shows: how many shows kill all / some / no queer characters, plus
 * three standout highlights (share of shows with any death, the single most
 * lethal show, and the highest death rate among shows with a real cast).
 *
 * @package LezWatch.TV
 */

use LWTV\Statistics\Build\Show_Death_Leaders;

// ---- Highlights: share of shows with any death, most lethal, highest rate ----
$ds_leaders          = ( new Show_Death_Leaders() )->generate();
$ds_total_shows      = (int) ( $ds_leaders['total_shows'] ?? 0 );
$ds_shows_with_death = (int) ( $ds_leaders['shows_with_death'] ?? 0 );
$ds_most_lethal      = $ds_leaders['most_lethal'] ?? null;
$ds_highest_rate     = $ds_leaders['highest_rate'] ?? null;

$ds_highlights = array();

if ( $ds_total_shows > 0 ) {
	$ds_death_pct = round( ( $ds_shows_with_death / $ds_total_shows ) * 100, 1 );

	$ds_highlights[] = array(
		'icon'   => 'chart-pie.svg',
		/* translators: %s: percentage of shows with a recorded death (one decimal). */
		'number' => sprintf( __( '%s%%', 'lwtv' ), number_format_i18n( $ds_death_pct, 1 ) ),
		/* translators: 1: shows with a recorded death, 2: total shows. */
		'label'  => sprintf( __( 'Of shows have killed at least one queer character (%1$s of %2$s).', 'lwtv' ), number_format_i18n( $ds_shows_with_death ), number_format_i18n( $ds_total_shows ) ),
	);
}

if ( ! empty( $ds_most_lethal ) ) {
	$ds_highlights[] = array(
		'icon'   => 'skull-crossbones.svg',
		'number' => number_format_i18n( $ds_most_lethal['count'] ),
		'label'  => __( 'Most Lethal Show — the most queer characters killed:', 'lwtv' ),
		'url'    => $ds_most_lethal['url'],
		'name'   => $ds_most_lethal['name'],
	);
}

if ( ! empty( $ds_highest_rate ) ) {
	$ds_highlights[] = array(
		'icon'   => 'chart-bar.svg',
		/* translators: %s: the highest death rate found among qualifying shows (one decimal). */
		'number' => sprintf( __( '%s%%', 'lwtv' ), number_format_i18n( $ds_highest_rate['pct'], 1 ) ),
		/* translators: %d: the minimum cast size a show needs to qualify for this highlight. */
		'label'  => sprintf( __( 'Of the cast dies — the highest death rate among shows with %d+ characters:', 'lwtv' ), \LWTV\Statistics\Build\Show_Death_Leaders::MIN_CAST_FOR_RATE ),
		'url'    => $ds_highest_rate['url'],
		'name'   => $ds_highest_rate['name'],
	);
}

if ( ! empty( $ds_highlights ) ) {
	?>
	<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Standout Numbers', 'lwtv' ); ?></p>
	<div class="lwtv-pullstats lwtv-pullstats--three lwtv-statcards lwtv-bars--death">
		<?php
		foreach ( $ds_highlights as $ds_highlight ) {
			?>
			<div class="lwtv-statcard">
				<span class="lwtv-statcard-icon">
					<?php echo lwtv_plugin()->get_symbolicon( svg: $ds_highlight['icon'], icon: 'svg-' . str_replace( '.svg', '', $ds_highlight['icon'] ), max_size: '18' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<span class="lwtv-statcard-number"><?php echo esc_html( $ds_highlight['number'] ); ?></span>
				<p class="lwtv-statcard-label">
					<?php echo esc_html( $ds_highlight['label'] ); ?>
					<?php if ( isset( $ds_highlight['url'] ) ) { ?>
						<a href="<?php echo esc_url( $ds_highlight['url'] ); ?>"><?php echo esc_html( $ds_highlight['name'] ); ?></a>
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

<?php
$ds_data  = lwtv_plugin()->generate_dead_statistics( 'shows', 'per-show', 'array' );
$ds_map   = array(
	'no_dead'   => array( __( 'No deaths', 'lwtv' ), 'magenta' ),
	'some_dead' => array( __( 'Some deaths', 'lwtv' ), 'royal-blue' ),
	'all_dead'  => array( __( 'All die', 'lwtv' ), 'lavender' ),
);
$ds_total = 0;
foreach ( $ds_data as $ds_row ) {
	$ds_total += (int) $ds_row['count'];
}
$ds_seg = array();
foreach ( $ds_map as $ds_key => $ds_meta ) {
	$ds_c     = isset( $ds_data[ $ds_key ] ) ? (int) $ds_data[ $ds_key ]['count'] : 0;
	$ds_seg[] = array(
		'label' => $ds_meta[0],
		'count' => $ds_c,
		'pct'   => ( $ds_total > 0 ) ? round( ( $ds_c / $ds_total ) * 100, 1 ) : 0,
		'class' => $ds_meta[1],
	);
}
$ds_alldead = isset( $ds_data['all_dead'] ) ? (int) $ds_data['all_dead']['count'] : 0;
?>
<?php
$donut = array(
	'segments'    => $ds_seg,
	'center'      => $ds_alldead,
	'center_sub'  => __( 'kill everyone', 'lwtv' ),
	'eyebrow'     => __( 'Deaths Per Show', 'lwtv' ),
	'headline'    => __( 'Most shows keep their queer characters alive', 'lwtv' ),
	'description' => __( 'Raw per-show death counts tend to match with how large a show\'s cast is.', 'lwtv' ),
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
