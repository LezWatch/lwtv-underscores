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
 * @var array  $character_cliches_data slug => ['name','count', …], every tracked cliché.
 */

use LWTV\Statistics\Build\Cliche_Leaders as Build_Cliche_Leaders;
use LWTV\Statistics\Build\Series_Trend;

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
		'type'    => 'cliches',
		'label'   => __( 'Clichés', 'lwtv' ),
		'count'   => (int) $count_cliches,
		'caption' => __( 'Recurring character quirks', 'lwtv' ),
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
		<div class="lwtv-metric-card card-header <?php echo esc_attr( $char_card['type'] ); ?>">
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
/*
 * The section index: each subpage's best already-cached number as a linked
 * tease, mirroring Shows' "The Headlines" (see partials/headlines.php).
 * Every figure below comes from the same transforms the subpages themselves
 * run, so this stays in lockstep with them.
 */
$idx_cards = array();

// On Air → the adaptive trend state, same Series_Trend pattern Shows uses.
$idx_oa_raw  = lwtv_plugin()->generate_characters_statistics( 'array', 'on-air' );
$idx_oa_data = ( is_array( $idx_oa_raw ) && ! empty( $idx_oa_raw ) ) ? (array) reset( $idx_oa_raw ) : array();
$idx_oa_pts  = array();
foreach ( $idx_oa_data as $idx_oa_row ) {
	$idx_oa_pts[] = array(
		'year'  => (int) ( $idx_oa_row['name'] ?? 0 ),
		'count' => (int) ( $idx_oa_row['count'] ?? 0 ),
	);
}
$idx_oa = Series_Trend::classify( $idx_oa_pts, (int) gmdate( 'Y' ) );
if ( ! empty( $idx_oa ) ) {
	switch ( $idx_oa['state'] ) {
		case 'at-peak':
			/* translators: %s: the latest complete year. */
			$idx_oa_text = sprintf( __( 'The most characters ever recorded on air are from %s.', 'lwtv' ), (string) $idx_oa['latest_year'] );
			break;
		case 'recovering':
			/* translators: 1: the latest complete year, 2: the peak year. */
			$idx_oa_text = sprintf( __( 'Characters on air in %1$s are climbing again after the %2$s peak.', 'lwtv' ), (string) $idx_oa['latest_year'], (string) $idx_oa['peak_year'] );
			break;
		case 'receding':
			/* translators: 1: the latest complete year, 2: the peak year. */
			$idx_oa_text = sprintf( __( '%1$s is down from the %2$s peak for characters on air.', 'lwtv' ), (string) $idx_oa['latest_year'], (string) $idx_oa['peak_year'] );
			break;
		default:
			/* translators: 1: the latest complete year, 2: the peak year. */
			$idx_oa_text = sprintf( __( 'Characters on air in %1$s is holding below the %2$s peak.', 'lwtv' ), (string) $idx_oa['latest_year'], (string) $idx_oa['peak_year'] );
			break;
	}

	$idx_cards['on-air'] = array(
		'eyebrow' => __( 'On Air', 'lwtv' ),
		'figure'  => number_format_i18n( $idx_oa['latest_count'] ),
		'text'    => $idx_oa_text,
		'url'     => $baseurl . 'on-air/',
	);
}

// Clichés → the most common tracked cliché and its share of all characters.
// Skips the "none" placeholder term (characters written with no cliché at
// all) the same way characters/cliches.php already excludes it from its
// own top-10 — "none" isn't a cliché, so it shouldn't be able to win this
// spot just because it's the largest single bucket.
$idx_top_cliche = false;
foreach ( $top_cliches as $idx_cliche_slug => $idx_cliche_row ) {
	if ( 'none' === $idx_cliche_slug ) {
		continue;
	}
	$idx_top_cliche = $idx_cliche_row;
	break;
}
if ( ! empty( $idx_top_cliche['name'] ) && (int) $character_count > 0 ) {
	$idx_cliche_pct = round( ( (int) $idx_top_cliche['count'] / (int) $character_count ) * 100, 1 );

	$idx_cards['cliches'] = array(
		'eyebrow' => __( 'Clichés', 'lwtv' ),
		'figure'  => $idx_top_cliche['name'],
		/* translators: 1: total clichés tracked, 2: the top cliché's share of all characters (one decimal). */
		'text'    => sprintf( __( 'The top of the %1$s clichés is on %2$s%% of all characters.', 'lwtv' ), number_format_i18n( (int) $count_cliches ), number_format_i18n( $idx_cliche_pct, 1 ) ),
		'url'     => $baseurl . 'cliches/',
	);
}

