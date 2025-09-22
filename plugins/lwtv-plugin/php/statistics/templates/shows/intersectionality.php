<?php
/**
 * The template for displaying intersectionality statistics
 *
 * @package LezWatch.TV
 */

?>
<h3>Intersectionality Breakdown</h3>

<div class="container chart-container">
	<div class="row">
		<div class="col-sm-6">
			<?php echo lwtv_plugin()->generate_shows_statistics( 'piechart', 'intersections' ); ?>
		</div>
		<div class="col-sm-6">
			<?php echo lwtv_plugin()->generate_shows_statistics( 'percentage', 'intersections' ); ?>
		</div>
	</div>
</div>
<?php
