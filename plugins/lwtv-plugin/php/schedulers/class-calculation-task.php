<?php
/**
 * Calculation Task Handler
 *
 * Handles deferred calculation operations to improve save/publish performance
 *
 * @package lwtv-plugin
 */

namespace LWTV\Schedulers;

use LWTV\CPTs\Actors\Calculations as Actors_Calculations;
use LWTV\CPTs\Shows\Calculations as Shows_Calculations;
use LWTV\CPTs\Characters\Calculations as Characters_Calculations;

/**
 * Class Calculation_Task
 */
class Calculation_Task {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Register Action Scheduler hook
		add_action( 'lwtv_calculation_task', array( $this, 'process_calculation_task' ) );
	}

	/**
	 * Process the scheduled calculation task
	 *
	 * @param int $post_id The post ID to process
	 * @return void
	 */
	public function process_calculation_task( int $post_id ): void {
		$post_type = get_post_type( $post_id );

		if ( ! $post_type ) {
			lwtv_plugin()->debug_log( 'calculations', "Invalid post ID: {$post_id}" );
			return;
		}

		lwtv_plugin()->debug_log( 'calculations', "Processing calculation task for {$post_type} ID: {$post_id}" );

		// Process calculations based on post type
		switch ( $post_type ) {
			case 'post_type_actors':
				$this->process_actor_calculations( $post_id );
				break;
			case 'post_type_shows':
				$this->process_show_calculations( $post_id );
				break;
			case 'post_type_characters':
				$this->process_character_calculations( $post_id );
				break;
			default:
				lwtv_plugin()->debug_log( 'calculations', "Unsupported post type: {$post_type} for ID: {$post_id}" );
				break;
		}
	}

	/**
	 * Process calculations for actors
	 *
	 * @param int $post_id The actor post ID
	 * @return void
	 */
	private function process_actor_calculations( int $post_id ): void {
		lwtv_plugin()->debug_log( 'calculations', "Processing actor calculations for ID: {$post_id}" );

		// Run the math calculations
		( new Actors_Calculations() )->do_the_math( $post_id );

		lwtv_plugin()->debug_log( 'calculations', "Completed actor calculations for ID: {$post_id}" );
	}

	/**
	 * Process calculations for shows
	 *
	 * @param int $post_id The show post ID
	 * @return void
	 */
	private function process_show_calculations( int $post_id ): void {
		lwtv_plugin()->debug_log( 'calculations', "Processing show calculations for ID: {$post_id}" );

		// Run the math calculations
		( new Shows_Calculations() )->do_the_math( $post_id );

		lwtv_plugin()->debug_log( 'calculations', "Completed show calculations for ID: {$post_id}" );
	}

	/**
	 * Process calculations for characters
	 *
	 * @param int $post_id The character post ID
	 * @return void
	 */
	private function process_character_calculations( int $post_id ): void {
		lwtv_plugin()->debug_log( 'calculations', "Processing character calculations for ID: {$post_id}" );

		// Run the math calculations
		( new Characters_Calculations() )->do_the_math( $post_id );

		lwtv_plugin()->debug_log( 'calculations', "Completed character calculations for ID: {$post_id}" );
	}
}
