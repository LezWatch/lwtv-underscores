<?php
/**
 * Name: Watch Host Map
 * Description: Which lez_watch_urls term owns which host.
 *
 * The replacement for exact-URL matching. A term's stored URLs are reduced to
 * their normalised hosts once, and resolution becomes a lookup instead of a
 * query per host.
 *
 * Why this exists at all: Theme\Ways_To_Watch::get_term_by_url() compared
 * `meta_value` against a list of bare `scheme://host` strings, so a term URL
 * stored with a trailing slash, a `www.`, or different case matched nothing --
 * on the front end as well as in the admin. Measured on the live data
 * (2026-08-27), 14 hosts across 51 show-links were rendering a name guessed from
 * the hostname while holding a perfectly good term, Paramount+ and AcornTV among
 * them.
 *
 * It also removes an N+1: one query for every term URL, then arithmetic, rather
 * than one query per host in use (~154 on the validation tab, one per watch link
 * on every show page).
 *
 * PURE. Array in, array out, no WordPress calls beyond Host_Name, which is
 * itself pure. Storage and memoisation live in Watch_Hosts.
 *
 * @package LWTV
 */

namespace LWTV\CPTs\Shows\Watching;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Watch_Host_Map {

	/**
	 * Reduce stored term URLs to a host => term_id map.
	 *
	 * **Order is the caller's business.** The first term to claim a host wins,
	 * and Watch_Hosts::term_urls() orders `t.name ASC, tm.meta_key ASC`, so the
	 * winner is at least deterministic and stable between runs. It is still
	 * arbitrary — which is why a contested host is reported rather than quietly
	 * resolved. See collisions.
	 *
	 * A row whose URL yields no host is skipped rather than mapped to ''. The
	 * audit (Watch_Term_Url_Audit) is where those get reported; silently
	 * mapping the empty host would let one bad row claim every unparseable
	 * lookup.
	 *
	 * @param array<int, array{term_id: int, name: string, url: string}> $term_urls Rows as Watch_Hosts::term_urls() returns them.
	 * @return array{map: array<string, int>, collisions: array<string, array<int, string>>}
	 */
	public static function build( array $term_urls ): array {
		$map       = array();
		$claimants = array();

		foreach ( $term_urls as $row ) {
			$term_id = (int) ( $row['term_id'] ?? 0 );
			$host    = self::host_of( (string) ( $row['url'] ?? '' ) );

			if ( ! $term_id || '' === $host ) {
				continue;
			}

			// Track every claimant before deciding, so a collision is visible
			// even though only the first claim is honoured.
			$claimants[ $host ][ $term_id ] = (string) ( $row['name'] ?? '' );

			if ( ! isset( $map[ $host ] ) ) {
				$map[ $host ] = $term_id;
			}
		}

		$collisions = array();
		foreach ( $claimants as $host => $terms ) {
			if ( count( $terms ) > 1 ) {
				$collisions[ $host ] = $terms;
			}
		}

		return array(
			'map'        => $map,
			'collisions' => $collisions,
		);
	}

	/**
	 * The term that owns a host, or 0.
	 *
	 * Walks Host_Name::host_candidates(), which yields the most specific form
	 * first and stops at the registrable domain. That preserves the one piece of
	 * precedence the old matcher had: a term registered on 'abc.go.com' beats one
	 * on 'go.com', and neither degrades to a bare public suffix.
	 *
	 * Scheme-blind by construction, because the map is keyed on hosts. That is
	 * the point -- a term stored as https and a show link served over http are
	 * the same provider, and requiring every term to list both forms was never a
	 * real requirement, just an artefact of string comparison.
	 *
	 * @param array<string, int> $map  Map from build().
	 * @param string             $host Hostname, any form.
	 * @return int Term ID, or 0 when nothing owns it.
	 */
	public static function resolve( array $map, string $host ): int {
		$host = Host_Name::normalise( $host );

		if ( '' === $host || empty( $map ) ) {
			return 0;
		}

		foreach ( Host_Name::host_candidates( $host ) as $candidate ) {
			if ( isset( $map[ $candidate ] ) ) {
				return (int) $map[ $candidate ];
			}
		}

		return 0;
	}

	/**
	 * Which shows reach each provider term, by way of the hosts they link to.
	 *
	 * Deduped per term, not summed. One show can list two of a provider's hosts
	 * -- hbomax.com and play.max.com are the same provider -- and summing the
	 * per-host counts double-counts it, which is what the counts on the Watch
	 * URLs report used to do. Here the count is always the length of the list an
	 * editor is shown, so the two cannot disagree.
	 *
	 * @param array<string, array<int|string>> $ids_by_host host => post IDs, as Watch_Hosts::show_ids_by_host() returns it.
	 * @param array<string, int>               $map         Map from build().
	 * @return array<int, array<int, int>> term_id => post IDs, first-seen order.
	 */
	public static function ids_per_term( array $ids_by_host, array $map ): array {
		$seen = array();

		foreach ( $ids_by_host as $host => $post_ids ) {
			$term_id = self::resolve( $map, (string) $host );

			if ( ! $term_id ) {
				continue;
			}

			foreach ( (array) $post_ids as $post_id ) {
				$seen[ $term_id ][ (int) $post_id ] = true;
			}
		}

		return array_map( 'array_keys', $seen );
	}

	/**
	 * The normalised host a stored URL points at.
	 *
	 * Tolerates the shapes the live data actually holds -- trailing slashes,
	 * `www.`, mixed case, a missing scheme -- because tolerating them is the
	 * whole reason this class replaced string comparison. A path is ignored
	 * rather than honoured: the audit confirmed no term carries one, and a term
	 * is a *provider*, so the host is the identifying part.
	 *
	 * @param string $url Stored URL.
	 * @return string Normalised host, or '' when there isn't one.
	 */
	private static function host_of( string $url ): string {
		$url = trim( $url );

		if ( '' === $url ) {
			return '';
		}

		$parsed = wp_parse_url( $url );

		if ( ! is_array( $parsed ) || empty( $parsed['host'] ) ) {
			// A bare 'hulu.com' parses as a path, not a host. Retry only when
			// the value never claimed a scheme -- prefixing one onto 'https://'
			// would come back with the host 'https'.
			if ( str_contains( $url, '://' ) ) {
				return '';
			}

			$parsed = wp_parse_url( 'https://' . ltrim( $url, '/' ) );

			if ( ! is_array( $parsed ) || empty( $parsed['host'] ) ) {
				return '';
			}
		}

		return Host_Name::normalise( (string) $parsed['host'] );
	}
}
