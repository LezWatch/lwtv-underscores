<?php
/**
 * The template for displaying worth-it statistics
 *
 * @package LezWatch.TV
 */
?>
<h3>Worth It Rating Breakdown</h3>

<div class="container chart-container">
	<div class="row">
		<div class="col-sm-6">
			<?php
			// ToDo: Convert this to use the new optimized function
			lwtv_plugin()->generate_statistics( 'shows', 'thumbs', 'piechart' );
			?>
		</div>
		<div class="col-sm-6">
			<?php
			// ToDo: Convert this to use the new optimized function
			lwtv_plugin()->generate_statistics( 'shows', 'thumbs', 'percentage' );
			?>
		</div>
	</div>
</div>
<?php
