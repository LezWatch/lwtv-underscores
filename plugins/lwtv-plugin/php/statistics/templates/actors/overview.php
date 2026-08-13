<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Actors overview: metric cards + a Headlines band (Roles promoted to the
 * lead plate, Gender/Sexuality in the rail) + representation callouts +
 * top panels.
 *
 * The Headlines band mirrors Characters'/Shows' section-index pattern, but
 * inlines its own markup (Shows' original approach) rather than including
 * the shared partials/headlines.php, which hard-gates below 4 total items —
 * a threshold Actors will never realistically clear with only three
 * subpages to draw from. See the render-gate below for the lower bar used
 * here instead.
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

use LWTV\Statistics\Build\Role_Podium;
use LWTV\Statistics\Build\Actors as Build_Actors;

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
/*
 * The section index: each subpage's best already-cached number as a
 * linked tease, same idea as Characters'/Shows' "The Headlines" —
 * "actors active this year" is promoted to the lead plate (it's the one
 * fact here with no panel of its own anywhere on the page), Gender,
 * Sexuality, and Roles fill the rail.
 */
$idx_cards = array();

// Sexuality → the dominant orientation and its share of all actors.
$idx_top_sexuality = is_array( $top_sexualities ) ? reset( $top_sexualities ) : false;
if ( ! empty( $idx_top_sexuality['name'] ) && (int) $actor_count > 0 ) {
	$idx_sexuality_pct = round( ( (int) $idx_top_sexuality['count'] / (int) $actor_count ) * 100, 1 );

	$idx_cards['sexuality'] = array(
		'eyebrow' => __( 'Sexuality', 'lwtv' ),
		'figure'  => $idx_top_sexuality['name'],
		/* translators: 1: total orientations tracked, 2: the top orientation's share of all actors (one decimal). */
		'text'    => sprintf( __( 'The top of the %1$s orientations is on %2$s%% of all actors.', 'lwtv' ), number_format_i18n( (int) $count_sexualities ), number_format_i18n( $idx_sexuality_pct, 1 ) ),
		'url'     => $baseurl . 'sexuality/',
	);
}

// Gender → the dominant identity and its share of all actors.
$idx_top_gender = is_array( $top_genders ) ? reset( $top_genders ) : false;
if ( ! empty( $idx_top_gender['name'] ) && (int) $actor_count > 0 ) {
	$idx_gender_pct = round( ( (int) $idx_top_gender['count'] / (int) $actor_count ) * 100, 1 );

	$idx_cards['gender'] = array(
		'eyebrow' => __( 'Gender', 'lwtv' ),
		'figure'  => $idx_top_gender['name'],
		/* translators: 1: total identities tracked, 2: the top identity's share of all actors (one decimal). */
		'text'    => sprintf( __( 'The top of the %1$s identities is on %2$s%% of all actors.', 'lwtv' ), number_format_i18n( (int) $count_genders ), number_format_i18n( $idx_gender_pct, 1 ) ),
		'url'     => $baseurl . 'gender/',
	);
}

// Roles → the leading role type and its share of all tagged appearances.
$idx_roles_raw    = lwtv_plugin()->generate_actors_statistics( 'array', 'roles' );
$idx_roles_data   = ( is_array( $idx_roles_raw ) && ! empty( $idx_roles_raw ) ) ? (array) reset( $idx_roles_raw ) : array();
$idx_roles_counts = array();
foreach ( Role_Podium::ORDER as $idx_roles_type ) {
	$idx_roles_counts[ $idx_roles_type ] = isset( $idx_roles_data[ $idx_roles_type ] ) ? (int) $idx_roles_data[ $idx_roles_type ]['count'] : 0;
}
$idx_roles_facts = Role_Podium::facts( $idx_roles_counts );
if ( '' !== $idx_roles_facts['leader'] ) {
	$idx_cards['roles'] = array(
		'eyebrow' => __( 'Roles', 'lwtv' ),
		'figure'  => $idx_roles_data[ $idx_roles_facts['leader'] ]['name'] ?? '',
		/* translators: %s: the leading role type's share of all tagged appearances (whole percent). */
		'text'    => sprintf( __( '%s%% of tagged character appearances are this role type.', 'lwtv' ), number_format_i18n( $idx_roles_facts['leader_share_pct'] ) ),
		'url'     => $baseurl . 'roles/',
	);
}

// On Air → distinct actors with a character on screen this year. The one
// fact on this page with no dedicated subpage of its own, so it's promoted
// to the lead plate below rather than sitting in the rail.
$idx_active_this_year = ( new Build_Actors() )->generate_active_this_year();
$idx_this_year_now    = (int) ( new \DateTime( 'now', new \DateTimeZone( LWTV_TIMEZONE ) ) )->format( 'Y' );
if ( $idx_active_this_year > 0 ) {
	$idx_cards['on-air'] = array(
		'eyebrow' => __( 'On Air', 'lwtv' ),
		'figure'  => number_format_i18n( $idx_active_this_year ),
		/* translators: %s: the current year. */
		'text'    => sprintf( __( 'Actors currently have a character on the air in %s.', 'lwtv' ), (string) $idx_this_year_now ),
		'url'     => home_url( '/this-year/' . $idx_this_year_now . '/' ),
	);
}

// Promote On Air to the lead plate and remove it from the rail so it never
// appears twice.
$idx_lead = array();
if ( isset( $idx_cards['on-air'] ) ) {
	$idx_lead = $idx_cards['on-air'];
	unset( $idx_cards['on-air'] );
}

// Fewer than two stats with data would render a lead with an empty rail (or
// vice versa) — skip the module. This is a lower bar than Characters'/
// Shows' shared partial (which gates at 4): Actors only has three subpages
// to draw from, so a 4-item minimum would mean this band never renders.
$idx_total = count( $idx_cards ) + ( empty( $idx_lead ) ? 0 : 1 );
?>
<?php if ( $idx_total >= 2 ) : ?>
	<section class="lwtv-hl-section" aria-labelledby="lwtv-hl-heading">
		<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section" id="lwtv-hl-heading"><?php esc_html_e( 'The Headlines', 'lwtv' ); ?></p>
		<div class="lwtv-hl bg-light">
			<?php if ( ! empty( $idx_lead ) ) : ?>
				<a class="lwtv-hl-lead" href="<?php echo esc_url( $idx_lead['url'] ); ?>">
					<span class="lwtv-hl-lead-figwrap">
						<span class="lwtv-hl-lead-label"><?php echo esc_html( $idx_lead['eyebrow'] ); ?></span>
						<span class="lwtv-hl-lead-fig"><?php echo esc_html( $idx_lead['figure'] ); ?></span>
					</span>
					<span class="lwtv-hl-lead-text"><?php echo esc_html( $idx_lead['text'] ); ?> <span class="lwtv-hl-arrow" aria-hidden="true">&#8599;</span></span>
				</a>
			<?php endif; ?>
			<ul class="lwtv-hl-rail">
				<?php foreach ( $idx_cards as $idx_key => $idx_card ) : ?>
					<li>
						<a class="lwtv-hl-item lwtv-hl-item--<?php echo esc_attr( $idx_key ); ?>" href="<?php echo esc_url( $idx_card['url'] ); ?>">
							<span class="lwtv-hl-spine" aria-hidden="true"></span>
							<span class="lwtv-hl-body">
								<span class="lwtv-hl-head">
									<span class="lwtv-hl-label"><?php echo esc_html( $idx_card['eyebrow'] ); ?></span>
									<span class="lwtv-hl-arrow" aria-hidden="true">&#8599;</span>
								</span>
								<span class="lwtv-hl-fig"><?php echo esc_html( $idx_card['figure'] ); ?></span>
								<span class="lwtv-hl-text"><?php echo esc_html( $idx_card['text'] ); ?></span>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>
<?php endif; ?>

<?php
// ---- Who Plays the Roles: Openly LGBTQ+ and Trans & Non-binary ----
// Same waffle-per-card treatment as Characters' Cliché Gap (--tint cards,
// a waffle showing each figure's share of all actors). Unlike Cliché Gap's
// Dead/No-Cliché pair (mutually exclusive) or the Casting Gap's queer/
// straight-cis split (complementary, sums to 100%), these two aren't
// opposite ends of anything — trans and non-binary actors are already part
// of the LGBTQ+ umbrella, not a separate category to ratio against it. So
// there's no "gap" callout here, just the two independent figures. See
// _stats.scss's .lwtv-tropegap--tint block for why .openly-queer/
// .trans-nb-actors reuse the existing .no-cliche/.queer-actors tint colors
// rather than getting their own.
$actor_lgbtq_ratio = ( $actor_lgbtq > 0 ) ? (int) round( (int) $actor_count / $actor_lgbtq ) : 0;
$actor_trans_ratio = ( $actor_transnb > 0 ) ? (int) round( (int) $actor_count / $actor_transnb ) : 0;
$actor_lgbtq_pct   = ( (int) $actor_count > 0 ) ? (int) round( ( $actor_lgbtq / (int) $actor_count ) * 100 ) : 0;
$actor_transnb_pct = ( (int) $actor_count > 0 ) ? (int) round( ( $actor_transnb / (int) $actor_count ) * 100 ) : 0;
?>
<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Who Plays the Roles', 'lwtv' ); ?></p>
<div class="lwtv-pullstats">
	<div class="lwtv-tropegap lwtv-tropegap--tint card-header openly-queer">
		<div class="lwtv-tropegap-top">
			<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Openly LGBTQ+', 'lwtv' ); ?></span>
			<span class="lwtv-tropegap-icon"><?php echo lwtv_plugin()->get_symbolicon( svg: 'rainbow.svg', icon: 'svg-rainbow', max_size: '22' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</div>
		<span class="lwtv-tropegap-number" data-count-to="<?php echo (int) $actor_lgbtq; ?>"><?php echo esc_html( number_format_i18n( $actor_lgbtq ) ); ?></span>
		<p class="lwtv-tropegap-desc">
			<?php
			printf(
				/* translators: %d: the "1 in N" ratio of openly-LGBTQ+ actors. */
				esc_html__( 'Actors who are openly LGBTQ+, about 1 in %d.', 'lwtv' ),
				(int) $actor_lgbtq_ratio
			);
			?>
		</p>
		<?php
		$waffle = array(
			'filled'  => $actor_lgbtq_pct,
			'total'   => 100,
			'columns' => 20,
			'radius'  => 8,
			/* translators: %s: percentage of all actors who are openly LGBTQ+. */
			'label'   => sprintf( __( '%s%% of all actors are openly LGBTQ+.', 'lwtv' ), number_format_i18n( $actor_lgbtq_pct ) ),
		);
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __DIR__ ) . 'partials/waffle.php';
		?>
		<p class="lwtv-tropegap-desc">
			<?php
			printf(
				/* translators: %s: percentage of all actors who are openly LGBTQ+. */
				esc_html__( '%s%% of everything we track.', 'lwtv' ),
				esc_html( number_format_i18n( $actor_lgbtq_pct ) )
			);
			?>
		</p>
		<a role="button" class="btn lwtv-tropegap-link" href="<?php echo esc_url( $baseurl . 'sexuality/' ); ?>"><?php esc_html_e( 'See the breakdown', 'lwtv' ); ?> <span aria-hidden="true">&#8599;</span></a>
	</div>
	<div class="lwtv-tropegap lwtv-tropegap--tint card-header trans-nb-actors">
		<div class="lwtv-tropegap-top">
			<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Trans &amp; Non-binary', 'lwtv' ); ?></span>
			<span class="lwtv-tropegap-icon"><?php echo lwtv_plugin()->get_symbolicon( svg: 'group.svg', icon: 'svg-users', max_size: '22' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</div>
		<span class="lwtv-tropegap-number" data-count-to="<?php echo (int) $actor_transnb; ?>"><?php echo esc_html( number_format_i18n( $actor_transnb ) ); ?></span>
		<p class="lwtv-tropegap-desc">
			<?php
			printf(
				/* translators: %d: the "1 in N" ratio of trans/non-binary actors. */
				esc_html__( 'Actors who are trans or non-binary, roughly 1 in %d.', 'lwtv' ),
				(int) $actor_trans_ratio
			);
			?>
		</p>
		<?php
		$waffle = array(
			'filled'  => $actor_transnb_pct,
			'total'   => 100,
			'columns' => 20,
			'radius'  => 8,
			/* translators: %s: percentage of all actors who are trans or non-binary. */
			'label'   => sprintf( __( '%s%% of all actors are trans or non-binary.', 'lwtv' ), number_format_i18n( $actor_transnb_pct ) ),
		);
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __DIR__ ) . 'partials/waffle.php';
		?>
		<p class="lwtv-tropegap-desc">
			<?php
			printf(
				/* translators: %s: percentage of all actors who are trans or non-binary. */
				esc_html__( '%s%% of everything we track.', 'lwtv' ),
				esc_html( number_format_i18n( $actor_transnb_pct ) )
			);
			?>
		</p>
		<a role="button" class="btn lwtv-tropegap-link" href="<?php echo esc_url( $baseurl . 'gender/' ); ?>"><?php esc_html_e( 'See the breakdown', 'lwtv' ); ?> <span aria-hidden="true">&#8599;</span></a>
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

