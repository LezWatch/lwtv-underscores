<?php
/**
 * The template for displaying characters queer-irl statistics - Optimized Version
 *
 * @package LezWatch.TV
 */
?>
<h3>Characters Played by Queer Actors</h3>

<div class="container chart-container">
	<div class="row">
		<div class="col-sm-6">
			<?php echo lwtv_plugin()->generate_characters_statistics( 'piechart', 'queer-irl' ); ?>
		</div>

		<div class="col-sm-6">
			<?php echo lwtv_plugin()->generate_characters_statistics( 'percentage', 'queer-irl' ); ?>
		</div>
	</div>
</div>
<?php
