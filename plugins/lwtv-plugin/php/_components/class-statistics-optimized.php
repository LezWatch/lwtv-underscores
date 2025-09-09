<?php
/**
 * Name: Statistics Code - Optimized Version
 *
 * This file has the basic defines for all stats with performance optimizations.
 * It's pretty much only called in /page-template/statistics.php
 */
namespace LWTV\_Components;

use LWTV\Queeries\Taxonomy as Queery_Taxonomy;
use LWTV\Statistics\{ Gutenberg_SSR, Matcher_Optimized as Matcher, Query_Vars, The_Array, The_Output };
use LWTV\Statistics\Build\Dead_Basic as Build_Dead_Basic;
use LWTV\Statistics\Build\Stations as Build_Stations;
use LWTV\Statistics\Build\Taxonomy_Breakdowns as Build_Taxonomy_Breakdowns;

class Statistics_Optimized implements Component, Templater {

	/**
	 * Versions of scripts.
	 */
	const VERSIONING = array(
		'chartjs'                   => '4.5.0',
		'chartjs-plugin-annotation' => '3.1.0',
		'palette'                   => '1.0.0',
		'tablesorter'               => '2.32.0',
	);

	/*
	 * Init
	 */
	public function init(): void {
		new Query_Vars();
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Gets tags to expose as methods accessible through `lwtv_plugin()`.
	 *
	 * @return array Associative array of $method_name => $callback_info pairs. Each $callback_info must either be
	 *               a callable or an array with key 'callable'. This approach is used to reserve the possibility of
	 *               adding support for further arguments in the future.
	 */
	public function get_template_tags(): array {
		return array(
			'generate_statistics'         => array( $this, 'generate' ),
			'generate_station_statistics' => array( $this, 'generate_station_statistics' ),
			'generate_shows_count'        => array( $this, 'count_shows' ),
			'generate_stats_block'        => array( $this, 'generate_stats_block' ),
			'generate_stats_block_actor'  => array( $this, 'generate_stats_block_actor' ),
		);
	}

	/**
	 * Enqueue Scripts
	 *
	 * @access public
	 * @return void
	 */
	public function enqueue_scripts() {

		// If it's not any of our pages, return.
		if ( ! is_page( array( 'statistics' ) ) && 'post_type_actors' !== get_post_type() && ! is_page( array( 'this-year' ) ) ) {
			return;
		}

		// Enqueue files shared:
		wp_enqueue_script( 'chartjs', LWTV_PLUGIN_URL . '/assets/js/chart.min.js', array( 'jquery' ), self::VERSIONING['chartjs'], false );
		wp_enqueue_script( 'chartjs-plugin-annotation', LWTV_PLUGIN_URL . '/assets/js/chartjs-plugin-annotation.min.js', array( 'chartjs' ), self::VERSIONING['chartjs-plugin-annotation'], false );
		wp_enqueue_script( 'palette', LWTV_PLUGIN_URL . '/assets/js/palette.min.js', array(), self::VERSIONING['palette'], false );

		// Custom extra for stats pages:
		if ( is_page( array( 'statistics' ) ) ) {
			wp_enqueue_script( 'tablesorter', LWTV_PLUGIN_URL . '/assets/js/jquery.tablesorter.min.js', array( 'jquery' ), self::VERSIONING['tablesorter'], false );
			wp_enqueue_style( 'tablesorter', LWTV_PLUGIN_URL . '/assets/css/theme.bootstrap.min.css', array(), self::VERSIONING['tablesorter'], false );

			$statistics = get_query_var( 'statistics', 'none' );
			$stat_view  = get_query_var( 'view', 'main' );

			switch ( $statistics ) {
				case 'nations':
					wp_add_inline_script( 'tablesorter', 'jQuery(document).ready(function($){ $("#nationsTable").tablesorter({ theme : "bootstrap", }); });' );
					break;
				case 'stations':
					wp_add_inline_script( 'tablesorter', 'jQuery(document).ready(function($){ $("#stationsTable").tablesorter({ theme : "bootstrap", }); });' );
					break;
				case 'death':
					wp_add_inline_script( 'tablesorter', 'jQuery(document).ready(function($){ $("#charactersTable").tablesorter({ theme : "bootstrap", }); });' );
					wp_add_inline_script( 'tablesorter', 'jQuery(document).ready(function($){ $("#showsTable").tablesorter({ theme : "bootstrap", }); });' );
					break;
			}

			switch ( $stat_view ) {
				case 'gender':
				case 'sexuality':
				case 'cliches':
					wp_add_inline_script( 'tablesorter', 'jQuery(document).ready(function($){ $("#charactersTable").tablesorter({ theme : "bootstrap", }); });' );
					break;
				case 'tropes':
				case 'genres':
					wp_add_inline_script( 'tablesorter', 'jQuery(document).ready(function($){ $("#showsTable").tablesorter({ theme : "bootstrap", }); });' );
					break;
			}
		}
	}

	/*
	 * Generate: Statistics Base Code - Optimized Version
	 *
	 * @param string $subject      'actors', 'characters', or 'shows'.
	 * @param string $data         The type stats being run.
	 * @param string $format       The format of the output.
	 * @param int    $post_id      Post ID (optional)
	 * @param array  $custom_array Extra array of data (optional)
	 *
	 * @return mixed/na -- Value or the formatted output.
	 */
	public function generate( $subject, $data, $format, $post_id = false, $custom_array = array() ) {
		// Bail early if we're not an approved subject matter.
		if ( ! in_array( $subject, array( 'characters', 'shows', 'actors' ), true ) ) {
			lwtv_plugin()->error_log( 'statistics-debug', 'Returning early - subject not in approved array: ' . $subject );
			return;
		}

		/**
		 * Count may change based on what we're counting.
		 *
		 * Default is the number of posts.
		 * Dead is number of dead.
		 * Deep data is just weird.
		 */

		// Default
		$count         = wp_count_posts( 'post_type_' . $subject )->publish;
		$data_original = null;

		// If dead ...
		if ( 'dead' === $data ) {
			$count = ( new Build_Dead_Basic() )->make( $subject, 'count' );
		}

		// If there isn't an EXACT match for the data, we may have DEEP data.
		if ( ! isset( Matcher::BUILD_CLASS_MATCHER[ $data ] ) ) {
			// Data for if this is complex and weird.
			$maybe_deep = $this->maybe_deep( $data, $format, $count, $subject );
			if ( false !== $maybe_deep ) {
				$data_original = $data;
				$data          = $maybe_deep['data'];
				$count         = $maybe_deep['count'];
			}

			// Data if this is a year:
			$maybe_year = $this->maybe_year( $data );

			if ( false !== $maybe_year ) {
				$data_original = $data;
				$data          = 'this-year';
			}
		} else {
			$maybe_deep = false;
		}

		// Return early if count:
		if ( 'count' === $format ) {
			return $count;
		}

		// OPTIMIZED: Use optimized array builder
		$build_array = ( new The_Array() )->make( $subject, $data, $format, $post_id, $custom_array, $count, $maybe_deep, $data_original );

		// If the array is empty, bail.
		if ( empty( $build_array ) || ! is_array( $build_array ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			lwtv_plugin()->error_log( 'statistics-debug', 'Returning early - build_array is empty: ' . print_r( $build_array, true ) );
			return;
		}

		// Return Array if array is format.
		// Also if we're dead-list and time. It's just a thing.
		if ( 'array' === $format || ( 'time' === $format && 'dead-list' === $data ) ) {
			return $build_array;
		}

		// Otherwise, build it!
		( new The_Output() )->make( $subject, $data, $build_array, $count, $format, $data_original );
	}

	/**
	 * Custom check for years. Since that comes as 'sexuality_year_YYYY' we need to check:
	 *
	 * 1. Is this between LWTV_FIRST_YEAR and this year?
	 * 2. Is the data in our approved subsets?
	 *
	 * If so, yes.
	 *
	 * @param string $data Data to check
	 *
	 * @return bool
	 */
	public function maybe_year( $data ) {
		$maybe_year = substr( $data, -4 );

		if ( $maybe_year <= gmdate( 'Y' ) && $maybe_year >= LWTV_FIRST_YEAR ) {
			$years_array = array( 'sexuality_year', 'gender_year' );
			$maybe_case  = substr( $data, 0, -5 );
			if ( in_array( $maybe_case, $years_array, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Deep Dive for custom data that is extra complex.
	 *
	 * @param string $data   Data we're looking for.
	 * @param string $format Output format.
	 *
	 * @return array Customized array.
	 */
	public function maybe_deep( $data, $format, $count, $subject ) {

		$details = explode( '_', $data );
		$valid   = array( 'nations', 'stations', 'formats', 'country' );

		// If the details don't match what we know to be true, we are false.
		if ( ! in_array( $details[0], $valid, true ) ) {
			lwtv_plugin()->error_log( 'statistics-debug', 'Returning false - first detail does not match: ' . $details[0] );
			return false;
		}

		$minor = $details[1]; // station or nation name.

		if ( 'trendline' === $format && 'on-air' === $details[2] ) {
			$run_data = 'on-air';

			// Build Pre-Array based on station or nation
			switch ( $details[0] ) {
				case 'stations':
					$pre_array = ( new Queery_Taxonomy() )->make( 'post_type_shows', 'lez_stations', 'slug', $minor );
					break;
				case 'country':
					$pre_array = ( new Queery_Taxonomy() )->make( 'post_type_shows', 'lez_country', 'slug', $minor );
					break;
			}

			$count = $this->count_shows( 'total', 'stations', $minor );
		} else {
			lwtv_plugin()->error_log( 'statistics-debug', 'Using taxonomy_breakdowns for data: ' . $data );
			$run_data  = 'taxonomy_breakdowns';
			$pre_count = $count;
			$count     = ( new Build_Taxonomy_Breakdowns() )->make( $pre_count, 'count', $data, $subject );
		}

		$return_data = array(
			'data'     => $run_data,
			'minor'    => $minor,
			'prearray' => isset( $pre_array ) ? $pre_array : false,
			'count'    => $count,
		);

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		lwtv_plugin()->error_log( 'statistics-debug', 'Returning data: ' . print_r( $return_data, true ) );
		return $return_data;
	}

	/*
	 * Count the number of shows along with some other weird things.
	 *
	 * @param string $type  Type of output (onair, total, score)
	 * @param string $tax   The taxonomy   (stations, nations, etc)
	 * @param string $term  The term       (amc, united-kingdom, etc)
	 *
	 * @return array        [total number, on-air, total score, on-air score]
	 */
	public function count_shows( $type, $tax, $term ) {
		$queery = ( new Queery_Taxonomy() )->make( 'post_type_shows', 'lez_' . $tax, 'slug', $term );
		$return = 0;

		if ( ! is_object( $queery ) ) {
			return 0;
		}

		// Create the date with regards to timezones
		$timestamp = time();
		$dt        = new \DateTime( 'now', new \DateTimeZone( LWTV_TIMEZONE ) ); //first argument "must" be a string
		$dt->setTimestamp( $timestamp ); //adjust the object to correct timestamp
		$date = $dt->format( 'Y' );

		if ( $queery->have_posts() ) {
			switch ( $type ) {
				case 'onair':
					// How many shows are on air.
					$onair = 0;
					foreach ( $queery->posts as $show ) {
						if ( get_post_meta( $show->ID, 'lezshows_airdates', true ) ) {
							$airdates = get_post_meta( $show->ID, 'lezshows_airdates', true );
							$end      = $airdates['finish'];
							if ( 'current' === lcfirst( $end ) || $end >= $date ) {
								++$onair;
							}
						}
					}
					$return = $onair;
					break;
				case 'score':
					// What's the average show score for the shows we're calculating.
					$score = 0;
					foreach ( $queery->posts as $show ) {
						if ( get_post_meta( $show->ID, 'lezshows_the_score', true ) ) {
							$this_score = get_post_meta( $show->ID, 'lezshows_the_score', true );
							$score     += $this_score;
						}
					}
					$score = ( $score / $queery->post_count );

					$return = round( $score, 2 );
					break;
				case 'onairscore':
					// What's the average show score for shows on air?
					$score = 0;
					$onair = 0;
					foreach ( $queery->posts as $show ) {
						if ( get_post_meta( $show->ID, 'lezshows_the_score', true ) ) {
							$this_score = get_post_meta( $show->ID, 'lezshows_the_score', true );
							$airdates   = get_post_meta( $show->ID, 'lezshows_airdates', true );
							$end        = $airdates['finish'];
							if ( 'current' === lcfirst( $end ) || $end >= $date ) {
								$score += $this_score;
								++$onair;
							}
						}
					}
					$score  = ( 0 !== $onair ) ? ( $score / $onair ) : $onair;
					$return = round( $score, 2 );
					break;
				default:
					// How many shows are there?
					$return = $queery->post_count;
			}
		}
		return $return;
	}

	/**
	 * Display statistics
	 *
	 *  @param array $attributes
	 *
	 * @return string
	 */
	public function generate_stats_block( $attributes ) {
		return ( new Gutenberg_SSR() )->statistics( $attributes );
	}

	/**
	 * Display Actor stats block
	 *
	 * @param  array $attributes
	 *
	 * @return string
	 */
	public function generate_stats_block_actor( $attributes ) {
		return ( new Gutenberg_SSR() )->mini_stats( $attributes );
	}

	/**
	 * Generate station-specific statistics
	 *
	 * Direct method for station statistics that bypasses the generic
	 * statistics system to avoid data mixing issues.
	 *
	 * @param string $station Station slug (e.g., 'cbs', 'abc') or 'all' for summary
	 * @param string $view View type ('all', 'gender', 'sexuality', 'tropes', 'on-air')
	 * @param string $format Output format ('array', 'barchart', 'trendline', etc.)
	 * @param array  $custom_data Optional custom data (counts, etc.)
	 * @return mixed Station statistics data
	 */
	public function generate_station_statistics( $station, $view = 'all', $format = 'array', $custom_data = array() ) {
		$stations_builder = new Build_Stations();

		// Handle main stations page (summary view)
		if ( 'all' === $station ) {
			$data = $stations_builder->get_station_summaries();

			if ( 'count' === $format ) {
				return count( $data );
			}

			return $data;
		}

		// Handle individual station pages
		// Remove underscore prefix if present (template adds _ prefix)
		$clean_station = ltrim( $station, '_' );
		$station_data  = $stations_builder->get_station_details( $clean_station );

		if ( empty( $station_data ) ) {
			lwtv_plugin()->error_log( 'stations-error', 'No data found for station: ' . $clean_station );
			return 'count' === $format ? 0 : array();
		}

		// Get specific view data
		$data = array();
		switch ( $view ) {
			case 'gender':
				$data = $station_data['gender'] ?? array();
				break;
			case 'sexuality':
				$data = $station_data['sexuality'] ?? array();
				break;
			case 'tropes':
				$data = $station_data['tropes'] ?? array();
				break;
			case 'on-air':
				$data = $station_data['on_air'] ?? array();
				break;
			case 'all':
			default:
				$data = $station_data;
				break;
		}

		// If format is 'array', return raw data
		if ( 'array' === $format ) {
			return $data;
		}

		// Handle formatted output
		return $this->format_station_data( $data, $format, $clean_station, $view, $custom_data );
	}

	/**
	 * Format station data for output
	 *
	 * @param array  $data Data to format
	 * @param string $format Output format
	 * @param string $station Station slug
	 * @param string $view View type
	 * @param array  $custom_data Optional custom data
	 * @return mixed Formatted output
	 */
	private function format_station_data( $data, $format, $station, $view, $custom_data = array() ) {
		// Handle different output formats
		switch ( $format ) {
			case 'barchart':
				return $this->format_barchart( $data, $station, $view, $custom_data );
			case 'trendline':
				return $this->format_trendline( $data, $station, $view );
			case 'piechart':
				return $this->format_piechart( $data, $station, $view );
			case 'percentage':
				return $this->format_percentage( $data, $station, $view );
			case 'list':
				return $this->format_list( $data, $station, $view );
			default:
				return $data;
		}
	}

	/**
	 * Format data as barchart
	 *
	 * @param array  $data Data to format
	 * @param string $station Station slug
	 * @param string $view View type
	 * @param array  $custom_data Optional custom data
	 * @return string HTML output
	 */
	private function format_barchart( $data, $station, $view, $custom_data = array() ) {
		if ( empty( $data ) ) {
			return '<p>No data available for this station.</p>';
		}

		// For overview (all) view, show basic stats
		if ( 'all' === $view || '_all' === $view ) {
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

		// Generate ChartJS barchart
		$chart_id = 'station_' . $station . '_' . $view;
		$output   = '<div id="container" style="width: 100%;">
			<canvas id="' . esc_attr( $chart_id ) . '" width="700" aria-label="Station statistics for ' . esc_attr( $station ) . '"></canvas>
		</div>

		<script>
		var ctx = document.getElementById("' . esc_attr( $chart_id ) . '").getContext("2d");
		var chart = new Chart(ctx, {
			type: "bar",
			data: {
				labels: [';

		foreach ( $chart_data as $item ) {
			$output .= '"' . esc_js( $item['name'] ) . '", ';
		}

		$output .= '],
				datasets: [{
					label: "Count",
					data: [';

		foreach ( $chart_data as $item ) {
			$output .= (int) $item['count'] . ', ';
		}

		$output .= '],
					backgroundColor: "rgba(255,99,132,0.2)",
					borderColor: "rgba(255,99,132,1)",
					borderWidth: 2
				}]
			},
			options: {
				scales: {
					y: {
						beginAtZero: true
					}
				}
			}
		});
		</script>';

		return $output;
	}

	/**
	 * Format data as trendline
	 *
	 * @param array  $data Data to format
	 * @param string $station Station slug
	 * @param string $view View type
	 * @return string HTML output
	 */
	private function format_trendline( $data, $station, $view ) {
		if ( empty( $data ) ) {
			return '<p>No data available for this station.</p>';
		}

		// Use existing Trendline formatter
		$trendline_formatter = new \LWTV\Statistics\Format\Trendline();
		ob_start();
		$trendline_formatter->make( 'shows', 'stations_' . $station . '_on-air', $data );
		return ob_get_clean();
	}

	/**
	 * Format data as piechart
	 *
	 * @param array  $data Data to format
	 * @param string $station Station slug
	 * @param string $view View type
	 * @return string HTML output
	 */
	private function format_piechart( $data, $station, $view ) {
		if ( empty( $data ) ) {
			return '<p>No data available for this station.</p>';
		}

		// Generate ChartJS piechart
		$chart_id = 'station_' . $station . '_' . $view . '_pie';
		$output   = '<div id="container" style="width: 100%;">
			<canvas id="' . esc_attr( $chart_id ) . '" width="700" aria-label="Station pie chart for ' . esc_attr( $station ) . '"></canvas>
		</div>

		<script>
		var ctx = document.getElementById("' . esc_attr( $chart_id ) . '").getContext("2d");
		var chart = new Chart(ctx, {
			type: "pie",
			data: {
				labels: [';

		foreach ( $data as $item ) {
			$output .= '"' . esc_js( $item['name'] ) . '", ';
		}

		$output .= '],
				datasets: [{
					data: [';

		foreach ( $data as $item ) {
			$output .= (int) $item['count'] . ', ';
		}

		$output .= '],
					backgroundColor: [
						"#FF6384", "#36A2EB", "#FFCE56", "#4BC0C0", "#9966FF",
						"#FF9F40", "#FF6384", "#C9CBCF", "#4BC0C0", "#FF6384"
					]
				}]
			}
		});
		</script>';

		return $output;
	}

	/**
	 * Format data as percentage list
	 *
	 * @param array  $data Data to format
	 * @param string $station Station slug
	 * @param string $view View type
	 * @return string HTML output
	 */
	private function format_percentage( $data, $station, $view ) {
		if ( empty( $data ) ) {
			return '<p>No data available for this station.</p>';
		}

		$total  = array_sum( array_column( $data, 'count' ) );
		$output = '<ul class="list-group">';

		foreach ( $data as $item ) {
			$percentage = $total > 0 ? round( ( $item['count'] / $total ) * 100, 1 ) : 0;
			$url        = isset( $item['url'] ) ? $item['url'] : '#';
			$output    .= '<li class="list-group-item d-flex justify-content-between align-items-center">
				<a href="' . esc_url( $url ) . '">' . esc_html( $item['name'] ) . '</a>
				<span class="badge badge-primary badge-pill">' . (int) $item['count'] . ' (' . $percentage . '%)</span>
			</li>';
		}

		$output .= '</ul>';
		return $output;
	}

	/**
	 * Format data as simple list
	 *
	 * @param array  $data Data to format
	 * @param string $station Station slug
	 * @param string $view View type
	 * @return string HTML output
	 */
	private function format_list( $data, $station, $view ) {
		if ( empty( $data ) ) {
			return '<p>No data available for this station.</p>';
		}

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
	 * Batch generate multiple statistics efficiently
	 *
	 * @param string $subject Post type subject
	 * @param array $data_types Array of data types to generate
	 * @param string $format Output format
	 * @return array Multi-dimensional array of statistics
	 */
	public function batch_generate( $subject, $data_types, $format = 'array' ) {
		$results = array();

		foreach ( $data_types as $data_type ) {
			$results[ $data_type ] = $this->generate( $subject, $data_type, $format );
		}

		return $results;
	}
}
