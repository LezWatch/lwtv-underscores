<?php
/**
 * Name: Ways to Watch
 * Description: Edit 'ways to watch' on the fly, based on networks and links
 *
 * The lez_watch_urls taxonomy is the source of truth for provider names. A term
 * holds the URLs that identify it (lezwatchurls_all_N_url) and its *name is the
 * display name* -- it is used verbatim, never reformatted.
 *
 * Matching is by normalised *host*, via Watch_Hosts::term_for(). It used to be an
 * exact comparison against the stored URL string, which meant a term URL saved
 * with a trailing slash or a `www.` matched nothing and the provider silently
 * fell through to the guess below. See CPTs\Shows\Watch_Host_Map.
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
use LWTV\CPTs\Shows\Watch_Hosts;


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

			$term = Watch_Hosts::term_for( $parsed_url['host'] );

			// No term for this host: fall back to guessing from the hostname.
			if ( ! $term ) {
				$old_style_urls[] = $url;
				continue;
			}

			// If Hide Display is flagged, hide the display.
			if ( '1' === get_term_meta( $term->term_id, 'lezwatchurls_setting_hide_display', true ) ) {
				continue;
			}

			// The term name IS the display name. Do not reformat it -- decoding
			// is not reformatting, see term_name().
			$links[] = $this->build_link( $url, $this->term_name( $term->name ) );
		}

		// If we have old style URLs, we need to generate those links.
		if ( ! empty( $old_style_urls ) ) {
			$old_links = $this->generate_links_old( $old_style_urls );
			$links     = array_merge( $links, $old_links );
		}

		return $links;
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
	 * A term name as text, not as HTML.
	 *
	 * WordPress stores term names entity-encoded, so "U&Alibi" comes back as
	 * "U&amp;Alibi" and "Seed&Spark" as "Seed&amp;Spark". build_link() escapes
	 * with esc_html(), which encodes the ampersand a second time, and the reader
	 * gets a literal "U&amp;Alibi" on the button.
	 *
	 * Decode on the way out rather than fixing the stored value: WordPress
	 * re-encodes on every term save, so a corrected name would not stay
	 * corrected.
	 *
	 * Twin of Debugger\Watch_URLs::term_name(), which solved this for the
	 * debugger's findings and documents the same reasoning. Two copies of a
	 * one-liner is not yet a pattern; a third means extracting it.
	 *
	 * @param  string $name Term name as stored.
	 * @return string
	 */
	private function term_name( string $name ): string {
		return html_entity_decode( $name, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
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
