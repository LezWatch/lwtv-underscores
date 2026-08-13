<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Death → Characters: who dies, by sexuality / gender / role — plus three
 * standout highlights (most resurrected character, deadliest single day,
 * and a decade-by-decade death trend) that turn three bare donuts into a
 * page with its own story.
 *
 * @package LezWatch.TV
 */

use LWTV\Statistics\Build\Character_Death_Leaders;
use LWTV\Statistics\Build\Death_Trend;

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
require_once plugin_dir_path( __DIR__ ) . 'partials/phrases.php';

// ---- Highlights: resurrected characters + the deadliest single day ----
$dc_death_leaders     = new Character_Death_Leaders();
$dc_top_resurrected   = $dc_death_leaders->generate( 1 );
$dc_resurrected_count = $dc_death_leaders->count_resurrected();

$dc_time_stats      = lwtv_plugin()->generate_dead_statistics( 'characters', 'all', 'time' );
$dc_deadliest_count = (int) ( $dc_time_stats['most']['count'] ?? 0 );
$dc_deadliest_date  = (string) ( $dc_time_stats['most']['date'] ?? '' );

$dc_highlights = array();

if ( $dc_resurrected_count > 0 ) {
	$dc_highlights[] = array(
		'icon'   => 'zombie.svg',
		'number' => number_format_i18n( $dc_resurrected_count ),
		'label'  => __( 'Characters who have died and come back more than once.', 'lwtv' ),
	);
}

if ( ! empty( $dc_top_resurrected ) ) {
	$dc_top_id       = array_key_first( $dc_top_resurrected );
	$dc_top_row      = $dc_top_resurrected[ $dc_top_id ];
	$dc_highlights[] = array(
		'icon'   => 'skull-crossbones.svg',
		'number' => number_format_i18n( $dc_top_row['count'] ),
		'label'  => __( 'Times died and come back — more than any other character:', 'lwtv' ),
		'url'    => $dc_top_row['url'],
		'name'   => $dc_top_row['name'],
	);
}

if ( $dc_deadliest_count > 0 && '' !== $dc_deadliest_date ) {
	$dc_deadliest_ts = strtotime( $dc_deadliest_date );
	$dc_highlights[] = array(
		'icon'   => 'calendar-alt.svg',
		'number' => number_format_i18n( $dc_deadliest_count ),
		/* translators: %s: the deadliest recorded date, e.g. "March 4, 2015". */
		'label'  => sprintf( __( 'Characters who died on %s, the deadliest day on record.', 'lwtv' ), $dc_deadliest_ts ? date_i18n( 'F j, Y', $dc_deadliest_ts ) : $dc_deadliest_date ),
	);
}

