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

use LWTV\CPTs\Shows\Watch_Hosts;
use LWTV\CPTs\Shows\Watch_Url_Health;
use LWTV\Theme\Ways_To_Watch as Theme_Ways_To_Watch;

class Watch_URLs {

	/**
	 * Transient holding the results of find_bad_watch_urls().
	 */
	const TRANSIENT_PROBLEMS = 'lwtv_debug_watch_urls';

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

		// Recomputed on both paths, not carried over. It costs two queries and no
		// requests, and re-deriving it is what lets a re-check clear a term that
		// has since been given a URL.
		$found = $this->terms_without_urls();

		$started = microtime( true );
		$first   = true;

		foreach ( $targets as $target ) {
			// Stop before starting a request that could run past the budget,
			// rather than after one already has.
			if ( null !== $budget && ( microtime( true ) - $started ) + (float) ( $timeout ?? Watch_Hosts::TIMEOUT ) > $budget ) {
				$found[] = $target['carry'] ?? $this->deferred( $target );
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

			$found[] = array(
				'id'      => $target['term_id'],
				'url'     => $target['url'],
				'term'    => $target['term'],
				'shows'   => $target['shows'],
				'status'  => $health['status'],
				'problem' => $health['problem'],
			);
		}

		$found = $this->sort_findings( $found );

		lwtv_plugin()->set_transient( self::TRANSIENT_PROBLEMS, $found, WEEK_IN_SECONDS );
		Status::record( self::STATUS_KEY, 'Watch provider URLs with problems', count( $found ) );

		return $found;
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
				'term'    => $row['name'],
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
			// A finding with no URL is a term with no URLs, which
			// terms_without_urls() re-derives from scratch on every run. Probing
			// '' would be nonsense.
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
				'term'    => $term->name,
				'url'     => (string) $item['url'],
				'shows'   => (int) ( $item['shows'] ?? 0 ),
				'carry'   => $item,
			);
		}

		return $targets;
	}

	/**
	 * Terms with no URLs at all.
	 *
	 * Free to find -- no request needed -- and worth saying, because
	 * Theme\Ways_To_Watch::get_term_by_url() matches on URLs, so a term without
	 * any can never be reached no matter how right its name is.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function terms_without_urls(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => Theme_Ways_To_Watch::TAXONOMY,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$has_urls = array();
		foreach ( Watch_Hosts::term_urls() as $row ) {
			$has_urls[ $row['term_id'] ] = true;
		}

		$found = array();
		foreach ( $terms as $term ) {
			if ( isset( $has_urls[ $term->term_id ] ) ) {
				continue;
			}

			$found[] = array(
				'id'      => (int) $term->term_id,
				'url'     => '',
				'term'    => $term->name,
				'shows'   => 0,
				'status'  => Watch_Url_Health::STATUS_REVIEW,
				'problem' => __( 'This term has no URLs, so nothing can ever match it. Add a URL or delete the term.', 'lwtv' ),
			);
		}

		return $found;
	}

	/**
	 * A finding for a URL we ran out of time to re-check.
	 *
	 * @param array $target Probe target.
	 * @return array<string, mixed>
	 */
	private function deferred( array $target ): array {
		return array(
			'id'      => $target['term_id'],
			'url'     => $target['url'],
			'term'    => $target['term'],
			'shows'   => $target['shows'],
			'status'  => Watch_Url_Health::STATUS_REVIEW,
			'problem' => __( 'Not re-checked yet — the page ran out of time. Press the button again.', 'lwtv' ),
		);
	}

	/**
	 * Worst first, then by how many shows are affected.
	 *
	 * A report nobody can triage is a report nobody reads, and the two questions
	 * an editor actually has are "what's definitely broken" and "what does the
	 * most damage".
	 *
	 * @param array $found Findings.
	 * @return array<int, array<string, mixed>>
	 */
	private function sort_findings( array $found ): array {
		usort(
			$found,
			function ( $first, $second ) {
				$rank = ( self::SEVERITY[ $first['status'] ] ?? 9 ) <=> ( self::SEVERITY[ $second['status'] ] ?? 9 );

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
