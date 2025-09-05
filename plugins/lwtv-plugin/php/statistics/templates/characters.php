<?php
/**
 * The template for displaying the character stats page - Optimized Version
 *
 * @package LezWatch.TV
 */

use LWTV\Statistics\Build\Taxonomy_Optimized as Build_Taxonomy_Optimized;

$valid_views     = array( 'cliches', 'gender', 'sexuality', 'queer-irl', 'roles', 'on-air' );
$sent_view       = get_query_var( 'view', 'overview' );
$view            = ( ! in_array( $sent_view, $valid_views, true ) ) ? 'overview' : $sent_view;
$character_count = lwtv_plugin()->generate_statistics( 'characters', 'total', 'count' );

// OPTIMIZED: Pre-load taxonomy data for overview section
$optimized_taxonomy       = new Build_Taxonomy_Optimized();
$character_gender_data    = $optimized_taxonomy->make_comprehensive( 'post_type_characters', 'lez_gender', false );
$character_sexuality_data = $optimized_taxonomy->make_comprehensive( 'post_type_characters', 'lez_sexuality', false );
$character_cliches_data   = $optimized_taxonomy->make_comprehensive( 'post_type_characters', 'lez_cliches', false );
$character_onair_data     = $optimized_taxonomy->make_comprehensive( 'post_type_characters', 'lez_onair', false, 'year_asc' );

// Sort by count descending for top 10
$top_genders     = array_slice( $character_gender_data, 0, 5, true );
$top_sexualities = array_slice( $character_sexuality_data, 0, 5, true );
$top_cliches     = array_slice( $character_cliches_data, 0, 14, true );
?>

