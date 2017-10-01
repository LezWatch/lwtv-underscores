<?php
/**
 * The template for displaying the shows stats page
 *
 * @package LezWatchTV
 */
?>
<h2>
	<a href="/shows/">Total Shows</a></strong> (<?php echo LWTV_Stats::generate( 'shows', 'total', 'count' ); ?>)
</h2>

<section id="toc" class="toc-container card-body">
	<nav class="breadcrumb">
		<h4 class="toc-title">Go to:</h4>
		<a class="breadcrumb-item smoothscroll" href="#charts">Charts</a>
		<a class="breadcrumb-item smoothscroll" href="#percentages">Percentages</a>
	</nav>
</section>


<h2>
	<a name="charts">Charts</a>
</h2>

<div class="container chart-container">
	<div class="row">
		<div class="col-sm-6">
			<h3>Worth It?</h3>
			<?php LWTV_Stats::generate( 'shows', 'thumbs', 'piechart' ); ?>
		</div>
		<div class="col-sm-6">
			<h3>Stars</h3>
			<?php LWTV_Stats::generate( 'shows', 'stars', 'piechart' ); ?>
		</div>
	</div>
	<div class="row">	
		<div class="col-sm-6">
			<h3>Show Format</h3>
			<?php LWTV_Stats::generate( 'shows', 'formats', 'piechart' ); ?>
		</div>
		<div class="col-sm-6">
			<h3>Trigger Warnings</h3>
			<?php LWTV_Stats::generate( 'shows', 'trigger', 'piechart' ); ?>
		</div>
	</div>
	<div class="row">
		<div class="col-sm-6">
			<h3>Shows We Love</h3>
			<?php LWTV_Stats::generate( 'shows', 'weloveit', 'piechart' ); ?>
		</div>
		<div class="col-sm-6">
			<h3>Currently Airing</h3>
			<?php LWTV_Stats::generate( 'shows', 'current', 'piechart' ); ?>
		</div>
	</div>
</div>

<hr>

<h2><a name="percentages">Percentages</a></h2>

<div class="container percentage-container">
	<div class="row">
		<div class="col-sm-6">
			<h3>Worth It Scores</h3>
			<?php LWTV_Stats::generate( 'shows', 'thumbs', 'percentage' ); ?>
		</div>
		<div class="col-sm-6">
			<h3>Stars Rankings</h3>
			<?php LWTV_Stats::generate( 'shows', 'stars', 'percentage' ); ?>
		</div>
	</div>
	<div class="row">
		<div class="col-sm-6">
			<h3>Show Formats</h3>
			<?php LWTV_Stats::generate( 'shows', 'formats', 'percentage' ); ?>
		</div>
		<div class="col-sm-6">
			<h3>Trigger Warnings</h3>
			<?php LWTV_Stats::generate( 'shows', 'trigger', 'percentage' ); ?>
		</div>
	</div>
	<div class="row">
		<div class="col-sm-6">
			<h3>Shows We Love</h3>
			<?php LWTV_Stats::generate( 'shows', 'weloveit', 'percentage' ); ?>
		</div>
		<div class="col-sm-6">
			<h3>Currently Airing</h3>
			<?php LWTV_Stats::generate( 'shows', 'current', 'percentage' ); ?>
		</div>
	</div>
</div>