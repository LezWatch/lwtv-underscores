<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The template for displaying the canceled shows statistics - Optimized Version
 *
 * @package LezWatch.TV
 *
 * Required variables:
 * @var int $this_year
 * @var int $canceled_shows_count
 * @var array $canceled_shows_by_name
 * @var array $canceled_shows_by_format
 * @var array $canceled_shows_by_country
 */


$h2_title = sprintf( _n( 'Show Ended', 'Shows Ended', $canceled_shows_count ), $canceled_shows_count );
?>

<h2><?php echo esc_html( $h2_title ); ?> in <?php echo esc_html( $this_year ); ?></h2>

<p>&nbsp;</p>

<ul class="nav nav-pills nav-fill" id="v-pills-tab" role="tablist">
	<?php
	if ( ! empty( $canceled_shows_by_name ) ) {
		echo '<li class="nav-item"><a class="nav-link active" id="v-pills-byname-tab" data-bs-toggle="pill" href="#v-pills-byname" role="tab" aria-controls="v-pills-byname" aria-selected="true">By Name</a></li>';
	}
	if ( ! empty( $canceled_shows_by_format ) ) {
		echo '<li class="nav-item"><a class="nav-link" id="v-pills-byformat-tab" data-bs-toggle="pill" href="#v-pills-byformat" role="tab" aria-controls="v-pills-byformat" aria-selected="true">By Format</a></li>';
	}
	if ( ! empty( $canceled_shows_by_country ) ) {
		echo '<li class="nav-item"><a class="nav-link" id="v-pills-bycountry-tab" data-bs-toggle="pill" href="#v-pills-bycountry" role="tab" aria-controls="v-pills-bycountry" aria-selected="true">By Country</a></li>';
	}
	?>
</ul>

<p>&nbsp;</p>

<div class="tab-content" id="v-pills-tabContent">
	<?php
	if ( ! empty( $canceled_shows_by_name ) ) {
		?>
		<div class="tab-pane fade show active" id="v-pills-byname" role="tabpanel" aria-labelledby="v-pills-byname-tab">
			<table class="table table-md table-hover table-striped">
				<thead class="thead-light">
					<tr>
						<th style="width: 200px;" scope="col">Letter</th>
						<th scope="col">Show(s)</th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $canceled_shows_by_name as $letter => $s_shows ) {
						echo '<tr><td><h4>' . esc_html( strtoupper( $letter ) ) . ' (' . count( $s_shows ) . ')</h4></td><td><ul class="this-year-shows showsonair">';
						foreach ( $s_shows as $s_show ) {
							$show_s_tooltip = ( $s_show['airdates']['start'] === $s_show['airdates']['finish'] ) ? $s_show['airdates']['start'] : $s_show['airdates']['start'] . '-' . $s_show['airdates']['finish'];
							echo '<li><a href="' . esc_url( $s_show['url'] ) . '" data-bs-toggle="tooltip" data-placement="top" title="On air ' . wp_kses_post( $show_s_tooltip ) . '">' . esc_html( $s_show['name'] ) . '</a> <small>(' . esc_html( $s_show['country'] ) . ' - ' . esc_html( $s_show['format'] ) . ')</small></li>';
						}
						echo '</ul></td></tr>';
					}
					?>
				</tbody>
			</table>
		</div>
		<?php
	}
	if ( ! empty( $canceled_shows_by_format ) ) {
		?>
		<div class="tab-pane fade" id="v-pills-byformat" role="tabpanel" aria-labelledby="v-pills-byformat-tab">
			<table class="table table-md table-hover table-striped">
				<thead class="thead-light">
					<tr>
						<th style="width: 200px;" scope="col">Format</th>
						<th scope="col">Show(s)</th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $canceled_shows_by_format as $letter => $s_shows ) {
						echo '<tr><td><h4>' . esc_html( strtoupper( $letter ) ) . ' (' . count( $s_shows ) . ')</h4></td><td><ul class="this-year-shows showsonair">';
						foreach ( $s_shows as $s_show ) {
							$show_s_tooltip = ( $s_show['airdates']['start'] === $s_show['airdates']['finish'] ) ? $s_show['airdates']['start'] : $s_show['airdates']['start'] . '-' . $s_show['airdates']['finish'];
							echo '<li><a href="' . esc_url( $s_show['url'] ) . '" data-bs-toggle="tooltip" data-placement="top" title="On air ' . wp_kses_post( $show_s_tooltip ) . '">' . esc_html( $s_show['name'] ) . '</a> <small>(' . esc_html( $s_show['country'] ) . ')</small></li>';
						}
						echo '</ul></td></tr>';
					}
					?>
				</tbody>
			</table>
		</div>
		<?php
	}
	if ( ! empty( $canceled_shows_by_country ) ) {
		?>
		<div class="tab-pane fade" id="v-pills-bycountry" role="tabpanel" aria-labelledby="v-pills-bycountry-tab">
			<table class="table table-md table-hover table-striped">
				<thead class="thead-light">
					<tr>
						<th style="width: 200px;" scope="col">Country</th>
						<th scope="col">Show(s)</th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $canceled_shows_by_country as $country => $s_shows ) {
						echo '<tr><td><h4>' . esc_html( strtoupper( $country ) ) . ' (' . count( $s_shows ) . ')</h4></td><td><ul class="this-year-shows showsonair">';
						foreach ( $s_shows as $s_show ) {
							$show_s_tooltip = ( $s_show['airdates']['start'] === $s_show['airdates']['finish'] ) ? $s_show['airdates']['start'] : $s_show['airdates']['start'] . '-' . $s_show['airdates']['finish'];
							echo '<li><a href="' . esc_url( $s_show['url'] ) . '" data-bs-toggle="tooltip" data-placement="top" title="On air ' . wp_kses_post( $show_s_tooltip ) . '">' . esc_html( $s_show['name'] ) . '</a> <small>(' . esc_html( $s_show['country'] ) . ')</small></li>';
						}
						echo '</ul></td></tr>';
					}
					?>
				</tbody>
			</table>
		</div>
		<?php
	}
	?>
</div>
