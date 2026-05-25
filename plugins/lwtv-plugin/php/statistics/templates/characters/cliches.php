<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The template for displaying characters cliches statistics - Optimized Version
 *
 * @package LezWatch.TV
 */
?>
<h3>Cliché Demographics</h3>

<div class="container chart-container">
	<div class="row">
		<div class="col-sm-6">
			<?php echo lwtv_plugin()->generate_characters_statistics( 'piechart', 'cliches' ); ?>
		</div>
		<div class="col-sm-6">
			<?php echo lwtv_plugin()->generate_characters_statistics( 'percentage', 'cliches' ); ?>
		</div>
	</div>
</div>
<?php
