<?php
/**
 * The template for displaying the overview of this year
 *
 * @package LezWatch.TV
 *
 * Required variables:
 * @var array $this_year
 * @var int $characters_on_air_count
 * @var int $dead_characters_count
 */

?>

<div class="container">
	<div class="row">
		<div class="col">
			<div class="card text-center">
				<h3 class="card-header characters">Characters On Air</h3>
				<div class="card-body bg-light">
					<h5 class="card-title"><?php echo (int) $characters_on_air_count; ?></h5>
				</div>
			</div>
		</div>
		<div class="col">
			<div class="card text-center">
				<h3 class="card-header dead-characters">Dead Characters</h3>
				<div class="card-body bg-light">
					<h5 class="card-title"><?php echo (int) $dead_characters_count; ?></h5>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="container">
	<div class="row">
		<div class="col">
			<div class="card text-center">
				<h3 class="card-header shows">Shows On Air</h3>
				<div class="card-body bg-light">
					<h5 class="card-title"><?php echo (int) $shows_on_air_count; ?></h5>
				</div>
			</div>
		</div>
		<div class="col">
			<div class="card text-center">
				<h3 class="card-header new-shows">New Shows</h3>
				<div class="card-body bg-light">
					<h5 class="card-title"><?php echo (int) $new_shows_count; ?></h5>
				</div>
			</div>
		</div>
		<div class="col">
			<div class="card text-center">
				<h3 class="card-header canceled-shows">Canceled Shows</h3>
				<div class="card-body bg-light">
					<h5 class="card-title"><?php echo (int) $canceled_shows_count; ?></h5>
				</div>
			</div>
		</div>
	</div>
</div>
