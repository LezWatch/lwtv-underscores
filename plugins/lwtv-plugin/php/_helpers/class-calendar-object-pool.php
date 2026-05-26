<?php
/**
 * Calendar Object Pool
 *
 * Manages shared instances of calendar-related objects to eliminate redundant object creation.
 * This provides significant performance improvements by reusing objects across multiple shows.
 *
 * @package lwtv-plugin
 */

namespace LWTV\_Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Calendar\{ Display, Names, TVMaze };

class Calendar_Object_Pool {

	/**
	 * Pool of shared instances
	 *
	 * @var array
	 */
	private static $instances = array();

	/**
	 * Get shared Display instance
	 *
	 * @return Display
	 */
	public static function get_display(): Display {
		if ( ! isset( self::$instances['display'] ) ) {
			self::$instances['display'] = new Display();
		}
		return self::$instances['display'];
	}

	/**
	 * Get shared Names instance
	 *
	 * @return Names
	 */
	public static function get_names(): Names {
		if ( ! isset( self::$instances['names'] ) ) {
			self::$instances['names'] = new Names();
		}
		return self::$instances['names'];
	}

	/**
	 * Get shared TVMaze instance
	 *
	 * @return TVMaze
	 */
	public static function get_tvmaze(): TVMaze {
		if ( ! isset( self::$instances['tvmaze'] ) ) {
			self::$instances['tvmaze'] = new TVMaze();
		}
		return self::$instances['tvmaze'];
	}

	/**
	 * Clear all instances (useful for testing or memory management)
	 *
	 * @return void
	 */
	public static function clear(): void {
		self::$instances = array();
	}

	/**
	 * Get pool statistics (useful for debugging)
	 *
	 * @return array
	 */
	public static function get_stats(): array {
		return array(
			'instances_count' => count( self::$instances ),
			'instances'       => array_keys( self::$instances ),
		);
	}
}
