<?php
/**
 * The template for displaying the main stats page
 *
 * @package LezWatch.TV
 */
?>

<?php 
	the_content(); 
	
	$characters = LWTV_Stats::generate( 'characters', 'total', 'count' );
	$shows      = LWTV_Stats::generate( 'shows', 'total', 'count' );
	$actors     = LWTV_Stats::generate( 'actors', 'total', 'count' );
	$dead_chars = LWTV_Stats::generate( 'characters', 'dead', 'count' );
	?>

<h2><a name="overview">Overview</a></h2>

<div class="container">
	<div class="row equal-height">
		<div class="col">
			<div class="alert alert-success" role="info"><center>
				<h3 class="alert-heading">Characters</h3>
				<h5><?php echo $characters; ?></h5>
				<p>[<a href="characters">Character Statistics</a>]</p>
			</center></div>
		</div>
		<div class="col">
			<div class="alert alert-info" role="info"><center>
				<h3 class="alert-heading">Shows</h3>
				<h5><?php echo $shows; ?></h5>
				<p>[<a href="shows">Show Statistics</a>]</p>
			</center></div>
		</div>
		<div class="col">
			<div class="alert alert-warning" role="info"><center>
				<h3 class="alert-heading">Actors</h3>
				<h5><?php echo $actors; ?></h5>
				<p>[<a href="actors">Actor Statistics</a>]</p>
			</center></div>
		</div>
		<div class="col">
			<div class="alert alert-danger" role="info"><center>
				<h3 class="alert-heading">Dead Characters</h3>
				<h5><?php echo $dead_chars; ?></h5>
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
						<th scope="col">&nbsp;</th>
						<th scope="col">Shows</th>
						<th scope="col">Percent</th>
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
								<td>' . round( ( ( $nation->count / $shows ) * 100 ) , 1 ) .'%</td>
							</tr>';
					}
					?>
				</tbody>
			</table>
			<a href="nations"><button type="button" class="btn btn-info btn-lg btn-block">All <?php echo wp_count_terms( 'lez_country' ); ?> Nations</button></a>
		</div>

		<div class="col">
			<h4>Top Ten Stations and Networks</h4>
			<table class="table table-striped table-hover">
				<thead>
					<tr>
						<th scope="col">&nbsp;</th>
						<th scope="col">Shows</th>
						<th scope="col">Percent</th>
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
								<td>' . round( ( ( $station->count / $shows ) * 100 ) , 1 ) .'%</td>
							</tr>';
					}
					?>
				</tbody>
			</table>
			<a href="stations"><button type="button" class="btn btn-info btn-lg btn-block">All <?php echo wp_count_terms( 'lez_stations' ); ?> Stations</button></a>
		</div>
	</div>
</div>
