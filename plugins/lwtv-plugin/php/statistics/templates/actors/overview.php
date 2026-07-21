<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Actors overview: metric cards + representation callouts + top panels.
 *
 * @package LezWatch.TV
 *
 * @var int    $actor_count
 * @var int    $count_sexualities
 * @var int    $count_genders
 * @var array  $top_sexualities
 * @var array  $top_genders
 * @var array  $actor_growth
 * @var int    $actor_lgbtq
 * @var int    $actor_transnb
 * @var string $baseurl
 */

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
require_once plugin_dir_path( __DIR__ ) . 'partials/sparkline.php';

$actor_rep_series = array(
	array( 'count' => 2 ),
	array( 'count' => 3 ),
	array( 'count' => 5 ),
	array( 'count' => 6 ),
	array( 'count' => 8 ),
	array( 'count' => 9 ),
	array( 'count' => 11 ),
);

$actor_cards = array(
	array(
		'type'    => 'actors',
		'label'   => __( 'Actors', 'lwtv' ),
		'count'   => (int) $actor_count,
		'caption' => __( 'Who\'ve played a queer role', 'lwtv' ),
		'svg'     => 'user.svg',
		'icon'    => 'svg-user',
		'points'  => lwtv_stats_sparkline_points( $actor_growth ),
	),
	array(
		'type'    => 'sexuality',
		'label'   => __( 'Sexual Orientations', 'lwtv' ),
		'count'   => (int) $count_sexualities,
		'caption' => __( 'Distinct orientations tracked', 'lwtv' ),
		'svg'     => 'heart.svg',
		'icon'    => 'svg-heart',
		'points'  => lwtv_stats_sparkline_points( $actor_rep_series ),
	),
	array(
		'type'    => 'characters', // green family for actor gender.
		'label'   => __( 'Gender Identities', 'lwtv' ),
		'count'   => (int) $count_genders,
		'caption' => __( 'Distinct identities tracked', 'lwtv' ),
		'svg'     => 'venus-double.svg',
		'icon'    => 'svg-venus-double',
		'points'  => lwtv_stats_sparkline_points( $actor_rep_series ),
	),
);
?>

<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Actors at a Glance', 'lwtv' ); ?></p>

