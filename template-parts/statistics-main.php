<?php
/**
 * The template for displaying the main stats page
 *
 * @package LezWatchTV
 */
?>
 
<hr>

<div class="container">
	<div class="row">
		<div class="col">
			<strong><a href="/characters/">Total Characters</a></strong> (<?php echo LWTV_Stats::generate( 'characters', 'total', 'count' ); ?>)
		</div>
		<div class="col">
			<strong><a href="/shows/">Total Shows</a></strong> (<?php echo LWTV_Stats::generate( 'shows', 'total', 'count' ); ?>)
		</div>
	</div>
</div>

<hr>

<div class="container">
	<div class="row">
		<div class="col">
			<?php LWTV_Stats::generate( 'characters', 'cliches', 'barchart' ); ?>
		</div>
	</div>
	<div class="row">
		<div class="col">
			<?php LWTV_Stats::generate( 'shows', 'tropes', 'barchart' ); ?>
		</div>
	</div>
</div>

<hr>

<div class="container">
	<div class="row">
		<div class="col">
			<?php LWTV_Stats::generate( 'characters', 'cliches', 'list' ); ?>
		</div>
	</div>
	<div class="row">
		<div class="col">
			<?php LWTV_Stats::generate( 'shows', 'tropes', 'list' ); ?>
		</div>
	</div>
</div>