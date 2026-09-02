<?php
/*
 * Find all problems with Queer data
 */

namespace LWTV\Debugger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Debugger\Build\Queer_Rules;
use LWTV\Debugger\Collect\Queer_Collector;
use LWTV\CPTs\Characters as CPT_Characters;

class Queers {

	/**
	 * Findings from find_queer_chars().
	 */
	const FINDINGS_QUEERCHECK = 'lwtv_debug_queercheck';

	/**
	 * Find Queers
	 *
	 * Find all characters who are mismatched with their queer settings
	 * and the actor who plays them
	 */
	public function find_queer_chars( $items = array() ) {
		$is_recheck = ! empty( $items );

		$characters = Scan::post_ids( $items, CPT_Characters::SLUG );

		if ( empty( $characters ) ) {
			return array();
		}

		$collector    = new Queer_Collector();
		$findings     = array();
		$progress_bar = array();

		// If this is WP-CLI, setup progress bar.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$progress_bar = \WP_CLI\Utils\make_progress_bar( sprintf( 'Starting queer checker. Found %d characters...', count( $characters ) ), count( $characters ) );
		}

		foreach ( array_chunk( $characters, Queer_Collector::BATCH ) as $batch ) {
			foreach ( $collector->collect( $batch ) as $character ) {
				// If this is WP-CLI, tick progress bar.
				if ( defined( 'WP_CLI' ) && WP_CLI ) {
					$progress_bar->tick();
				}

				$findings = array_merge( $findings, Queer_Rules::evaluate( $character ) );
			}
		}

		// If this is WP-CLI, finish progress bar.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$progress_bar->finish();
		}

		return Scan::finish(
			array(
				'scope'    => 'queercheck',
				'findings' => self::FINDINGS_QUEERCHECK,
				'label'    => 'Queer Checker',
			),
			$findings,
			$is_recheck
		);
	}
}
