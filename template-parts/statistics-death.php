<?php
/**
 * The template for displaying the death stats page
 *
 * @package LezWatchTV
 */

$deadchars = LWTV_Stats::generate( 'characters', 'dead', 'count' );
$allchars  = LWTV_Stats::generate( 'characters', 'all', 'count' );
$deadshows = LWTV_Stats::generate( 'shows', 'dead', 'count' );
$allshows  = LWTV_Stats::generate( 'shows', 'all', 'count' );

$deadchar_percent = round( ( $deadchars / $allchars ) * 100 , 2 ) ;
$deadshow_percent = round( ( $deadshows / $allshows ) * 100 , 2 );

?>
<h2>Totals of Death</h2>

<div class="container">
	<div class="row">
		<div class="col">
			<strong><a href="/cliche/dead/">Dead Characters</a> — <?php echo $deadchar_percent; ?>% (<?php echo $deadchars; ?> characters)
		</div>
		<div class="col">
			<strong><a href="/trope/dead-queers/">Shows with Dead</a> — <?php echo $deadshow_percent; ?>% (<?php echo $deadshows; ?> shows)
		</div>
	</div>
</div>

<center><a href="#charts">Charts</a> // <a href="#percentages">Percentages</a></center>

<hr>

<h2><a name="charts">Charts</a></h2>

<div class="container">
	<div class="row">
		<div class="col-sm-6">
			<h3>Shows With Dead</h3>
			<?php LWTV_Stats::generate( 'characters', 'dead-shows', 'piechart' ); ?>	
		</div>
		<div class="col-sm-6">
			<h3>Character Sexuality</h3>
			<?php LWTV_Stats::generate( 'characters', 'dead-sex', 'piechart' ); ?>
		</div>

		<div class="w-100"></div>
	
		<div class="col-sm-6">
			<h3>Character Gender Identity</h3>
			<?php LWTV_Stats::generate( 'characters', 'dead-gender', 'piechart' ); ?>
		</div>
		<div class="col-sm-6">
			<h3>Character Role</h3>
				<?php LWTV_Stats::generate( 'characters', 'dead-role', 'piechart' ); ?>
		</div>
	</div>
</div>

<p>On average, <strong><?php LWTV_Stats::generate( 'characters', 'dead-years', 'average' ); ?></strong> characters die per year (including years where no queers died).</p>

<div class="container">
	<div class="row">
		<div class="col">
			<?php LWTV_Stats::generate( 'characters', 'dead-years', 'barchart' ); ?>
		</div>
	</div>
	<div class="row">
		<div class="col">
			<h3>Deaths by Year</h3>
			<?php LWTV_Stats::generate( 'characters', 'dead-years', 'percentage' ); ?>
		</div>
	</div>
</div>

<hr>

<h2><a name="percentages">Percentages</a></h2>

<p>Percentages are of <em>all</em> queers and shows, not just the dead.</p>

<div class="container">
	<div class="row">
		<div class="col">
			<h3>Shows</h3>
			<?php LWTV_Stats::generate( 'shows', 'dead-shows', 'percentage' ); ?>
		</div>
	</div>
	<div class="row">
		<div class="col">
			<h3>Sexual Orientation</h3>
			<?php LWTV_Stats::generate( 'characters', 'dead-sex', 'percentage' ); ?>
		</div>
	</div>
	<div class="row">
		<div class="col">
			<h3>Gender Identity</h3>
			<?php LWTV_Stats::generate( 'characters', 'dead-gender', 'percentage' ); ?>
		</div>
	</div>
	<div class="row">
		<div class="col">
			<h3>Character Role</h3>
			<?php LWTV_Stats::generate( 'characters', 'dead-role', 'percentage' ); ?>
		</div>
	</div>
</div>