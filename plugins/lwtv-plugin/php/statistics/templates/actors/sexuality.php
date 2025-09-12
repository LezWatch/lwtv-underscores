<?php
/**
 * The template for displaying actors sexuality statistics
 *
 * @package LezWatch.TV
 */
?>
<h3>Actor Sexuality Demographics</h3>

<div class="container chart-container">
	<div class="row">
		<div class="col-sm-6">
			<?php echo lwtv_plugin()->generate_actors_statistics( 'piechart', 'sexuality' ); ?>
		</div>

		<div class="col-sm-6">
			<?php echo lwtv_plugin()->generate_actors_statistics( 'percentage', 'sexuality' ); ?>
		</div>
	</div>
</div>
<?php
