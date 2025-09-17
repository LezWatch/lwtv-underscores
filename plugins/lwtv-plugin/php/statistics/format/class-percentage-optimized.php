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
		if ( in_array( $type, array( 'nation', 'station', 'death' ), true ) ) {
			$show_percent = true;
			$count_title  = '# of Characters';
		}

		// Make a table
		$readable_view = $this->get_readable_view( $clean_view, $context );
		$table_id      = $this->get_table_id( $view, $type, $context );
		$table_start   = '<table id="' . esc_attr( $table_id ) . '" class="tablesorter table table-striped table-hover">';
		$table_header  = '<thead><tr><th scope="col">' . ucfirst( $readable_view ) . '</th><th scope="col">' . $count_title . '</th>';
		if ( $show_percent ) {
			$table_header .= '<th scope="col">Percentage</th>';
		}
		$table_header .= '</tr></thead>';
		$table_body    = '<tbody>';
		$table_body   .= $this->format_data_for_chart( $data, $clean_view, $show_percent, $count_total, $type, $context );
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
	 * @param string $type Type of context
	 * @param string $context Context
	 * @return array Formatted data
	 */
	private function format_data_for_chart( $data, $clean_view, $show_percent, $count_total, $type, $context ) {

		$table_body = '';

		switch ( $clean_view ) {
			case 'tropes':
			case 'formats':
			case 'shows':
			case 'characters':
			case 'actors':
			case 'queer_irl':
				foreach ( $data as $item ) {

					if ( in_array( $type, array( 'stars', 'actors', 'characters' ), true ) && 0 === $item['count'] ) {
						continue;
					}

					$url         = $item['url'];
					$table_body .= '<tr><td><a href="' . esc_url( $url ) . '">' . ucfirst( $item['name'] ) . '</a></td><td>' . (int) $item['count'] . '</td>';
					if ( $show_percent ) {
						$first_count = round( ( ( $item['count'] / $count_total ) * 100 ), 1 );
						$table_body .= '<td><div class="progress"><div class="progress-bar bg-info" role="progressbar" style="width: ' . esc_html( $first_count ) . '%;" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div></div>&nbsp;' . esc_html( $first_count ) . '%</td>';
					}
					$table_body .= '</tr>';
				}
				break;
			case 'we_love_it':
			case 'worth_it':
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
			case 'death':
				$param = '';
				if ( in_array( $context, array( 'stations', 'nations' ), true ) ) {
					$context = rtrim( $context, 's' );
					$param   = '?fwp_show_tropes=dead-queers';
				} elseif ( in_array( $context, array( 'gender', 'sexuality', 'role' ), true ) ) {
					$param = '?fwp_char_cliches=dead';
				}

				foreach ( $data as $slug => $item ) {
					if ( isset( $item['slug'] ) ) {
						$slug = $item['slug'];
					}

					$url         = home_url( "/{$context}/{$slug}/{$param}" );
					$table_body .= '<tr><td><a href="' . esc_url( $url ) . '">' . ucfirst( $item['name'] ) . '</a></td><td>' . (int) $item['count'] . '</td>';
					if ( $show_percent ) {
						$first_count = $item['percentage'];
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
			case 'shows':
			case 'we_love_it':
			case 'worth_it':
			case 'queer_irl':
			case 'death':
				return array_sum( array_column( $data, 'count' ) );
			default:
				return array_sum( $data );
		}
	}

	/**
	 * Get table id
	 *
	 * @param string $view View type
	 * @param string $type Type of context
	 * @return string Table id
	 */
	private function get_table_id( $view, $type, $context ) {
		// remove any trailing s's from $type
		$table_type = isset( $view ) ? $view : $type;
		$table_type = ( 'death' === $table_type ) ? $context : $table_type;

		// remove any underscores
		$table_type = str_replace( '_', '', $table_type );

		// remove any trailing s's but ONLY if there are two!
		if ( substr_count( $table_type, 's' ) >= 2 ) {
			$table_type = rtrim( $table_type, 's' );
		}

		return $table_type . 'Table';
	}

	/**
	 * Get readable view
	 *
	 * @param string $clean_view Clean view
	 * @param string $context Context
	 * @return string Readable view
	 */
	private function get_readable_view( $clean_view, $context ) {
		$raw_view = ( 'death' === $clean_view ) ? $context : $clean_view;
		// Break - and _ into spaces
		$readable_view = str_replace( array( '-', '_' ), ' ', $raw_view );
		return ucwords( $readable_view );
	}
}
