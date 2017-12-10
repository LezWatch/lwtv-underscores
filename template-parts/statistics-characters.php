<?php
/**
 * The template for displaying the character stats page
 *
 * @package LezWatchTV
 */
?>

<h2>
	<a href="/characters/">Total Characters</a></strong> (<?php echo LWTV_Stats::generate( 'characters', 'total', 'count' ); ?>)
</h2>

<section id="toc" class="toc-container card-body">
	<nav class="breadcrumb">
		<h4 class="toc-title">Go to:</h4>
		<a class="breadcrumb-item smoothscroll" href="#charts">Charts</a>
		<a class="breadcrumb-item smoothscroll" href="#percentages">Percentages</a>
	</nav>
</section>

<h2><a name="charts">Charts</a></h2>

<div class="container chart-container">
	<div class="row">
		<div class="col-sm-6">
			<h3>Sexuality</h3>
			<?php LWTV_Stats::generate( 'characters', 'sexuality', 'piechart' ); ?>
		</div>
		<div class="col-sm-6">
			<h3>Gender Identity</h3>
			<?php LWTV_Stats::generate( 'characters', 'gender', 'piechart' ); ?>
		</div>
	</div>
	<div class="row">
		<div class="col-sm-6">
			<h3>Queer IRL</h3>
			<?php LWTV_Stats::generate( 'characters', 'queer-irl', 'piechart' ); ?>
		</div>
		<div class="col-sm-6">
			<h3>By Role</h3>
			<?php LWTV_Stats::generate( 'characters', 'role', 'piechart' ); ?>
		</div>
	</div>
</div>

<hr>

<h2><a name="barcharts">Barcharts</a></h2>

<div class="container chart-container">
	<div class="row">
		<div class="col">
			<?php LWTV_Stats::generate( 'actors', 'per-char', 'barchart' ); ?>
			<p>This chart displays the number of actors who play each character. So for example, "11 (1)" means there's one character who has 11 actors (and yes, there is one).</p>
		</div>
	</div>
	<div class="row">
		<div class="col">
			<?php LWTV_Stats::generate( 'actors', 'per-actor', 'barchart' ); ?>
			<p>This chart displays the number of characters each actor plays. So for example, "20 (1)" means there's one actor who played 20 characters (that would be the 'unknown' actor).</p>
		</div>
	</div>
</div>

<hr>

<h2><a name="percentages">Percentages</a></h2>

<div class="container percentage-container">
	<div class="row">
		<div class="col-sm-6">
			<h3>Sexual Identity</h3>
			<?php LWTV_Stats::generate( 'characters', 'sexuality', 'percentage' ); ?>
		</div>
		<div class="col-sm-6">
			<h3>Gender Identity</h3>
			<?php LWTV_Stats::generate( 'characters', 'gender', 'percentage' ); ?>
		</div>
	</div>
	<div class="row">
		<div class="col-sm-6">
			<h3>Roles</h3>
			<?php LWTV_Stats::generate( 'characters', 'role', 'percentage' ); ?>
		</div>
		<div class="col-sm-6">
			<h3>Queer IRL</h3>
			<?php LWTV_Stats::generate( 'characters', 'queer-irl', 'percentage' ); ?>
		</div>
	</div>
</div>