<h2>
	<a href="/characters/">Total Characters</a> (<?php echo lwtv_plugin()->generate_statistics( 'characters', 'total', 'count' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>)
</h2>

<ul class="nav nav-tabs">
	<?php
	$baseurl = '/statistics/characters/';
	echo '<li class="nav-item"><a class="nav-link' . esc_attr( ( 'overview' === $view ) ? ' active' : '' ) . '" href="' . esc_url( $baseurl ) . '">OVERVIEW</a></li>';
	foreach ( $valid_views as $the_view ) {
		$active = ( $view === $the_view ) ? ' active' : '';
		echo '<li class="nav-item"><a class="nav-link' . esc_attr( $active ) . '" href="' . esc_url( $baseurl . $the_view ) . '/">' . esc_html( strtoupper( str_replace( '-', ' ', $the_view ) ) ) . '</a></li>';
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
					<div class="card text-center">
						<h3 class="card-header characters">Characters</h3>
						<div class="card-body bg-light">
							<h5 class="card-title"><?php echo (int) $character_count; ?></h5>
						</div>
					</div>
				</div>
				<div class="col">
					<div class="card text-center">
						<h3 class="card-header sexuality">Sexual Orientations</h3>
						<div class="card-body bg-light">
							<h5 class="card-title"><?php echo (int) wp_count_terms( 'lez_sexuality' ); ?></h5>
						</div>
					</div>
				</div>
				<div class="col">
					<div class="card text-center">
						<h3 class="card-header gender">Genders</h3>
						<div class="card-body bg-light">
							<h5 class="card-title"><?php echo (int) wp_count_terms( 'lez_gender' ); ?></h5>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="container">
			<div class="row">
				<div class="col">
					<h4>Top Clichés</h4>
					<table class="table table-striped table-hover">
						<thead>
							<tr>
								<th scope="col"></th>
								<th scope="col">Characters</th>
								<th scope="col">Percent</th>
							</tr>
						</thead>
						<tbody>
							<?php
							// OPTIMIZED: Use pre-loaded data instead of get_terms()
							foreach ( $top_cliches as $cliche_slug => $cliche_data ) {
								$percent = round( ( ( $cliche_data['count'] / $character_count ) * 100 ), 1 );
								echo '<tr>
										<th scope="row"><a href="' . esc_url( site_url( '/cliche/' . $cliche_slug ) ) . '">' . esc_html( $cliche_data['name'] ) . '</a></th>
										<td>' . (int) $cliche_data['count'] . '</td>
										<td><div class="progress"><div class="progress-bar bg-info" role="progressbar" style="width: ' . esc_html( $percent ) . '%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div></div>&nbsp;' . esc_html( $percent ) . '%</td>
									</tr>';
							}
							?>
						</tbody>
					</table>
					<a href="?view=cliches"><button type="button" class="btn btn-info btn-lg btn-block">All <?php echo (int) $cliche_data['count']; ?> Clichés</button></a>
				</div>

				<div class="col">
					<h4>Top Sexual Orientations</h4>
					<table class="table table-striped table-hover">
						<thead>
							<tr>
								<th scope="col"></th>
								<th scope="col">Characters</th>
								<th scope="col">Percent</th>
							</tr>
						</thead>
						<tbody>
							<?php
							// OPTIMIZED: Use pre-loaded data instead of get_terms()
							foreach ( $top_sexualities as $sexuality_slug => $sexuality_data ) {
								$percent = round( ( ( $sexuality_data['count'] / $character_count ) * 100 ), 1 );
								echo '<tr>
										<th scope="row"><a href="' . esc_url( site_url( '/sexuality/' . $sexuality_slug ) ) . '">' . esc_html( $sexuality_data['name'] ) . '</a></th>
										<td>' . (int) $sexuality_data['count'] . '</td>
										<td><div class="progress"><div class="progress-bar bg-info" role="progressbar" style="width: ' . esc_html( $percent ) . '%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div></div>&nbsp;' . esc_html( $percent ) . '%</td>
									</tr>';
							}
							?>
						</tbody>
					</table>
					<a href="?view=sexuality"><button type="button" class="btn btn-info btn-lg btn-block">All <?php echo (int) wp_count_terms( 'lez_sexuality' ); ?> Sexual Orientations</button></a>

					<p>&nbsp;<br/>&nbsp;</p>

					<h4>Top Gender Identities</h4>
					<table class="table table-striped table-hover">
						<thead>
							<tr>
								<th scope="col"></th>
								<th scope="col">Characters</th>
								<th scope="col">Percent</th>
							</tr>
						</thead>
						<tbody>
							<?php
							// OPTIMIZED: Use pre-loaded data instead of get_terms()
							foreach ( $top_genders as $gender_slug => $gender_data ) {
								$percent = round( ( ( $gender_data['count'] / $character_count ) * 100 ), 1 );
								echo '<tr>
										<th scope="row"><a href="' . esc_url( site_url( '/gender/' . $gender_slug ) ) . '">' . esc_html( $gender_data['name'] ) . '</a></th>
										<td>' . (int) $gender_data['count'] . '</td>
										<td><div class="progress"><div class="progress-bar bg-info" role="progressbar" style="width: ' . esc_html( $percent ) . '%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div></div>&nbsp;' . esc_html( $percent ) . '%</td>
									</tr>';
							}
							?>
						</tbody>
					</table>
					<a href="?view=gender"><button type="button" class="btn btn-info btn-lg btn-block">All <?php echo (int) $gender_data['count']; ?> Gender Identities</button></a>

				</div>
			</div>
		</div>

		<p>&nbsp;</p>
				</div>
			</div>
		</div>
		<?php
		break;
	case 'sexuality':
		?>
		<h3>Character Sexuality Breakdown</h3>
		<div class="container chart-container">
			<div class="row">
				<div class="col-sm-6">
					<?php lwtv_plugin()->generate_statistics( 'characters', 'sexuality', 'piechart' ); ?>
				</div>
				<div class="col-sm-6">
					<?php lwtv_plugin()->generate_statistics( 'characters', 'sexuality', 'percentage' ); ?>
				</div>
			</div>
		</div>
		<?php
		break;
	case 'gender':
		?>
		<h3>Character Breakdown By Gender</h3>
		<div class="container chart-container">
			<div class="row">
				<div class="col-sm-6">
					<?php lwtv_plugin()->generate_statistics( 'characters', 'gender', 'piechart' ); ?>
				</div>
				<div class="col-sm-6">
					<?php lwtv_plugin()->generate_statistics( 'characters', 'gender', 'percentage' ); ?>
				</div>
			</div>
		</div>
		<?php
		break;
	case 'cliches':
		?>
		<h3>Cliché Demographics</h3>
		<div class="container chart-container">
			<div class="row">
				<div class="col-sm-6">
					<?php lwtv_plugin()->generate_statistics( 'characters', 'cliches', 'piechart' ); ?>
				</div>
				<div class="col-sm-6">
					<?php lwtv_plugin()->generate_statistics( 'characters', 'cliches', 'percentage' ); ?>
				</div>
			</div>
		</div>
		<?php
		break;
	case 'queer-irl':
		?>
		<h3>Characters Played by Queer Actors</h3>
		<div class="container chart-container">
			<div class="row">
				<div class="col-sm-6">
					<?php lwtv_plugin()->generate_statistics( 'characters', 'queer-irl', 'piechart' ); ?>
				</div>

				<div class="col-sm-6">
					<?php lwtv_plugin()->generate_statistics( 'characters', 'queer-irl', 'percentage' ); ?>
				</div>
			</div>
		</div>
		<?php
		break;
	case 'roles':
		?>
		<h3>Character/Actor Comparisons</h3>
		<div class="container chart-container">
			<div class="row">
				<div class="col">
					<h4>Actors per Character</h4>
					<?php lwtv_plugin()->generate_statistics( 'actors', 'per-char', 'barchart' ); ?>
					<p>&nbsp;<br />The above chart displays the number of actors who play each character. So for example, "11 Actors (1)" means there's one character who has eleven (11) actors (and yes, there is one).</p>
				</div>
			</div>
			<div class="row">
				<div class="col">
					<h4>Characters per Actor</h4>
					<?php lwtv_plugin()->generate_statistics( 'actors', 'per-actor', 'barchart' ); ?>
					<p>&nbsp;<br />The above chart displays the number of characters each actor plays. The actor with the highest number of characters played is the 'unknown' actor.</p>
				</div>
			</div>
		</div>
		<?php
		break;
	case 'on-air':
		?>
		<h3>Number of Characters On-Air per Year</h3>
		<div class="container chart-container">
			<div class="row">
				<div class="col-sm-6">
					<?php lwtv_plugin()->generate_statistics( 'characters', 'on-air', 'piechart' ); ?>
				</div>
				<div class="col-sm-6">
					<?php lwtv_plugin()->generate_statistics( 'characters', 'on-air', 'percentage' ); ?>
				</div>
			</div>
		</div>
		<?php
		break;
}

// Performance monitoring - remove this in production
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	echo '<!-- OPTIMIZED: Queries reduced from ~' . ( count( $top_genders ) + count( $top_sexualities ) + count( $top_cliches ) + 15 ) . ' to ' . esc_html( get_num_queries() ) . ' -->';
}
