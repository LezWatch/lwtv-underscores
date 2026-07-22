<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows → Triggers: donut of trigger-warning severity.
 *
 * @package LezWatch.TV
 *
 * @var int $shows_count
 */

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
require_once plugin_dir_path( __DIR__ ) . 'partials/phrases.php';

$trig_raw   = lwtv_plugin()->generate_shows_statistics( 'array', 'triggers' );
$trig_data  = ( is_array( $trig_raw ) && ! empty( $trig_raw ) ) ? (array) reset( $trig_raw ) : array();
$trig_total = (int) $shows_count;

$trig_order = array(
	'high'   => array( __( 'High', 'lwtv' ), 'red' ),
	'medium' => array( __( 'Medium', 'lwtv' ), 'sev-med' ),
	'low'    => array( __( 'Low', 'lwtv' ), 'sev-low' ),
);

$trig_segments = array();
$trig_sum      = 0;
foreach ( $trig_order as $trig_key => $trig_meta ) {
	$trig_count      = isset( $trig_data[ $trig_key ] ) ? (int) $trig_data[ $trig_key ]['count'] : 0;
	$trig_sum       += $trig_count;
	$trig_segments[] = array(
		'label' => $trig_meta[0],
		'count' => $trig_count,
		'pct'   => ( $trig_total > 0 ) ? round( ( $trig_count / $trig_total ) * 100, 1 ) : 0,
		'class' => $trig_meta[1],
	);
}
$trig_none = max( 0, $trig_total - $trig_sum );

// Which severity is the most common warning (for the description)?
$trig_top_key = '';
$trig_top_val = 0;
foreach ( $trig_order as $trig_key => $trig_meta ) {
	$trig_count = isset( $trig_data[ $trig_key ] ) ? (int) $trig_data[ $trig_key ]['count'] : 0;
	if ( $trig_count > $trig_top_val ) {
		$trig_top_val = $trig_count;
		$trig_top_key = $trig_key;
	}
}

array_unshift(
	$trig_segments,
	array(
		'label' => __( 'None', 'lwtv' ),
		'count' => $trig_none,
		'pct'   => ( $trig_total > 0 ) ? round( ( $trig_none / $trig_total ) * 100, 1 ) : 0,
		'class' => 'grey',
	)
);

$trig_none_pct  = ( $trig_total > 0 ) ? round( ( $trig_none / $trig_total ) * 100, 1 ) : 0;
$trig_top_label = '';

if ( '' === $trig_top_key ) {
	$trig_desc = __( 'Hardly any shows carry a content warning yet.', 'lwtv' );
} else {
	$trig_top_label = strtolower( $trig_order[ $trig_top_key ][0] );
	/* translators: %s: a severity level, lowercased (low / medium / high). */
	$trig_desc = sprintf( __( 'Where a show does carry a content warning, it is most often a %s-severity note.', 'lwtv' ), $trig_top_label );
}

$donut = array(
	'segments'    => $trig_segments,
	'center'      => $trig_none,
	'center_sub'  => __( 'no warning', 'lwtv' ),
	'eyebrow'     => __( 'Trigger Warnings', 'lwtv' ),
	/* translators: %s: a fraction phrase like "Nearly all". */
	'headline'    => sprintf( __( '%s carry no warning at all', 'lwtv' ), lwtv_stats_fraction_phrase( $trig_none_pct ) ),
	'description' => $trig_desc,
);

// Callouts: coverage, then average + median stars per show (across shows that have at least one).
$inter_stats   = ( new \LWTV\Statistics\Build\Taxonomy_Optimized() )->get_terms_per_object_stats( 'post_type_shows', 'lez_triggers' );
$lwtv_callouts = array();
if ( (int) $inter_stats['shows'] > 0 && (int) $shows_count > 0 ) {
	$inter_with = (int) $inter_stats['shows'];
	$inter_pct  = round( ( $inter_with / (int) $shows_count ) * 100, 1 );

	$lwtv_callouts[] = array(
		'label' => __( 'Shows with triggers', 'lwtv' ),
		'icon'  => 'warning.svg',
		/* translators: %s: percentage of all shows carrying at least one trigger (one decimal). */
		'text'  => sprintf( __( '%s%% of all shows have a warning.', 'lwtv' ), number_format_i18n( $inter_pct, 1 ) ),
	);

	$lwtv_callouts[] = array(
		'label' => __( 'Biggest warnings', 'lwtv' ),
		'icon'  => 'volcano.svg',
		/* translators: 1: Name of the most common trigger, 2: number of shows with that trigger. */
		'text'  => sprintf( __( 'The most common warning is "%1$s", seen on %2$s shows.', 'lwtv' ), $trig_top_label, $trig_top_val ),
	);

	// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
	include plugin_dir_path( __DIR__ ) . 'partials/callouts.php';
}

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
