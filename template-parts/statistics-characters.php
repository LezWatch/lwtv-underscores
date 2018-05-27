<?php
/**
 * The template for displaying the character stats page
 *
 * @package LezWatchTV
 */

$valid_views = array( 'overview', 'cliches', 'gender', 'sexuality', 'queer-irl', 'roles' );
$view        = ( !isset( $_GET['view'] ) || !in_array( $_GET['view'], $valid_views ) )? 'overview' : $_GET['view'];
?>

<h2>
	<a href="/characters/">Total Characters</a></strong> (<?php echo LWTV_Stats::generate( 'characters', 'total', 'count' ); ?>)
</h2>

<ul class="nav nav-tabs">
	<?php
	foreach ( $valid_views as $the_view ) {
		$active = ( $view == $the_view )? ' active' : '';
		echo '<li class="nav-item"><a class="nav-link' . $active . '" href="' . esc_url( add_query_arg( 'view', $the_view, '/statistics/characters/' ) ) . '">' . strtoupper( str_replace( '-', ' ', $the_view ) ) . '</a></li>';
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
				<div class="col">
					<div class="alert alert-success" role="info"><center>
						<h3 class="alert-heading">Characters</h3>
						<h5><?php echo LWTV_Stats::generate( 'characters', 'total', 'count' ); ?></h5>
					</center></div>
				</div>
				<div class="col">
					<div class="alert alert-info" role="info"><center>
						<h3 class="alert-heading">Sexual Orientations</h3>
						<h5><?php echo wp_count_terms( 'lez_sexuality' ); ?></h5>
					</center></div>
				</div>
				<div class="col">
					<div class="alert alert-warning" role="info"><center>
						<h3 class="alert-heading">Gender Identities</h3>
						<h5><?php echo wp_count_terms( 'lez_gender' ); ?></h5>
					</center></div>
				</div>
			</div>
		</div>

		<div class="container">
			<div class="row equal-height">
				<div class="col">
					<h4>Top Clichés</h4>
					<table class="table table-striped table-hover">
						<thead>
							<tr>
								<th scope="col">Cliché</th>
								<th scope="col">Characters</th>
							</tr>
						</thead>
						<tbody>
							<?
							$cliches = get_terms( 'lez_cliches', array(
								'number'     => 14,
								'orderby'    => 'count',
								'hide_empty' => 0,
								'order'      => 'DESC',
							) );
							foreach( $cliches as $cliche ) {
								echo '<tr>
										<th scope="row"><a href="/cliche/' . $cliche->slug . '">' . $cliche->name . '</a></th>
										<td>' . $cliche->count . '</td>
									</tr>';
							}
							?>
						</tbody>
					</table>
					<a href="?view=cliches"><button type="button" class="btn btn-info btn-lg btn-block">All <?php echo wp_count_terms( 'lez_cliches' ); ?> Clichés</button></a>
				</div>
		
				<div class="col">
					<h4>Top Sexual Orientations</h4>
					<table class="table table-striped table-hover">
						<thead>
							<tr>
								<th scope="col">Sexuality</th>
								<th scope="col">Characters</th>
							</tr>
						</thead>
						<tbody>
							<?
							$stations = get_terms( 'lez_sexuality', array(
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
					<a href="?view=sexuality"><button type="button" class="btn btn-info btn-lg btn-block">All <?php echo wp_count_terms( 'lez_sexuality' ); ?> Sexual Orientations</button></a>

					<p>&nbsp;</p>

					<h4>Top Gender Identities</h4>
					<table class="table table-striped table-hover">
						<thead>
							<tr>
								<th scope="col">Gender</th>
								<th scope="col">Characters</th>
							</tr>
						</thead>
						<tbody>
							<?
							$genders = get_terms( 'lez_gender', array(
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
					<a href="?view=gender"><button type="button" class="btn btn-info btn-lg btn-block">All <?php echo wp_count_terms( 'lez_gender' ); ?> Gender Identities</button></a>

				</div>
			</div>
		</div>
		<?php
		break;
	case 'cliches':
		?>
		<div class="container chart-container">
			<div class="row">
				<div class="col">
					<?php LWTV_Stats::generate( 'characters', 'cliches', 'barchart' ); ?>
				</div>
			</div>
			<div class="row">
				<div class="col">
					<?php LWTV_Stats::generate( 'characters', 'cliches', 'list' ); ?>
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
					<?php LWTV_Stats::generate( 'characters', 'sexuality', 'piechart' ); ?>
				</div>
				
				<div class="col-sm-6">
					<?php LWTV_Stats::generate( 'characters', 'sexuality', 'percentage' ); ?>
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
					<?php LWTV_Stats::generate( 'characters', 'gender', 'piechart' ); ?>
				</div>
				
				<div class="col-sm-6">
					<?php LWTV_Stats::generate( 'characters', 'gender', 'percentage' ); ?>
				</div>
			</div>
		</div>
		<?php
		break;
	case 'queer-irl':
		?>
		<div class="container chart-container">
			<div class="row">
				<div class="col-sm-6">
					<?php LWTV_Stats::generate( 'characters', 'queer-irl', 'piechart' ); ?>
				</div>
				
				<div class="col-sm-6">
					<?php LWTV_Stats::generate( 'characters', 'queer-irl', 'percentage' ); ?>
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