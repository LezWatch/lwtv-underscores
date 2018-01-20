<?php
/**
 * The template for displaying trendline statistics
 *
 * @package LezWatchTV
 */
?>
<h2><a name="deathperyear">Shows per Nation</a></h2>

<div class="container">
	<div class="row">
		<div class="col">
			<?php LWTV_Stats::generate( 'shows', 'nations', 'barchart' ); ?>
		</div>
	</div>
</div>

<div class="container">
	<div class="row">
		<div class="col">
			<?php LWTV_Stats::generate( 'shows', 'nations', 'stackedbar' ); ?>
		</div>
	</div>
</div>