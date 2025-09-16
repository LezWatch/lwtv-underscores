<?php
/**
 * The template for displaying the death characters statistics - Optimized Version
 *
 * @package LezWatch.TV
 */

?>
<h3>Death By Character Sexual Orientation</h3>
<div class="container chart-container">
	<div class="row">
		<div class="col-sm-6">
			<?php echo lwtv_plugin()->generate_dead_statistics( 'characters', 'sexuality', 'piechart' ); ?>
		</div>
		<div class="col-sm-6">
			<?php echo lwtv_plugin()->generate_dead_statistics( 'characters', 'sexuality', 'percentage' ); ?>
		</div>
	</div>
</div>
<h3>Death By Character Gender Identity</h3>
<div class="container chart-container">
	<div class="row">
		<div class="col-sm-6">
			<?php echo lwtv_plugin()->generate_dead_statistics( 'characters', 'gender', 'piechart' ); ?>
		</div>
		<div class="col-sm-6">
			<?php echo lwtv_plugin()->generate_dead_statistics( 'characters', 'gender', 'percentage' ); ?>
		</div>
	</div>
</div>
<h3>Death By Character Role</h3>
<div class="container chart-container">
	<div class="row">
		<div class="col-sm-6">
			<?php echo lwtv_plugin()->generate_dead_statistics( 'characters', 'role', 'piechart' ); ?>
		</div>
		<div class="col-sm-6">
			<?php echo lwtv_plugin()->generate_dead_statistics( 'characters', 'role', 'percentage' ); ?>
		</div>
	</div>
</div>
<?php
