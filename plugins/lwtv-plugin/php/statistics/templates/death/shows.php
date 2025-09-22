<?php
/**
 * The template for displaying the death shows statistics - Optimized Version
 *
 * @package LezWatch.TV
 */

?>
<h3>Death per Show Breakdown</h3>
<div class="container chart-container">
	<div class="row">
		<div class="col-sm-6">
			<?php echo lwtv_plugin()->generate_dead_statistics( 'shows', 'per-show', 'piechart' ); ?>
		</div>
		<div class="col-sm-6">
			<?php echo lwtv_plugin()->generate_dead_statistics( 'shows', 'per-show', 'percentage' ); ?>
		</div>
	</div>
</div>
<?php
