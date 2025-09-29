<?php

namespace LWTV\Statistics\Format;

class Lists_Optimized {

	/**
	 * Format data as simple list
	 *
	 * @param array  $data Data to format
	 * @param string $context Station/Nation etc slug - CBS, USA, etc
	 * @param string $view View type - sexuality, gender, tropes, intersections, formats, on-air
	 * @param string $type Type of context - station, nation, etc
	 * @return string HTML output
	 */
	// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function format( $data, $context, $view, $type ) {
		if ( empty( $data ) ) {
			return '<p>No data available for this list.</p>';
		}

		$data = Shared::sort_data( $data, $view );

		$output = '<ul class="list-group">';

		foreach ( $data as $item ) {
			$url     = isset( $item['url'] ) ? $item['url'] : '#';
			$output .= '<li class="list-group-item d-flex justify-content-between align-items-center">
				<a href="' . esc_url( $url ) . '">' . esc_html( $item['name'] ) . '</a>
				<span class="badge badge-primary badge-pill">' . (int) $item['count'] . '</span>
			</li>';
		}

		$output .= '</ul>';
		return $output;
	}

	/**
	 * Format data as a list
	 *
	 * @param array  $data Data to format
	 * @param string $context Station/Nation etc slug - CBS, USA, etc
	 * @param string $view View type - sexuality, gender, tropes, intersections, formats, on-air
	 * @param string $type Type of context - station, nation, etc
	 * @return string HTML output
	 */
	public function format_dead_list( $data, $context, $view, $type ) {
		if ( empty( $data ) ) {
			return '<p>No data available for this list.</p>';
		}

		// For dead lists, the data structure is different - extract the 'all' key
		if ( isset( $data['all'] ) ) {
			$data = $data['all'];
		}

		$output = '<table id="listTable" class="tablesorter table table-striped table-hover">';
		$thead  = '<thead>
			<tr>
				<th style="width: 150px;" scope="col">Date</th>
				<th style="width: 125px;" scope="col">Days Since</th>
				<th scope="col">Character(s)</th>
			</tr>
		</thead>';
		$tbody  = '<tbody>';

		foreach ( $data as $date => $item ) {
			$characters = '';
			foreach ( $item['chars'] as $char ) {
				$characters .= '<li><a href="' . esc_url( $char['url'] ) . '">' . esc_html( $char['name'] ) . '</a></li>';
			}
			$characters = '<ul>' . $characters . '</ul>';

			$tbody .= '<tr>
				<td><a name="' . esc_html( $item['date'] ) . '">' . esc_html( $item['date'] ) . '</a></td>
				<td>' . esc_html( $item['since'] ?? 0 ) . '</td>
				<td>' . wp_kses_post( $characters ) . '</td>
			</tr>';
		}

		$output .= $thead . $tbody . '</table>';
		return $output;
	}
}
