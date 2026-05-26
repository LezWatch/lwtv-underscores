<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The template for displaying the shows overview
 *
 * @package LezWatch.TV
 *
 * Required variables:
 * @var int $shows_count
 * @var int $count_tropes
 * @var int $count_genres
 */
?>

<div class="container">
	<div class="row">
		<div class="col">
			<div class="card text-center">
				<h3 class="card-header shows">Shows</h3>
				<div class="card-body bg-light">
					<h5 class="card-title"><?php echo (int) $shows_count; ?></h5>
				</div>
			</div>
		</div>
		<div class="col">
			<div class="card text-center">
				<h3 class="card-header tropes">Tropes</h3>
				<div class="card-body bg-light">
					<h5 class="card-title"><?php echo (int) $count_tropes; ?></h5>
				</div>
			</div>
		</div>
		<div class="col">
			<div class="card text-center">
				<h3 class="card-header genres">Genres</h3>
				<div class="card-body bg-light">
					<h5 class="card-title"><?php echo (int) $count_genres; ?></h5>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="container">
	<div class="row">
		<div class="col">
			<h4>Top Tropes</h4>
			<table class="table table-striped table-hover">
				<thead>
					<tr>
						<th scope="col">Trope</th>
						<th scope="col">Shows</th>
					</tr>
				</thead>
				<tbody>
					<?php
					// OPTIMIZED: Use pre-loaded data instead of get_terms()
					foreach ( $top_tropes as $trope_slug => $trope_data ) {
						echo '<tr>
								<th scope="row"><a href="' . esc_url( site_url( '/trope/' . $trope_slug ) ) . '">' . esc_html( $trope_data['name'] ) . '</a></th>
								<td>' . (int) $trope_data['count'] . '</td>
							</tr>';
					}
					?>
				</tbody>
			</table>
			<a href="?view=tropes"><button type="button" class="btn btn-lg btn-block">All <?php echo (int) $count_tropes; ?> Tropes</button></a>
		</div>

		<div class="col">
			<h4>Top Genres</h4>
			<table class="table table-striped table-hover">
				<thead>
					<tr>
						<th scope="col">Genre</th>
						<th scope="col">Show</th>
					</tr>
				</thead>
				<tbody>
					<?php
					// OPTIMIZED: Use pre-loaded data instead of get_terms()
					foreach ( $top_genres as $genre_slug => $genre_data ) {
						echo '<tr>
								<th scope="row"><a href="' . esc_url( site_url( '/genre/' . $genre_slug ) ) . '">' . esc_html( $genre_data['name'] ) . '</a></th>
								<td>' . (int) $genre_data['count'] . '</td>
							</tr>';
					}
					?>
				</tbody>
			</table>
			<a href="?view=genres"><button type="button" class="btn btn-lg btn-block">All <?php echo (int) $count_genres; ?> Genres</button></a>
		</div>
	</div>
</div>
<?php
