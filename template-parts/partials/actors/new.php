<?php
/**
 * The template for displaying a new actor.
 *
 * This is a placeholder for new actors that are just created and we're waiting for the data to be calculated.
 *
 * @package LezWatch.TV
 */

$under_construction = lwtv_plugin()->get_symbolicon( svg: 'stanp-portrait.svg', icon: 'svg-square', max_size: '15' );
?>

<section name="newactor" id="newactor" class="showschar-section">
	<h2>Under Construction</h2>

	<div class="card-body">
		<div class="alert alert-info" role="alert">
			<?php echo $under_construction; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			This actor's data is still being calculated. It will be available as soon as possible.
		</div>
	</div>
</section>
