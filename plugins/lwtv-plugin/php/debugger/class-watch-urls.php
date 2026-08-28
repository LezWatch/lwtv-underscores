<?php
/*
 * Check that the URLs on every watch provider term still work.
 *
 * The Watch Providers tab answers "which hosts have no term". This answers the
 * other half: of the terms we do have, are they still pointing anywhere useful.
 *
 * Scoped to term URLs rather than to every Ways to Watch field on every show.
 * Thousands of show URLs resolve to a few hundred distinct provider URLs, so
 * checking the terms is the same coverage for an order of magnitude fewer
 * requests -- and it puts the finding on the record you'd actually edit to fix
 * it, instead of on each of the 400 shows that inherited the problem.
 *
 * This replaces the old `show_urls` check, which walked every show. See
 * DEBUGGER-REVIEW.md item 6.
 */

namespace LWTV\Debugger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Shows\Watching\Watch_Hosts;
use LWTV\CPTs\Shows\Watching\Watch_Url_Health;
use LWTV\Debugger\Build\Findings;
use LWTV\Debugger\Build\Issue_Registry;
use LWTV\Debugger\Format\Rows;
use LWTV\Theme\Ways_To_Watch as Theme_Ways_To_Watch;

class Watch_URLs {

	/**
	 * Transient holding the results of find_bad_watch_urls().
	 *
	 * `_v2` because the row shape changed with typed findings -- `status` became
	 * `health`, freeing `status` for the baseline's new/open/resolved. Bumping the
	 * key rather than migrating means old rows are simply never read: the tab says
	 * the scan has not run until the next sweep, which is honest, and the Sunday
	 * cron refills it.
	 */
	const TRANSIENT_PROBLEMS = 'lwtv_debug_watch_urls_v2';

	/**
	 * Key inside the debugger status option.
	 */
	const STATUS_KEY = 'watch_urls';

	/**
	 * Pause between requests, in microseconds.
	 *
	 * A few hundred requests in a tight loop is rude to the smaller hosts and a
	 * good way to get our user agent blocked by the larger ones, which would
	 * quietly turn this check into a list of false 403s.
	 */
	const SLEEP_US = 250000;

	/**
	 * Issue type per URL health verdict.
	 *
	 * The health is what the report's status column shows; the issue type is what
	 * the finding *is*. They line up one-to-one for probed URLs, but two other
	 * issue types also carry a health of "needs review" -- a term with no URLs at
	 * all, and one we ran out of time to re-check -- so the two are not the same
	 * thing.
	 */
	const ISSUE_FOR_HEALTH = array(
		Watch_Url_Health::STATUS_BROKEN  => 'watch-url-broken',
		Watch_Url_Health::STATUS_REVIEW  => 'watch-url-suspect',
		Watch_Url_Health::STATUS_BLOCKED => 'watch-url-blocked',
	);

	/**
	 * Severity order for reporting. Worst first.
	 */
	const SEVERITY = array(
		Watch_Url_Health::STATUS_BROKEN  => 0,
		Watch_Url_Health::STATUS_REVIEW  => 1,
		Watch_Url_Health::STATUS_BLOCKED => 2,
	);

	/**
	 * Find provider terms whose URLs don't work.
	 *
	 * Two modes, matching the rest of the debugger:
	 *
	 *   - No $items: full scan of every URL on every term.
	 *   - $items given: re-probe only those, so a fixed URL drops off the list
	 *     without paying for a full sweep. Used by the admin tab.
	 *
	 * @param array    $items   Existing findings to re-check, or empty for a full scan.
	 * @param int|null $timeout Per-request timeout in seconds.
	 * @param int|null $budget  Wall-clock ceiling in seconds. Anything not reached
	 *                          is carried over unchanged rather than dropped, so a
	 *                          budget can never silently clear a real finding.
	 * @return array<int, array<string, mixed>>
	 */
	public function find_bad_watch_urls( array $items = array(), ?int $timeout = null, ?int $budget = null ): array {
		$targets = empty( $items ) ? $this->all_targets() : $this->targets_from_items( $items );

		$found = array();

		$started = microtime( true );
		$first   = true;

		foreach ( $targets as $target ) {
			// Stop before starting a request that could run past the budget,
			// rather than after one already has.
			if ( null !== $budget && ( microtime( true ) - $started ) + (float) ( $timeout ?? Watch_Hosts::TIMEOUT ) > $budget ) {
				$found[] = $this->carried( $target );
				continue;
			}

			// Between requests, not before the first one.
			if ( ! $first && null === $budget ) {
				usleep( self::SLEEP_US );
			}
			$first = false;

			$probe  = Watch_Hosts::probe( $target['url'], $timeout );
			$health = Watch_Url_Health::classify( $probe, $target['term'], $target['url'] );

			if ( Watch_Url_Health::STATUS_OK === $health['status'] ) {
				continue;
			}

			$found[] = $this->finding( $target, $health['status'], $health['problem'] );
		}

		/*
		 * Diffed like every other check, but never on a partial run: a budgeted
		 * re-check visits a subset, and $items being non-empty is what says so.
		 */
		return Scan::finish(
			array(
				'scope'     => self::STATUS_KEY,
				'transient' => self::TRANSIENT_PROBLEMS,
				'label'     => 'Watch provider URLs with problems',
			),
			$found,
			! empty( $items ),
			/*
			 * Term-shaped rows, one per URL rather than grouped per term, and
			 * sorted after tagging so each row keeps the status it was given.
			 */
			fn ( array $tagged ) => $this->sort_findings( Rows::from_term_findings( $tagged ) )
		);
	}

