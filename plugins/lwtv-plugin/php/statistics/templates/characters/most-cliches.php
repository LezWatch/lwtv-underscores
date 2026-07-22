<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Characters → Most Clichés: leaderboard of individual characters.
 *
 * @package LezWatch.TV
 *
 * @var int $character_count
 */

use LWTV\Statistics\Build\Cliche_Leaders as Build_Cliche_Leaders;

$cliche_leaders = ( new Build_Cliche_Leaders() )->generate();
$leader_rows    = is_array( $cliche_leaders ) ? $cliche_leaders : array();

$ranked = array(
	'rows'   => $leader_rows,
	'total'  => (int) $character_count,
	'family' => 'characters',
	'mode'   => 'leaderboard',
	'svg'    => 'medal.svg',
	'icon'   => 'svg-trophy',
	'title'  => __( 'Most Clichés', 'lwtv' ),
	/* translators: %s: number of characters shown. */
	'sub'    => sprintf( __( '%s characters carrying the most distinct clichés', 'lwtv' ), number_format_i18n( count( $leader_rows ) ) ),
	'base'   => '',
);
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/ranked-bars.php';
