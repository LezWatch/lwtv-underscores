<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Death → Characters: who dies, by sexuality / gender / role.
 *
 * @package LezWatch.TV
 */

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
require_once plugin_dir_path( __DIR__ ) . 'partials/phrases.php';

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
