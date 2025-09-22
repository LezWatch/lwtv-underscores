<?php
/**
 * The template for displaying actors overview statistics
 *
 * @package LezWatch.TV
 *
 * Required variables:
 * @var int $actor_count
 * @var array $top_sexualities
 * @var array $top_genders
 * @var int $count_sexualities
 * @var int $count_genders
 */
?>
<div class="container">
	<div class="row">
		<div class="col">
			<div class="card text-center">
				<h3 class="card-header actors">Actors</h3>
				<div class="card-body bg-light">
					<h5 class="card-title"><?php echo (int) $actor_count; ?></h5>
				</div>
			</div>
		</div>
		<div class="col">
			<div class="card text-center">
				<h3 class="card-header sexuality">Sexual Orientation</h3>
				<div class="card-body bg-light">
					<h5 class="card-title"><?php echo (int) $count_sexualities; ?></h5>
				</div>
			</div>
		</div>
		<div class="col">
			<div class="card text-center">
				<h3 class="card-header actor_gender">Gender Identities</h3>
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
			<h4>Top Sexual Orientations</h4>
			<table class="table table-striped table-hover">
				<thead>
					<tr>
						<th scope="col">Sexuality</th>
						<th scope="col">Actors</th>
					</tr>
				</thead>
				<tbody>
					<?php
					// OPTIMIZED: Use pre-loaded data instead of get_terms()
					foreach ( $top_sexualities as $sexuality_slug => $sexuality_data ) {
						echo '<tr>
								<th scope="row"><a href="' . esc_url( home_url( '/actor_sexuality/' . $sexuality_slug ) ) . '">' . esc_html( $sexuality_data['name'] ) . '</a></th>
								<td>' . (int) $sexuality_data['count'] . '</td>
							</tr>';
					}
					?>
				</tbody>
			</table>
			<a href="?view=sexuality"><button type="button" class="btn btn-info btn-lg btn-block">All <?php echo (int) $count_sexualities; ?> Sexual Orientations</button></a>
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
					<?php
					// OPTIMIZED: Use pre-loaded data instead of get_terms()
					foreach ( $top_genders as $gender_slug => $gender_data ) {
						echo '<tr>
								<th scope="row"><a href="' . esc_url( home_url( '/actor_gender/' . $gender_slug ) ) . '">' . esc_html( $gender_data['name'] ) . '</a></th>
								<td>' . (int) $gender_data['count'] . '</td>
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
