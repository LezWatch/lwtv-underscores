<?php
/**
 * The template for displaying the main stats page -- Optimized Version
 *
 * @package LezWatch.TV
 */

$characters = lwtv_plugin()->generate_total_counts( 'characters' );
$shows      = lwtv_plugin()->generate_total_counts( 'shows' );
$actors     = lwtv_plugin()->generate_total_counts( 'actors' );
$dead_chars = lwtv_plugin()->generate_total_dead( 'characters' );

?>
<h2><a name="overview">Overview</a></h2>

<?php
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __FILE__ ) . 'main/overview.php';
?>

<p>&nbsp;</p>

<div class="container">
	<div class="row">
		<div class="col">
			<?php
			// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
			include plugin_dir_path( __FILE__ ) . 'main/top-nations.php';
			?>
			<a href="nations"><button type="button" class="btn btn-lg btn-block">All <?php echo (int) wp_count_terms( 'lez_country' ); ?> Nations</button></a>
		</div>

		<div class="col">
			<?php
			// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
			include plugin_dir_path( __FILE__ ) . 'main/top-stations.php';
			?>
			<a href="stations"><button type="button" class="btn btn-lg btn-block">All <?php echo (int) wp_count_terms( 'lez_stations' ); ?> Stations</button></a>
		</div>
	</div>
</div>
