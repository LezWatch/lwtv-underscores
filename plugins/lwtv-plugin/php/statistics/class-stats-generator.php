<?php
/**
 * Statistics Generator Class
 *
 * @package LezWatch.TV
 */

namespace LWTV\Statistics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Statistics\Build\We_Love_It as Build_We_Love_It;
use LWTV\Statistics\Build\Worth_It as Build_Worth_It;
use LWTV\Statistics\Build\Nations as Build_Nations;
use LWTV\Statistics\Build\Stations as Build_Stations;
use LWTV\Statistics\Build\Formats as Build_Formats;
use LWTV\Statistics\Build\Taxonomy_Optimized as Build_Taxonomy_Optimized;
use LWTV\Statistics\Build\On_Air_Optimized as Build_On_Air;
use LWTV\Statistics\Build\Queer_IRL as Build_Queer_IRL;
use LWTV\Statistics\Build\Dead as Build_Dead;
use LWTV\Statistics\Build\Actors as Build_Actors;
use LWTV\Statistics\Stats_Handler;
use LWTV\CPTs\Actors as CPT_Actors;
use LWTV\CPTs\Characters as CPT_Characters;

class Stats_Generator {

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
	public function generate_nations( $nation, $view = 'all', $format = 'array', $custom_data = array(), $bar_direction = 'vertical' ) {
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
		$station_data = ( new Build_Nations() )->get_nation_details( $nation, $format, $view );
		$data         = $station_data['formatted'] ?? $station_data;

		return ( new Stats_Handler() )->handle( $data, $nation, $view, $format, 'nation', $custom_data, $bar_direction );
	}

	/**
	 * Generate shows statistics
	 *
	 * @param string $format Output format
	 * @param string $type View type (what subpage we're on, so formats, tropes, genres, etc)
	 * @return array statistics data
	 */
	public function generate_shows( $format = 'list', $type = 'formats' ) {
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
			lwtv_plugin()->debug_log( 'statistics', 'All data for shows is empty' );
			return array();
		}

		$data          = array();
		$data[ $view ] = $all_data;