// Most Clichés → the single most-clichéd character.
$idx_cliche_leaders = ( new Build_Cliche_Leaders() )->generate();
$idx_top_cliched    = ! empty( $idx_cliche_leaders ) ? reset( $idx_cliche_leaders ) : false;
if ( ! empty( $idx_top_cliched['name'] ) ) {
	$idx_cards['most-cliches'] = array(
		'eyebrow' => __( 'Most Clichés', 'lwtv' ),
		'figure'  => $idx_top_cliched['name'],
		/* translators: %s: number of clichés the leading character carries. */
		'text'    => sprintf( __( 'Carries %s clichés at once, more than any other character.', 'lwtv' ), number_format_i18n( (int) $idx_top_cliched['count'] ) ),
		'url'     => $baseurl . 'most-cliches/',
	);
}

// Gender → the dominant identity and its share of all characters.
$idx_top_gender = is_array( $top_genders ) ? reset( $top_genders ) : false;
if ( ! empty( $idx_top_gender['name'] ) && (int) $character_count > 0 ) {
	$idx_gender_pct = round( ( (int) $idx_top_gender['count'] / (int) $character_count ) * 100, 1 );

	$idx_cards['gender'] = array(
		'eyebrow' => __( 'Gender', 'lwtv' ),
		'figure'  => $idx_top_gender['name'],
		/* translators: 1: total gender identities tracked, 2: the top identity's share of all characters (one decimal). */
		'text'    => sprintf( __( 'The top of the %1$s identities is on %2$s%% of all characters.', 'lwtv' ), number_format_i18n( (int) $count_genders ), number_format_i18n( $idx_gender_pct, 1 ) ),
		'url'     => $baseurl . 'gender/',
	);
}

// Sexuality → the dominant orientation and its share of all characters.
$idx_top_sexuality = is_array( $top_sexualities ) ? reset( $top_sexualities ) : false;
if ( ! empty( $idx_top_sexuality['name'] ) && (int) $character_count > 0 ) {
	$idx_sexuality_pct = round( ( (int) $idx_top_sexuality['count'] / (int) $character_count ) * 100, 1 );

	$idx_cards['sexuality'] = array(
		'eyebrow' => __( 'Sexuality', 'lwtv' ),
		'figure'  => $idx_top_sexuality['name'],
		/* translators: 1: total orientations tracked, 2: the top orientation's share of all characters (one decimal). */
		'text'    => sprintf( __( 'The top of the %1$s orientations is on %2$s%% of all characters.', 'lwtv' ), number_format_i18n( (int) $count_sexualities ), number_format_i18n( $idx_sexuality_pct, 1 ) ),
		'url'     => $baseurl . 'sexuality/',
	);
}

// Queer IRL → share of characters played by a queer actor.
if ( ( $char_queer_yes + $char_queer_no ) > 0 ) {
	$idx_queer_pct = round( ( $char_queer_yes / ( $char_queer_yes + $char_queer_no ) ) * 100, 1 );

	$idx_cards['queer-irl'] = array(
		'eyebrow' => __( 'Queer IRL', 'lwtv' ),
		'figure'  => number_format_i18n( $idx_queer_pct, 1 ) . '%',
		/* translators: %s: number of characters played by a queer actor. */
		'text'    => sprintf( __( '%s characters are played by an actor who is queer in real life.', 'lwtv' ), number_format_i18n( $char_queer_yes ) ),
		'url'     => $baseurl . 'queer-irl/',
	);
}

// Promote On Air to the lead plate — Characters' one strong lead candidate
// today — and remove it from the rail so it never appears twice.
$idx_lead = array();
if ( isset( $idx_cards['on-air'] ) ) {
	$idx_lead = $idx_cards['on-air'];
	unset( $idx_cards['on-air'] );
}

