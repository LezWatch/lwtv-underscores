<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * The main statistics overview page — redesigned.
 *
 * Computes all server-side data, then includes focused partials.
 *
 * @package LezWatch.TV
 */

use LWTV\Statistics\Build\Stations as Build_Stations;
use LWTV\Statistics\Build\Nations as Build_Nations;

// Totals.
$stats_shows      = (int) lwtv_plugin()->generate_total_counts( 'shows' );
$stats_characters = (int) lwtv_plugin()->generate_total_counts( 'characters' );
$stats_actors     = (int) lwtv_plugin()->generate_total_counts( 'actors' );
$stats_dead       = (int) lwtv_plugin()->generate_total_dead( 'characters' );

// Growth series for the sparklines.
$stats_series = array(
	'shows'      => lwtv_plugin()->generate_growth_series( 'shows' ),
	'characters' => lwtv_plugin()->generate_growth_series( 'characters' ),
	'actors'     => lwtv_plugin()->generate_growth_series( 'actors' ),
	'dead'       => lwtv_plugin()->generate_growth_series( 'dead' ),
);

// Panels data.
$stats_top_stations   = ( new Build_Stations() )->get_top_stations( 7 );
$stats_top_nations    = ( new Build_Nations() )->get_top_nations( 4 );
$stats_total_stations = (int) wp_count_terms( array( 'taxonomy' => 'lez_stations' ) );
$stats_total_nations  = (int) wp_count_terms( array( 'taxonomy' => 'lez_country' ) );

// Derived: "1 in N" ratio for the death band (guard against divide-by-zero).
$stats_dead_ratio = ( $stats_dead > 0 ) ? (int) round( $stats_characters / $stats_dead ) : 0;

$stats_partials = plugin_dir_path( __FILE__ ) . 'main/';
?>

<div class="lwtv-stats-overview">
	<?php
	// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
	include $stats_partials . 'tabbar.php';
	// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
	include $stats_partials . 'overview.php';
	// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
	include $stats_partials . 'bury-your-gays.php';
	// Remaining panels are added in later tasks:
	// include $stats_partials . 'where-tv-lives.php';
	// include $stats_partials . 'around-the-world.php';
	?>
</div>
