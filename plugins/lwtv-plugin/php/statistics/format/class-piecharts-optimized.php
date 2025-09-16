<?php

namespace LWTV\Statistics\Format;

class Piecharts_Optimized {
	/**
	 * Format data as piechart
	 *
	 * @param array  $data Data to format
	 * @param string $context Station/Nation etc slug - CBS, USA, etc
	 * @param string $view View type - sexuality, gender, tropes, intersections, formats, on-air
	 * @param string $type Type of context - station, nation, etc
	 * @return string HTML output
	 */
	public function format( $data, $context, $view, $type ) {
		$clean_view = ltrim( $view, '_' );

		if ( empty( $data ) || ! isset( $data[ $clean_view ] ) ) {
			lwtv_plugin()->error_log( 'piecharts-debug', 'No data available for this piechart: ' . $type );
			return '<p>No data available for this piechart.</p>';
		}

		// Generate ChartJS piechart
		$chart_id            = $this->generate_chart_id( $type, $context, $view );
		$position_or_display = 'display: false';

		// We show empty sets for these:
		$show_zero = array( 'actor_char_dead', 'actor_char_roles' );

		// Top Bar
		$show_top = array( 'gender_year', 'sexuality_year' );
		$data_top = $clean_view;

		// Customize the piechart for the data
		if ( in_array( $data, $show_zero, true ) ) {
			$position_or_display = "position: 'bottom'";
		} elseif ( in_array( $data_top, $show_top, true ) ) {
			$position_or_display = "position: 'top'";
		}

		$formatting_data = self::format_data_for_chart( $data, $clean_view, $type );

		$chart_labels = $formatting_data['labels'];
		$chart_data   = $formatting_data['data'];

		$chart_output  = '<div id="container" style="width: 100%;"><canvas id="' . esc_attr( $chart_id ) . '" width="500px" height="500px" aria-label="Pie chart for ' . esc_attr( $context ) . '"><p>Your browser cannot display this piechart for stats on' . esc_html( $context ) . '.</p></canvas></div>';
		$script_output = '<script>
		var ' . esc_attr( $chart_id ) . 'Dataset = [' . $chart_data . '];
		var ctx = document.getElementById("' . esc_attr( $chart_id ) . '").getContext("2d");
		var chart = new Chart(ctx, {
			type: "pie",
			options: {
				plugins: {
					legend: {
						' . $position_or_display . ',
						labels: {
							boxWidth: 10,
						}
					},
				},
				tooltips: {
					callbacks: {
						label: function(tooltipItem, data) {
							return data.labels[tooltipItem.index];
						}
					},
				},
			},
			data: {
				labels: [' . $chart_labels . '],
				datasets: [{
					data: ' . esc_attr( $chart_id ) . 'Dataset,
					backgroundColor: palette("tol-rainbow", ' . esc_attr( $chart_id ) . 'Dataset.length).map(function(hex) { return "#" + hex; }),
				}]
			}
		});
		</script>';

		return $chart_output . $script_output;
	}

	/**
	 * Format data for chart
	 *
	 * @param array  $data Data to format
	 * @param string $clean_view Clean view
	 * @param string $type Type of context
	 * @return array Formatted data
	 */
	private function format_data_for_chart( $data, $clean_view, $type ) {

		$labels  = '';
		$dataset = '';

		// Get the right data.
		if ( isset( $data[ $clean_view ] ) && is_array( $data[ $clean_view ] ) ) {
			$data = $data[ $clean_view ];
		}

		try {
			switch ( $clean_view ) {
				case 'shows':
				case 'characters':
				case 'actors':
					$reformatted = $this->format_post_type_data_for_chart( $data, $type );
					$labels      = $reformatted['labels'];
					$dataset     = $reformatted['data'];
					break;
				case 'tropes':
				case 'formats':
				case 'death':
					foreach ( $data as $item ) {
						$labels  .= '"' . esc_js( $item['name'] ) . '", ';
						$dataset .= '"' . esc_js( $item['count'] ) . '", ';
					}
					break;
				default:
					foreach ( $data as $name => $count ) {
						$labels  .= '"' . esc_js( $name ) . '", ';
						$dataset .= '"' . esc_js( $count ) . '", ';
					}
					break;
			}

			$return = array(
				'labels' => $labels,
				'data'   => $dataset,
			);
		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'piecharts-error', 'Error formatting data for chart: ' . $e->getMessage() );
			return array();
		}

		return $return;
	}

	/**
	 * Format post type data for chart
	 *
	 * @param array $data Data to format
	 * @return array Formatted data
	 */
	private function format_post_type_data_for_chart( $data, $type ) {
		$labels  = '';
		$dataset = '';

		switch ( $type ) {
			case 'formats':
			case 'queer-irl':
				foreach ( $data as $name => $count ) {
					$labels  .= '"' . esc_js( $name ) . '", ';
					$dataset .= '"' . esc_js( $count ) . '", ';
				}
				break;
			default:
				foreach ( $data as $item => $item_data ) {

					if ( 'stars' === $type && 0 === $item_data['count'] ) {
						continue;
					}

					$labels  .= '"' . esc_js( $item_data['name'] ) . '", ';
					$dataset .= '"' . esc_js( $item_data['count'] ) . '", ';
				}
				break;
		}

		return array(
			'labels' => $labels,
			'data'   => $dataset,
		);
	}

	/**
	 * Generate chart ID
	 *
	 * @param string $type Type of context
	 * @param string $context Station/Nation etc slug - CBS, USA, etc
	 * @param string $view View type - sexuality, gender, tropes, intersections, formats, on-air
	 * @return string Chart ID
	 */
	private function generate_chart_id( $type, $context, $view ) {
		return str_replace( '-', '_', $type . '_' . $context . '_' . $view . '_pie' );
	}
}