$headlines = array(
	'lead'  => $idx_lead,
	'items' => $idx_cards,
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/headlines.php';
?>

<?php
// ---- The Cliché Gap: Bury Your Gays vs. No Cliché ----
// Same Trope Gap treatment Shows uses (waffle-per-card + a computed ratio
// callout) — replaces the old flat "Stories We Keep Telling" pull-stats.
// "No Cliché" pairs against Dead rather than reusing "Played by Queer
// Actors" (which The Casting Gap below already covers) so the two gap
// sections don't repeat each other's story.
$char_none = isset( $character_cliches_data['none'] ) ? (int) $character_cliches_data['none']['count'] : 0;

$clichegap_dead_pct = ( (int) $character_count > 0 ) ? (int) round( ( $char_dead / (int) $character_count ) * 100 ) : 0;
$clichegap_none_pct = ( (int) $character_count > 0 ) ? (int) round( ( $char_none / (int) $character_count ) * 100 ) : 0;
$clichegap_ratio    = ( $char_none > 0 ) ? round( $char_dead / $char_none, 1 ) : 0;
?>
<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'The Cliché Gap', 'lwtv' ); ?></p>
<div class="lwtv-pullstats">
	<div class="lwtv-tropegap lwtv-tropegap--tint card-header dead-characters">
		<div class="lwtv-tropegap-top">
			<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'Bury Your Gays', 'lwtv' ); ?></span>
			<span class="lwtv-tropegap-icon"><?php echo lwtv_plugin()->get_symbolicon( svg: 'skull.svg', icon: 'svg-skull', max_size: '22' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</div>
		<span class="lwtv-tropegap-number" data-count-to="<?php echo (int) $char_dead; ?>"><?php echo esc_html( number_format_i18n( $char_dead ) ); ?></span>
		<p class="lwtv-tropegap-desc"><?php esc_html_e( 'Characters written with the Dead cliché.', 'lwtv' ); ?></p>
		<?php
		$waffle = array(
			'filled'  => $clichegap_dead_pct,
			'total'   => 100,
			'columns' => 20,
			'radius'  => 8,
			/* translators: %s: percentage of all characters carrying the Dead cliché. */
			'label'   => sprintf( __( '%s%% of all characters carry the Dead cliché.', 'lwtv' ), number_format_i18n( $clichegap_dead_pct ) ),
		);
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __DIR__ ) . 'partials/waffle.php';
		?>
		<p class="lwtv-tropegap-desc">
			<?php
			printf(
				/* translators: %s: percentage of all characters carrying the Dead cliché. */
				esc_html__( '%s%% of everything we track.', 'lwtv' ),
				esc_html( number_format_i18n( $clichegap_dead_pct ) )
			);
			?>
		</p>
		<a role="button" class="btn lwtv-tropegap-link" href="<?php echo esc_url( site_url( '/cliche/dead/' ) ); ?>"><?php esc_html_e( 'See these characters', 'lwtv' ); ?> <span aria-hidden="true">&#8599;</span></a>
	</div>
	<div class="lwtv-tropegap lwtv-tropegap--tint card-header no-cliche">
		<div class="lwtv-tropegap-top">
			<span class="lwtv-stats-eyebrow"><?php esc_html_e( 'No Cliché', 'lwtv' ); ?></span>
			<span class="lwtv-tropegap-icon"><?php echo lwtv_plugin()->get_symbolicon( svg: 'badge.svg', icon: 'svg-badge', max_size: '22' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</div>
		<span class="lwtv-tropegap-number" data-count-to="<?php echo (int) $char_none; ?>"><?php echo esc_html( number_format_i18n( $char_none ) ); ?></span>
		<p class="lwtv-tropegap-desc"><?php esc_html_e( 'Characters written without any cliché at all.', 'lwtv' ); ?></p>
		<?php
		$waffle = array(
			'filled'  => $clichegap_none_pct,
			'total'   => 100,
			'columns' => 20,
			'radius'  => 8,
			/* translators: %s: percentage of all characters carrying no cliché. */
			'label'   => sprintf( __( '%s%% of all characters carry no cliché.', 'lwtv' ), number_format_i18n( $clichegap_none_pct ) ),
		);
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __DIR__ ) . 'partials/waffle.php';
		?>
		<p class="lwtv-tropegap-desc">
			<?php
			printf(
				/* translators: %s: percentage of all characters carrying no cliché. */
				esc_html__( '%s%% of everything we track.', 'lwtv' ),
				esc_html( number_format_i18n( $clichegap_none_pct ) )
			);
			?>
		</p>
		<a role="button" class="btn lwtv-tropegap-link" href="<?php echo esc_url( site_url( '/cliche/none/' ) ); ?>"><?php esc_html_e( 'See these characters', 'lwtv' ); ?> <span aria-hidden="true">&#8599;</span></a>
	</div>
</div>

<?php if ( $clichegap_ratio > 0 ) : ?>
	<?php
	$lwtv_callouts = array(
		array(
			'label' => __( 'The gap', 'lwtv' ),
			'icon'  => 'chart-bar.svg',
			/* translators: %s: how many times more characters carry the Dead cliché than carry no cliché at all. */
			'text'  => sprintf( __( 'Characters are %s times more likely to be killed off than to escape cliché entirely.', 'lwtv' ), number_format_i18n( $clichegap_ratio, 1 ) ),
		),
	);
	// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
	include plugin_dir_path( __DIR__ ) . 'partials/callouts.php';
	?>
<?php endif; ?>

