<?php
/**
 * The template for displaying weloveit statistics
 *
 * @package LezWatch.TV
 */
?>

<h3>Shows We Love</h3>

<div class="container chart-container">
	<div class="row">
		<div class="col-sm-6">
			<?php echo lwtv_plugin()->generate_shows_statistics( 'piechart', 'we-love-it' ); ?>
		</div>
		<div class="col-sm-6">
			<?php echo lwtv_plugin()->generate_shows_statistics( 'percentage', 'we-love-it' ); ?>
		</div>
	</div>
</div>
