<?php
/*
 * Check that the URLs on every watch provider term still work.
 *
 * The Watch Providers tab answers "which hosts have no term". This answers the
 * other half: of the terms we do have, are they still pointing anywhere useful.
 *
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
	 * Findings from find_bad_watch_urls().
	 */
	const FINDINGS_PROBLEMS = 'lwtv_debug_watch_urls_v2';

	/**
	 * Key inside the debugger status option.
	 */
	const STATUS_KEY = 'watch_urls';

	/**
	 * Pause between requests, in microseconds.
	 */
	const SLEEP_US = 250000;

	/**
	 * Issue type per URL health verdict.
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

			$result = $this->probe_and_classify( $target, $timeout );

			if ( null !== $result ) {
				$found[] = $result;
			}
		}

		return Scan::finish(
			array(
				'scope'    => self::STATUS_KEY,
				'findings' => self::FINDINGS_PROBLEMS,
				'label'    => 'Watch provider URLs with problems',
			),
			$found,
			! empty( $items ),
			fn ( array $tagged ) => $this->sort_findings( Rows::from_term_findings( $tagged ) )
		);
	}

	/**
	 * Re-check exactly one flagged URL, leaving every other row untouched.
	 *
	 * @param  array    $all_items Every row currently in the findings store.
	 * @param  array    $target    The one row to re-check, from $all_items.
	 * @param  int|null $timeout   Per-request timeout in seconds.
	 * @return array{resolved: bool, row: array|null} `row` is the new display
	 *                             row when still flagged, null when resolved.
	 */
	public function recheck_one( array $all_items, array $target, ?int $timeout = null ): array {
		$targets = $this->targets_from_items( array( $target ) );
		$finding = empty( $targets ) ? null : $this->probe_and_classify( $targets[0], $timeout );

		$row = null;

		if ( null !== $finding ) {
			$tagged = Baseline_Store::tag_only( self::STATUS_KEY, array( $finding ) )['findings'];
			$rows   = Rows::from_term_findings( $tagged );
			$row    = $rows[0] ?? null;
		}

		$merged = array();

		foreach ( $all_items as $item ) {
			if ( self::same_url( $item, $target ) ) {
				if ( null !== $row ) {
					$merged[] = $row;
				}

				continue;
			}

			$merged[] = $item;
		}

		$merged = $this->sort_findings( $merged );

		Scan::store( self::FINDINGS_PROBLEMS, $merged );
		Status::record( self::STATUS_KEY, 'Watch provider URLs with problems', count( $merged ) );

		return array(
			'resolved' => null === $row,
			'row'      => $row,
		);
	}

	/**
	 * Probe one target and classify what came back.
	 *
	 * Null means the URL passed.
	 *
	 * @param  array    $target  Probe target.
	 * @param  int|null $timeout Per-request timeout in seconds.
	 * @return array<string, mixed>|null
	 */
	private function probe_and_classify( array $target, ?int $timeout ): ?array {
		$probe  = Watch_Hosts::probe( $target['url'], $timeout );
		$health = Watch_Url_Health::classify( $probe, $target['term'], $target['url'], (bool) ( $target['name_confirmed'] ?? false ) );

		if ( Watch_Url_Health::STATUS_OK === $health['status'] ) {
			return null;
		}

		return $this->finding( $target, $health['status'], $health['problem'], '', (string) ( $health['reason'] ?? '' ) );
	}

	/**
	 * Same term, same URL; the identity a term-URL row is keyed on.
	 *
	 * A term can have several bad URLs, so the term ID alone is not enough to
	 * pick out the one row a single-row recheck should replace.
	 *
	 * @param  array $item   A stored display row.
	 * @param  array $target The row being re-checked.
	 * @return bool
	 */
	private static function same_url( array $item, array $target ): bool {
		return (int) ( $item['id'] ?? 0 ) === (int) ( $target['id'] ?? 0 )
			&& (string) ( $item['url'] ?? '' ) === (string) ( $target['url'] ?? '' );
	}

	/**
	 * Every term URL on the site, with the shows that give it weight.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function all_targets(): array {
		$per_term = Watch_Hosts::show_ids_per_term();
		$targets  = array();

		foreach ( Watch_Hosts::term_urls() as $row ) {
			$show_ids = (array) ( $per_term[ $row['term_id'] ] ?? array() );

			$targets[] = array(
				'term_id'        => $row['term_id'],
				'term'           => Theme_Ways_To_Watch::term_name( (string) $row['name'] ),
				'url'            => $row['url'],
				'shows'          => count( $show_ids ),
				'show_ids'       => $show_ids,
				'name_confirmed' => Watch_Hosts::name_confirmed( $row['term_id'] ),
			);
		}

		return $targets;
	}

	/**
	 * Turn an existing findings list back into things to probe.
	 *
	 * The show count and the IDs behind it are carried across rather than
	 * recomputed.
	 *
	 * @param array $items Findings from a previous run.
	 * @return array<int, array<string, mixed>>
	 */
	private function targets_from_items( array $items ): array {
		$targets = array();

		foreach ( $items as $item ) {
			if ( empty( $item['url'] ) || empty( $item['id'] ) ) {
				continue;
			}

			$term = get_term( (int) $item['id'], Theme_Ways_To_Watch::TAXONOMY );

			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$targets[] = array(
				'term_id'        => (int) $item['id'],
				'term'           => Theme_Ways_To_Watch::term_name( $term->name ),
				'url'            => (string) $item['url'],
				'shows'          => (int) ( $item['shows'] ?? 0 ),
				'show_ids'       => array_map( 'intval', (array) ( $item['show_ids'] ?? array() ) ),
				'name_confirmed' => Watch_Hosts::name_confirmed( (int) $item['id'] ),
				'carry'          => $item,
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
			$issue_type,
			(string) ( $row['reason'] ?? '' )
		);
	}

	/**
	 * One typed finding about a term URL.
	 *
	 * @param  array  $target     Probe target.
	 * @param  string $health     Watch_Url_Health status.
	 * @param  string $problem    Message, or '' for the registry default.
	 * @param  string $issue_type Overrides the health-derived type.
	 * @param  string $reason     Watch_Url_Health::REASON_* constant, '' when not applicable.
	 * @return array<string, mixed>
	 */
	private function finding( array $target, string $health, string $problem = '', string $issue_type = '', string $reason = '' ): array {
		if ( '' === $issue_type ) {
			$issue_type = self::ISSUE_FOR_HEALTH[ $health ] ?? 'watch-url-suspect';
		}

		return Findings::make_for_term(
			(int) $target['term_id'],
			Theme_Ways_To_Watch::TAXONOMY,
			$issue_type,
			$problem,
			array(
				'url'      => $target['url'],
				'term'     => $target['term'],
				'shows'    => $target['shows'],
				'health'   => $health,
				'show_ids' => (array) ( $target['show_ids'] ?? array() ),
				'reason'   => $reason,
			),
			// A term can have several bad URLs, and each is its own finding.
			(string) $target['url']
		);
	}

	/**
	 * Drop every stored row for one term that was flagged for a specific
	 * reason -- for when a term-wide override (an editor confirming the
	 * provider despite a published-name mismatch) makes that reason moot.
	 *
	 * @param  array  $all_items Every row currently in the findings store.
	 * @param  int    $term_id   Term whose rows to filter.
	 * @param  string $reason    Watch_Url_Health::REASON_* constant to drop.
	 * @return int Number of rows dropped.
	 */
	public function drop_reason( array $all_items, int $term_id, string $reason ): int {
		return $this->drop_where(
			$all_items,
			static fn ( array $item ): bool => (int) ( $item['id'] ?? 0 ) === $term_id
				&& (string) ( $item['reason'] ?? '' ) === $reason
		);
	}

	/**
	 * Drop every stored row for one term, whatever it was flagged for. Used
	 * when the term itself is gone.
	 *
	 * A term can hold several URLs and each is its own finding, so retiring one
	 * term can clear more than one row. Nothing here deletes the term; the caller
	 * has already done that and is bringing the report into line with it.
	 *
	 * @param  array $all_items Every row currently in the findings store.
	 * @param  int   $term_id   Term whose rows to drop.
	 * @return int Number of rows dropped.
	 */
	public function drop_term( array $all_items, int $term_id ): int {
		return $this->drop_where(
			$all_items,
			static fn ( array $item ): bool => (int) ( $item['id'] ?? 0 ) === $term_id
		);
	}

	/**
	 * Drop the stored rows a predicate matches, and re-record the count.
	 *
	 * The store and the status option are written together or not at all: a
	 * status entry that disagrees with the findings behind it is how a tab ends
	 * up badged with a number its own table cannot account for.
	 *
	 * @param  array    $all_items Every row currently in the findings store.
	 * @param  callable $matches   fn( array $item ): bool -- true to drop.
	 * @return int Number of rows dropped.
	 */
	private function drop_where( array $all_items, callable $matches ): int {
		$merged  = array();
		$dropped = 0;

		foreach ( $all_items as $item ) {
			if ( $matches( (array) $item ) ) {
				++$dropped;
				continue;
			}

			$merged[] = $item;
		}

		if ( $dropped > 0 ) {
			Scan::store( self::FINDINGS_PROBLEMS, $merged );
			Status::record( self::STATUS_KEY, 'Watch provider URLs with problems', count( $merged ) );
		}

		return $dropped;
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