		return ( new Stats_Handler() )->handle( $data, 'all', $view, $format, 'shows', array(), 'horizontal' );
	}

	/**
	 * Generate stations statistics
	 *
	 * @param string $station Station slug (e.g., 'cbs', 'abc')
	 * @param string $view View type ('all', 'gender', 'sexuality', 'tropes', 'on-air')
	 * @param string $format Output format ('array', 'barchart', 'trendline', etc.)
	 * @param array  $custom_data Optional custom data (counts, etc.)
	 * @param string $bar_direction Direction of the barchart ('vertical', 'horizontal')
	 * @return mixed Station statistics data
	 */
	public function generate_stations( $station, $view = 'all', $format = 'array', $custom_data = array(), $bar_direction = 'vertical' ) {
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
		$station_data = ( new Build_Stations() )->get_station_details( $station, $format, $view );
		$data         = $station_data['formatted'] ?? $station_data;

		return ( new Stats_Handler() )->handle( $data, $station, $view, $format, 'station', $custom_data, $bar_direction );
	}

	/**
	 * Generate characters statistics
	 *
	 * @param string $format Output format
	 * @param string $type View type (what subpage we're on, so cliches, gender, sexuality, etc)
	 * @return array characters statistics data
	 */
	public function generate_characters( $format = 'list', $type = 'cliches' ) {
		$all_data = array();
		$view     = 'characters';

		switch ( $type ) {
			case 'cliches':
				$all_data = ( new Build_Taxonomy_Optimized() )->make_comprehensive( CPT_Characters::SLUG, 'lez_cliches', true );
				break;
			case 'gender':
				$all_data = ( new Build_Taxonomy_Optimized() )->make_comprehensive( CPT_Characters::SLUG, 'lez_gender', true );
				break;
			case 'sexuality':
				$all_data = ( new Build_Taxonomy_Optimized() )->make_comprehensive( CPT_Characters::SLUG, 'lez_sexuality', true );
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
			lwtv_plugin()->debug_log( 'statistics', 'All data for characters is empty' );
			return array();
		}

		$data          = array();
		$data[ $view ] = $all_data;

		return ( new Stats_Handler() )->handle( $data, 'all', $view, $format, 'characters', array(), 'vertical' );
	}

	/**
	 * Generate actors statistics
	 *
	 * @param string $format Output format
	 * @param string $type View type (what subpage we're on, so gender, sexuality, etc)
	 * @return array actors statistics data
	 */
	public function generate_actors( $format = 'list', $type = 'gender' ) {
		$all_data = array();
		$view     = 'actors';

		switch ( $type ) {
			case 'gender':
				$all_data = ( new Build_Taxonomy_Optimized() )->make_comprehensive( CPT_Actors::SLUG, 'lez_actor_gender', true );
				break;
			case 'sexuality':
				$all_data = ( new Build_Taxonomy_Optimized() )->make_comprehensive( CPT_Actors::SLUG, 'lez_actor_sexuality', true );
				break;
		}

		if ( empty( $all_data ) ) {
			lwtv_plugin()->debug_log( 'statistics', 'All data for actors is empty' );
			return array();
		}

		$data          = array();
		$data[ $view ] = $all_data;

		return ( new Stats_Handler() )->handle( $data, 'all', $view, $format, 'actors', array(), 'horizontal' );
	}

	/**
	 * Generate dead statistics
	 *
	 * @param string $subject Subject type (characters/shows)
	 * @param string $view View type (years/roles/sexuality/gender/stations/nations)
	 * @param string $format Format type (array/count/percentage/piechart/barchart/trendline/list)
	 * @return array Dead statistics data
	 */
	public function generate_dead( $subject, $view, $format ) {
		$all_data      = array();
		$context       = 'all';
		$bar_direction = 'vertical';

		if ( 'count' === $format ) {
			if ( ! in_array( $subject, array( 'characters', 'shows' ), true ) ) {
				lwtv_plugin()->debug_log( 'statistics', 'Invalid subject for death count: ' . $subject );
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
				if ( in_array( $format, array( 'piechart', 'percentage', 'barchart' ), true ) ) {
					$context = $view;
					$view    = 'death';
				}
				$bar_direction = 'horizontal';
				break;
		}

		if ( empty( $all_data ) ) {
			lwtv_plugin()->debug_log( 'statistics', 'All data for dead is empty' );
		}

		return ( new Stats_Handler() )->handle( $all_data, $context, $view, $format, 'death', array(), $bar_direction );
	}

	/**
	 * Generate total dead
	 *
	 * @param string $subject Subject type (characters/shows)
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

	/**
	 * Generate individual actors statistics
	 *
	 * @param string $actor_id Actor ID
	 * @param string $format Output format
	 * @param string $type  Roles or Dead
	 * @return array actors statistics data
	 */
	public function generate_individual_actors( $actor_id, $format = 'piechart', $type = 'roles' ) {
		$all_data = array();
		$view     = 'all';

		switch ( $type ) {
			case 'roles':
				$view = 'roles';
				lwtv_plugin()->debug_log( 'statistics', 'Generating ROLES statistics for actor: ' . $actor_id );
				$all_data[ $view ] = ( new Build_Actors() )->generate_roles( $actor_id );
				break;
			case 'dead':
				$view = 'dead';
				lwtv_plugin()->debug_log( 'statistics', 'Generating DEAD statistics for actor: ' . $actor_id );
				$all_data[ $view ] = ( new Build_Actors() )->generate_dead( $actor_id );
				break;
		}

		if ( empty( $all_data ) ) {
			lwtv_plugin()->debug_log( 'statistics', 'All data for actors is empty' );
			return array();
		}

		return ( new Stats_Handler() )->handle( $all_data, $actor_id, $view, $format, 'actors', array(), 'horizontal' );
	}
}
