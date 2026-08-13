<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Actors → Unknown Actor: a spotlight on the "Unknown" placeholder actor
 * (post 14080) — every other actor-facing stat on the site deliberately
 * excludes this post (see Build_Actors::get_actor_character_counts() and
 * Character_Queer_Cast_Firsts::build_trans_actor_oldest()); this page is the
 * one place that queries for it on purpose, turning "we don't know who
 * played this" into its own tracked figure instead of a silent gap.
 *
 * @package LezWatch.TV
 *
 * @var int $actor_count
 */

use LWTV\Statistics\Build\Unknown_Actor;

$unk_report = ( new Unknown_Actor() )->generate_report();
// Not $actor_count — this page's "share of the whole" is a share of
// characters (how many carry the Unknown actor), not actors.
$unk_total = (int) lwtv_plugin()->generate_total_counts( 'characters' );
$unk_count = (int) $unk_report['character_count'];
$unk_pct   = ( $unk_total > 0 ) ? round( ( $unk_count / $unk_total ) * 100, 1 ) : 0.0;

// ---- Headline: 50-dot waffle, same "1 dot ≈ 2%" convention Queer IRL uses ----
$unk_dots_gap  = ( $unk_total > 0 ) ? (int) round( ( $unk_count / $unk_total ) * 50 ) : 0;
$unk_dots_gap  = max( 0, min( 50, $unk_dots_gap ) );
$unk_dots_rest = 50 - $unk_dots_gap;

$waffle = array(
	'segments' => array(
		array(
			'count' => $unk_dots_gap,
			'class' => 'gap',
		),
		array(
			'count' => $unk_dots_rest,
			'class' => 'known',
		),
	),
	'total'    => 50,
	'columns'  => 10,
	'radius'   => 8,
	/* translators: 1: percentage of characters with no confirmed performer, 2: percentage with one. */
	'label'    => sprintf( __( '%1$s%% of characters have no confirmed performer on record; %2$s%% do.', 'lwtv' ), number_format_i18n( $unk_pct, 1 ), number_format_i18n( round( 100 - $unk_pct, 1 ), 1 ) ),
);
?>
<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'The Unknown Actor', 'lwtv' ); ?></p>

<section class="lwtv-panel bg-light lwtv-unknown-card">
	<div class="lwtv-unknown-row">
		<div class="lwtv-unknown-waffle">
			<?php
			// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
			include plugin_dir_path( __DIR__ ) . 'partials/waffle.php';
			?>
		</div>
		<div class="lwtv-unknown-body">
			<h2 class="lwtv-donut-headline">
				<?php
				printf(
					/* translators: %s: percentage of characters with no confirmed performer on record. */
					esc_html__( '%s%% of characters have no confirmed performer', 'lwtv' ),
					esc_html( number_format_i18n( $unk_pct, 1 ) )
				);
				?>
			</h2>
			<p class="lwtv-donut-desc"><?php esc_html_e( 'Every one of these characters is missing real-world casting info — this page tracks the gap instead of hiding it.', 'lwtv' ); ?></p>
			<ul class="lwtv-donut-legend lwtv-donut-legend--compact">
				<li class="lwtv-donut-legend-row">
					<span class="lwtv-donut-dot lwtv-donut-seg--amber"></span>
					<span class="lwtv-donut-legend-name"><?php esc_html_e( 'No confirmed performer', 'lwtv' ); ?></span>
					<span class="lwtv-donut-legend-val"><?php echo esc_html( number_format_i18n( $unk_count ) . ' · ' . number_format_i18n( $unk_pct, 1 ) . '%' ); ?></span>
				</li>
				<li class="lwtv-donut-legend-row">
					<span class="lwtv-donut-dot lwtv-donut-seg--bordergrey"></span>
					<span class="lwtv-donut-legend-name"><?php esc_html_e( 'Performer on record', 'lwtv' ); ?></span>
					<span class="lwtv-donut-legend-val"><?php echo esc_html( number_format_i18n( max( 0, $unk_total - $unk_count ) ) . ' · ' . number_format_i18n( round( 100 - $unk_pct, 1 ), 1 ) . '%' ); ?></span>
				</li>
			</ul>
		</div>
	</div>
	<p class="lwtv-qirl-footnote">
		<?php
		printf(
			/* translators: %s: total number of characters affected. */
			esc_html__( 'Each dot is roughly 2%% of the %s characters carrying the Unknown actor.', 'lwtv' ),
			esc_html( number_format_i18n( $unk_count ) )
		);
		?>
	</p>
