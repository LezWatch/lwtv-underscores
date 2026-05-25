<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The template for displaying tropes statistics
 *
 * @package LezWatch.TV
 */
?>
<h3>Trope Breakdown</h3>

<div class="container chart-container">
	<div class="row">
		<div class="col-sm-6">
			<?php echo lwtv_plugin()->generate_shows_statistics( 'piechart', 'tropes' ); ?>
		</div>
		<div class="col-sm-6">
			<?php echo lwtv_plugin()->generate_shows_statistics( 'percentage', 'tropes' ); ?>
		</div>
	</div>
</div>
<?php
