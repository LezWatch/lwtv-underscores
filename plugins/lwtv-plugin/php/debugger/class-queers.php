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
use LWTV\Debugger\Format\Rows;
use LWTV\Queeries\Post_Type;
use LWTV\CPTs\Characters as CPT_Characters;

class Queers {

	/**
	 * Transient holding the results of find_queer_chars().
	 */
	const TRANSIENT_QUEERCHECK = 'lwtv_debug_queercheck';

	/**
	 * Find Queers
	 *
	 * Find all characters who are mismatched with their queer settings
	 * and the actor who plays them
	 */
	public function find_queer_chars( $items = array() ) {

		// The array we will be checking.
		$characters = array();

		// A recheck only revisits the posts already flagged, so it is tagged
		// against the baseline rather than diffed against it. See tag_only().
		$is_recheck = ! empty( $items );

		// Are we a full scan or a recheck?
		if ( ! empty( $items ) ) {
			// Check only the characters from items!
			foreach ( $items as $character_item ) {
				if ( get_post_status( $character_item['id'] ) !== 'draft' ) {
					// If it's NOT a draft, we'll recheck.
					$characters[] = $character_item['id'];
				}
			}
		} else {
			// Get all the characters
			$characters = ( new Post_Type() )->get_ids( CPT_Characters::SLUG );
		}

		// If somehow characters is totally empty...
		if ( empty( $characters ) ) {
			return false;
		}

		// Make sure we don't have dupes.
		$characters = array_unique( $characters );

		/*
		 * Collect, then evaluate. Build\Queer_Rules holds the comparison; the
		 * collector resolves each actor's is-queer verdict once per batch, since
		 * the same actors recur across characters.
		 */
		$collector = new Queer_Collector();
		$findings  = array();

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

		$diff  = $is_recheck
			? Baseline_Store::tag_only( 'queercheck', $findings )
			: Baseline_Store::apply( 'queercheck', $findings );
		$items = Rows::from_findings( $diff['findings'] );

		// Save Transient
		lwtv_plugin()->set_transient( self::TRANSIENT_QUEERCHECK, $items, WEEK_IN_SECONDS );

		// Update Options
		Status::record( 'queercheck', 'Queer Checker', count( $items ), $diff['summary'] );

		return $items;
	}
}
