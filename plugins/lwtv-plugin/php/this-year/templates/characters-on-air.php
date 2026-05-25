<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The template for displaying characters on-air statistics - Optimized Version
 *
 * @package LezWatch.TV
 *
 * Required variables:
 * @var array $characters_on_air
 * @var array $characters_on_air_by_show
 */

// translators: %s is the number of characters on air
$h2_title = sprintf( _n( '%s Character On Air', '%s Characters On Air', $characters_on_air_count ), $characters_on_air_count );
?>

<h2><?php echo esc_html( $h2_title ); ?></h2>

<div class="container chart-container">
	<div class="row">
		<div class="col">
			<h3>By Character Name</h3>
			<table class="table table-md table-hover table-striped">
				<thead class="thead-light">
					<tr>
						<th scope="col">Name</th>
						<th scope="col">Show(s)</th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $characters_on_air as $character ) {
						$url = '/characters/' . $character['slug'] . '/';

						echo '<tr>';
						echo '<td><a href="' . esc_url( $url ) . '">' . esc_html( $character['name'] ) . '</a></td>';
						echo '<td>';

						foreach ( $character['shows'] as $show ) {
							echo '<em><a href="' . esc_url( $show['url'] ) . '">' . esc_html( $show['name'] ) . '</a></em> <small>(' . esc_html( $show['type'] ) . ' character)</small>';
						}

						echo '</td>';
						echo '</tr>';
					}
					?>
				</tbody>
			</table>
		</div>
		<div class="col">
		<h3>By Show</h3>
		<table class="table table-md table-hover table-striped">
			<thead class="thead-light">
				<tr>
					<th style="width: 200px;" scope="col">Show</th>
					<th scope="col">Character(s)</th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $characters_on_air_by_show as $show ) {
					$url   = '/shows/' . $show['slug'] . '/';
					$count = count( $show['characters'] );

					$nations = '';
					foreach ( $show['nations'] as $nation ) {
						$nations .= '<a href="' . $nation['url'] . '">' . $nation['name'] . '</a>, ';
					}

					// remove the last comma and space
					$nations = rtrim( $nations, ', ' );

					$format = '';
					foreach ( $show['formats'] as $formated ) {
						$format .= '<a href="' . $formated['url'] . '">' . $formated['name'] . '</a>, ';
					}

					// remove the last comma and space
					$format = rtrim( $format, ', ' );

					echo '<tr>';
					echo '<td><em><a href="' . esc_url( $url ) . '">' . esc_html( $show['name'] ) . '</a></em> <small>(' . esc_html( $count ) . ')</small><br><small>(' . wp_kses_post( $nations ) . ' &bull; ' . wp_kses_post( $format ) . ')</small></td>';
					echo '<td><ul>';

					foreach ( $show['characters'] as $character ) {
						echo '<li><a href="' . esc_url( $character['url'] ) . '">' . esc_html( $character['name'] ) . '</a> <small>(' . esc_html( $character['type'] ) . ' character)</small></li>';
					}

					echo '</ul></td>';
					echo '</tr>';
				}
				?>
			</tbody>
		</table>
		</div>
	</div>
</div>
<?php

