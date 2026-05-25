<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The template for displaying characters sexuality statistics - Optimized Version
 *
 * @package LezWatch.TV
 */
?>
<h3>Character Sexuality Breakdown</h3>

<div class="container chart-container">
	<div class="row">
		<div class="col-sm-6">
			<?php echo lwtv_plugin()->generate_characters_statistics( 'piechart', 'sexuality' ); ?>
		</div>
		<div class="col-sm-6">
			<?php echo lwtv_plugin()->generate_characters_statistics( 'percentage', 'sexuality' ); ?>
		</div>
	</div>
</div>
<?php
