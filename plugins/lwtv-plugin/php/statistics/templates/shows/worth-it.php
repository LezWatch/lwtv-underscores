<?php
/**
 * The template for displaying worth-it statistics
 *
 * @package LezWatch.TV
 */
?>
<h3>Worth It Ratings</h3>

<div class="container chart-container">
	<div class="row">
		<div class="col-sm-6">
			<?php echo lwtv_plugin()->generate_shows_statistics( 'piechart', 'worth-it' ); ?>
		</div>
		<div class="col-sm-6">
			<?php echo lwtv_plugin()->generate_shows_statistics( 'percentage', 'worth-it' ); ?>
		</div>
	</div>
</div>
<?php
