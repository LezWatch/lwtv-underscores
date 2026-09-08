<?php
/**
 * Name: Watch Term URL Audit
 * Description: What is actually stored in the lez_watch_urls term URL rows.
 *
 *   - Cosmetic (trailing slash, case, http, www, port): exact-URL matching
 *     fails on these today. Host matching fixes them. They are why hosts that
 *     genuinely have a term still show up as problems.
 *
 *   - Blocking (path, query, fragment, credentials, unparseable): a term that
 *     has registered 'youtube.com/c/something' means something narrower than
 *     'youtube.com'. Host matching would widen it to the whole host and let one
 *     web series' term swallow every other YouTube URL on the site. A human has
 *     to look at these before the matcher changes.
 *
 * Collisions (two different terms whose URLs reduce to the same host) are
 * blocking for the same reason, from the other direction: host matching has to
 * pick a winner, and there is no correct way to pick one automatically.
 *
 * @package LWTV
 */

namespace LWTV\CPTs\Shows\Watching;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Watch_Term_Url_Audit {

	/**
	 * Cosmetic flags. Host matching resolves all of these; the stored value is
	 * merely untidy.
	 */
	const FLAG_TRAILING_SLASH = 'trailing-slash';
	const FLAG_UPPERCASE      = 'uppercase';
	const FLAG_HTTP_SCHEME    = 'http-scheme';
	const FLAG_NO_SCHEME      = 'no-scheme';
	const FLAG_WWW            = 'www';
	const FLAG_PORT           = 'port';
	const FLAG_DUPLICATE      = 'duplicate';

	/**
	 * Blocking flags. Each one needs a human decision before the matcher
	 * changes, because host matching would alter what the row means.
	 */
	const FLAG_PATH        = 'path';
	const FLAG_QUERY       = 'query';
	const FLAG_FRAGMENT    = 'fragment';
	const FLAG_CREDENTIALS = 'credentials';
	const FLAG_UNPARSEABLE = 'unparseable';

	/**
	 * The flags that stop Phase 1 shipping.
	 *
	 * @return array<string>
	 */
	public static function blocking_flags(): array {
		return array(
			self::FLAG_PATH,
			self::FLAG_QUERY,
			self::FLAG_FRAGMENT,
			self::FLAG_CREDENTIALS,
			self::FLAG_UNPARSEABLE,
		);
	}

	/**
	 * Does this row need a human before host matching goes in?
	 *
	 * @param array<string> $flags Flags from one inspected row.
	 * @return bool
	 */
	public static function is_blocking( array $flags ): bool {
		return count( array_intersect( $flags, self::blocking_flags() ) ) > 0;
	}

	/**
	 * Reduce a list of stored URLs to the canonical rows a term should hold.
	 *
	 * One bare `https://host` per distinct host, first occurrence winning, order
	 * otherwise preserved. This is the shape Phase 1's matcher wants and the
	 * shape `Watch_Hosts::set_term_urls()` writes: after host matching, a term
	 * carrying both `https://www.vix.com/` and `https://vix.com/` is holding the
	 * same fact twice, and the `www`/trailing-slash variants only ever existed to
	 * satisfy exact-string comparison.
	 *
	 * Unparseable values are dropped rather than passed through — writing a value
	 * back that can never match anything would be worse than losing it, and the
	 * caller is expected to have run inspect() first.
	 *
	 * @param array<string> $urls Stored URLs, any shape.
	 * @return array<string> Canonical `https://host` URLs, deduplicated.
	 */
	public static function canonical_urls( array $urls ): array {
		$out  = array();
		$seen = array();

		foreach ( $urls as $url ) {
			$host = self::parse( trim( (string) $url ) )['host'];

			if ( '' === $host || isset( $seen[ $host ] ) ) {
				continue;
			}

			$seen[ $host ] = true;
			$out[]         = 'https://' . $host;
		}

		return $out;
	}

