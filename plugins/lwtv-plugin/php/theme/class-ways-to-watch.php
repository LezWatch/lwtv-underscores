<?php
/**
 * Name: Ways to Watch
 * Description: Edit 'ways to watch' on the fly, based on networks and links
 *
 * The lez_watch_urls taxonomy is the source of truth for provider names. A term
 * holds the URLs that identify it (lezwatchurls_all_N_url) and its *name is the
 * display name* -- it is used verbatim, never reformatted.
 *
 * Hosts with no term fall through to guess_name(), which does its best from the
 * hostname. That path is permanent: LWTV documents web series, and each one
 * lives on its own domain, so there will always be a long tail not worth a term.
 */

namespace LWTV\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Ways_To_Watch {

	/**
	 * Taxonomy holding the watch providers.
	 */
	const TAXONOMY = 'lez_watch_urls';

	/**
	 * Subdomain prefixes to strip when guessing a name, and when looking for an
	 * alternate host to match on.
	 */
	const SUBDOMAINS = array( 'gshow.', 'play.', 'premium.', 'watch.', 'www.' );

	/**
	 * Suffixes to strip when guessing a name.
	 *
	 * Not all of these are real TLDs -- '.cbc', '.globo' and friends are middle
	 * segments of hosts like gem.cbc.ca. clean_tlds() strips repeatedly and
	 * longest-first, so multi-part suffixes resolve without needing every
	 * combination listed.
	 */
	const TLDS = array(
		'.co.nz',
		'.co.uk',
		'.go.com',
		'.fandom',
		'.globo',
		'.com',
		'.cbc',
		'.net',
		'.org',
		'.ca',
		'.co',
		'.es',
		'.go',
		'.tv',
	);

	/**
	 * Call Custom Links
	 *
	 * This is used by shows to figure out where people can watch things
	 * There's some juggling for certain sites
	 */
	public function ways_to_watch( $id ) {
		$rows       = get_field( 'lezshows_waystowatch', $id );
		$watch_urls = is_array( $rows ) ? array_filter( array_column( $rows, 'url' ) ) : array();

		$links       = self::generate_links( $watch_urls );
		$link_output = implode( '', $links );

		$icon   = lwtv_plugin()->get_symbolicon( svg: 'tv-hd.svg', icon: 'svg-tv' );
		$output = $icon . '<span class="how-to-watch">Ways to Watch:</span> ' . $link_output;

		return $output;
	}

	/**
	 * Generate URLs
	 *
	 * @param  array $watch_urls
	 * @return array
	 */
	public function generate_links( $watch_urls ) {
		// No URLs? Bail early.
		if ( empty( $watch_urls ) || ! is_array( $watch_urls ) ) {
			return array();
		}

		$old_style_urls = array();
		$links          = array();

		foreach ( $watch_urls as $url ) {
			$parsed_url = wp_parse_url( $url );

			// Junk in the field shouldn't warn or crash the whole block.
			if ( ! is_array( $parsed_url ) || empty( $parsed_url['host'] ) ) {
				continue;
			}

			$terms = $this->get_term_by_url( $this->url_candidates( $parsed_url ) );

			// No term for this host: fall back to guessing from the hostname.
			if ( empty( $terms ) ) {
				$old_style_urls[] = $url;
				continue;
			}

			$term = $terms[0];

			// If Hide Display is flagged, hide the display.
			if ( '1' === get_term_meta( $term->term_id, 'lezwatchurls_setting_hide_display', true ) ) {
				continue;
			}

			// The term name IS the display name. Do not reformat it.
			$links[] = $this->build_link( $url, $term->name );
		}

		// If we have old style URLs, we need to generate those links.
		if ( ! empty( $old_style_urls ) ) {
			$old_links = $this->generate_links_old( $old_style_urls );
			$links     = array_merge( $links, $old_links );
		}

		return $links;
	}

	/**
	 * Every stored URL form worth trying for one parsed URL.
	 *
	 * Terms store 'scheme://host'. Editors are inconsistent about www and about
	 * http vs https, and a term registered as https will never match an http
	 * show URL on an exact comparison, so both are offered here rather than
	 * requiring every term to list every variant.
	 *
	 * @param  array $parsed_url Output of wp_parse_url().
	 * @return array<string>
	 */
	private function url_candidates( array $parsed_url ): array {
		$host = strtolower( $parsed_url['host'] );
		$bare = $this->clean_subdomain( $host );

		$hosts = array_unique(
			array(
				$host,
				$bare,
				'www.' . $bare,
			)
		);

		// Try the URL's own scheme first, then the other one.
		$scheme  = ( isset( $parsed_url['scheme'] ) && 'http' === $parsed_url['scheme'] ) ? 'http' : 'https';
		$schemes = array( $scheme, 'http' === $scheme ? 'https' : 'http' );

		$candidates = array();
		foreach ( $schemes as $one_scheme ) {
			foreach ( $hosts as $one_host ) {
				$candidates[] = $one_scheme . '://' . $one_host;
			}
		}

		return array_values( array_unique( $candidates ) );
	}

