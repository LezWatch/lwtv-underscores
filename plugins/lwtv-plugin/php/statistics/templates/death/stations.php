<?php
/**
 * The template for displaying the death stations statistics - Optimized Version
 *
 * @package LezWatch.TV
 */

?>
<h3>Death per Station/Network Breakdown</h3>

<div class="container chart-container">
	<p class="d-inline-flex gap-1">
		<button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#chartCollapse,#listCollapse" aria-expanded="true" aria-controls="chartCollapse">Chart</button>
		<button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#listCollapse,#chartCollapse" aria-expanded="false" aria-controls="listCollapse">List</button>
	</p>

	<div class="row collapse show" id="chartCollapse">
		<div class="col-sm-12">
			<h2><a name="chart">Chart</a></h2>
			<?php echo lwtv_plugin()->generate_dead_statistics( 'shows', 'stations', 'barchart' ); ?>
		</div>
	</div>
	<div class="row collapse" id="listCollapse">
		<div class="col-sm-12">
			<h2><a name="list">List</a></h2>
			<?php echo lwtv_plugin()->generate_dead_statistics( 'shows', 'stations', 'percentage' ); ?>
		</div>
	</div>
</div>
<?php
