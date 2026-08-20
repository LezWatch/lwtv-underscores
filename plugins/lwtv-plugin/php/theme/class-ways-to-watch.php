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

use LWTV\CPTs\Shows\Host_Name;
use LWTV\CPTs\Shows\Watch_Host_Names;


class Ways_To_Watch {

	/**
	 * Taxonomy holding the watch providers.
	 */
	const TAXONOMY = 'lez_watch_urls';

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
	 * Every stored URL form worth trying for one parsed URL, most specific first.
	 *
	 * Terms store 'scheme://host'. Editors are inconsistent about www and about
	 * http vs https, and a term registered as https will never match an http
	 * show URL on an exact comparison, so both are offered rather than requiring
	 * every term to list every variant.
	 *
	 * @param  array $parsed_url Output of wp_parse_url().
	 * @return array<string>
	 */
	private function url_candidates( array $parsed_url ): array {
		$hosts = Host_Name::host_candidates( $parsed_url['host'] );

		if ( empty( $hosts ) ) {
			return array();
		}

		// The URL's own scheme first, then the other one.
		$scheme  = ( isset( $parsed_url['scheme'] ) && 'http' === $parsed_url['scheme'] ) ? 'http' : 'https';
		$schemes = array( $scheme, 'http' === $scheme ? 'https' : 'http' );

		$candidates = array();
		foreach ( $hosts as $one_host ) {
			foreach ( $schemes as $one_scheme ) {
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
	 * When several candidates match, the earliest in $urls wins, so a term
	 * registered on 'abc.go.com' beats one registered on 'go.com'.
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
		$matches = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tm.meta_value AS matched_url, t.term_id
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

		if ( empty( $matches ) ) {
			return array();
		}

		// SQL has no opinion on our candidate order, so resolve it here.
		$by_url = array();
		foreach ( $matches as $match ) {
			$by_url[ $match->matched_url ] = (int) $match->term_id;
		}

		$term_id = 0;
		foreach ( $urls as $candidate ) {
			if ( isset( $by_url[ $candidate ] ) ) {
				$term_id = $by_url[ $candidate ];
				break;
			}
		}

		if ( ! $term_id ) {
			return array();
		}

		$term = get_term( $term_id, self::TAXONOMY );

		return ( $term instanceof \WP_Term ) ? array( $term ) : array();
	}

	/**
	 * Generate links for hosts with no term, guessing the name.
	 *
	 * @param  array $watch_urls
	 * @return array
	 */
	public function generate_links_old( $watch_urls ) {
		$links = array();

		foreach ( $watch_urls as $url ) {
			$parsed_url = wp_parse_url( $url );

			if ( ! is_array( $parsed_url ) || empty( $parsed_url['host'] ) ) {
				continue;
			}

			$links[] = $this->build_link( $url, $this->guess_name( $parsed_url['host'] ) );
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
	 * Best available display name for a host with no term.
	 *
	 * Three tiers, best first:
	 *   1. A lez_watch_urls term name  -- handled by the caller, wins outright.
	 *   2. A name the host published about itself, discovered by
	 *      `wp lwtv waystowatch enrich` and cached. Reads the cache only; never
	 *      makes a request during a page load.
	 *   3. Host_Name's guess from the hostname, which is pure and unit-tested
	 *      but can only ever be best-effort.
	 *
	 * @param  string $host Hostname.
	 * @return string
	 */
	private function guess_name( string $host ): string {
		$discovered = Watch_Host_Names::get( $host );

		if ( null !== $discovered && '' !== $discovered ) {
			return $discovered;
		}

		return Host_Name::guess( $host );
	}
}
