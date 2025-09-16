<?php
/**
 * Name: Statistics Code - Optimized Version
 *
 * This file has the basic defines for all stats with performance optimizations.
 * It's pretty much only called in /page-template/statistics.php
 */
namespace LWTV\_Components;

use LWTV\Queeries\Taxonomy as Queery_Taxonomy;
use LWTV\Statistics\{ Gutenberg_SSR, Query_Vars };
use LWTV\Statistics\Build\Dead as Build_Dead;
use LWTV\Statistics\Build\Formats as Build_Formats;
use LWTV\Statistics\Build\Nations as Build_Nations;
use LWTV\Statistics\Build\On_Air_Optimized as Build_On_Air;
use LWTV\Statistics\Build\Queer_IRL as Build_Queer_IRL;
use LWTV\Statistics\Build\Stations as Build_Stations;
use LWTV\Statistics\Build\We_Love_It as Build_We_Love_It;
use LWTV\Statistics\Build\Worth_It as Build_Worth_It;
use LWTV\Statistics\Build\Taxonomy_Optimized as Build_Taxonomy_Optimized;
use LWTV\Statistics\Format\{ Barcharts_Optimized, Lists_Optimized, Percentage_Optimized, Piecharts_Optimized, Trendline_Optimized };

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
			'generate_statistics'            => array( $this, 'generate' ),
			'generate_station_statistics'    => array( $this, 'generate_station_statistics' ),
			'generate_nation_statistics'     => array( $this, 'generate_nation_statistics' ),
			'generate_shows_statistics'      => array( $this, 'generate_shows_statistics' ),
			'generate_actors_statistics'     => array( $this, 'generate_actors_statistics' ),
			'generate_characters_statistics' => array( $this, 'generate_characters_statistics' ),
			'generate_dead_statistics'       => array( $this, 'generate_dead_statistics' ),
			'generate_shows_count'           => array( $this, 'count_shows' ),
			'generate_stats_block'           => array( $this, 'generate_stats_block' ),
			'generate_stats_block_actor'     => array( $this, 'generate_stats_block_actor' ),
			'generate_total_counts'          => array( $this, 'generate_total_counts' ),
			'generate_total_dead'            => array( $this, 'generate_total_dead' ),
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

			lwtv_plugin()->error_log( 'statistics-debug', 'statistics: ' . $statistics );
			lwtv_plugin()->error_log( 'statistics-debug', 'stat_view: ' . $stat_view );

			switch ( $statistics ) {
				case 'nations':
				case 'stations':
					wp_add_inline_script( 'tablesorter', 'jQuery(document).ready(function($){ $("#' . $statistics . 'Table").tablesorter({ theme : "bootstrap", }); });' );
					break;
				case 'death':
					wp_add_inline_script( 'tablesorter', 'jQuery(document).ready(function($){ $("#DeadCharactersTable").tablesorter({ theme : "bootstrap", }); });' );
					break;
			}

			switch ( $stat_view ) {
				case 'tropes':
				case 'genres':
				case 'formats':
				case 'intersectionality':
				case 'stars':
				case 'triggers':
					wp_add_inline_script( 'tablesorter', 'jQuery(document).ready(function($){ $("#' . $stat_view . 'Table").tablesorter({ theme : "bootstrap", }); });' );
					wp_add_inline_script( 'tablesorter', 'jQuery(document).ready(function($){ $("#showTable").tablesorter({ theme : "bootstrap", }); });' );
					break;
				case 'we-love-it':
					wp_add_inline_script( 'tablesorter', 'jQuery(document).ready(function($){ $("#weloveitTable").tablesorter({ theme : "bootstrap", }); });' );
					wp_add_inline_script( 'tablesorter', 'jQuery(document).ready(function($){ $("#showTable").tablesorter({ theme : "bootstrap", }); });' );
					break;
				default:
					wp_add_inline_script( 'tablesorter', 'jQuery(document).ready(function($){ $("#' . $stat_view . 'Table").tablesorter({ theme : "bootstrap", }); });' );
					break;
			}
		}
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
	 * Handle output for different formats
	 *
	 * @param array $data Data to format
	 * @param string $context Context (Station, Nation, etc.)
	 * @param string $view View type
	 * @param string $format Output format
	 * @param string $source_type Source type
	 * @param array $custom_data Custom data
	 * @param string $bar_direction Direction of the barchart
	 * @return mixed Formatted data
	 */
	public function handle_output( $data, $context, $view, $format, $source_type, $custom_data = array(), $bar_direction = 'horizontal' ) {
		switch ( $format ) {
			case 'barchart':
				return ( new Barcharts_Optimized() )->format( $data, $context, $view, $source_type, $custom_data, $bar_direction );
			case 'trendline':
				return ( new Trendline_Optimized() )->format( $data, $context, $view, $source_type );
			case 'piechart':
				return ( new Piecharts_Optimized() )->format( $data, $context, $view, $source_type );
			case 'percentage':
				return ( new Percentage_Optimized() )->format( $data, $context, $view, $source_type );
			case 'list':
				if ( 'death' === $source_type ) {
					return ( new Lists_Optimized() )->format_dead_list( $data, $context, $view, $source_type );
				}
				return ( new Lists_Optimized() )->format( $data, $context, $view, $source_type );
			default:
				return $data;
		}
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
	 * @param string $bar_direction Direction of the barchart ('vertical', 'horizontal')
	 * @return mixed Station statistics data
	 */
	public function generate_station_statistics( $station, $view = 'all', $format = 'array', $custom_data = array(), $bar_direction = 'vertical' ) {
		// Handle main stations page (summary view)
		if ( 'all' === $station ) {
			$stations_builder = new Build_Stations();
			$data             = $stations_builder->get_station_summaries();

			if ( 'count' === $format ) {
				return count( $data );
			}

			return $data;
		}

		// Handle individual station pages
		$station_data = new Build_Stations()->get_station_details( $station, $format, $view );
		$data         = $station_data['formatted'] ?? $station_data;

		return self::handle_output( $data, $station, $view, $format, 'station', $custom_data, $bar_direction );
	}

	/**
	 * Generate nation-specific statistics
	 *
	 * @param string $nation Nation slug (e.g., 'usa', 'canada')
	 * @param string $view View type ('all', 'gender', 'sexuality', 'tropes', 'on-air')
	 * @param string $format Output format ('array', 'barchart', 'trendline', etc.)
	 * @param array  $custom_data Optional custom data (counts, etc.)
	 * @param string $bar_direction Direction of the barchart ('vertical', 'horizontal')
	 * @return mixed Nation statistics data
	 */
	public function generate_nation_statistics( $nation, $view = 'all', $format = 'array', $custom_data = array(), $bar_direction = 'vertical' ) {
		// Handle main stations page (summary view)
		if ( 'all' === $nation ) {
			$nations_builder = new Build_Nations();
			$data            = $nations_builder->get_nation_summaries();

			if ( 'count' === $format ) {
				return count( $data );
			}

			return $data;
		}

		// Handle individual station pages
		$station_data = new Build_Nations()->get_nation_details( $nation, $format, $view );
		$data         = $station_data['formatted'] ?? $station_data;

		return self::handle_output( $data, $nation, $view, $format, 'nation', $custom_data, $bar_direction );
	}

	/**
	 * Generate statistics for shows
	 *
	 * @param string $format Output format
	 * @param string $type View type (what subpage we're on, so formats, tropes, genres, etc)
	 * @return array statistics data
	 */
	public function generate_shows_statistics( $format = 'list', $type = 'formats' ) {
		$all_data = array();
		$view     = 'shows';
		switch ( $type ) {
			case 'formats':
				$all_data = ( new Build_Formats() )->generate( $format );
				break;
			case 'tropes':
			case 'genres':
			case 'intersections':
			case 'stars':
			case 'triggers':
				$all_data = ( new Build_Taxonomy_Optimized() )->make_comprehensive( 'post_type_shows', 'lez_' . $type, true );
				break;
			case 'on-air':
			case 'on_air':
				$view     = 'on_air';
				$all_data = ( new Build_On_Air() )->generate( 'shows' );
				break;
			case 'we-love-it':
				$view     = 'we_love_it';
				$all_data = ( new Build_We_Love_It() )->generate( $format );
				break;
			case 'worth-it':
				$view     = 'worth_it';
				$all_data = ( new Build_Worth_It() )->generate( $format );
				break;
		}

		if ( empty( $all_data ) ) {
			lwtv_plugin()->error_log( 'shows-debug', 'All data is empty' );
			return array();
		}

		$data          = array();
		$data[ $view ] = $all_data;

		return self::handle_output( $data, 'all', $view, $format, 'shows', array(), 'horizontal' );
	}

	/**
	 * Generate actors statistics
	 *
	 * @param string $format Output format
	 * @param string $type View type (what subpage we're on, so gender, sexuality, etc)
	 * @return array actors statistics data
	 */
	public function generate_actors_statistics( $format = 'list', $type = 'gender' ) {
		$all_data = array();
		$view     = 'actors';

		switch ( $type ) {
			case 'gender':
				$all_data = ( new Build_Taxonomy_Optimized() )->make_comprehensive( 'post_type_actors', 'lez_actor_gender', true );
				break;
			case 'sexuality':
				$all_data = ( new Build_Taxonomy_Optimized() )->make_comprehensive( 'post_type_actors', 'lez_actor_sexuality', true );
				break;
		}

		if ( empty( $all_data ) ) {
			lwtv_plugin()->error_log( 'actors-debug', 'All data is empty' );
			return array();
		}

		$data          = array();
		$data[ $view ] = $all_data;

		return self::handle_output( $data, 'all', $view, $format, 'actors' );
	}

	/**
	 * Generate characters statistics
	 *
	 * @param string $format Output format
	 * @param string $type View type (what subpage we're on, so gender, sexuality, etc)
	 * @return array characters statistics data
	 */
	public function generate_characters_statistics( $format = 'list', $type = 'gender' ) {
		$all_data = array();
		$view     = 'characters';

		switch ( $type ) {
			case 'cliches':
				$all_data = ( new Build_Taxonomy_Optimized() )->make_comprehensive( 'post_type_characters', 'lez_cliches', true );
				break;
			case 'gender':
				$all_data = ( new Build_Taxonomy_Optimized() )->make_comprehensive( 'post_type_characters', 'lez_gender', true );
				break;
			case 'sexuality':
				$all_data = ( new Build_Taxonomy_Optimized() )->make_comprehensive( 'post_type_characters', 'lez_sexuality', true );
				break;
			case 'queer-irl':
				$view     = 'queer_irl';
				$all_data = ( new Build_Queer_IRL() )->generate( $format );
				break;
			case 'on-air':
				$view     = 'on_air';
				$all_data = ( new Build_On_Air() )->generate( 'characters' );
				break;
		}

		if ( empty( $all_data ) ) {
			lwtv_plugin()->error_log( 'characters-debug', 'All data is empty' );
			return array();
		}

		$data          = array();
		$data[ $view ] = $all_data;

		return self::handle_output( $data, 'all', $view, $format, 'characters', array(), 'vertical' );
	}

	/**
	 * Generate dead statistics
	 *
	 * @param string $subject Subject type (characters/shows)
	 * @param string $view View type (years/roles/sexuality/gender/stations/nations)
	 * @param string $format Format type (array/count/percentage/piechart/barchart/trendline/list)
	 *
	 * @return array Dead statistics data
	 */
	public function generate_dead_statistics( $subject, $view, $format ) {
		$all_data = array();
		$context  = 'all';

		if ( 'count' === $format ) {
			if ( ! in_array( $subject, array( 'characters', 'shows' ), true ) ) {
				lwtv_plugin()->error_log( 'dead-debug', 'Invalid subject for death count: ' . $subject );
				return 0;
			}

			return ( new Build_Dead() )->total_dead_characters( $subject );
		}

		switch ( $subject ) {
			case 'characters':
				$all_data = ( new Build_Dead() )->generate_characters( $view, $format );
				if ( in_array( $format, array( 'piechart', 'percentage' ), true ) ) {
					$context = $view;
					$view    = 'death';
				}
				break;
			case 'shows':
				$all_data = ( new Build_Dead() )->generate_shows( $view, $format );
				break;
		}

		if ( empty( $all_data ) ) {
			lwtv_plugin()->error_log( 'dead-debug', 'All data is empty' );
			return array();
		}

		return self::handle_output( $all_data, $context, $view, $format, 'death', array(), 'vertical' );
	}

	/**
	 * Generate total counts
	 *
	 * @return int Total counts
	 */
	public function generate_total_counts( $subject, $death = false ) {
		if ( $death ) {
			return ( new Build_Dead() )->total_dead_characters( $subject );
		}

		return wp_count_posts( 'post_type_' . $subject )->publish;
	}

	/**
	 * Generate total dead
	 *
	 * @return int Total dead
	 */
	public function generate_total_dead( $subject ) {
		switch ( $subject ) {
			case 'characters':
				return ( new Build_Dead() )->total_dead_characters();
			case 'shows':
				return ( new Build_Dead() )->total_dead_shows();
			default:
				return 0;
		}
	}
}
