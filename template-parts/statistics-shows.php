<?php
/**
 * The template for displaying the shows stats page
 *
 * @package LezWatchTV
 */
?>
<h2><a href="/shows/">Total Shows</a></strong> (<?php echo LWTV_Stats::generate( 'shows', 'total', 'count' ); ?>)</h2>

<center><a href="#charts">Charts</a> // <a href="#percentages">Percentages</a></center>

<hr>

<h2><a name="charts">Charts</a></h2>

<div class="container">
	<div class="row">
		<div class="col">
			<h3>Worth It?</h3>
			<div class="statistics-piechart">
				<?php LWTV_Stats::generate( 'shows', 'thumbs', 'piechart' ); ?>
			</div>
		</div>
		<div class="col">
			<h3>Stars</h3>
			<div class="statistics-piechart">
				<?php LWTV_Stats::generate( 'shows', 'stars', 'piechart' ); ?>
			</div>
		</div>

		<div class="w-100"></div>
	
		<div class="col">
			<h3>Show Format</h3>
			<div class="statistics-piechart">
				<?php LWTV_Stats::generate( 'shows', 'formats', 'piechart' ); ?>
			</div>
		</div>
		<div class="col">
			<h3>Trigger Warnings</h3>
			<div class="statistics-piechart">
				<?php LWTV_Stats::generate( 'shows', 'trigger', 'piechart' ); ?>
			</div>
		</div>

		<div class="w-100"></div>
	
		<div class="col">
			<h3>Trigger Warnings</h3>
			<div class="statistics-piechart">
				<?php LWTV_Stats::generate( 'shows', 'trigger', 'piechart' ); ?>
			</div>
		</div>
		<div class="col">
			<h3>Currently Airing</h3>
			<div class="statistics-piechart">
				<?php LWTV_Stats::generate( 'shows', 'current', 'piechart' ); ?>
			</div>
		</div>
	</div>
</div>

<hr>

<h2><a name="percentages">Percentages</a></h2>

<div class="container">
	<div class="row">
		<div class="col">
			<h3>Worth It Scores</h3>
			<?php LWTV_Stats::generate( 'shows', 'thumbs', 'percentage' ); ?>
		</div>
	</div>
	<div class="row">
		<div class="col">
			<h3>Stars Rankings</h3>
			<?php LWTV_Stats::generate( 'shows', 'stars', 'percentage' ); ?>
		</div>
	</div>
	<div class="row">
		<div class="col">
			<h3>Stars Rankings</h3>
			<?php LWTV_Stats::generate( 'shows', 'formats', 'percentage' ); ?>
		</div>
	</div>
	<div class="row">
		<div class="col">
			<h3>Trigger Warnings</h3>
			<?php LWTV_Stats::generate( 'shows', 'trigger', 'percentage' ); ?>
		</div>
	</div>
	<div class="row">
		<div class="col">
			<h3>Currently Airing</h3>
			<?php LWTV_Stats::generate( 'shows', 'current', 'percentage' ); ?>
		</div>
	</div>
</div>