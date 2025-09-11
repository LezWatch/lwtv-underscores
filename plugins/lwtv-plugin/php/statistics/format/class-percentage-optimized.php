<?php
/**
 * Optimized Percentage Format Class
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics\Format;

class Percentage_Optimized {

	/**
	 * Format data as percentage
	 *
	 * @param array  $data Data to format
	 * @param string $context Station/Nation etc slug - CBS, USA, etc
	 * @param string $view View type - sexuality, gender, tropes, intersections, formats, on-air
	 * @param string $type Type of context - station, nation
	 * @return string HTML output
	 */
	public function format( $data, $context, $view, $type ) {
		$clean_view = ltrim( $view, '_' );
		if ( empty( $data ) || ! isset( $data[ $clean_view ] ) ) {
			return '<p>No data available for this percentage list.</p>';
		}

		$data = Shared::sort_data( $data, $clean_view );

		$count_total  = $this->get_count_total( $data, $clean_view );
		$count_title  = 'Count';
		$show_percent = false;
		if ( in_array( $type, array( 'nation', 'station' ), true ) ) {
			$show_percent = true;
			$count_title  = '# of Characters';
		}

		// Make a table
		$table_start  = '<table id="' . esc_attr( $type . 'sTable' ) . '" class="tablesorter table table-striped table-hover">';
		$table_header = '<thead><tr><th scope="col">' . ucfirst( $clean_view ) . '</th><th scope="col">' . $count_title . '</th>';
		if ( $show_percent ) {
			$table_header .= '<th scope="col">Percentage</th>';
		}
		$table_header .= '</tr></thead>';
		$table_body    = '<tbody>';
		$table_body   .= $this->format_data_for_chart( $data, $clean_view, $show_percent, $count_total );
		$table_body   .= '</tbody>';
		$table_end     = '</table>';

		$output = $table_start . $table_header . $table_body . $table_end;

		return $output;
	}

	/**
	 * Format data for chart
	 *
	 * @param array  $data Data to format
	 * @param string $clean_view Clean view
	 * @param bool $show_percent Show percent
	 * @param int $count_total Count total
	 * @return array Formatted data
	 */
	private function format_data_for_chart( $data, $clean_view, $show_percent, $count_total ) {

		$table_body = '';

		switch ( $clean_view ) {
			case 'tropes':
			case 'formats':
				foreach ( $data as $item ) {
					$url         = $item['url'];
					$table_body .= '<tr><td><a href="' . esc_url( $url ) . '">' . ucfirst( $item['name'] ) . '</a></td><td>' . (int) $item['count'] . '</td>';
					if ( $show_percent ) {
						$first_count = round( ( ( $item['count'] / $count_total ) * 100 ), 1 );
						$table_body .= '<td><div class="progress"><div class="progress-bar bg-info" role="progressbar" style="width: ' . esc_html( $first_count ) . '%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div></div>&nbsp;' . esc_html( $first_count ) . '%</td>';
					}
					$table_body .= '</tr>';
				}
				break;
			default:
				foreach ( $data as $name => $count ) {
					$url         = home_url( "/{$clean_view}/{$name}" );
					$table_body .= '<tr><td><a href="' . esc_url( $url ) . '">' . ucfirst( $name ) . '</a></td><td>' . (int) $count . '</td>';
					if ( $show_percent ) {
						$first_count = round( ( ( $count / $count_total ) * 100 ), 1 );
						$table_body .= '<td><div class="progress"><div class="progress-bar bg-info" role="progressbar" style="width: ' . esc_html( $first_count ) . '%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div></div>&nbsp;' . esc_html( $first_count ) . '%</td>';
					}
				}
				break;
		}

		return $table_body;
	}

	/**
	 * Get count total
	 *
	 * @param array  $data Data to format
	 * @param string $clean_view Clean view
	 * @return int Count total
	 */
	private function get_count_total( $data, $clean_view ) {
		switch ( $clean_view ) {
			case 'tropes':
			case 'formats':
				return array_sum( array_column( $data, 'count' ) );
			default:
				return array_sum( $data );
		}
	}
}