if ( ! empty( $dc_highlights ) ) {
	?>
	<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Standout Numbers', 'lwtv' ); ?></p>
	<div class="lwtv-pullstats lwtv-pullstats--three lwtv-statcards lwtv-bars--death">
		<?php
		foreach ( $dc_highlights as $dc_highlight ) {
			// Plain column layout for all three cards, even the linked one —
			// .lwtv-statcard--firsts' row layout is meant for a row where every
			// card is that style (Gender/Sexuality/Queer IRL's Firsts lists);
			// mixing it into an otherwise-plain row put the icon and number in
			// a different spot on the linked card than its neighbors.
			?>
			<div class="lwtv-statcard">
				<span class="lwtv-statcard-icon">
					<?php echo lwtv_plugin()->get_symbolicon( svg: $dc_highlight['icon'], icon: 'svg-' . str_replace( '.svg', '', $dc_highlight['icon'] ), max_size: '18' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<span class="lwtv-statcard-number"><?php echo esc_html( $dc_highlight['number'] ); ?></span>
				<p class="lwtv-statcard-label">
					<?php echo esc_html( $dc_highlight['label'] ); ?>
					<?php if ( isset( $dc_highlight['url'] ) ) { ?>
						<a href="<?php echo esc_url( $dc_highlight['url'] ); ?>"><?php echo esc_html( $dc_highlight['name'] ); ?></a>
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
$dc_ramp = array( 'dkpink', 'pink', 'mid', 'mid2', 'ltpink' );

$dc_build = function ( $data, $topn = 5, $grey_slug = '' ) use ( $dc_ramp ) {
	$data  = is_array( $data ) ? $data : array();
	$total = 0;
	foreach ( $data as $r ) {
		$total += (int) $r['count'];
	}
	$segments = array();
	$grey_val = 0;
	if ( '' !== $grey_slug && isset( $data[ $grey_slug ] ) ) {
		$grey_val   = (int) $data[ $grey_slug ]['count'];
		$segments[] = array(
			'label' => $data[ $grey_slug ]['name'],
			'count' => $grey_val,
			'pct'   => ( $total > 0 ) ? round( ( $grey_val / $total ) * 100, 1 ) : 0,
			'class' => 'grey',
		);
		unset( $data[ $grey_slug ] );
	}
	uasort( $data, fn( $a, $b ) => (int) $b['count'] <=> (int) $a['count'] );
	$named = $grey_val;
	$i     = 0;
	$top   = array(
		'name'  => '',
		'count' => 0,
		'pct'   => 0,
	);
	foreach ( $data as $r ) {
		if ( 0 === $i ) {
			$top = array(
				'name'  => $r['name'],
				'count' => (int) $r['count'],
				'pct'   => ( $total > 0 ) ? round( ( (int) $r['count'] / $total ) * 100, 1 ) : 0,
			);
		}
		if ( $i >= $topn || (int) $r['count'] <= 0 ) {
			break;
		}
		$c          = (int) $r['count'];
		$named     += $c;
		$segments[] = array(
			'label' => $r['name'],
			'count' => $c,
			'pct'   => ( $total > 0 ) ? round( ( $c / $total ) * 100, 1 ) : 0,
			'class' => $dc_ramp[ $i ],
		);
		++$i;
	}
	$other = max( 0, $total - $named );
	if ( $other > 0 ) {
		$segments[] = array(
			'label' => __( 'Other', 'lwtv' ),
			'count' => $other,
			'pct'   => ( $total > 0 ) ? round( ( $other / $total ) * 100, 1 ) : 0,
			'class' => 'grey',
		);
	}
	return array( $segments, $total, $top );
};

// Sexuality.
$dc_sex = lwtv_plugin()->generate_dead_statistics( 'characters', 'sexuality', 'array' );
list( $dc_sex_seg, $dc_sex_total, $dc_sex_top ) = $dc_build( $dc_sex, 5 );
?>

<?php
$donut = array(
	'segments'    => $dc_sex_seg,
	'center'      => $dc_sex_total,
	'center_sub'  => __( 'deaths', 'lwtv' ),
	'eyebrow'     => __( 'Death By Sexual Orientation', 'lwtv' ),
	/* translators: %s: the orientation with the most deaths. */
	'headline'    => sprintf( __( '%s characters die most', 'lwtv' ), $dc_sex_top['name'] ),
	/* translators: 1: fraction phrase, 2: orientation. */
	'description' => sprintf( __( '%1$s of all queer deaths are %2$s characters.', 'lwtv' ), lwtv_stats_fraction_phrase( $dc_sex_top['pct'] ), strtolower( $dc_sex_top['name'] ) ),
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';

// Gender (cisgender grey).
$dc_gen = lwtv_plugin()->generate_dead_statistics( 'characters', 'gender', 'array' );
list( $dc_gen_seg, $dc_gen_total, $dc_gen_top ) = $dc_build( $dc_gen, 4, 'cisgender' );
?>
<hr>
<?php
$donut = array(
	'segments'    => $dc_gen_seg,
	'center'      => $dc_gen_total,
	'center_sub'  => __( 'deaths', 'lwtv' ),
	'eyebrow'     => __( 'Death By Gender Identity', 'lwtv' ),
	'headline'    => __( 'Gender of the dead', 'lwtv' ),
	'description' => '',
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';

// Role.
$dc_role = lwtv_plugin()->generate_dead_statistics( 'characters', 'role', 'array' );
list( $dc_role_seg, $dc_role_total, $dc_role_top ) = $dc_build( $dc_role, 3 );
?>
<hr>
<?php
$donut = array(
	'segments'    => $dc_role_seg,
	'center'      => $dc_role_total,
	'center_sub'  => __( 'deaths', 'lwtv' ),
	'eyebrow'     => __( 'Death By Role', 'lwtv' ),
	'headline'    => __( 'Regulars, recurring, and guests', 'lwtv' ),
	'description' => '',
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';

// ---- Deaths by Decade: small compact donuts, oldest to newest ----
// Mirrors Sexuality Mix by Decade (characters/sexuality.php) exactly, just
// anchored to each death's own recorded date instead of a character's
// earliest on-screen year — see Death_Trend's docblock for why a character
// can (and, for the resurrected, does) land in more than one bucket here.
$dc_trend          = new Death_Trend();
$dc_decade_buckets = $dc_trend->generate_decades( 'lez_sexuality', 10 );

if ( ! empty( $dc_decade_buckets ) ) {
	?>
	<hr>
	<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Deaths by Decade', 'lwtv' ); ?></p>
	<div class="lwtv-decade-row">
		<?php
		foreach ( $dc_decade_buckets as $dc_decade_bucket ) {
			$dc_decade_terms = $dc_decade_bucket['terms'];
			arsort( $dc_decade_terms );

			$dc_decade_segments = array();
			$dc_decade_i        = 0;
			foreach ( $dc_decade_terms as $dc_decade_term_name => $dc_decade_term_count ) {
				$dc_decade_segments[] = array(
					'label' => $dc_decade_term_name,
					'count' => $dc_decade_term_count,
					'pct'   => $dc_decade_bucket['pcts'][ $dc_decade_term_name ] ?? 0,
					'class' => $dc_ramp[ min( $dc_decade_i, count( $dc_ramp ) - 1 ) ],
				);
				++$dc_decade_i;
			}

			// The trailing "s" is wrapped in .lwtv-decade-suffix so it can be
			// forced lowercase inside the uppercase-transformed eyebrow (see
			// partials/donut.php's compact branch).
			if ( 'before' === $dc_decade_bucket['type'] ) {
				$dc_decade_label = $dc_decade_bucket['to']
					/* translators: %d: the decade this bucket ends before, e.g. "Before 1980s". */
					? sprintf( __( 'Before %1$d<span class="lwtv-decade-suffix">s</span>', 'lwtv' ), $dc_decade_bucket['to'] )
					: __( 'Earliest years', 'lwtv' );
			} else {
				/* translators: %d: a decade, e.g. "1980s". */
				$dc_decade_label = sprintf( __( '%1$d<span class="lwtv-decade-suffix">s</span>', 'lwtv' ), $dc_decade_bucket['from'] );
			}

			$donut = array(
				'layout'        => 'compact',
				'segments'      => $dc_decade_segments,
				'eyebrow'       => $dc_decade_label,
				'center_pct'    => (int) round( $dc_decade_bucket['lead_pct'] ),
				'center_family' => $dc_decade_segments[0]['class'] ?? 'pink',
				'center_sub'    => $dc_decade_bucket['lead_term'],
			);
			?>
			<div class="lwtv-decade-tile">
				<?php
				// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
				include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
				?>
				<p class="lwtv-decade-count">
					<?php
					printf(
						/* translators: %s: number of deaths recorded in this decade bucket. */
						esc_html__( '%s deaths', 'lwtv' ),
						esc_html( number_format_i18n( $dc_decade_bucket['total'] ) )
					);
					?>
				</p>
			</div>
			<?php
		}
		?>
	</div>
	<?php
}
?>
