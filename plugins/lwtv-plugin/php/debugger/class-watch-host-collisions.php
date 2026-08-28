<?php
/*
 * Find hosts claimed by more than one watch provider term.
 *
 * Host-based matching has to pick a winner when two terms list the same host,
 * and it picks the first by term name. That is deterministic and stable, and
 * still arbitrary -- "Lesflicks" beat "LezFlicks" on the live data because `s`
 * sorts before `z`, which happened to be correct and could just as easily not
 * have been. So the tie is reported rather than tolerated.
 *
 * Nothing here is fixable automatically. Resolving a collision means deciding
 * which term is right and removing a URL row from the other, or merging them
 * with `wp lwtv waystowatch merge`. Level 'watch_term' keeps Repair away from
 * it, which is correct for exactly that reason.
 *
 * Costs two queries and no requests. The Watch Providers tab does not read this
 * check's transient -- it renders collisions live from the same host map it
 * already builds for its own list, so it can never show a stale one. This check
 * exists for the weekly cron, `wp lwtv debug watchhosts`, and the tab's count
 * badge, all of which need a stored number.
 */

namespace LWTV\Debugger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Shows\Watch_Hosts;
use LWTV\Debugger\Build\Findings;
use LWTV\Debugger\Format\Rows;
use LWTV\Theme\Ways_To_Watch as Theme_Ways_To_Watch;

class Watch_Host_Collisions {

	/**
	 * Transient holding the results of find_host_collisions().
	 */
	const TRANSIENT_PROBLEMS = 'lwtv_debug_watch_host_collisions';

	/**
	 * Key inside the debugger status options.
	 */
	const STATUS_KEY = 'watch_host_collisions';

	/**
	 * Issue type for a contested host.
	 */
	const ISSUE = 'watch-host-collision';

	/**
	 * Find every contested host.
	 *
	 * One finding per (term, host) pair rather than one per host. Two terms
	 * claiming `lesflicks.com` is two findings, each anchored on its own term ID
	 * with the host as its identity.
	 *
	 * That shape is deliberate. Anchoring one finding per host would mean picking
	 * an arbitrary term to hang it on, and the baseline key is
	 * `post_id:issue_type:identity` -- so the finding would move when the winner
	 * changed and read as new. Per-pair also means removing one term's URL row
	 * resolves that finding *and* drops the other on the next run, because the
	 * host is no longer contested.
	 *
	 * @param array $items Ignored. Every run is a full scan: the whole thing is
	 *                     two queries, so there is no subset worth revisiting and
	 *                     therefore never a partial run to protect the baseline
	 *                     from.
	 * @return array<int, array<string, mixed>>
	 */
	public function find_host_collisions( array $items = array() ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- kept to match the scanner-callable signature; every run is a full scan, see docblock.
		$in_use = Watch_Hosts::in_use();
		$found  = array();

		foreach ( Watch_Hosts::host_collisions() as $host => $terms ) {
			foreach ( $terms as $term_id => $term_name ) {
				$rivals = array();

				foreach ( $terms as $other_id => $other_name ) {
					if ( $other_id !== $term_id ) {
						$rivals[] = sprintf( '%s (#%d)', Theme_Ways_To_Watch::term_name( $other_name ), $other_id );
					}
				}

				$found[] = Findings::make_for_term(
					(int) $term_id,
					Theme_Ways_To_Watch::TAXONOMY,
					self::ISSUE,
					sprintf(
						/* translators: 1: hostname, 2: list of other provider terms. */
						__( '%1$s is also claimed by %2$s. Only one term can win, and which one is decided by name order. Remove the URL from whichever is wrong, or merge them.', 'lwtv' ),
						$host,
						implode( ', ', $rivals )
					),
					array(
						'url'   => 'https://' . $host,
						'term'  => Theme_Ways_To_Watch::term_name( $term_name ),
						'shows' => (int) ( $in_use[ $host ] ?? 0 ),
					),
					$host
				);
			}
		}

		return Scan::finish(
			array(
				'scope'     => self::STATUS_KEY,
				'transient' => self::TRANSIENT_PROBLEMS,
				'label'     => 'Watch hosts claimed by more than one term',
			),
			$found,
			false,
			fn ( array $tagged ) => Rows::from_term_findings( $tagged )
		);
	}
}