	/**
	 * Every term URL on the site, with the show count that gives it weight.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function all_targets(): array {
		$per_term = Watch_Hosts::shows_per_term();
		$targets  = array();

		foreach ( Watch_Hosts::term_urls() as $row ) {
			$targets[] = array(
				'term_id' => $row['term_id'],
				'term'    => Theme_Ways_To_Watch::term_name( (string) $row['name'] ),
				'url'     => $row['url'],
				'shows'   => (int) ( $per_term[ $row['term_id'] ] ?? 0 ),
			);
		}

		return $targets;
	}

	/**
	 * Turn an existing findings list back into things to probe.
	 *
	 * The show count is carried across rather than recomputed: it costs a query
	 * per host to work out and hasn't changed in the seconds since the tab
	 * rendered. The whole original finding is carried too, so an item skipped for
	 * budget reasons comes back out exactly as it went in.
	 *
	 * @param array $items Findings from a previous run.
	 * @return array<int, array<string, mixed>>
	 */
	private function targets_from_items( array $items ): array {
		$targets = array();

		foreach ( $items as $item ) {
			// Nothing to probe. Cached rows from before the URL-less-term check
			// was retired can still carry an empty url, and probing '' would be
			// nonsense.
			if ( empty( $item['url'] ) || empty( $item['id'] ) ) {
				continue;
			}

			$term = get_term( (int) $item['id'], Theme_Ways_To_Watch::TAXONOMY );

			// The term was deleted since the last scan, which resolves the
			// finding by definition.
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$targets[] = array(
				'term_id' => (int) $item['id'],
				'term'    => Theme_Ways_To_Watch::term_name( $term->name ),
				'url'     => (string) $item['url'],
				'shows'   => (int) ( $item['shows'] ?? 0 ),
				'carry'   => $item,
			);
		}

		return $targets;
	}

	/**
	 * A finding for a URL we ran out of time to re-check.
	 *
	 * @param array $target Probe target.
	 * @return array<string, mixed>
	 */
	private function deferred( array $target ): array {
		return $this->finding( $target, Watch_Url_Health::STATUS_REVIEW, '', 'watch-url-deferred' );
	}

	/**
	 * The finding for a target we ran out of time to re-probe.
	 *
	 * A budget must never silently clear a real finding, so what the previous run
	 * knew is rebuilt rather than replaced with "not checked": a URL that was
	 * broken an hour ago is still reported as broken.
	 *
	 * Rebuilt rather than carried verbatim, because `carry` holds the previous
	 * *row* — one row per finding, so its single issue type and message are
	 * exactly what is needed — and a row pushed into a findings array would have
	 * no issue type for the baseline to key on.
	 *
	 * @param  array $target Probe target, possibly carrying a previous row.
	 * @return array<string, mixed>
	 */
	private function carried( array $target ): array {
		$row        = (array) ( $target['carry'] ?? array() );
		$issue_type = (string) ( $row['issues'][0] ?? '' );

		// No previous finding to preserve: this target came from a full sweep.
		if ( '' === $issue_type || ! Issue_Registry::exists( $issue_type ) ) {
			return $this->deferred( $target );
		}

		return $this->finding(
			$target,
			(string) ( $row['health'] ?? Watch_Url_Health::STATUS_REVIEW ),
			(string) ( $row['messages'][0] ?? '' ),
			$issue_type
		);
	}

	/**
	 * One typed finding about a term URL.
	 *
	 * @param  array  $target     Probe target.
	 * @param  string $health     Watch_Url_Health status.
	 * @param  string $problem    Message, or '' for the registry default.
	 * @param  string $issue_type Overrides the health-derived type.
	 * @return array<string, mixed>
	 */
	private function finding( array $target, string $health, string $problem = '', string $issue_type = '' ): array {
		if ( '' === $issue_type ) {
			$issue_type = self::ISSUE_FOR_HEALTH[ $health ] ?? 'watch-url-suspect';
		}

		return Findings::make_for_term(
			(int) $target['term_id'],
			Theme_Ways_To_Watch::TAXONOMY,
			$issue_type,
			$problem,
			array(
				'url'    => $target['url'],
				'term'   => $target['term'],
				'shows'  => $target['shows'],
				'health' => $health,
			),
			// A term can have several bad URLs, and each is its own finding.
			(string) $target['url']
		);
	}

	/**
	 * Worst first, then by how many shows are affected.
	 *
	 * A report nobody can triage is a report nobody reads, and the two questions
	 * an editor actually has are "what's definitely broken" and "what does the
	 * most damage".
	 *
	 * @param array $found Display rows.
	 * @return array<int, array<string, mixed>>
	 */
	private function sort_findings( array $found ): array {
		usort(
			$found,
			function ( $first, $second ) {
				$rank = ( self::SEVERITY[ $first['health'] ?? '' ] ?? 9 ) <=> ( self::SEVERITY[ $second['health'] ?? '' ] ?? 9 );

				if ( 0 !== $rank ) {
					return $rank;
				}

				$weight = (int) $second['shows'] <=> (int) $first['shows'];

				if ( 0 !== $weight ) {
					return $weight;
				}

				return strcasecmp( (string) $first['term'], (string) $second['term'] );
			}
		);

		return $found;
	}
}
