<?php
/**
 * Name: Change Filter
 * Description: Decides when a post change must update the index.
 *
 * Pure. Documents quote character, show and actor titles and only name
 * published posts, so a rename or a move in or out of publish needs a
 * re-sync; routine edits do not.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Change_Filter {

	/**
	 * Whether an update to a character, show or actor should re-sync the index.
	 *
	 * True for a move in or out of publish, or a rename while published.
	 * Routine edits and auto-draft creation are not.
	 *
	 * @param string $old_title  Title before the update.
	 * @param string $new_title  Title after the update.
	 * @param string $old_status Status before the update.
	 * @param string $new_status Status after the update.
	 * @return bool
	 */
	public static function needs_resync( string $old_title, string $new_title, string $old_status, string $new_status ): bool {
		if ( 'auto-draft' === $old_status ) {
			return false;
		}

		if ( self::crosses_publish( $old_status, $new_status ) ) {
			return true;
		}

		return 'publish' === $new_status && $old_title !== $new_title && '' !== trim( $new_title );
	}

	/**
	 * Whether a status change moves a post onto or off the public site.
	 *
	 * @param string $old_status Old status.
	 * @param string $new_status New status.
	 * @return bool
	 */
	public static function crosses_publish( string $old_status, string $new_status ): bool {
		return $old_status !== $new_status && ( 'publish' === $old_status || 'publish' === $new_status );
	}
}
