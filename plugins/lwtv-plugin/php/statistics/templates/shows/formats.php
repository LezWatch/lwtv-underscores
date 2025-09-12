<?php
/**
 * The template for displaying formats statistics
 *
 * @package LezWatch.TV
 *
 * Required variables:
 * @var array $shows_count
 */
?>

<h3>Show Format Breakdown</h3>

<p>See <a href="/statistics/formats/">Format Statistics</a> for more information.</p>

<div class="container chart-container">
	<div class="row">
		<div class="col-sm-6">
			<?php echo lwtv_plugin()->generate_shows_statistics( 'piechart', 'formats' ); ?>
		</div>
		<div class="col-sm-6">
			<?php echo lwtv_plugin()->generate_shows_statistics( 'percentage', 'formats' ); ?>
		</div>
	</div>
</div>
<?php
