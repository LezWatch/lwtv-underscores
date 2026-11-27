<?php
/**
 * Name: Job Checks
 * Description: Pure checks a background index job makes before it says "done".
 *
 * - first_failure(): a job's own document or settings tasks that Meilisearch
 *   rejected, from GET /tasks, so a job never reports done over failed batches.
 * - keep(): which documents the prune keeps. A job reads its pages over
 *   minutes, so a post published after its page was read must survive the
 *   prune (Sync added it), while a post that was read but not built (an actor
 *   marked hide_all) must go.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch\Build;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Job_Checks {

	/**
	 * The first failed or canceled task in a GET /tasks response, as a
	 * log-safe line; null when there is none.
	 *
	 * @param array $response Decoded GET /tasks response.
	 * @return string|null
	 */
	public static function first_failure( array $response ): ?string {
		foreach ( (array) ( $response['results'] ?? array() ) as $task ) {
			if ( ! is_array( $task ) || ! in_array( $task['status'] ?? '', array( 'failed', 'canceled' ), true ) ) {
				continue;
			}

			$error   = is_array( $task['error'] ?? null ) ? $task['error'] : array();
			$code    = (string) ( $error['code'] ?? $task['status'] );
			$message = (string) ( $error['message'] ?? '' );

			return sprintf( 'Task %d failed: %s', (int) ( $task['uid'] ?? 0 ), '' === $message ? $code : $code . ' — ' . $message );
		}

		return null;
	}

	/**
	 * Post IDs whose documents the prune keeps: everything the job built, plus
	 * whatever is published now that the job never read.
	 *
	 * @param int[] $built     IDs the job built documents for.
	 * @param int[] $seen      IDs the job's pages returned (built or skipped).
	 * @param int[] $published IDs published right now.
	 * @return int[] Sorted, unique.
	 */
	public static function keep( array $built, array $seen, array $published ): array {
		$keep = array_merge( array_map( 'intval', $built ), array_diff( array_map( 'intval', $published ), array_map( 'intval', $seen ) ) );
		$keep = array_values( array_unique( $keep ) );
		sort( $keep );

		return $keep;
	}
}