<div class="lwtv-metric-grid lwtv-metric-grid--3">
	<?php
	foreach ( $actor_cards as $actor_card ) {
		?>
		<div class="lwtv-metric-card card-header <?php echo esc_attr( $actor_card['type'] ); ?>">
			<div class="lwtv-metric-top">
				<span class="lwtv-stats-eyebrow"><?php echo esc_html( $actor_card['label'] ); ?></span>
				<span class="lwtv-metric-icon <?php echo esc_attr( $actor_card['type'] ); ?>">
					<?php echo lwtv_plugin()->get_symbolicon( svg: $actor_card['svg'], icon: $actor_card['icon'], max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
			</div>
			<span class="lwtv-metric-number" data-count-to="<?php echo (int) $actor_card['count']; ?>"><?php echo esc_html( number_format_i18n( $actor_card['count'] ) ); ?></span>
			<?php if ( '' !== $actor_card['points'] ) : ?>
				<svg class="lwtv-sparkline" viewBox="0 0 120 26" preserveAspectRatio="none" aria-hidden="true">
					<polygon class="lwtv-sparkline-area" points="<?php echo esc_attr( $actor_card['points'] . ' 120,26 0,26' ); ?>" fill="currentColor" fill-opacity="0.15" stroke="none" />
					<polyline points="<?php echo esc_attr( $actor_card['points'] ); ?>" fill="none" stroke="currentColor" stroke-width="1.5" />
				</svg>
			<?php endif; ?>
			<span class="lwtv-metric-caption"><?php echo esc_html( $actor_card['caption'] ); ?></span>
		</div>
		<?php
	}
	?>
</div>

<?php
$actor_lgbtq_ratio = ( $actor_lgbtq > 0 ) ? (int) round( (int) $actor_count / $actor_lgbtq ) : 0;
$actor_trans_ratio = ( $actor_transnb > 0 ) ? (int) round( (int) $actor_count / $actor_transnb ) : 0;
?>
<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Who Plays the Roles', 'lwtv' ); ?></p>
<div class="lwtv-pullstats">
	<div class="lwtv-tropegap card-header openly-queer">
		<div class="lwtv-tropegap-top">
			<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Openly LGBTQ+', 'lwtv' ); ?></span>
			<span class="lwtv-tropegap-icon"><?php echo lwtv_plugin()->get_symbolicon( svg: 'rainbow.svg', icon: 'svg-rainbow', max_size: '22' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</div>
		<span class="lwtv-tropegap-number" data-count-to="<?php echo (int) $actor_lgbtq; ?>"><?php echo esc_html( number_format_i18n( $actor_lgbtq ) ); ?></span>
		<p class="lwtv-tropegap-desc">
			<?php
			printf(
				/* translators: %d: the "1 in N" ratio of openly-LGBTQ+ actors. */
				esc_html__( 'actors are openly LGBTQ+, making up about 1 in %d.', 'lwtv' ),
				(int) $actor_lgbtq_ratio
			);
			?>
		</p>
		<a class="lwtv-tropegap-link" href="<?php echo esc_url( $baseurl . 'sexuality/' ); ?>"><?php esc_html_e( 'See the breakdown', 'lwtv' ); ?> <span aria-hidden="true">&#8599;</span></a>
	</div>
	<div class="lwtv-tropegap card-header trans-nb-actors">
		<div class="lwtv-tropegap-top">
			<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Trans &amp; Non-binary', 'lwtv' ); ?></span>
			<span class="lwtv-tropegap-icon"><?php echo lwtv_plugin()->get_symbolicon( svg: 'group.svg', icon: 'svg-users', max_size: '22' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</div>
		<span class="lwtv-tropegap-number" data-count-to="<?php echo (int) $actor_transnb; ?>"><?php echo esc_html( number_format_i18n( $actor_transnb ) ); ?></span>
		<p class="lwtv-tropegap-desc">
			<?php
			printf(
				/* translators: %d: the "1 in N" ratio of trans/non-binary actors. */
				esc_html__( 'actors are trans or non-binary, which is roughly 1 in %d.', 'lwtv' ),
				(int) $actor_trans_ratio
			);
			?>
		</p>
		<a class="lwtv-tropegap-link" href="<?php echo esc_url( $baseurl . 'gender/' ); ?>"><?php esc_html_e( 'See the breakdown', 'lwtv' ); ?> <span aria-hidden="true">&#8599;</span></a>
	</div>
</div>

<?php
$actor_panels = array(
	array(
		'title'  => __( 'Top Sexual Orientations', 'lwtv' ),
		'family' => 'sexuality',
		'svg'    => 'heart.svg',
		'icon'   => 'svg-heart',
		'rows'   => $top_sexualities,
		'base'   => '/actor_sexuality/',
		/* translators: %s: total orientations. */
		'sub'    => sprintf( __( '%s orientations tracked', 'lwtv' ), number_format_i18n( (int) $count_sexualities ) ),
		/* translators: %s: total orientations. */
		'all'    => sprintf( __( 'View all %s orientations →', 'lwtv' ), number_format_i18n( (int) $count_sexualities ) ),
		'more'   => $baseurl . 'sexuality/',
	),
	array(
		'title'  => __( 'Top Gender Identities', 'lwtv' ),
		'family' => 'characters', // green.
		'svg'    => 'venus-double.svg',
		'icon'   => 'svg-venus-double',
		'rows'   => $top_genders,
		'base'   => '/actor_gender/',
		/* translators: %s: total identities. */
		'sub'    => sprintf( __( '%s identities tracked', 'lwtv' ), number_format_i18n( (int) $count_genders ) ),
		/* translators: %s: total identities. */
		'all'    => sprintf( __( 'View all %s identities →', 'lwtv' ), number_format_i18n( (int) $count_genders ) ),
		'more'   => $baseurl . 'gender/',
	),
);
?>
<div class="lwtv-panels lwtv-panels--2">
	<?php
	foreach ( $actor_panels as $actor_panel ) {
		$actor_rows    = is_array( $actor_panel['rows'] ) ? $actor_panel['rows'] : array();
		$actor_leaders = array_slice( $actor_rows, 0, 5, true );
		$actor_tail    = array_slice( $actor_rows, 5, 5, true );
		?>
		<section class="lwtv-panel bg-light">
			<header class="lwtv-panel-head">
				<span class="lwtv-panel-icon <?php echo esc_attr( $actor_panel['family'] ); ?>">
					<?php echo lwtv_plugin()->get_symbolicon( svg: $actor_panel['svg'], icon: $actor_panel['icon'], max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<div>
					<h2 class="lwtv-panel-title"><?php echo esc_html( $actor_panel['title'] ); ?></h2>
					<p class="lwtv-panel-sub"><?php echo esc_html( $actor_panel['sub'] ); ?></p>
				</div>
			</header>
			<div class="lwtv-leaders lwtv-bars--<?php echo esc_attr( $actor_panel['family'] ); ?>">
				<?php
				foreach ( $actor_leaders as $actor_slug => $actor_row ) {
					$actor_row_count = (int) $actor_row['count'];
					$actor_pct       = ( $actor_count > 0 ) ? round( ( $actor_row_count / (int) $actor_count ) * 100, 1 ) : 0;
					?>
					<div class="lwtv-leader-row">
						<div class="lwtv-leader-head">
							<a class="lwtv-leader-name" href="<?php echo esc_url( home_url( $actor_panel['base'] . $actor_slug ) ); ?>"><?php echo esc_html( $actor_row['name'] ); ?></a>
							<span class="lwtv-leader-value"><?php echo esc_html( number_format_i18n( $actor_row_count ) . ' · ' . $actor_pct . '%' ); ?></span>
						</div>
						<div class="progress lwtv-leader-track">
							<div class="progress-bar" role="progressbar" style="width:0" data-grow-to="<?php echo esc_attr( (string) $actor_pct ); ?>" aria-valuenow="<?php echo esc_attr( (string) $actor_row_count ); ?>" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( (string) $actor_count ); ?>"></div>
						</div>
					</div>
					<?php
				}
				?>
			</div>
			<?php if ( ! empty( $actor_tail ) ) : ?>
				<ul class="lwtv-tail">
					<?php
					foreach ( $actor_tail as $actor_slug => $actor_row ) {
						?>
						<li class="lwtv-tail-row">
							<a class="lwtv-tail-name" href="<?php echo esc_url( home_url( $actor_panel['base'] . $actor_slug ) ); ?>"><?php echo esc_html( $actor_row['name'] ); ?></a>
							<span class="lwtv-tail-count"><?php echo esc_html( number_format_i18n( (int) $actor_row['count'] ) ); ?></span>
						</li>
						<?php
					}
					?>
				</ul>
			<?php endif; ?>
			<a class="lwtv-panel-foot" href="<?php echo esc_url( $actor_panel['more'] ); ?>"><?php echo esc_html( $actor_panel['all'] ); ?></a>
		</section>
		<?php
	}
	?>
</div>

<?php
$download_csv = array(
	'page'  => 'actor',
	'title' => 'Actors who played queer characters.',
	'count' => $actor_count,
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/download-csv.php';

