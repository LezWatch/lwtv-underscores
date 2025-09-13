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
					array(
						'name'  => 'Dead Characters',
						'count' => 0,
					),
				);
			}
		} else {
			// For specific views, use the data directly
			$chart_data = $data;
		}

		$index_axis = ( 'horizontal' === $bar_direction ) ? 'y' : 'x';

		// Generate chart ID
		$chart_id = $type . '_' . $context . '_' . $view;

		// Generate chart labels in correct format
		$chart_labels = $this->get_chart_labels( $chart_data, $view );

		// Generate chart data in correct format
		$chart_data_output = $this->get_chart_data( $chart_data, $view );

		lwtv_plugin()->error_log( 'barcharts-debug', 'chart_data_output: ' . $chart_data_output );

		$div_output = '<div id="container" style="width: 100%;"><canvas id="' . esc_attr( $chart_id ) . '" width="700" aria-label="Station statistics for ' . esc_attr( $context ) . '"></canvas></div>';

		$script_output = '<script>
		Chart.defaults.responsive = true;
		Chart.defaults.plugins.legend.display = false;

		var ' . esc_attr( $chart_id ) . 'Dataset = [' . rtrim( $chart_data_output, ', ' ) . '];
		var ctx = document.getElementById("' . esc_attr( $chart_id ) . '").getContext("2d");
		var chart = new Chart(ctx, {
			type: "bar",
			options: {},
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
	 * Get chart labels
	 *
	 * @param array  $chart_data Data to format
	 * @param string $view View type - sexuality, gender, tropes, intersections, formats, on-air
	 * @return string HTML output
	 */
	private function get_chart_labels( $chart_data, $view ) {
		$labels = '';
		switch ( $view ) {
			case 'on_air':
				foreach ( $chart_data['on_air'] as $year => $item ) {
					$labels .= '"' . esc_js( $year ) . '", ';
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
				foreach ( $chart_data['on_air'] as $year => $item ) {
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
