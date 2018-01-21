<?php
/**
 * The template for displaying station statistics
 *
 * @package LezWatchTV
 */
?>
<h2>
	Total Stations (<?php echo LWTV_Stats::generate( 'shows', 'stations', 'count' ); ?>)
</h2>

<div class="container">
	<div class="row">
		<div class="col">
			<?php //LWTV_Stats::generate( 'shows', 'stations', 'barchart' ); ?>
		</div>
	</div>
</div>

<div class="container">
	<div class="row">
		<div class="col">
			<?php //LWTV_Stats::generate( 'shows', 'stations-gender', 'stackedbar' ); ?>
		</div>
	</div>
</div>

<div class="container">
	<div class="row">
		<div class="col">
			<?php //LWTV_Stats::generate( 'shows', 'stations-sexuality', 'stackedbar' ); ?>
		</div>
	</div>
</div>

<hr>

<div class="container">
	<div class="row">
		<div class="col">
			<?php LWTV_Stats::generate( 'shows', 'stations', 'percentage' ); ?>
		</div>
	</div>
</div>