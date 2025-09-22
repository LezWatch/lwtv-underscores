<?php
/**
 * The template for displaying characters gender statistics - Optimized Version
 *
 * @package LezWatch.TV
 */
?>
<h3>Character Gender Identity Breakdown</h3>

<div class="container chart-container">
	<div class="row">
		<div class="col-sm-6">
			<?php echo lwtv_plugin()->generate_characters_statistics( 'piechart', 'gender' ); ?>
		</div>
		<div class="col-sm-6">
			<?php echo lwtv_plugin()->generate_characters_statistics( 'percentage', 'gender' ); ?>
		</div>
	</div>
</div>
<?php
