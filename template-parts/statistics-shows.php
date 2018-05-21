<?php
/**
 * The template for displaying the shows stats page
 *
 * @package LezWatchTV
 */

$valid_views = array( 'overview', 'tropes', 'genres', 'formats', 'stars', 'triggers', 'on-air', 'worth-it', 'we-love-it' );
$view        = ( !isset( $_GET['view'] ) || !in_array( $_GET['view'], $valid_views ) )? 'overview' : $_GET['view'];

?>
<h2>
	<a href="/shows/">Total Shows</a> (<?php echo LWTV_Stats::generate( 'shows', 'total', 'count' ); ?>)
</h2>

<ul class="nav nav-tabs">
	<?php
	foreach ( $valid_views as $the_view ) {
		$active = ( $view == $the_view )? ' active' : '';
		echo '<li class="nav-item"><a class="nav-link' . $active . '" href="' . esc_url( add_query_arg( 'view', $the_view, '/statistics/shows/' ) ) . '">' . strtoupper( str_replace( '-', ' ', $the_view ) ) . '</a></li>';
	}
	?>
</ul>

<p>&nbsp;</p>

<?php

switch ( $view ) {
	case 'overview':
		?>

		<div class="container">
			<div class="row equal-height">
				<div class="col-4">
					<div class="alert alert-success" role="info"><center>
						<h3 class="alert-heading">Shows</h3>
						<h5><?php echo LWTV_Stats::generate( 'shows', 'total', 'count' ); ?></h5>
					</center></div>
				</div>
				<div class="col-4">
					<div class="alert alert-info" role="info"><center>
						<h3 class="alert-heading">Tropes</h3>
						<h5><?php echo wp_count_terms( 'lez_tropes' ); ?></h5>
					</center></div>
				</div>
				<div class="col-4">
					<div class="alert alert-warning" role="info"><center>
						<h3 class="alert-heading">Genres</h3>
						<h5><?php echo wp_count_terms( 'lez_genres' ); ?></h5>
					</center></div>
				</div>
			</div>
		</div>

		<p>The average show score is <strong><?php LWTV_Stats::generate( 'shows', 'scores', 'average' ); ?></strong>. The lowest score is <strong><?php LWTV_Stats::generate( 'shows', 'scores', 'low' ); ?></strong> and the highest is <strong><?php LWTV_Stats::generate( 'shows', 'scores', 'high' ); ?></strong>.</p>

		<div class="container">
			<div class="row equal-height">
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
							<?
							$tropes  = get_terms( 'lez_tropes', array(
								'number'     => 10,
								'orderby'    => 'count',
								'hide_empty' => 0,
								'order'      => 'DESC',
							) );
							foreach( $tropes as $trope ) {
								echo '<tr>
										<th scope="row"><a href="/trope/' . $trope->slug . '">' . $trope->name . '</a></th>
										<td>' . $trope->count . '</td>
									</tr>';
							}
							?>
						</tbody>
					</table>
					<a href="?view=tropes"><button type="button" class="btn btn-info btn-lg btn-block">All <?php echo wp_count_terms( 'lez_tropes' ); ?> Tropes</button></a>
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
							<?
							$genres = get_terms( 'lez_genres', array(
								'number'     => 10,
								'orderby'    => 'count',
								'hide_empty' => 0,
								'order'      => 'DESC',
							) );
							foreach( $genres as $genre ) {
								echo '<tr>
										<th scope="row"><a href="/genre/' . $genre->slug . '">' . $genre->name . '</a></th>
										<td>' . $genre->count . '</td>
									</tr>';
							}
							?>
						</tbody>
					</table>
					<a href="?view=genres"><button type="button" class="btn btn-info btn-lg btn-block">All <?php echo wp_count_terms( 'lez_genres' ); ?> Genres</button></a>
				</div>
			</div>
		</div>
		<?php
		break;
	case 'tropes':
		?>
		<div class="container chart-container">
			<div class="row">
				<div class="col">
					<?php LWTV_Stats::generate( 'shows', 'tropes', 'barchart' ); ?>
				</div>
			</div>
			<div class="row">
				<div class="col">
					<?php LWTV_Stats::generate( 'shows', 'tropes', 'list' ); ?>
				</div>
			</div>
		</div>
		<?php
		break;
	case 'genres':
		?>
		<div class="container chart-container">
			<div class="row">
				<div class="col">
					<?php LWTV_Stats::generate( 'shows', 'genres', 'barchart' ); ?>
				</div>
			</div>
			<div class="row">
				<div class="col">
					<?php LWTV_Stats::generate( 'shows', 'genres', 'list' ); ?>
				</div>
			</div>
		</div>
		<?php
		break;
	case 'worth-it':
		?>
		<div class="container chart-container">
			<div class="row">
				<div class="col-sm-6">
					<?php LWTV_Stats::generate( 'shows', 'thumbs', 'piechart' ); ?>
				</div>
				<div class="col-sm-6">
					<?php LWTV_Stats::generate( 'shows', 'thumbs', 'percentage' ); ?>
				</div>
			</div>
		</div>
		<?php
		break;
	case 'stars':
		?>
		<div class="container chart-container">
			<div class="row">
				<div class="col-sm-6">
					<?php LWTV_Stats::generate( 'shows', 'stars', 'piechart' ); ?>
				</div>
				<div class="col-sm-6">
					<?php LWTV_Stats::generate( 'shows', 'stars', 'percentage' ); ?>
				</div>
			</div>
		</div>
		<?php
		break;
	case 'format':
		?>
		<div class="container chart-container">
			<div class="row">
				<div class="col-sm-6">
					<?php LWTV_Stats::generate( 'shows', 'formats', 'piechart' ); ?>
				</div>
				<div class="col-sm-6">
					<?php LWTV_Stats::generate( 'shows', 'formats', 'percentage' ); ?>
				</div>
			</div>
		</div>
		<?php
		break;
	case 'triggers':
		?>
		<div class="container chart-container">
			<div class="row">
				<div class="col-sm-6">
					<?php LWTV_Stats::generate( 'shows', 'triggers', 'piechart' ); ?>
				</div>
				<div class="col-sm-6">
					<?php LWTV_Stats::generate( 'shows', 'triggers', 'percentage' ); ?>
				</div>
			</div>
		</div>
		<?php
		break;
	case 'we-love-it':
		?>
		<div class="container chart-container">
			<div class="row">
				<div class="col-sm-6">
					<?php LWTV_Stats::generate( 'shows', 'weloveit', 'piechart' ); ?>
				</div>
				<div class="col-sm-6">
					<?php LWTV_Stats::generate( 'shows', 'weloveit', 'percentage' ); ?>
				</div>
			</div>
		</div>
		<?php
		break;
	case 'on-air'
		?>
		<div class="container chart-container">
			<div class="row">
				<div class="col-sm-6">
					<?php LWTV_Stats::generate( 'shows', 'current', 'piechart' ); ?>
				</div>
				<div class="col-sm-6">
					<?php LWTV_Stats::generate( 'shows', 'current', 'percentage' ); ?>
				</div>
			</div>
		</div>
		<?php
		break;
}