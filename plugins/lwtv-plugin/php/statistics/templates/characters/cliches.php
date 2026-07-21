<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Characters → Clichés: ranked bars (green).
 *
 * @package LezWatch.TV
 *
 * @var int $character_count
 */

$cliches_raw  = lwtv_plugin()->generate_characters_statistics( 'array', 'cliches' );
$cliches_data = ( is_array( $cliches_raw ) && ! empty( $cliches_raw ) ) ? (array) reset( $cliches_raw ) : array();

// Callouts: average + median clichés per character (excluding the "None" cliché;
// characters whose only cliché is "None" drop out of the count entirely).
$cliches_stats = ( new \LWTV\Statistics\Build\Taxonomy_Optimized() )->get_terms_per_object_stats( 'post_type_characters', 'lez_cliches', array( 'none' ) );
$lwtv_callouts = array();
if ( (int) $cliches_stats['shows'] > 0 ) {
	$cliches_avg = (float) $cliches_stats['average'];
	$cliches_med = (float) $cliches_stats['median'];

	$lwtv_callouts[] = array(
		'label' => __( 'Average per character', 'lwtv' ),
		'icon'  => 'chart-bar.svg',
		/* translators: %s: average number of clichés per character (one decimal). */
		'text'  => sprintf( __( 'The average character carries %s clichés.', 'lwtv' ), number_format_i18n( $cliches_avg, 1 ) ),
	);

	if ( floor( $cliches_med ) === $cliches_med ) {
		/* translators: %s: median number of clichés per character. */
		$cliches_med_text = sprintf( _n( 'The typical character has %s cliché.', 'The typical character has %s clichés.', (int) $cliches_med, 'lwtv' ), number_format_i18n( $cliches_med ) );
	} else {
		/* translators: %s: median number of clichés per character (one decimal). */
		$cliches_med_text = sprintf( __( 'The typical character has %s clichés.', 'lwtv' ), number_format_i18n( $cliches_med, 1 ) );
	}
	$lwtv_callouts[] = array(
		'label' => __( 'Median per character', 'lwtv' ),
		'icon'  => 'scales.svg',
		'text'  => $cliches_med_text,
	);

	// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
	include plugin_dir_path( __DIR__ ) . 'partials/callouts.php';
}

$ranked = array(
	'rows'   => $cliches_data,
	'total'  => (int) $character_count,
	'family' => 'characters',
	'svg'    => 'tag.svg',
	'icon'   => 'svg-tag',
	'title'  => __( 'All Clichés, Ranked', 'lwtv' ),
	/* translators: %s: number of clichés. */
	'sub'    => sprintf( __( '%s clichés, by number of characters. A character can carry several, so shares add up past 100%%.', 'lwtv' ), number_format_i18n( count( $cliches_data ) ) ),
	'base'   => '/cliche/',
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/ranked-bars.php';