<?php
// ---- The Casting Gap: Played by Queer Actors vs. Straight/Cis Actors ----
// One combined two-color waffle rather than two near-duplicate cards (the
// "24%/76% of everything we track" framing repeated itself with almost no
// new information). Mirrors Tropes' Mixed Alignment figure-left/legend-right
// row (partials/donut.php's markup classes, reused directly rather than
// through that partial — same reasoning as Mixed Alignment: this already
// sits inside its own .lwtv-panel, so wrapping it again would double the
// box). cis_pct is derived as the complement of queer_pct, not rounded
// independently, so the two waffle segments always sum to exactly 100 dots.
$castinggap_total     = $char_queer_yes + $char_queer_no;
$castinggap_queer_pct = ( $castinggap_total > 0 ) ? (int) round( ( $char_queer_yes / $castinggap_total ) * 100 ) : 0;
$castinggap_cis_pct   = ( $castinggap_total > 0 ) ? 100 - $castinggap_queer_pct : 0;
$castinggap_ratio     = ( $char_queer_yes > 0 ) ? round( $char_queer_no / $char_queer_yes, 1 ) : 0;
?>
<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'The Casting Gap', 'lwtv' ); ?></p>
<section class="lwtv-panel bg-light lwtv-castinggap">
	<header class="lwtv-panel-head">
		<span class="lwtv-panel-icon">
			<?php echo lwtv_plugin()->get_symbolicon( svg: 'user-heart.svg', icon: 'svg-user', max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</span>
		<div>
			<h2 class="lwtv-panel-title"><?php esc_html_e( 'Who Plays Queer Characters', 'lwtv' ); ?></h2>
			<p class="lwtv-panel-sub"><?php esc_html_e( 'Whether the actor behind a queer character is queer in real life, or straight and cisgender.', 'lwtv' ); ?></p>
		</div>
	</header>
	<div class="lwtv-castinggap-row">
		<div class="lwtv-castinggap-waffle">
			<?php
			$waffle = array(
				'segments' => array(
					array(
						'count' => $castinggap_queer_pct,
						'class' => 'queer',
					),
					array(
						'count' => $castinggap_cis_pct,
						'class' => 'cis',
					),
				),
				'total'    => 100,
				'columns'  => 20,
				'radius'   => 8,
				/* translators: 1: percentage of characters played by a queer actor, 2: percentage played by a straight or cis actor. */
				'label'    => sprintf( __( '%1$s%% of characters are played by a queer actor; %2$s%% by a straight or cis actor.', 'lwtv' ), number_format_i18n( $castinggap_queer_pct ), number_format_i18n( $castinggap_cis_pct ) ),
			);
			// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
			include plugin_dir_path( __DIR__ ) . 'partials/waffle.php';
			?>
		</div>
		<ul class="lwtv-donut-legend lwtv-donut-legend--compact">
			<li class="lwtv-donut-legend-row">
				<span class="lwtv-donut-dot lwtv-donut-seg--pink"></span>
				<span class="lwtv-donut-legend-name"><?php esc_html_e( 'Played by queer actors', 'lwtv' ); ?></span>
				<span class="lwtv-donut-legend-val"><?php echo esc_html( number_format_i18n( $char_queer_yes ) . ' · ' . number_format_i18n( $castinggap_queer_pct ) . '%' ); ?></span>
			</li>
			<li class="lwtv-donut-legend-row">
				<span class="lwtv-donut-dot lwtv-donut-seg--grey"></span>
				<span class="lwtv-donut-legend-name"><?php esc_html_e( 'Played by straight or cis actors', 'lwtv' ); ?></span>
				<span class="lwtv-donut-legend-val"><?php echo esc_html( number_format_i18n( $char_queer_no ) . ' · ' . number_format_i18n( $castinggap_cis_pct ) . '%' ); ?></span>
			</li>
		</ul>
	</div>
	<a class="lwtv-panel-foot" href="<?php echo esc_url( $baseurl . 'queer-irl/' ); ?>"><?php esc_html_e( 'See the full breakdown →', 'lwtv' ); ?></a>
</section>

<?php if ( $castinggap_ratio > 0 ) : ?>
	<?php
	$lwtv_callouts = array(
		array(
			'label' => __( 'The gap', 'lwtv' ),
			'icon'  => 'chart-bar.svg',
			/* translators: %s: how many times more often straight/cis actors are cast in queer roles than queer actors are. */
			'text'  => sprintf( __( 'Straight or cis actors are cast in queer roles %s times more often than queer actors are.', 'lwtv' ), number_format_i18n( $castinggap_ratio, 1 ) ),
		),
	);
	// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
	include plugin_dir_path( __DIR__ ) . 'partials/callouts.php';
	?>
<?php endif; ?>

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
