<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The template for displaying all stations statistics - Optimized Version
 *
 * @package LezWatch.TV
 *
 * Required variables:
 * @var array $all_stations_data - Station data array
 * @var array $character_counts - Character counts array
 * @var int   $shows_count - Total shows count for this station
 * @var int   $all_shows_count - Total shows count for all shows
 */

?>
<p>For more information on individual stations, please use the dropdown menu, or click on a station listed below.</p>
<table id="stationsTable" class="tablesorter table table-striped table-hover">
	<thead>
		<tr>
			<th scope="col">Station Name</th>
			<th scope="col">Total Shows</th>
			<th scope="col">Percentage (of all shows)</th>
			<th scope="col"># of Characters</th>
			<th scope="col"># of Dead Characters</th>
		</tr>
	</thead>
	<tbody>
		<?php
		foreach ( $all_stations_data as $station_slug => $station_data ) {

			if ( 0 === $station_data['count'] ) {
				continue;
			}

			// OPTIMIZED: Use pre-loaded character counts instead of individual queries
			$characters = $character_counts[ $station_slug ]['total'] ?? 0;
			$dead       = $character_counts[ $station_slug ]['dead'] ?? 0;
			$percent    = round( ( ( $station_data['count'] / $all_shows_count ) * 100 ), 1 );
			echo '<tr>
					<th scope="row"><a href="?station=' . esc_attr( $station_slug ) . '">' . esc_html( $station_data['name'] ) . '</a></th>
					<td>' . (int) $station_data['count'] . '</td>
					<td><div class="progress"><div class="progress-bar bg-info" role="progressbar" style="width: ' . esc_html( $percent ) . '%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div></div>&nbsp;' . esc_html( $percent ) . '%</td>
					<td>' . (int) $characters . '</td>
					<td>' . (int) $dead . '</td>
				</tr>';
		}
		?>
	</tbody>
</table>
<?php
