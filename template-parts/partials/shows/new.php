<?php
/**
 * The template for displaying a new show.
 *
 * This is a placeholder for new shows that are just created and we're waiting for the data to be calculated.
 *
 * @package LezWatch.TV
 */

$under_construction = lwtv_plugin()->get_symbolicon( svg: 'construction.svg', icon: 'svg-construction', max_size: '15' );
?>

<section name="newshow" id="newshow" class="showschar-section">
	<h2>Under Construction</h2>

	<div class="card-body">
		<div class="alert alert-info" role="alert">
			<?php echo $under_construction; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			This show's data is still being calculated. It will be available as soon as possible.
		</div>
	</div>
</section>
