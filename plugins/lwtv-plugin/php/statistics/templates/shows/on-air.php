<?php
/**
 * The template for displaying on-air statistics
 *
 * @package LezWatch.TV
 */
?>
<h3>Number of Shows On-Air per Year</h3>

<div class="container chart-container">
	<div class="row">
		<div class="col-sm-6">
			<?php
			// ToDo: Convert this to use the new optimized function
			lwtv_plugin()->generate_statistics( 'shows', 'on-air', 'piechart' );
			?>
		</div>
		<div class="col-sm-6">
			<?php
			// ToDo: Convert this to use the new optimized function
			lwtv_plugin()->generate_statistics( 'shows', 'on-air', 'percentage' );
			?>
		</div>
	</div>
</div>
<?php
