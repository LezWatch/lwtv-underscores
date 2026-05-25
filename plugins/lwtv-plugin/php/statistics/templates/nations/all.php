<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The template for displaying all nations statistics - Optimized Version
 *
 * @package LezWatch.TV
 *
 * Required variables:
 * @var array $all_nations_data - Nation data array
 * @var array $character_counts - Character counts array
 * @var int   $shows_count - Total shows count for this nation
 * @var int   $all_shows_count - Total shows count for all shows
 */
?>
<p>For more information on individual nations, please use the dropdown menu, or click on a nation listed below.</p>
<table id="nationsTable" class="tablesorter table table-striped table-hover">
	<thead>
		<tr>
			<th scope="col">Nation</th>
			<th scope="col">Total Shows</th>
			<th scope="col">Percentage (of all shows)</th>
			<th scope="col"># of Characters</th>
			<th scope="col"># of Dead Characters</th>
		</tr>
	</thead>
	<tbody>
		<?php
		foreach ( $all_nations_data as $nation_slug => $nation_data ) {

			if ( 0 === $nation_data['count'] ) {
				continue;
			}

			// OPTIMIZED: Use pre-loaded character counts instead of individual queries
			$characters = $character_counts[ $nation_slug ]['total'] ?? 0;
			$dead       = $character_counts[ $nation_slug ]['dead'] ?? 0;
			$percent    = round( ( ( $nation_data['count'] / $all_shows_count ) * 100 ), 1 );
			echo '<tr>
					<th scope="row"><a href="?nation=' . esc_attr( $nation_slug ) . '">' . esc_html( $nation_data['name'] ) . '</a></th>
					<td>' . (int) $nation_data['count'] . '</td>
					<td><div class="progress"><div class="progress-bar bg-info" role="progressbar" style="width: ' . esc_html( $percent ) . '%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div></div>&nbsp;' . esc_html( $percent ) . '%</td>
					<td>' . (int) $characters . '</td>
					<td>' . (int) $dead . '</td>
				</tr>';
		}
		?>
	</tbody>
</table>
<?php
