<?php

namespace LWTV\Statistics\Format;

class Barcharts_Optimized {

	/**
	 * Format data as barchart
	 *
	 * @param array  $data Data to format
	 * @param string $context Station/Nation etc slug - CBS, USA, etc
	 * @param string $view View type - sexuality, gender, tropes, intersections, formats, on-air
	 * @param string $type Type of context - station, nation
	 * @param array  $custom_data Optional custom data
	 * @param string $bar_direction Direction of the barchart ('vertical', 'horizontal')
	 * @return string HTML output
	 */
	public function format( $data, $context, $view, $type, $custom_data = array(), $bar_direction = 'horizontal' ) {
		if ( empty( $data ) ) {
			return '<p>No data available for this barchart.</p>';
		}

		// For overview (all) view, show basic stats
		if ( in_array( $view, array( 'all', '_all' ), true ) ) {
			// Use custom data if provided (from template)
			if ( ! empty( $custom_data ) ) {
				$chart_data = array(
					array(
						'name'  => 'Shows',
						'count' => (int) ( $custom_data['total'] ?? 0 ),
					),
					array(
						'name'  => 'Characters',
						'count' => (int) ( $custom_data['characters'] ?? 0 ),
					),
					array(
						'name'  => 'Dead Characters',
						'count' => (int) ( $custom_data['dead'] ?? 0 ),
					),
				);
			} elseif ( isset( $data['basic'] ) ) {
				// Check if we have basic data structure
				$basic      = $data['basic'];
				$chart_data = array(
					array(
						'name'  => 'Shows',
						'count' => (int) $basic['show_count'],
					),
					array(
						'name'  => 'Characters',
						'count' => (int) $basic['character_count'],
					),
					array(
						'name'  => 'Dead Characters',
						'count' => (int) $basic['dead_count'],
					),
				);
			} else {
				// Fallback: create basic chart from available data
				$chart_data = array(
					array(
						'name'  => 'Shows',
						'count' => 0,
					),
					array(
						'name'  => 'Characters',
						'count' => 0,
					),
					array(
						'name'  => 'Dead Characters',
						'count' => 0,
					),
				);
			}
		} elseif ( isset( $data[ $view ] ) ) {
				$chart_data = $data[ $view ];
		} else {
			$chart_data = $data;
		}

		// Get some settings
		$index_axis = ( 'horizontal' === $bar_direction ) ? 'y' : 'x';
		$count      = $this->get_chart_data_count( $chart_data, $view );
		$height     = ( 'x' === $index_axis ) ? 300 : max( ( $count * 20 ), 30 ) + 20;

		// Generate chart ID
		$chart_id = str_replace( '-', '_', $type . '_' . $context . '_' . $view );

		// Generate chart labels in correct format
		$chart_labels = $this->get_chart_labels( $chart_data, $view );

		// Generate chart data in correct format
		$chart_data_output = $this->get_chart_data( $chart_data, $view );

		$div_output = '<div id="container" style="width: 100%"><canvas id="' . esc_attr( $chart_id ) . '" width="700" height="' . (int) $height . '" aria-label="Station statistics for ' . esc_attr( $context ) . '"></canvas></div>';

		$script_output = '<script>
		Chart.defaults.responsive = true;
		Chart.defaults.plugins.legend.display = false;

		var ' . esc_attr( $chart_id ) . 'Dataset = [' . rtrim( $chart_data_output, ', ' ) . '];
		var ctx = document.getElementById("' . esc_attr( $chart_id ) . '").getContext("2d");
		var chart = new Chart(ctx, {
			type: "bar",
			data: {
				labels: [' . $chart_labels . '],
				datasets: [{
					label: "Count",
					data: [' . $chart_data_output . '],
					borderWidth: 2
				}]
			},
			options: {
				responsive: true,
				indexAxis: "' . $index_axis . '",
			}
		});
		</script>';

		return $div_output . $script_output;
	}

	/**
	 * Get chart data count
	 *
	 * @param array  $chart_data Data to format
	 * @param string $view View type - sexuality, gender, tropes, intersections, formats, on-air
	 * @return int Count
	 */
	private function get_chart_data_count( $chart_data, $view ) {
		switch ( $view ) {
			case 'death':
				// remove all 0 values
				$chart_data = array_filter(
					$chart_data,
					function ( $item ) {
						return 0 !== $item['count'];
					}
				);
				$count      = count( $chart_data );
				break;
			default:
				$count = count( $chart_data );
				break;
		}

		return $count;
	}

	/**
	 * Get chart labels
	 *
	 * @param array  $chart_data Data to format
	 * @param string $view View type - sexuality, gender, tropes, intersections, formats, on-air
	 * @return string HTML output
	 */
	private function get_chart_labels( $chart_data, $view ) {
		lwtv_plugin()->debug_log( 'statistics', 'View: ' . $view );
		$labels = '';
		switch ( $view ) {
			case 'on_air':
				foreach ( $chart_data as $year => $item ) {
					$labels .= '"' . esc_js( $year ) . '", ';
				}
				break;
			case 'death':
				foreach ( $chart_data as $item ) {
					if ( 0 === $item['count'] ) {
						continue;
					}
					$labels .= '"' . esc_js( $item['name'] ) . '", ';
				}
				break;
			default:
				$labels = implode(
					', ',
					array_map(
						function ( $item ) {
							return '"' . esc_js( isset( $item['name'] ) ? $item['name'] : '' ) . '"';
						},
						$chart_data
					)
				);
				break;
		}

		// For each label, look for &amp; and replace with &
		$labels = str_replace( '&amp;', '&', $labels );

		return $labels;
	}

	/**
	 * Get chart data
	 *
	 * @param array  $chart_data Data to format
	 * @param string $view View type - sexuality, gender, tropes, intersections, formats, on-air
	 * @return string HTML output
	 */
	private function get_chart_data( $chart_data, $view ) {
		$data = '';
		switch ( $view ) {
			case 'on_air':
				foreach ( $chart_data as $year => $item ) {
					$data .= (int) ( isset( $item['count'] ) ? $item['count'] : 0 ) . ', ';
				}
				break;
			case 'death':
				foreach ( $chart_data as $item ) {
					if ( 0 === $item['count'] ) {
						continue;
					}
					$data .= (int) ( isset( $item['count'] ) ? $item['count'] : 0 ) . ', ';
				}
				break;
			default:
				foreach ( $chart_data as $item ) {
					$data .= (int) ( isset( $item['count'] ) ? $item['count'] : 0 ) . ', ';
				}
				break;
		}

		return $data;
	}
}
