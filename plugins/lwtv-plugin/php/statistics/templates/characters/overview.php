<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Characters overview: metric cards + callouts + top panels.
 *
 * @package LezWatch.TV
 *
 * @var int    $character_count
 * @var int    $count_sexualities
 * @var int    $count_genders
 * @var int    $count_cliches
 * @var array  $top_cliches
 * @var array  $top_sexualities
 * @var array  $top_genders
 * @var array  $char_growth
 * @var int    $char_dead
 * @var int    $char_queer_yes
 * @var int    $char_queer_no
 * @var string $baseurl
 */

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
require_once plugin_dir_path( __DIR__ ) . 'partials/sparkline.php';

// Representative decorative sparkline for term-count cards (no real time series).
$char_rep_series = array(
	array( 'count' => 2 ),
	array( 'count' => 3 ),
	array( 'count' => 5 ),
	array( 'count' => 6 ),
	array( 'count' => 8 ),
	array( 'count' => 9 ),
	array( 'count' => 11 ),
);

$char_cards = array(
	array(
		'type'    => 'characters',
		'label'   => __( 'Characters', 'lwtv' ),
		'count'   => (int) $character_count,
		'caption' => __( 'Queer & trans, all time', 'lwtv' ),
		'svg'     => 'group.svg',
		'icon'    => 'svg-users',
		'points'  => lwtv_stats_sparkline_points( $char_growth ),
	),
	array(
		'type'    => 'sexuality',
		'label'   => __( 'Sexual Orientations', 'lwtv' ),
		'count'   => (int) $count_sexualities,
		'caption' => __( 'Distinct orientations tracked', 'lwtv' ),
		'svg'     => 'heart.svg',
		'icon'    => 'svg-heart',
		'points'  => lwtv_stats_sparkline_points( $char_rep_series ),
	),
	array(
		'type'    => 'gender',
		'label'   => __( 'Gender Identities', 'lwtv' ),
		'count'   => (int) $count_genders,
		'caption' => __( 'Distinct identities tracked', 'lwtv' ),
		'svg'     => 'venus-double.svg',
		'icon'    => 'svg-venus-double',
		'points'  => lwtv_stats_sparkline_points( $char_rep_series ),
	),
	array(
		'type'    => 'dead-characters',
		'label'   => __( 'Clichés', 'lwtv' ),
		'count'   => (int) $count_cliches,
		'caption' => __( 'Recurring character tropes', 'lwtv' ),
		'svg'     => 'tag.svg',
		'icon'    => 'svg-tag',
		'points'  => lwtv_stats_sparkline_points( $char_rep_series ),
	),
);
?>

<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Characters at a Glance', 'lwtv' ); ?></p>

<div class="lwtv-metric-grid">
	<?php
	foreach ( $char_cards as $char_card ) {
		// Icon-tile background class uses the type modifier; the .dead-characters
		// family maps to the "dead" icon-tile modifier.
		$char_icon_mod = ( 'dead-characters' === $char_card['type'] ) ? 'dead' : $char_card['type'];
		?>
		<div class="lwtv-metric-card bg-light card-header <?php echo esc_attr( $char_card['type'] ); ?>">
			<div class="lwtv-metric-top">
				<span class="lwtv-stats-eyebrow"><?php echo esc_html( $char_card['label'] ); ?></span>
				<span class="lwtv-metric-icon <?php echo esc_attr( $char_icon_mod ); ?>">
					<?php echo lwtv_plugin()->get_symbolicon( svg: $char_card['svg'], icon: $char_card['icon'], max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
			</div>
			<span class="lwtv-metric-number" data-count-to="<?php echo (int) $char_card['count']; ?>"><?php echo esc_html( number_format_i18n( $char_card['count'] ) ); ?></span>
			<?php if ( '' !== $char_card['points'] ) : ?>
				<svg class="lwtv-sparkline" viewBox="0 0 120 26" preserveAspectRatio="none" aria-hidden="true">
					<polygon class="lwtv-sparkline-area" points="<?php echo esc_attr( $char_card['points'] . ' 120,26 0,26' ); ?>" fill="currentColor" fill-opacity="0.15" stroke="none" />
					<polyline points="<?php echo esc_attr( $char_card['points'] ); ?>" fill="none" stroke="currentColor" stroke-width="1.5" />
				</svg>
			<?php endif; ?>
			<span class="lwtv-metric-caption"><?php echo esc_html( $char_card['caption'] ); ?></span>
		</div>
		<?php
	}
	?>
</div>

<?php
$char_dead_ratio = ( $char_dead > 0 ) ? (int) round( $character_count / $char_dead ) : 0;
?>
<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'The Stories We Keep Telling', 'lwtv' ); ?></p>
<div class="lwtv-pullstats">
	<div class="lwtv-tropegap card-header dead-characters">
		<div class="lwtv-tropegap-top">
			<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Bury Your Gays', 'lwtv' ); ?></span>
			<span class="lwtv-tropegap-icon"><?php echo lwtv_plugin()->get_symbolicon( svg: 'skull.svg', icon: 'svg-skull', max_size: '22' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</div>
		<span class="lwtv-tropegap-number" data-count-to="<?php echo (int) $char_dead; ?>"><?php echo esc_html( number_format_i18n( $char_dead ) ); ?></span>
		<p class="lwtv-tropegap-desc">
			<?php
			printf(
				/* translators: %d: the "1 in N" ratio of dead characters. */
				esc_html__( 'characters carry the Dead cliché — that\'s roughly one in %d.', 'lwtv' ),
				(int) $char_dead_ratio
			);
			?>
		</p>
		<a class="lwtv-tropegap-link" href="<?php echo esc_url( site_url( '/cliche/dead/' ) ); ?>"><?php esc_html_e( 'See these characters', 'lwtv' ); ?> <span aria-hidden="true">&#8599;</span></a>
	</div>
	<div class="lwtv-tropegap card-header characters">
		<div class="lwtv-tropegap-top">
			<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Played by Queer Actors', 'lwtv' ); ?></span>
			<span class="lwtv-tropegap-icon"><?php echo lwtv_plugin()->get_symbolicon( svg: 'user-heart.svg', icon: 'svg-user', max_size: '22' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</div>
		<span class="lwtv-tropegap-number" data-count-to="<?php echo (int) $char_queer_yes; ?>"><?php echo esc_html( number_format_i18n( $char_queer_yes ) ); ?></span>
		<p class="lwtv-tropegap-desc">
			<?php
			printf(
				/* translators: %s: the number of characters played by straight or cis actors. */
				esc_html__( 'characters are played by queer actors; %s by straight or cis actors.', 'lwtv' ),
				esc_html( number_format_i18n( $char_queer_no ) )
			);
			?>
		</p>
		<a class="lwtv-tropegap-link" href="<?php echo esc_url( $baseurl . 'queer-irl/' ); ?>"><?php esc_html_e( 'See the breakdown', 'lwtv' ); ?> <span aria-hidden="true">&#8599;</span></a>
	</div>
</div>
