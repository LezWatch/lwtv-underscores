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

<?php
$char_panels = array(
	array(
		'title'  => __( 'Top Clichés', 'lwtv' ),
		'family' => 'characters',
		'svg'    => 'tag.svg',
		'icon'   => 'svg-tag',
		'rows'   => $top_cliches,
		'base'   => '/cliche/',
		'count'  => (int) $count_cliches,
		/* translators: %s: total clichés. */
		'sub'    => sprintf( __( '%s clichés tracked', 'lwtv' ), number_format_i18n( (int) $count_cliches ) ),
		/* translators: %s: total clichés. */
		'all'    => sprintf( __( 'View all %s clichés →', 'lwtv' ), number_format_i18n( (int) $count_cliches ) ),
		'more'   => $baseurl . 'cliches/',
	),
	array(
		'title'  => __( 'Top Sexual Orientations', 'lwtv' ),
		'family' => 'sexuality',
		'svg'    => 'heart.svg',
		'icon'   => 'svg-heart',
		'rows'   => $top_sexualities,
		'base'   => '/sexuality/',
		'count'  => (int) $count_sexualities,
		/* translators: %s: total orientations. */
		'sub'    => sprintf( __( '%s orientations tracked', 'lwtv' ), number_format_i18n( (int) $count_sexualities ) ),
		/* translators: %s: total orientations. */
		'all'    => sprintf( __( 'View all %s orientations →', 'lwtv' ), number_format_i18n( (int) $count_sexualities ) ),
		'more'   => $baseurl . 'sexuality/',
	),
	array(
		'title'  => __( 'Top Gender Identities', 'lwtv' ),
		'family' => 'gender',
		'svg'    => 'venus-double.svg',
		'icon'   => 'svg-venus-double',
		'rows'   => $top_genders,
		'base'   => '/gender/',
		'count'  => (int) $count_genders,
		/* translators: %s: total identities. */
		'sub'    => sprintf( __( '%s identities tracked', 'lwtv' ), number_format_i18n( (int) $count_genders ) ),
		/* translators: %s: total identities. */
		'all'    => sprintf( __( 'View all %s identities →', 'lwtv' ), number_format_i18n( (int) $count_genders ) ),
		'more'   => $baseurl . 'gender/',
	),
);
?>
<div class="lwtv-panels lwtv-panels--3">
	<?php
	foreach ( $char_panels as $char_panel ) {
		$char_rows    = is_array( $char_panel['rows'] ) ? $char_panel['rows'] : array();
		$char_leaders = array_slice( $char_rows, 0, 5, true );
		$char_tail    = array_slice( $char_rows, 5, 5, true );
		?>
		<section class="lwtv-panel bg-light">
			<header class="lwtv-panel-head">
				<span class="lwtv-panel-icon <?php echo esc_attr( $char_panel['family'] ); ?>">
					<?php echo lwtv_plugin()->get_symbolicon( svg: $char_panel['svg'], icon: $char_panel['icon'], max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<div>
					<h2 class="lwtv-panel-title"><?php echo esc_html( $char_panel['title'] ); ?></h2>
					<p class="lwtv-panel-sub"><?php echo esc_html( $char_panel['sub'] ); ?></p>
				</div>
			</header>
			<div class="lwtv-leaders lwtv-bars--<?php echo esc_attr( $char_panel['family'] ); ?>">
				<?php
				foreach ( $char_leaders as $char_slug => $char_row ) {
					$char_row_count = (int) $char_row['count'];
					$char_pct       = ( $character_count > 0 ) ? round( ( $char_row_count / $character_count ) * 100, 1 ) : 0;
					?>
					<div class="lwtv-leader-row">
						<div class="lwtv-leader-head">
							<a class="lwtv-leader-name" href="<?php echo esc_url( site_url( $char_panel['base'] . $char_slug ) ); ?>"><?php echo esc_html( $char_row['name'] ); ?></a>
							<span class="lwtv-leader-value"><?php echo esc_html( number_format_i18n( $char_row_count ) . ' · ' . $char_pct . '%' ); ?></span>
						</div>
						<div class="progress lwtv-leader-track">
							<div class="progress-bar" role="progressbar" style="width:0" data-grow-to="<?php echo esc_attr( (string) $char_pct ); ?>" aria-valuenow="<?php echo esc_attr( (string) $char_row_count ); ?>" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( (string) $character_count ); ?>"></div>
						</div>
					</div>
					<?php
				}
				?>
			</div>
			<?php if ( ! empty( $char_tail ) ) : ?>
				<ul class="lwtv-tail">
					<?php
					foreach ( $char_tail as $char_slug => $char_row ) {
						?>
						<li class="lwtv-tail-row">
							<a class="lwtv-tail-name" href="<?php echo esc_url( site_url( $char_panel['base'] . $char_slug ) ); ?>"><?php echo esc_html( $char_row['name'] ); ?></a>
							<span class="lwtv-tail-count"><?php echo esc_html( number_format_i18n( (int) $char_row['count'] ) ); ?></span>
						</li>
						<?php
					}
					?>
				</ul>
			<?php endif; ?>
			<a class="lwtv-panel-foot" href="<?php echo esc_url( $char_panel['more'] ); ?>"><?php echo esc_html( $char_panel['all'] ); ?></a>
		</section>
		<?php
	}
	?>
</div>
