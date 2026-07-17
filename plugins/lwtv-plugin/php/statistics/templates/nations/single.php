<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Single-nation statistics: profile bar + one view.
 *
 * @package LezWatch.TV
 *
 * @var array  $all_nations_data
 * @var array  $character_counts
 * @var array  $show_counts
 * @var string $nation  Nation slug, '_'-prefixed.
 * @var string $view    View, '_'-prefixed.
 */

$lwtv_slug    = ltrim( $nation, '_' );
$lwtv_vslug   = ltrim( $view, '_' );
$lwtv_ndata   = $all_nations_data[ $lwtv_slug ] ?? array(
	'name'  => __( 'Nation', 'lwtv' ),
	'count' => 0,
);
$lwtv_name    = $lwtv_ndata['name'];
$lwtv_shows   = (int) ( $show_counts[ $lwtv_slug ]['total'] ?? $lwtv_ndata['count'] ?? 0 );
$lwtv_onair   = (int) ( $show_counts[ $lwtv_slug ]['onair'] ?? 0 );
$lwtv_score   = (float) ( $show_counts[ $lwtv_slug ]['score'] ?? 0 );
$lwtv_oascore = (float) ( $show_counts[ $lwtv_slug ]['onairscore'] ?? 0 );
$lwtv_chars   = (int) ( $character_counts[ $lwtv_slug ]['total'] ?? 0 );
$lwtv_dead    = (int) ( $character_counts[ $lwtv_slug ]['dead'] ?? 0 );
?>
<div class="lwtv-nation-profile bg-light">
	<div class="lwtv-nation-profile-id">
		<span class="lwtv-stats-eyebrow sexuality"><?php esc_html_e( 'Nation Profile', 'lwtv' ); ?></span>
		<h2 class="lwtv-nation-profile-name"><?php echo esc_html( $lwtv_name ); ?></h2>
	</div>
	<div class="lwtv-nation-profile-figs">
		<span><strong data-count-to="<?php echo (int) $lwtv_shows; ?>"><?php echo esc_html( number_format_i18n( $lwtv_shows ) ); ?></strong><em><?php esc_html_e( 'shows', 'lwtv' ); ?></em></span>
		<span><strong data-count-to="<?php echo (int) $lwtv_chars; ?>"><?php echo esc_html( number_format_i18n( $lwtv_chars ) ); ?></strong><em><?php esc_html_e( 'characters', 'lwtv' ); ?></em></span>
		<span class="lwtv-nation-profile-dead"><strong data-count-to="<?php echo (int) $lwtv_dead; ?>"><?php echo esc_html( number_format_i18n( $lwtv_dead ) ); ?></strong><em><?php esc_html_e( 'dead', 'lwtv' ); ?></em></span>
	</div>
</div>

<?php
switch ( $view ) {
	case '_all':
		$lwtv_ov_cards = array(
			array(
				'family' => 'shows',
				'label'  => __( 'Shows', 'lwtv' ),
				'count'  => $lwtv_shows,
				'svg'    => 'tv.svg',
				'icon'   => 'svg-tv',
			),
			array(
				'family' => 'sexuality',
				'label'  => __( 'On Air Now', 'lwtv' ),
				'count'  => $lwtv_onair,
				'svg'    => 'tv.svg',
				'icon'   => 'svg-tv',
			),
			array(
				'family' => 'characters',
				'label'  => __( 'Characters', 'lwtv' ),
				'count'  => $lwtv_chars,
				'svg'    => 'group.svg',
				'icon'   => 'svg-users',
			),
			array(
				'family' => 'dead-characters',
				'label'  => __( 'Dead', 'lwtv' ),
				'count'  => $lwtv_dead,
				'svg'    => 'skull.svg',
				'icon'   => 'svg-skull',
			),
		);
		?>
		<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section">
			<?php
			/* translators: %s: nation name. */
			printf( esc_html__( '%s at a Glance', 'lwtv' ), esc_html( $lwtv_name ) );
			?>
		</p>
		<div class="lwtv-metric-grid lwtv-metric-grid--4">
			<?php
			foreach ( $lwtv_ov_cards as $lwtv_c ) {
				// Icon-tile background class uses the type modifier; the .dead-characters
				// family maps to the "dead" icon-tile modifier (see characters/overview.php).
				$lwtv_icon_mod = ( 'dead-characters' === $lwtv_c['family'] ) ? 'dead' : $lwtv_c['family'];
				?>
				<div class="lwtv-metric-card bg-light card-header <?php echo esc_attr( $lwtv_c['family'] ); ?>">
					<div class="lwtv-metric-top">
						<span class="lwtv-stats-eyebrow"><?php echo esc_html( $lwtv_c['label'] ); ?></span>
						<span class="lwtv-metric-icon <?php echo esc_attr( $lwtv_icon_mod ); ?>"><?php echo lwtv_plugin()->get_symbolicon( svg: $lwtv_c['svg'], icon: $lwtv_c['icon'], max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					</div>
					<span class="lwtv-metric-number" data-count-to="<?php echo (int) $lwtv_c['count']; ?>"><?php echo esc_html( number_format_i18n( $lwtv_c['count'] ) ); ?></span>
				</div>
				<?php
			}
			?>
		</div>
		<p class="lwtv-nation-score">
			<?php
			/* translators: 1: average score, 2: on-air average score. */
			printf( esc_html__( 'Average score: %1$s / 100 (on-air %2$s / 100).', 'lwtv' ), esc_html( number_format_i18n( round( $lwtv_score ) ) ), esc_html( number_format_i18n( round( $lwtv_oascore ) ) ) );
			?>
		</p>
		<p class="lwtv-nation-sentence">
			<?php
			/* translators: 1: on-air count, 2: nation name, 3: total shows. */
			printf( esc_html__( '%1$s of %2$s\'s %3$s shows are currently on air. Use the tabs above to break its catalogue down by sexuality, gender, tropes, formats, and shows-on-air over time.', 'lwtv' ), esc_html( number_format_i18n( $lwtv_onair ) ), esc_html( $lwtv_name ), esc_html( number_format_i18n( $lwtv_shows ) ) );
			?>
		</p>
		<?php
		break;

	case '_on-air':
		echo wp_kses_post( '<h4>Shows On-Air Per Year</h4>' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the function. (Replaced in Task 6.)
		echo lwtv_plugin()->generate_nation_statistics( $nation, $view, 'trendline' );
		break;

	default:
		// Sexuality / Gender / Tropes / Formats — current output until Tasks 5–6.
		?>
		<div class="row">
			<div class="col-sm-6"><?php echo lwtv_plugin()->generate_nation_statistics( $nation, $view, 'piechart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div class="col-sm-6"><?php echo lwtv_plugin()->generate_nation_statistics( $nation, $view, 'percentage' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		</div>
		<?php
		break;
}