	/**
	 * Inspect every stored term URL.
	 *
	 * @param array<int, array{term_id: int, name: string, url: string}> $term_urls    Rows as Watch_Hosts::term_urls() returns them.
	 * @param array<string, int>                                        $hosts_in_use host => show count, as Watch_Hosts::in_use() returns it. Optional; adds a 'shows' column so a flagged row can be weighed.
	 * @return array{
	 *     rows: array<int, array{term_id: int, term: string, url: string, host: string, shows: int, bare: string, flags: array<string>, blocking: bool}>,
	 *     collisions: array<string, array<int, string>>,
	 *     flag_counts: array<string, int>,
	 *     totals: array{rows: int, terms: int, hosts: int, flagged: int, blocking: int, collisions: int}
	 * }
	 */
	public static function inspect( array $term_urls, array $hosts_in_use = array() ): array {
		$rows = array();

		// host => term_id => term name. Built as we go so a second term on the
		// same host is caught without a second pass.
		$hosts_seen = array();

		// term_id => host => true, for spotting redundant rows on one term.
		$term_hosts = array();

		foreach ( $term_urls as $row ) {
			$term_id = (int) ( $row['term_id'] ?? 0 );
			$term    = (string) ( $row['name'] ?? '' );
			$url     = trim( (string) ( $row['url'] ?? '' ) );

			$parsed = self::parse( $url );
			$host   = $parsed['host'];
			$flags  = $parsed['flags'];

			if ( '' !== $host ) {
				if ( isset( $term_hosts[ $term_id ][ $host ] ) ) {
					$flags[] = self::FLAG_DUPLICATE;
				}

				$term_hosts[ $term_id ][ $host ] = true;
				$hosts_seen[ $host ][ $term_id ] = $term;
			}

			$rows[] = array(
				'term_id'  => $term_id,
				'term'     => $term,
				'url'      => $url,
				'host'     => $host,
				'shows'    => (int) ( $hosts_in_use[ $host ] ?? 0 ),
				'bare'     => '' === $host ? '' : 'https://' . $host,
				'flags'    => $flags,
				'blocking' => self::is_blocking( $flags ),
			);
		}

		// A collision is two *different* terms on one host. The same term
		// listing a host twice is a duplicate row, already flagged above.
		$collisions = array();
		foreach ( $hosts_seen as $host => $terms ) {
			if ( count( $terms ) > 1 ) {
				$collisions[ $host ] = $terms;
			}
		}

		$flag_counts = array();
		$flagged     = 0;
		$blocking    = 0;
		$term_ids    = array();

		foreach ( $rows as $row ) {
			$term_ids[ $row['term_id'] ] = true;

			if ( count( $row['flags'] ) > 0 ) {
				++$flagged;
			}

			if ( $row['blocking'] ) {
				++$blocking;
			}

			foreach ( $row['flags'] as $flag ) {
				$flag_counts[ $flag ] = ( $flag_counts[ $flag ] ?? 0 ) + 1;
			}
		}

		arsort( $flag_counts );

		return array(
			'rows'        => $rows,
			'collisions'  => $collisions,
			'flag_counts' => $flag_counts,
			'totals'      => array(
				'rows'       => count( $rows ),
				'terms'      => count( $term_ids ),
				'hosts'      => count( $hosts_seen ),
				'flagged'    => $flagged,
				'blocking'   => $blocking,
				'collisions' => count( $collisions ),
			),
		);
	}

	/**
	 * Pull one stored URL apart and say what is wrong with it.
	 *
	 * @param string $url Stored value.
	 * @return array{host: string, flags: array<string>}
	 */
	private static function parse( string $url ): array {
		$flags = array();

		if ( '' === $url ) {
			return array(
				'host'  => '',
				'flags' => array( self::FLAG_UNPARSEABLE ),
			);
		}

		$parsed = wp_parse_url( $url );

		if ( ! is_array( $parsed ) || empty( $parsed['host'] ) ) {
			// Retry only when the value never claimed to have a scheme.
			// This addresses an issue in wp_parse_url() where a URL without
			// schema can be misinterpreted as a path.
			$retry = str_contains( $url, '://' )
				? false
				: wp_parse_url( 'https://' . ltrim( $url, '/' ) );

			if ( ! is_array( $retry ) || empty( $retry['host'] ) ) {
				return array(
					'host'  => '',
					'flags' => array( self::FLAG_UNPARSEABLE ),
				);
			}

			$parsed  = $retry;
			$flags[] = self::FLAG_NO_SCHEME;
		} else {
			$scheme = strtolower( (string) ( $parsed['scheme'] ?? '' ) );

			if ( '' === $scheme ) {
				$flags[] = self::FLAG_NO_SCHEME;
			} elseif ( 'https' !== $scheme ) {
				$flags[] = self::FLAG_HTTP_SCHEME;
			}
		}

		$raw_host = (string) $parsed['host'];

		if ( strtolower( $raw_host ) !== $raw_host ) {
			$flags[] = self::FLAG_UPPERCASE;
		}

		if ( str_starts_with( strtolower( $raw_host ), 'www.' ) ) {
			$flags[] = self::FLAG_WWW;
		}

		if ( ! empty( $parsed['port'] ) ) {
			$flags[] = self::FLAG_PORT;
		}

		if ( ! empty( $parsed['user'] ) || ! empty( $parsed['pass'] ) ) {
			$flags[] = self::FLAG_CREDENTIALS;
		}

		$path = (string) ( $parsed['path'] ?? '' );

		if ( '/' === $path ) {
			$flags[] = self::FLAG_TRAILING_SLASH;
		} elseif ( '' !== $path ) {
			$flags[] = self::FLAG_PATH;
		}

		if ( ! empty( $parsed['query'] ) ) {
			$flags[] = self::FLAG_QUERY;
		}

		if ( ! empty( $parsed['fragment'] ) ) {
			$flags[] = self::FLAG_FRAGMENT;
		}

		$host = Host_Name::normalise( $raw_host );

		if ( '' === $host ) {
			return array(
				'host'  => '',
				'flags' => array( self::FLAG_UNPARSEABLE ),
			);
		}

		return array(
			'host'  => $host,
			'flags' => $flags,
		);
	}
}