	/**
	 * Get Term by URL
	 *
	 * Searches ACF repeater subfield rows (lezwatchurls_all_N_url) for an exact
	 * match against any of the supplied URLs, in one query.
	 *
	 * @param  string|array $urls One URL, or candidates in priority order.
	 * @return array
	 */
	public function get_term_by_url( $urls ): array {
		global $wpdb;

		$urls = array_values( array_filter( (array) $urls ) );

		if ( empty( $urls ) ) {
			return array();
		}

		$placeholders = implode( ', ', array_fill( 0, count( $urls ), '%s' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$term_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT t.term_id
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
				INNER JOIN {$wpdb->termmeta} tm ON t.term_id = tm.term_id
				WHERE tt.taxonomy = %s
				AND tm.meta_key REGEXP '^lezwatchurls_all_[0-9]+_url$'
				AND tm.meta_value IN ( {$placeholders} )",
				array_merge( array( self::TAXONOMY ), $urls )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( empty( $term_ids ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'include'    => $term_ids,
			)
		);

		return ( ! is_wp_error( $terms ) && is_array( $terms ) ) ? $terms : array();
	}

	/**
	 * Generate links for hosts with no term, guessing the name.
	 *
	 * @param  array $watch_urls
	 * @return array
	 */
	public function generate_links_old( $watch_urls ) {
		$links = array();

		// Parse each URL to figure out who it is...
		foreach ( $watch_urls as $url ) {
			$parsed_url = wp_parse_url( $url );

			if ( ! is_array( $parsed_url ) || empty( $parsed_url['host'] ) ) {
				continue;
			}

			$hostname = strtolower( $parsed_url['host'] );

			// Clean the subdomain.
			$hostname = $this->clean_subdomain( $hostname );

			// Remove TLDs from the end:
			$hostname = $this->clean_tlds( $hostname );

			// Add to the links array.
			$links[] = $this->build_link( $url, $this->guess_name( $hostname ) );
		}

		return $links;
	}

	/**
	 * Build formatted link
	 *
	 * @param  string $url
	 * @param  string $name
	 * @return string
	 */
	public function build_link( $url, $name ): string {
		return '<a href="' . esc_url( $url ) . '" target="_blank" class="btn btn-primary" rel="nofollow">' . esc_html( $name ) . '</a>';
	}

	/**
	 * Clean Subdomains
	 *
	 * @param  string $hostname
	 * @return string
	 */
	public function clean_subdomain( $hostname ): string {
		foreach ( self::SUBDOMAINS as $remove ) {
			// substr, not ltrim: ltrim's second argument is a character list, so
			// ltrim( 'watch.amazon.com', 'watch.' ) also ate the leading 'a' and
			// produced 'mazon.com'.
			if ( str_starts_with( $hostname, $remove ) ) {
				return substr( $hostname, strlen( $remove ) );
			}
		}

		return $hostname;
	}

	/**
	 * Clean TLDs off hosts
	 *
	 * Strips repeatedly and longest-match-first, so 'abc.go.com' resolves to
	 * 'abc' rather than stopping at '.com' and leaving 'abc.go'.
	 *
	 * @param  string $hostname
	 * @return string
	 */
	public function clean_tlds( $hostname ): string {
		static $suffixes = null;

		if ( null === $suffixes ) {
			$suffixes = self::TLDS;
			// Longest first, so '.go.com' beats '.com' and '.co.uk' beats '.co'.
			usort(
				$suffixes,
				static function ( $a, $b ) {
					return strlen( $b ) <=> strlen( $a );
				}
			);
		}

		$changed = true;
		while ( $changed ) {
			$changed = false;

			foreach ( $suffixes as $remove ) {
				if ( '' === $remove || ! str_ends_with( $hostname, $remove ) ) {
					continue;
				}

				$trimmed = substr( $hostname, 0, -strlen( $remove ) );

				// Never strip down to nothing -- a host that *is* a suffix
				// (say 'globo') should keep its name.
				if ( '' === $trimmed ) {
					break;
				}

				$hostname = $trimmed;
				$changed  = true;
				break;
			}
		}

		return $hostname;
	}

	/**
	 * Guess a display name from a hostname.
	 *
	 * Only used for hosts with no term. Short hostnames are almost always
	 * acronyms (abc, cbs, hbo, ifc), so those are upper-cased; anything longer
	 * just gets its first letter capitalised. Give a host a term when the guess
	 * isn't good enough -- that is what the taxonomy is for.
	 *
	 * @param  string $hostname Hostname, already stripped of subdomain and TLD.
	 * @return string
	 */
	private function guess_name( string $hostname ): string {
		$hostname = trim( $hostname, " \t\n\r\0\x0B." );

		if ( '' === $hostname ) {
			return 'Watch Online';
		}

		return ( strlen( $hostname ) <= 3 ) ? strtoupper( $hostname ) : ucfirst( $hostname );
	}
}
