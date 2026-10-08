<?php
/**
 * Name: Ways to Watch
 * Description: Edit 'ways to watch' on the fly, based on networks and links
 *
 * A lez_watch_urls term, matched by host, supplies the display name verbatim;
 * hosts with no term fall through to guess_name().
 * See docs/architecture/watch-providers.md#display-names.
 *
 * With JUSTWATCH_API_KEY defined and a usable IMDb ID, the JustWatch widget
 * replaces the curated links unless the show's override is on.
 * See docs/plans/justwatch-widget.md.
 */

namespace LWTV\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Shows\Watching\Host_Name;
use LWTV\CPTs\Shows\Watching\JustWatch;
use LWTV\CPTs\Shows\Watching\Watch_Host_Names;
use LWTV\CPTs\Shows\Watching\Watch_Hosts;


class Ways_To_Watch {

	/**
	 * Taxonomy holding the watch providers.
	 */
	const TAXONOMY = 'lez_watch_urls';

	/**
	 * Script handle for the JustWatch widget.
	 */
	const JUSTWATCH_HANDLE = 'justwatch-widget';

	/**
	 * The JustWatch widget script.
	 */
	const JUSTWATCH_SCRIPT = 'https://widget.justwatch.com/justwatch_widget.js';

	/**
	 * Generic JustWatch link, used for branding until JustWatch confirms what
	 * they require. See docs/plans/justwatch-widget.md#open-questions.
	 */
	const JUSTWATCH_HOME = 'https://www.justwatch.com/';

	/**
	 * Call Custom Links
	 *
	 * This is used by shows to figure out where people can watch things
	 * There's some juggling for certain sites
	 */
	public function ways_to_watch( $id ) {
		$decision = $this->decision( $id );

		if ( JustWatch::MODE_NONE === $decision['mode'] ) {
			return '';
		}

		if ( JustWatch::MODE_JUSTWATCH === $decision['mode'] ) {
			return $this->render_justwatch( $decision['imdb'] );
		}

		$rows       = get_field( 'lezshows_waystowatch', $id );
		$watch_urls = is_array( $rows ) ? array_filter( array_column( $rows, 'url' ) ) : array();

		$links       = self::generate_links( $watch_urls );
		$link_output = implode( '', $links );

		return $this->heading() . ' ' . $link_output;
	}

	/**
	 * Icon and label shared by the curated and JustWatch outputs, so the
	 * section looks the same either way.
	 *
	 * @return string
	 */
	private function heading(): string {
		$icon = lwtv_plugin()->get_symbolicon( svg: 'tv-hd.svg', icon: 'svg-tv' );

		return $icon . '<span class="how-to-watch">Ways to Watch:</span>';
	}

	/**
	 * Which Ways to Watch this show gets. Reads the inputs; JustWatch::decide()
	 * makes the call. See docs/plans/justwatch-widget.md#decision-logic.
	 *
	 * @param  int|string $id Show ID.
	 * @return array{mode: string, imdb: string}
	 */
	private function decision( $id ): array {
		return JustWatch::decide(
			self::justwatch_key(),
			get_post_meta( $id, 'lezshows_imdb', true ),
			get_post_meta( $id, 'lezshows_imdb_canonical', true ),
			'1' === (string) get_post_meta( $id, 'lezshows_waystowatch_curated', true ),
			// The ACF repeater's row count: '' (never set) and '0' (all rows
			// deleted) are both correctly falsy.
			(bool) get_post_meta( $id, 'lezshows_waystowatch', true )
		);
	}

	/**
	 * The JustWatch widget key from wp-config.php, or '' when unset.
	 *
	 * @return string
	 */
	private static function justwatch_key(): string {
		return defined( 'JUSTWATCH_API_KEY' ) && is_scalar( JUSTWATCH_API_KEY ) ? (string) JUSTWATCH_API_KEY : '';
	}

	/**
	 * The JustWatch widget, its fallback link, the theme sync and the branding.
	 *
	 * The link inside the widget div is for readers whose browser never runs
	 * the widget script (ad blockers commonly block it). It assumes the widget
	 * replaces the div's children; see docs/plans/justwatch-widget.md#open-questions.
	 *
	 * @param  string $imdb Normalised IMDb ID from JustWatch::decide().
	 * @return string
	 */
	private function render_justwatch( string $imdb ): string {
		// Light on the server; the inline script below corrects it to the
		// reader's chosen mode before the async widget script can run.
		$attrs = JustWatch::widget_attributes( self::justwatch_key(), $imdb, 'light' );

		$attr_html = '';
		foreach ( $attrs as $name => $value ) {
			$attr_html .= ' ' . $name . ( '' === $value ? '' : '="' . esc_attr( $value ) . '"' );
		}

		$fallback = '<a href="' . esc_url( self::JUSTWATCH_HOME ) . '" target="_blank" rel="noopener">'
			. esc_html__( 'Find where to watch on JustWatch', 'lwtv' )
			. '</a>';

		// bootstrap-color-mode.js sets data-bs-theme on <html> in the <head>, so
		// it is already final when this runs.
		$theme_sync = wp_get_inline_script_tag(
			'(function(){var t=document.documentElement.getAttribute("data-bs-theme")==="dark"?"dark":"light";'
			. 'document.querySelectorAll("[data-jw-widget]").forEach(function(e){e.setAttribute("data-theme",t);});})();'
		);

		$branding = '<p class="justwatch-attribution">'
			. esc_html__( 'Streaming availability from', 'lwtv' ) . ' '
			. '<a href="' . esc_url( self::JUSTWATCH_HOME ) . '" target="_blank" rel="noopener">JustWatch</a>'
			. '</p>';

		// Third-party script: JustWatch controls its versioning.
		// phpcs:disable WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_script(
			self::JUSTWATCH_HANDLE,
			self::JUSTWATCH_SCRIPT,
			array(),
			null,
			array(
				'in_footer' => true,
				'strategy'  => 'async',
			)
		);
		// phpcs:enable WordPress.WP.EnqueuedResourceParameters.MissingVersion

		return $this->heading()
			. '<div class="justwatch"' . $attr_html . '>' . $fallback . '</div>'
			. $theme_sync
			. $branding;
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
			$links[] = $this->build_link( $url, self::term_name( $term->name ) );
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
	 * A provider term's name as text, not as HTML.
	 *
	 * Term names are stored entity-encoded; every surface that renders one must
	 * decode before escaping, or the name double-encodes.
	 * See docs/architecture/watch-providers.md#name-decoding.
	 *
	 * @param  string $name Term name as stored.
	 * @return string
	 */
	public static function term_name( string $name ): string {
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
	 * Best available display name for a host with no term: the cached
	 * self-published name, else Host_Name::guess(). Never makes a request.
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
