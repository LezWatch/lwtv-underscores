<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The template for displaying on-air statistics
 *
 * @package LezWatch.TV
 */
?>
<h3>Number of Shows On-Air per Year</h3>

<div class="container chart-container">
	<div class="row">
		<div class="col-sm-12">
			<?php
			echo lwtv_plugin()->generate_shows_statistics( 'trendline', 'on-air' );
			?>
		</div>
	</div>
</div>
<?php