</section>

<?php
// ---- Pullstats: character count, show count ----
$unk_pullstats = array(
	array(
		'icon'   => 'tag.svg',
		'number' => number_format_i18n( $unk_count ),
		'label'  => __( 'Characters with no confirmed performer.', 'lwtv' ),
	),
	array(
		'icon'   => 'tv.svg',
		'number' => number_format_i18n( $unk_report['show_count'] ),
		'label'  => __( 'Shows with at least one such character.', 'lwtv' ),
	),
);
?>
<div class="lwtv-pullstats lwtv-pullstats--three lwtv-statcards lwtv-bars--actors">
	<?php foreach ( $unk_pullstats as $unk_pullstat ) : ?>
		<div class="lwtv-statcard">
			<span class="lwtv-statcard-icon">
				<?php echo lwtv_plugin()->get_symbolicon( svg: $unk_pullstat['icon'], icon: 'svg-' . str_replace( '.svg', '', $unk_pullstat['icon'] ), max_size: '18' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</span>
			<span class="lwtv-statcard-number"><?php echo esc_html( $unk_pullstat['number'] ); ?></span>
			<p class="lwtv-statcard-label"><?php echo esc_html( $unk_pullstat['label'] ); ?></p>
		</div>
	<?php endforeach; ?>
</div>

<?php
// ---- Who are these characters: gender + sexuality + role mini donuts, one shared card ----
$unk_roles_total = array_sum( wp_list_pluck( $unk_report['roles'], 'count' ) );

if ( ! empty( $unk_report['gender'] ) || ! empty( $unk_report['sexuality'] ) || $unk_roles_total > 0 ) :
	$unk_amber_ramp = array( 'amber', 'medamber', 'midamber', 'paleamber' );

	/**
	 * Build a mini-donut $donut array from a ranked term breakdown, top 4
	 * ramped + the rest folded into "Other" — same shape actors/sexuality.php
	 * and actors/gender.php use for their own donuts, just rendered via the
	 * 'mini' layout so several can share one outer card below.
	 *
	 * @param array  $unk_terms   [ slug => { 'name', 'count' } ], count desc.
	 * @param int    $unk_of      Total this breakdown is a share of (this character set).
	 * @param string $unk_eyebrow Mini donut eyebrow.
	 * @return array|null $donut array, or null if there's nothing to chart.
	 */
	$unk_build_mini_donut = static function ( array $unk_terms, int $unk_of, string $unk_eyebrow ) use ( $unk_amber_ramp ) {
		if ( $unk_of <= 0 || empty( $unk_terms ) ) {
			return null;
		}

		$unk_segments = array();
		$unk_named    = 0;
		$unk_i        = 0;
		foreach ( $unk_terms as $unk_row ) {
			if ( $unk_i >= 4 || (int) $unk_row['count'] <= 0 ) {
				break;
			}
			$unk_seg_count  = (int) $unk_row['count'];
			$unk_named     += $unk_seg_count;
			$unk_segments[] = array(
				'label' => $unk_row['name'],
				'count' => $unk_seg_count,
				'pct'   => round( ( $unk_seg_count / $unk_of ) * 100, 1 ),
				'class' => $unk_amber_ramp[ $unk_i ],
			);
			++$unk_i;
		}
		$unk_other = max( 0, $unk_of - $unk_named );
		if ( $unk_other > 0 ) {
			$unk_segments[] = array(
				'label' => __( 'Other', 'lwtv' ),
				'count' => $unk_other,
				'pct'   => round( ( $unk_other / $unk_of ) * 100, 1 ),
				'class' => 'ltamber',
			);
		}

		$unk_lead = $unk_segments[0] ?? array(
			'pct'   => 0,
			'label' => '',
		);

		return array(
			'layout'        => 'mini',
			'segments'      => $unk_segments,
			'eyebrow'       => $unk_eyebrow,
			'center_pct'    => (int) round( $unk_lead['pct'] ),
			'center_family' => $unk_lead['class'] ?? 'amber',
			'center_sub'    => $unk_lead['label'],
		);
	};

	// Role has a fixed 3-slot ramp (same amber/medamber/ltamber roles.php
	// uses), not the rank-then-ramp treatment gender/sexuality need for their
	// open-ended term lists — built separately rather than forcing it through
	// $unk_build_mini_donut above.
	$unk_role_donut = null;
	if ( $unk_roles_total > 0 ) {
		$unk_role_donut_ramp = array(
			'regular'   => 'amber',
			'recurring' => 'medamber',
			'guest'     => 'ltamber',
		);
		$unk_role_segments   = array();
		foreach ( $unk_report['roles'] as $unk_role_type => $unk_role_row ) {
			$unk_role_count = (int) $unk_role_row['count'];
			if ( $unk_role_count <= 0 ) {
				continue;
			}
			$unk_role_segments[] = array(
				'label' => $unk_role_row['name'],
				'count' => $unk_role_count,
				'pct'   => round( ( $unk_role_count / $unk_roles_total ) * 100, 1 ),
				'class' => $unk_role_donut_ramp[ $unk_role_type ] ?? 'amber',
			);
		}
		$unk_role_lead  = $unk_role_segments[0] ?? array(
			'pct'   => 0,
			'label' => '',
		);
		$unk_role_donut = array(
			'layout'        => 'mini',
			'segments'      => $unk_role_segments,
			'eyebrow'       => __( 'By Role', 'lwtv' ),
			'center_pct'    => (int) round( $unk_role_lead['pct'] ),
			'center_family' => $unk_role_segments[0]['class'] ?? 'amber',
			'center_sub'    => $unk_role_lead['label'],
		);
	}

	$unk_gender_donut    = $unk_build_mini_donut( $unk_report['gender'], $unk_count, __( 'By Gender', 'lwtv' ) );
	$unk_sexuality_donut = $unk_build_mini_donut( $unk_report['sexuality'], $unk_count, __( 'By Sexuality', 'lwtv' ) );
	?>
	<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Who Are These Characters?', 'lwtv' ); ?></p>
	<section class="lwtv-panel bg-light">
		<div class="lwtv-donut-mini-row">
			<?php
			foreach ( array( $unk_gender_donut, $unk_sexuality_donut, $unk_role_donut ) as $unk_donut ) :
				if ( null === $unk_donut ) :
					continue;
				endif;
				$donut = $unk_donut;
				// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
				include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
			endforeach;
			?>
		</div>
	</section>
<?php endif; ?>

<?php
// ---- Time Dimension: oldest/newest character carrying the Unknown actor ----
$unk_time_rows = array();
if ( ! empty( $unk_report['oldest'] ) ) {
	$unk_time_rows[] = array(
		'term' => __( 'Oldest, still uncredited', 'lwtv' ),
		'row'  => $unk_report['oldest'],
	);
}
if ( ! empty( $unk_report['newest'] ) && ( $unk_report['newest']['name'] ?? null ) !== ( $unk_report['oldest']['name'] ?? null ) ) {
	$unk_time_rows[] = array(
		'term' => __( 'Newest, still uncredited', 'lwtv' ),
		'row'  => $unk_report['newest'],
	);
}

if ( ! empty( $unk_time_rows ) ) :
	?>
	<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Time Dimension', 'lwtv' ); ?></p>
	<div class="lwtv-pullstats lwtv-pullstats--three lwtv-statcards lwtv-bars--actors">
		<?php foreach ( $unk_time_rows as $unk_time_row ) : ?>
			<div class="lwtv-statcard lwtv-statcard--firsts">
				<span class="lwtv-statcard-icon">
					<?php echo lwtv_plugin()->get_symbolicon( svg: 'calendar-alt.svg', icon: 'svg-calendar-alt', max_size: '18' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<span class="lwtv-statcard-number"><?php echo esc_html( (string) $unk_time_row['row']['year'] ); ?></span>
				<p class="lwtv-statcard-label">
					<?php echo esc_html( $unk_time_row['term'] ); ?>:
					<a href="<?php echo esc_url( $unk_time_row['row']['url'] ); ?>"><?php echo esc_html( $unk_time_row['row']['name'] ); ?></a>
				</p>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<?php
// ---- Top Shows: which shows carry the most Unknown-actor characters ----
if ( ! empty( $unk_report['top_shows'] ) ) :
	?>
	<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Top Shows', 'lwtv' ); ?></p>
	<div class="lwtv-pullstats lwtv-pullstats--three lwtv-statcards lwtv-bars--actors">
		<?php foreach ( $unk_report['top_shows'] as $unk_show_row ) : ?>
			<div class="lwtv-statcard lwtv-statcard--firsts">
				<span class="lwtv-statcard-icon">
					<?php echo lwtv_plugin()->get_symbolicon( svg: 'tv.svg', icon: 'svg-tv', max_size: '18' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<span class="lwtv-statcard-number"><?php echo esc_html( number_format_i18n( $unk_show_row['count'] ) ); ?></span>
				<p class="lwtv-statcard-label">
					<a href="<?php echo esc_url( $unk_show_row['url'] ); ?>"><?php echo esc_html( $unk_show_row['name'] ); ?></a>
				</p>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<?php
// Role Breakdown used to have its own pullstat-card section here, but it's
// now folded into the "By Role" mini donut in the "Who Are These
// Characters?" card above — showing the same three counts twice added
// nothing. Recast Overlap (Unknown-only vs. Unknown-plus-a-named-actor) was
// removed outright: checked against real data, every character carrying the
// Unknown actor has Unknown as its *only* listed actor, so the split never
// had a second side to show.

// ---- Dead or Alive ----
$unk_dead_total = $unk_report['dead']['alive'] + $unk_report['dead']['dead'];
if ( $unk_dead_total > 0 ) :
	?>
	<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Dead or Alive', 'lwtv' ); ?></p>
	<div class="lwtv-pullstats lwtv-pullstats--three lwtv-statcards lwtv-bars--actors">
		<div class="lwtv-statcard">
			<span class="lwtv-statcard-icon">
				<?php echo lwtv_plugin()->get_symbolicon( svg: 'heart.svg', icon: 'svg-heart', max_size: '18' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</span>
			<span class="lwtv-statcard-number"><?php echo esc_html( number_format_i18n( $unk_report['dead']['alive'] ) ); ?></span>
			<p class="lwtv-statcard-label"><?php esc_html_e( 'Still alive.', 'lwtv' ); ?></p>
		</div>
		<div class="lwtv-statcard">
			<span class="lwtv-statcard-icon">
				<?php echo lwtv_plugin()->get_symbolicon( svg: 'skull.svg', icon: 'svg-skull', max_size: '18' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</span>
			<span class="lwtv-statcard-number"><?php echo esc_html( number_format_i18n( $unk_report['dead']['dead'] ) ); ?></span>
			<p class="lwtv-statcard-label"><?php esc_html_e( 'Dead.', 'lwtv' ); ?></p>
		</div>
	</div>
<?php endif; ?>
