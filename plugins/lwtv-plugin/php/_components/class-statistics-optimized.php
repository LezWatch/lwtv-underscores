<?php
/**
 * Name: Statistics Code - Optimized Version
 *
 * This file has the basic defines for all stats with performance optimizations.
 * It's pretty much only called in /page-template/statistics.php
 */
namespace LWTV\_Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Statistics\{ Gutenberg_SSR, Query_Vars, Stats_Counter, Stats_Handler, Stats_Generator };
use LWTV\Statistics\Stats_Enqueues;
use LWTV\CPTs\Actors as CPT_Actors;

class Statistics_Optimized implements Component, Templater {

	/**
	 * Versions of scripts.
	 */
	const VERSIONING = array(
		'tablesorter'    => '2.32.0',
		'stats-overview' => '1.1.0',
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
			'generate_total_counts'          => array( $this, 'generate_total_counts' ),
			'generate_total_dead'            => array( $this, 'generate_total_dead' ),
			'generate_growth_series'         => array( $this, 'generate_growth_series' ),
			'generate_individual_actors'     => array( $this, 'generate_individual_actors' ),
		);
	}

	/**
	 * Enqueue Scripts
	 *
	 * @access public
	 * @return void
	 */
	public function enqueue_scripts() {
		$is_stats_page = is_page( array( 'statistics' ) ) || is_page( array( 'this-year' ) );
		$is_actor      = CPT_Actors::SLUG === get_post_type();

		// If it's not any of our pages, return.
		if ( ! $is_stats_page && ! $is_actor ) {
			return;
		}

		// Custom extra for the statistics landing pages.
		if ( is_page( array( 'statistics' ) ) ) {
			( new Stats_Enqueues() )->enqueue_scripts( self::VERSIONING );
		}

		// Actor pages: server-rendered donut modals use count-up.
		if ( $is_actor ) {
			wp_enqueue_script( 'lwtv-stats-overview', LWTV_PLUGIN_URL . '/assets/js/statistics-overview.js', array(), self::VERSIONING['stats-overview'], true );
		}

		// This Year pages: count-up headline numbers.
		if ( is_page( array( 'this-year' ) ) ) {
			wp_enqueue_script( 'lwtv-stats-overview', LWTV_PLUGIN_URL . '/assets/js/statistics-overview.js', array(), self::VERSIONING['stats-overview'], true );
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
		return ( new Stats_Counter() )->count_shows( $type, $tax, $term );
	}


	/**
	 * Generate total counts
	 *
	 * @param mixed $subject
	 * @param bool  $death
	 *
	 * @return int Total counts
	 */
	public function generate_total_counts( $subject, $death = false ) {
		return ( new Stats_Counter() )->generate_total_counts( $subject, $death );
	}

	/**
	 * Generate cumulative growth series for the overview sparklines.
	 *
	 * @param string $subject One of: shows, characters, actors, dead.
	 * @return array
	 */
	public function generate_growth_series( $subject ) {
		return ( new Stats_Counter() )->get_growth_series( $subject );
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
		return ( new Stats_Handler() )->handle( $data, $context, $view, $format, $source_type, $custom_data, $bar_direction );
	}

	/**
	 * Generate station-specific statistics
	 *
	 * Direct method for station statistics that bypasses the generic
	 * statistics system to avoid data mixing issues.
	 *
	 * @param string $station Station slug (e.g., 'cbs', 'abc') or 'all' for summary
	 * @param string $view View type ('all', 'gender', 'sexuality', 'tropes', 'on-air')
	 * @param string $format Output format ('array', 'percentage', 'list', etc.)
	 * @param array  $custom_data Optional custom data (counts, etc.)
	 * @param string $bar_direction Direction of the barchart ('vertical', 'horizontal')
	 * @return mixed Station statistics data
	 */
	public function generate_station_statistics( $station, $view = 'all', $format = 'array', $custom_data = array(), $bar_direction = 'vertical' ) {
		return ( new Stats_Generator() )->generate_stations( $station, $view, $format, $custom_data, $bar_direction );
	}

	/**
	 * Generate nation-specific statistics
	 *
	 * @param string $nation Nation slug (e.g., 'usa', 'canada')
	 * @param string $view View type ('all', 'gender', 'sexuality', 'tropes', 'on-air')
	 * @param string $format Output format ('array', 'percentage', 'list', etc.)
	 * @param array  $custom_data Optional custom data (counts, etc.)
	 * @param string $bar_direction Direction of the barchart ('vertical', 'horizontal')
	 * @return mixed Nation statistics data
	 */
	public function generate_nation_statistics( $nation, $view = 'all', $format = 'array', $custom_data = array(), $bar_direction = 'vertical' ) {
		return ( new Stats_Generator() )->generate_nations( $nation, $view, $format, $custom_data, $bar_direction );
	}

	/**
	 * Generate statistics for shows
	 *
	 * @param string $format Output format
	 * @param string $type View type (what subpage we're on, so formats, tropes, genres, etc)
	 * @return array statistics data
	 */
	public function generate_shows_statistics( $format = 'list', $type = 'formats' ) {
		return ( new Stats_Generator() )->generate_shows( $format, $type );
	}

	/**
	 * Generate actors statistics
	 *
	 * @param string $format Output format
	 * @param string $type View type (what subpage we're on, so gender, sexuality, etc)
	 * @return array actors statistics data
	 */
	public function generate_actors_statistics( $format = 'list', $type = 'gender' ) {
		return ( new Stats_Generator() )->generate_actors( $format, $type );
	}

	/**
	 * Generate characters statistics
	 *
	 * @param string $format Output format
	 * @param string $type View type (what subpage we're on, so gender, sexuality, etc)
	 * @return array characters statistics data
	 */
	public function generate_characters_statistics( $format = 'list', $type = 'gender' ) {
		return ( new Stats_Generator() )->generate_characters( $format, $type );
	}

	/**
	 * Generate dead statistics
	 *
	 * @param string $subject Subject type (characters/shows)
	 * @param string $view View type (years/roles/sexuality/gender/stations/nations)
	 * @param string $format Format type (array/count/percentage/list)
	 *
	 * @return array Dead statistics data
	 */
	public function generate_dead_statistics( $subject, $view, $format ) {
		return ( new Stats_Generator() )->generate_dead( $subject, $view, $format );
	}

	/**
	 * Generate total dead
	 *
	 * @return int Total dead
	 */
	public function generate_total_dead( $subject ) {
		return ( new Stats_Generator() )->generate_total_dead( $subject );
	}

	/**
	 * Generate individual actors statistics
	 *
	 * @param string $format Output format
	 * @param string $type View type (what subpage we're on, so gender, sexuality, etc)
	 * @return array actors statistics data
	 */
	public function generate_individual_actors( $actor_id, $format = 'array', $type = 'roles' ) {
		return ( new Stats_Generator() )->generate_individual_actors( $actor_id, $format, $type );
	}
}
