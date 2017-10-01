<?php
/**
 * The template for displaying trendline statistics
 *
 * @package LezWatchTV
 */
?>
<h2><a name="deathperyear">Death Per Year</a></h2>

<div class="container">
	<div class="row">
		<div class="col">
			<?php LWTV_Stats::generate( 'characters', 'dead-years', 'trendline' ); ?>
		</div>
	</div>
</div>