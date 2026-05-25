<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The template for displaying characters on-air statistics - Optimized Version
 *
 * @package LezWatch.TV
 */
?>
<h3>Number of Characters On-Air per Year</h3>

<div class="container chart-container">
	<div class="row">
		<div class="col-sm-12">
			<?php echo lwtv_plugin()->generate_characters_statistics( 'barchart', 'on-air' ); ?>
		</div>
	</div>
</div>
<?php
