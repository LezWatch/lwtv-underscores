<?php

namespace LWTV\Statistics\Format;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Trendline_Optimized {
	/**
	 * Format data as trendline
	 *
	 * @param array  $data Data to format
	 * @param string $context Station/Nation etc slug - CBS, USA, etc
	 * @param string $view View type - sexuality, gender, tropes, intersections, formats, on-air
	 * @param string $type Type of context - station, nation, etc
	 * @return string HTML output
	 */
	public function format( $data, $context, $view, $type ) {
		$clean_view = str_replace( '-', '_', $view );
		if ( empty( $data ) ) {
			return '<p>No data available for this trendline.</p>';
		}

		$data = Shared::sort_data( $data, $clean_view );

		$chart_id = str_replace( '-', '_', $type . $context . $clean_view . '_trendline' );

		$labels   = '';
		$datasets = '';

		foreach ( $data as $item ) {
			$labels   .= '"' . wp_kses_post( $item['name'] ) . ' (' . (int) $item['count'] . ')", ';
			$datasets .= '"' . (int) $item['count'] . '", ';
		}

		$canvas_output = '<div id="container" style="width: 100%;">
			<canvas id="' . esc_attr( $chart_id ) . '" width="700" aria-label="A trendline for stats on ' . esc_html( $context ) . '" />
				<p>Your browser cannot display this trendline for stats on ' . esc_html( $context ) . '.</p>
			</canvas>
		</div>';

		$script_output = '<script>
			var ctx = document.getElementById("' . esc_attr( $chart_id ) . '").getContext("2d");
			var chart = new Chart(ctx, {
				type: "bar",
				data: {
					labels: [' . $labels . '],
					datasets: [{
						label: "Count",
						data: [' . $datasets . '],
						borderWidth: 2,
						backgroundColor: "rgba(255,99,132,0.2)",
						borderColor: "rgba(255,99,132,1)",
						hoverBackgroundColor: "rgba(255,99,132,0.4)",
						hoverBorderColor: "rgba(255,99,132,1)",
						trendlineLinear: {
							colorMin: "rgba(75,192,192,1)",
							colorMax: "rgba(75,192,192,1)",
							lineStyle: "solid",
							width: 2,
						}
					}]
				},
				options: {
					responsive: true,
					indexAxis: "x",
					scales: {
						y: {
							ticks: {
								beginAtZero: true,
								stepSize: 5,
								precision: 0,
								callback: function(value) {if (value % 1 === 0) {return value;}},
							}
						},
					},
				}
			});
		</script>';

		return $canvas_output . $script_output;
	}
}
