<?php
/**
 * The template for displaying the dead characters statistics - Optimized Version
 *
 * @package LezWatch.TV
 *
 * Required variables:
 * @var int $this_year
 * @var int $dead_characters_count
 * @var array $characters_on_air_by_show
 * @var array $characters_on_air
 */

// translators: %s is the number of characters that died
$h2_title = sprintf( _n( '%s Character Died', '%s Characters Died', $dead_characters_count ), $dead_characters_count );

$dead_by_date = array();
foreach ( $characters_on_air as $character ) {
	if ( ! $character['dead'] ) {
		continue;
	}

	// for each death year, if the year is $this_year, add the character to the $dead_characters array
	foreach ( $character['death_years'] as $death_date ) {
		// Get the year from the death date (it's the last 4 digits)
		$death_year = substr( $death_date, 0, 4 );
		if ( $death_year === $this_year ) {
			$dead_by_date[ $death_date ][] = $character;
		}
	}
}
// Sort the $dead_by_date array by the death date
ksort( $dead_by_date );

$dead_by_show = array();
foreach ( $characters_on_air_by_show as $show_id => $show_data ) {
	foreach ( $show_data['characters'] as $character_item => $character ) {
		if ( empty( $character['dead'] ) ) {
			continue;
		}

		if ( ! isset( $dead_by_show[ $show_id ]['show'] ) ) {
			$dead_by_show[ $show_id ]['show'] = array(
				'name'    => $show_data['name'],
				'url'     => '/show/' . $show_data['slug'] . '/',
				'nations' => $show_data['nations'],
				'formats' => $show_data['formats'],
			);
		}

		$dead_by_show[ $show_id ]['characters'][] = $character;
	}
}

if ( empty( $dead_by_date ) ) {
	?>
	<h2>No characters died this year</h2>

	<p>I know! We're surprised too.</p>
	<?php
} else {
	?>
	<h2><?php echo esc_html( $h2_title ); ?></h2>

	<div class="container chart-container">
		<div class="row">
			<div class="col">
				<h3>By Date</h3>
				<table class="table table-md table-hover table-striped">
					<thead class="thead-dark">
						<tr>
							<th style="width: 150px;" scope="col">Date</th>
							<th scope="col">Character(s)</th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $dead_by_date as $death_date => $characters ) {

							$character_list = '';
							foreach ( $characters as $character ) {
								$url   = '/characters/' . $character['slug'] . '/';
								$shows = '';
								foreach ( $character['shows'] as $show_item => $show ) {
									$shows .= '<em><a href="' . $show['url'] . '">' . $show['name'] . '</a></em> <small>(' . $show['type'] . ' character)</small>, ';
								}

								// remove the last comma and space
								$shows = rtrim( $shows, ', ' );

								$character_list .= '<li><a href="' . $url . '">' . $character['name'] . '</a> - ' . $shows . '</li>';
							}
							$character_list = '<ul>' . $character_list . '</ul>';

							echo '<tr>';
							echo '<td>' . esc_html( gmdate( 'F jS', strtotime( $death_date ) ) ) . '</td>';
							echo '<td>' . wp_kses_post( $character_list ) . '</td>';
							echo '</tr>';
						}
						?>
					</tbody>
				</table>
			</div>

			<div class="col">
				<h3>By Show</h3>
				<table class="table table-md table-hover table-striped">
					<thead class="thead-dark">
						<tr>
							<th style="width: 200px;" scope="col">Show</th>
							<th scope="col">Character(s)</th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $dead_by_show as $show_id => $show_data ) {
							$nations = '';
							foreach ( $show_data['show']['nations'] as $nation ) {
								$nations .= '<a href="' . $nation['url'] . '">' . $nation['name'] . '</a>, ';
							}

							$formats = '';
							foreach ( $show_data['show']['formats'] as $format ) {
								$formats .= '<a href="' . $format['url'] . '">' . $format['name'] . '</a>, ';
							}

							$character_list = '';
							foreach ( $show_data['characters'] as $character ) {
								$url             = $character['url'];
								$character_list .= '<li><a href="' . $url . '">' . $character['name'] . '</a></li>';
							}
							$character_list = '<ul>' . $character_list . '</ul>';

							// remove the last comma and space
							$nations = rtrim( $nations, ', ' );
							$formats = rtrim( $formats, ', ' );

							echo '<tr>';
							echo '<td><em><a href="' . esc_url( $show_data['show']['url'] ) . '">' . esc_html( $show_data['show']['name'] ) . '</a></em><br /><small>(' . wp_kses_post( $nations ) . ' ' . wp_kses_post( $formats ) . ')</small></td>';
							echo '<td>' . wp_kses_post( $character_list ) . '</td>';
							echo '</tr>';
						}
						?>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<?php
}

