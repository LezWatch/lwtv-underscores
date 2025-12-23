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
	 * @param string $stat_view Stat view - all, gender, sexuality, tropes, intersections, formats, on-air
	 * @return string HTML output
	 */
	public function format( $data, $context, $view, $type, $stat_view ) {
		$clean_view = ltrim( $view, '_' );

		lwtv_plugin()->debug_log( 'statistics', 'STAT VIEW: ' . $stat_view );

		if ( empty( $data ) || ! isset( $data[ $clean_view ] ) ) {
			lwtv_plugin()->debug_log( 'statistics', 'No data available for this piechart: ' . $type );
			return '<p>No data available for this piechart.</p>';
		}

		// Generate ChartJS piechart
		$chart_id        = $this->generate_chart_id( $type, $context, $view );
		$formatting_data = self::format_data_for_chart( $data, $clean_view, $type, $stat_view );

		// If the labels are empty, we don't want to display the piechart
		if ( empty( $formatting_data['labels'] ) || empty( $formatting_data['data'] ) ) {
			return '<p>No data available for this piechart.</p>';
		}

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
						display: ' . ( 'actors' === $type ? 'true' : 'false' ) . ',
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
					data: ' . esc_attr( $chart_id ) . 'Dataset' . ( 'actors' !== $type ? ',
					backgroundColor: palette("tol-rainbow", ' . esc_attr( $chart_id ) . 'Dataset.length).map(function(hex) { return "#" + hex; })' : '' ) . '
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
	 * @param string $stat_view Stat view - all, gender, sexuality, tropes, intersections, formats, on-air
	 * @return array Formatted data
	 */
	private function format_data_for_chart( $data, $clean_view, $type, $stat_view ) {

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
					$reformatted = $this->format_post_type_data_for_chart( $data, $type, $stat_view );
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
			lwtv_plugin()->error_log( 'statistics', 'Error formatting data for chart: ' . $e->getMessage() );
			return array();
		}

		return $return;
	}

	/**
	 * Format post type data for chart
	 *
	 * @param array $data Data to format
	 * @param string $type Type of context
	 * @param string $stat_view Stat view - all, gender, sexuality, tropes, intersections, formats, on-air
	 * @return array Formatted data
	 */
	private function format_post_type_data_for_chart( $data, $type, $stat_view ) {

		lwtv_plugin()->debug_log( 'statistics', 'Formatting post type data for chart: ' . $type );
		lwtv_plugin()->debug_log( 'statistics', 'Data: ' . wp_json_encode( $data ) );
		lwtv_plugin()->debug_log( 'statistics', 'Stat view: ' . $stat_view );

		$labels  = '';
		$dataset = '';
		switch ( $type ) {
			case 'actors':
			case 'formats':
			case 'queer-irl':
			case 'shows':
				if ( in_array( $stat_view, array( 'tropes', 'genres', 'intersectionality', 'stars', 'triggers' ), true ) ) {
					foreach ( $data as $slug => $item ) {
						$labels  .= '"' . esc_js( $item['name'] ) . '", ';
						$dataset .= '"' . esc_js( $item['count'] ) . '", ';
					}
				} else {
					foreach ( $data as $name => $count ) {
						$labels  .= '"' . esc_js( $name ) . '", ';
						$dataset .= '"' . esc_js( $count ) . '", ';
					}
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
		$chart_id = str_replace( '-', '_', $type . '_' . $context . '_' . $view . '_pie' );
		lwtv_plugin()->debug_log( 'statistics', 'Chart ID: ' . $chart_id );
		return $chart_id;
	}
}
