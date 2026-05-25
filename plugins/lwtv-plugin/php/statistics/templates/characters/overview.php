<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The template for displaying characters overview - Optimized Version
 *
 * @package LezWatch.TV
 *
 * Required variables:
 * @var int $character_count
 * @var array $top_sexualities
 * @var array $top_genders
 * @var array $top_cliches
 * @var int $count_sexualities
 * @var int $count_genders
 * @var int $count_cliches
 */
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
					<h5 class="card-title"><?php echo (int) $count_sexualities; ?></h5>
				</div>
			</div>
		</div>
		<div class="col">
			<div class="card text-center">
				<h3 class="card-header gender">Genders</h3>
				<div class="card-body bg-light">
					<h5 class="card-title"><?php echo (int) $count_genders; ?></h5>
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
			<a href="?view=cliches"><button type="button" class="btn btn-info btn-lg btn-block">All <?php echo (int) $count_cliches; ?> Clichés</button></a>
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
			<a href="?view=sexuality"><button type="button" class="btn btn-info btn-lg btn-block">All <?php echo (int) $count_sexualities; ?> Sexual Orientations</button></a>

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
			<a href="?view=gender"><button type="button" class="btn btn-info btn-lg btn-block">All <?php echo (int) $count_genders; ?> Gender Identities</button></a>

		</div>
	</div>
</div>
<?php
