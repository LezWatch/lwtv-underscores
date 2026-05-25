<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The template for displaying the overview statistics - Optimized Version
 *
 * @package LezWatch.TV
 *
 * Required variables:
 * @var int $shows - Total shows
 * @var int $characters - Total characters
 * @var int $actors - Total actors
 * @var int $dead_chars - Total dead characters
 */

?>

<div class="container">
	<div class="row">
		<div class="col">
			<div class="card text-center">
				<h3 class="card-header shows">Shows</h3>
				<div class="card-body bg-light">
					<h5 class="card-title"><?php echo (int) $shows; ?></h5>
					<a href="shows" class="btn btn-primary btn-sm">Show Statistics</a>
				</div>
			</div>
		</div>
		<div class="col">
			<div class="card text-center">
				<h3 class="card-header characters">Characters</h3>
				<div class="card-body bg-light">
					<h5 class="card-title"><?php echo (int) $characters; ?></h5>
					<a href="characters" class="btn btn-primary btn-sm">Character Statistics</a>
				</div>
			</div>
		</div>
		<div class="col">
			<div class="card text-center">
				<h3 class="card-header actors">Actors</h3>
				<div class="card-body bg-light">
					<h5 class="card-title"><?php echo (int) $actors; ?></h5>
					<a href="actors" class="btn btn-primary btn-sm">Actor Statistics</a>
				</div>
			</div>
		</div>
		<div class="col">
			<div class="card text-center">
				<h3 class="card-header dead-characters">Dead Characters</h3>
				<div class="card-body bg-light">
					<h5 class="card-title"><?php echo (int) $dead_chars; ?></h5>
					<a href="death" class="btn btn-primary btn-sm">Death Statistics</a>
				</div>
			</div>
		</div>
	</div>
</div>
