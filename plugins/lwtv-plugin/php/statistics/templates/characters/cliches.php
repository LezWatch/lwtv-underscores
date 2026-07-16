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
$ranked       = array(
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
