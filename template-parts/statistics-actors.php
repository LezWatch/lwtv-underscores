<?php
/**
 * The template for displaying the actor stats page
 *
 * @package LezWatchTV
 */

$valid_views = array( 'overview', 'gender', 'sexuality', 'roles' );
$view        = ( !isset( $_GET['view'] ) || !in_array( $_GET['view'], $valid_views ) )? 'overview' : $_GET['view'];
?>

<h2>
	<a href="/actors/">Total Actors</a></strong> (<?php echo LWTV_Stats::generate( 'actors', 'total', 'count' ); ?>)
</h2>

<ul class="nav nav-tabs">
	<?php
	foreach ( $valid_views as $the_view ) {
		$active = ( $view == $the_view )? ' active' : '';
		echo '<li class="nav-item"><a class="nav-link' . $active . '" href="' . esc_url( add_query_arg( 'view', $the_view, '/statistics/actors/' ) ) . '">' . strtoupper( str_replace( '-', ' ', $the_view ) ) . '</a></li>';
	}
	?>
</ul>

<p>&nbsp;</p>

<?php

switch ( $view ) {
	case 'overview':
		?>
		<div class="container">
			<div class="row">
				<div class="col">
					<div class="alert alert-success" role="info"><center>
						<h3 class="alert-heading">Actors</h3>
						<h5><?php echo LWTV_Stats::generate( 'actors', 'total', 'count' ); ?></h5>
					</center></div>
				</div>
				<div class="col">
					<div class="alert alert-info" role="info"><center>
						<h3 class="alert-heading">Sexual Orientations</h3>
						<h5><?php echo wp_count_terms( 'lez_actor_sexuality' ); ?></h5>
					</center></div>
				</div>
				<div class="col">
					<div class="alert alert-warning" role="info"><center>
						<h3 class="alert-heading">Gender Identities</h3>
						<h5><?php echo wp_count_terms( 'lez_actor_gender' ); ?></h5>
					</center></div>
				</div>
			</div>
		</div>

		<div class="container">
			<div class="row equal-height">
				<div class="col">
					<h4>Top Sexual Orientations</h4>
					<table class="table table-striped table-hover">
						<thead>
							<tr>
								<th scope="col">Sexuality</th>
								<th scope="col">Actors</th>
							</tr>
						</thead>
						<tbody>
							<?
							$stations = get_terms( 'lez_actor_sexuality', array(
								'number'     => 5,
								'orderby'    => 'count',
								'hide_empty' => 0,
								'order'      => 'DESC',
							) );
							foreach( $stations as $station ) {
								echo '<tr>
										<th scope="row"><a href="/sexuality/' . $station->slug . '">' . $station->name . '</a></th>
										<td>' . $station->count . '</td>
									</tr>';
							}
							?>
						</tbody>
					</table>
					<a href="?view=sexuality"><button type="button" class="btn btn-info btn-lg btn-block">All <?php echo wp_count_terms( 'lez_actor_sexuality' ); ?> Sexual Orientations</button></a>
				</div>
		
				<div class="col">
					<h4>Top Gender Identities</h4>
					<table class="table table-striped table-hover">
						<thead>
							<tr>
								<th scope="col">Gender</th>
								<th scope="col">Actors</th>
							</tr>
						</thead>
						<tbody>
							<?
							$genders = get_terms( 'lez_actor_gender', array(
								'number'     => 5,
								'orderby'    => 'count',
								'hide_empty' => 0,
								'order'      => 'DESC',
							) );
							foreach( $genders as $gender ) {
								echo '<tr>
										<th scope="row"><a href="/gender/' . $gender->slug . '">' . $gender->name . '</a></th>
										<td>' . $gender->count . '</td>
									</tr>';
							}
							?>
						</tbody>
					</table>
					<a href="?view=gender"><button type="button" class="btn btn-info btn-lg btn-block">All <?php echo wp_count_terms( 'lez_actor_gender' ); ?> Gender Identities</button></a>

				</div>
			</div>
		</div>
		<?php
		break;
	case 'sexuality':
		?>
		<div class="container chart-container">
			<div class="row">
				<div class="col-sm-6">
					<?php LWTV_Stats::generate( 'actors', 'actor_sexuality', 'piechart' ); ?>
				</div>
				
				<div class="col-sm-6">
					<?php LWTV_Stats::generate( 'actors', 'actor_sexuality', 'percentage' ); ?>
				</div>
			</div>
		</div>
		<?php
		break;
	case 'gender':
		?>
		<div class="container chart-container">
			<div class="row">
				<div class="col-sm-6">
					<?php LWTV_Stats::generate( 'actors', 'actor_gender', 'piechart' ); ?>
				</div>
				
				<div class="col-sm-6">
					<?php LWTV_Stats::generate( 'actors', 'actor_gender', 'percentage' ); ?>
				</div>
			</div>
		</div>
		<?php
		break;
	case 'roles':
		?>
		<div class="container chart-container">
			<div class="row">
				<div class="col">
					<h3>Actors per Character</h3>
					<?php LWTV_Stats::generate( 'actors', 'per-char', 'barchart' ); ?>
					<p>This chart displays the number of actors who play each character. So for example, "11 Actors (1)" means there's one character who has 11 actors (and yes, there is one).</p>
				</div>
			</div>
			<div class="row">
				<div class="col">
					<h3>Characters per Actor</h3>
					<?php LWTV_Stats::generate( 'actors', 'per-actor', 'barchart' ); ?>
					<p>This chart displays the number of characters each actor plays. The actor with the highest number of characters played is the 'unknown' actor.</p>
				</div>
			</div>
		</div>
		<?php
		break;
}