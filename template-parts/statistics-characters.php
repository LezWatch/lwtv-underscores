<?php
/**
 * The template for displaying the character stats page
 *
 * @package LezWatchTV
 */
?>
<h2><a href="/characters/">Total Characters</a></strong> (<?php echo LWTV_Stats::generate( 'characters', 'total', 'count' ); ?>)</h2>

<center><a href="#charts">Charts</a> // <a href="#percentages">Percentages</a></center>

<h2><a name="charts">Charts</a></h2>

<div class="container">
	<div class="row">
		<div class="col">
			<h3>Sexuality</h3>
			<div class="statistics-piechart">
				<?php LWTV_Stats::generate( 'characters', 'sexuality', 'piechart' ); ?>
			</div>
		</div>
		<div class="col">
			<h3>Gender Identity</h3>
			<div class="statistics-piechart">
				<?php LWTV_Stats::generate( 'characters', 'gender', 'piechart' ); ?>
			</div>
		</div>

		<div class="w-100"></div>
	
		<div class="col">
			<h3>Queer IRL</h3>
			<div class="statistics-piechart">
				<?php LWTV_Stats::generate( 'characters', 'queer-irl', 'piechart' ); ?>
			</div>
		</div>
		<div class="col">
			<h3>By Role</h3>
			<div class="statistics-piechart">
				<?php LWTV_Stats::generate( 'characters', 'role', 'piechart' ); ?>
			</div>
		</div>
	</div>
</div>

<hr>

<h2><a name="percentages">Percentages</a></h2>

<div class="container">
	<div class="row">
		<div class="col">
			<h3>Sexual Identity</h3>
			<?php LWTV_Stats::generate( 'characters', 'sexuality', 'percentage' ); ?>
		</div>
	</div>
	<div class="row">
		<div class="col">
			<h3>Gender Identity</h3>
			<?php LWTV_Stats::generate( 'characters', 'gender', 'percentage' ); ?>
		</div>
	</div>
	<div class="row">
		<div class="col">
			<h3>Roles</h3>
			<?php LWTV_Stats::generate( 'characters', 'role', 'percentage' ); ?>
		</div>
	</div>
	<div class="row">
		<div class="col">
			<h3>Queer IRL</h3>
			<?php LWTV_Stats::generate( 'characters', 'queer-irl', 'percentage' ); ?>
		</div>
	</div>
</div>
