<?php
/**
 * The template for displaying the main stats page
 *
 * @package LezWatchTV
 */
?>

<?php the_content(); ?>

<h2><a name="overview">Overview</a></h2>

<div class="container">
	<div class="row equal-height">
		<div class="col-3">
			<div class="alert alert-success" role="info"><center>
				<h3 class="alert-heading">Characters</h3>
				<h5><?php echo LWTV_Stats::generate( 'characters', 'total', 'count' ); ?></h5>
				<p>[<a href="characters">Character Statistics</a>]</p>
			</center></div>
		</div>
		<div class="col-3">
			<div class="alert alert-info" role="info"><center>
				<h3 class="alert-heading">Shows</h3>
				<h5><?php echo LWTV_Stats::generate( 'shows', 'total', 'count' ); ?></h5>
				<p>[<a href="shows">Show Statistics</a>]</p>
			</center></div>
		</div>
		<div class="col-3">
			<div class="alert alert-warning" role="info"><center>
				<h3 class="alert-heading">Actors</h3>
				<h5><?php echo LWTV_Stats::generate( 'actors', 'total', 'count' ); ?></h5>
				<p>[<a href="actors">Actor Statistics</a>]</p>
			</center></div>
		</div>
		<div class="col-3">
			<div class="alert alert-danger" role="info"><center>
				<h3 class="alert-heading">Dead Characters</h3>
				<h5><?php echo LWTV_Stats::generate( 'characters', 'dead', 'count' ); ?></h5>
				<p>[<a href="death">Death Statistics</a>]</p>
			</center></div>
		</div>
	</div>
</div>

<hr>

<div class="container">
	<div class="row equal-height">
		<div class="col">
			<h4>Top Ten Nations</h4>
			<table class="table table-striped table-hover">
				<thead>
					<tr>
						<th scope="col">Nation</th>
						<th scope="col">Shows</th>
					</tr>
				</thead>
				<tbody>
					<?
					$nations = get_terms( 'lez_country', array(
						'number'     => 10,
						'orderby'    => 'count',
						'hide_empty' => 0,
						'order'      => 'DESC',
					) );
					foreach( $nations as $nation ) {
						echo '<tr>
								<th scope="row"><a href="nations/?country=' . $nation->slug . '">' . $nation->name . '</a></th>
								<td>' . $nation->count . '</td>
							</tr>';
					}
					?>
				</tbody>
			</table>
			<a href="nations"><button type="button" class="btn btn-info btn-lg btn-block">All <?php echo LWTV_Stats::generate( 'shows', 'country', 'count' ); ?> Nations</button></a>
		</div>

		<div class="col">
			<h4>Top Ten Stations and Networks</h4>
			<table class="table table-striped table-hover">
				<thead>
					<tr>
						<th scope="col">Station</th>
						<th scope="col">Shows</th>
					</tr>
				</thead>
				<tbody>
					<?
					$stations = get_terms( 'lez_stations', array(
						'number'     => 10,
						'orderby'    => 'count',
						'hide_empty' => 0,
						'order'      => 'DESC',
					) );
					foreach( $stations as $station ) {
						echo '<tr>
								<th scope="row"><a href="stations/?station=' . $station->slug . '">' . $station->name . '</a></th>
								<td>' . $station->count . '</td>
							</tr>';
					}
					?>
				</tbody>
			</table>
			<a href="stations"><button type="button" class="btn btn-info btn-lg btn-block">All <?php echo LWTV_Stats::generate( 'shows', 'stations', 'count' ); ?> Stations</button></a>
		</div>
	</div>
</div>
