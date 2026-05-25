<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The template for displaying actors gender statistics
 *
 * @package LezWatch.TV
 */
?>
<h3>Actor Gender Identity Demographics</h3>

<div class="container chart-container">
	<div class="row">
		<div class="col-sm-6">
			<?php echo lwtv_plugin()->generate_actors_statistics( 'piechart', 'gender' ); ?>
		</div>

		<div class="col-sm-6">
			<?php echo lwtv_plugin()->generate_actors_statistics( 'percentage', 'gender' ); ?>
		</div>
	</div>
</div>
<?php
