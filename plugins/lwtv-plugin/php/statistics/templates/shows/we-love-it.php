<?php
/**
 * The template for displaying weloveit statistics
 *
 * @package LezWatch.TV
 */
?>

<h3>Shows We Love Breakdown</h3>

<div class="container chart-container">
	<div class="row">
		<div class="col-sm-6">
			<?php
			// ToDo: Convert this to use the new optimized function
			lwtv_plugin()->generate_statistics( 'shows', 'weloveit', 'piechart' );
			?>
		</div>
		<div class="col-sm-6">
			<?php
			// ToDo: Convert this to use the new optimized function
			lwtv_plugin()->generate_statistics( 'shows', 'weloveit', 'percentage' );
			?>
		</div>
	</div>
</div>
