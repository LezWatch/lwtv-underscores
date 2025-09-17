<?php

namespace LWTV\Statistics\Format;

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

		$trend    = self::calculate_trendline( $data );
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
			var ' . esc_attr( $chart_id ) . 'Dataset = [' . $datasets . '];
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
					}]
				},
				options: {
					responsive: true,
					indexAxis: "x",
					plugins: {
					annotation: {
						annotations: {
							line1: {
								type: "line",
								yMin: ' . (int) min( $trend ) . ',
								yMax: ' . (int) end( $trend ) . ',
								borderColor: "rgba(75,192,192,1)",
								borderWidth: 2,
							}
						}
					}
				},
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


	/**
	 * Calculate Trendlines
	 *
	 * @param array $data_array Array of Data to process.
	 *
	 * @return array $trend     Trendline array.
	 */
	public function calculate_trendline( $data_array ) {
		// Calculate Trend
		$names = array();
		$count = array();
		$trend = array();

		foreach ( $data_array as $item ) {
			$names[] = $item['name'];
			$count[] = $item['count'];
		}

		$trendarray = self::linear_regression( $names, $count );

		foreach ( $data_array as $item ) {
			$number  = ( $trendarray['slope'] * $item['name'] ) + $trendarray['intercept'];
			$trend[] = ( $number <= 0 ) ? 0 : $number;
		}

		return $trend;
	}

	/**
	 * Linear regression function.
	 *
	 * @param $x array x-coords
	 * @param $y array y-coords
	 *
	 * @return array() m=>slope, b=>intercept
	 */
	public function linear_regression( $x, $y ) {

		// calculate number points
		$n = count( $x );

		// ensure both arrays of points are the same size
		if ( count( $y ) !== $n ) {
			trigger_error( 'linear_regression(): Number of elements in coordinate arrays do not match.', E_USER_ERROR );
		}

		// calculate sums
		$x_sum = array_sum( $x );
		$y_sum = array_sum( $y );

		$xx_sum = 0;
		$xy_sum = 0;

		for ( $i = 0; $i < $n; $i++ ) {
			$xy_sum += ( $x[ $i ] * $y [ $i ] );
			$xx_sum += ( $x[ $i ] * $x[ $i ] );
		}

		// Pre-check for zeros...
		$divisor = ( ( $n * $xx_sum ) - ( $x_sum * $x_sum ) );
		if ( 0 !== $divisor ) {
			// calculate slope
			$slope = ( ( $n * $xy_sum ) - ( $x_sum * $y_sum ) ) / $divisor;
			// calculate intercept
			$intercept = ( $y_sum - ( $slope * $x_sum ) ) / $n;
		}

		// Sort return.
		$return = array(
			'slope'     => ( isset( $slope ) ) ? $slope : 0,
			'intercept' => ( isset( $intercept ) ) ? $intercept : 0,
		);
		return $return;
	}
}
