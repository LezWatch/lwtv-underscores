<?php
/**
 * Name: Search Modal
 * Description: Front-end glue for the Meilisearch header search modal.
 *
 * Decides whether the modal is on for this request (the LWTV_MEILI_MODAL
 * switch) and prints its browser config. Only the search key goes into the
 * page; the indexing key is never read here.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Meilisearch\Build\Modal_Config;
use LWTV\Meilisearch\Build\Modal_Switch;

class Search_Modal {

	/**
	 * The switch constant.
	 */
	const SWITCH_CONSTANT = 'LWTV_MEILI_MODAL';

	private Config $config;
	private Search_Settings_Store $settings;

	/**
	 * @param Config                     $config   Connection settings; may be unconfigured.
	 * @param Search_Settings_Store|null $settings The switch and timeout; null reads the option and constant.
	 */
	public function __construct( Config $config, ?Search_Settings_Store $settings = null ) {
		$this->config   = $config;
		$this->settings = $settings ?? new Search_Settings_Store();
	}

	/**
	 * Whether this request gets the Meilisearch modal.
	 *
	 * @return bool
	 */
	public function enabled(): bool {
		// LWTV_MEILI_MODAL wins while defined; otherwise the Search Settings option.
		$setting = $this->settings->effective()['mode'];

		// A search key that is really the indexing key would land in every page: treat as unconfigured.
		$configured = $this->config->is_configured() && $this->config->search_key_is_public();

		return Modal_Switch::enabled( $setting, $configured, current_user_can( 'edit_posts' ) );
	}

	/**
	 * Preconnect to the search server, so the first search doesn't spend
	 * its 2-second budget on DNS, TLS and the CORS preflight.
	 *
	 * Hooked to wp_resource_hints. crossorigin because the search request is
	 * a CORS fetch, which uses the anonymous connection pool.
	 *
	 * @param array  $urls          Hints for this relation type.
	 * @param string $relation_type 'preconnect', 'dns-prefetch', etc.
	 * @return array
	 */
	public function resource_hints( $urls, $relation_type ) {
		if ( 'preconnect' !== $relation_type || ! is_array( $urls ) || ! $this->enabled() ) {
			return $urls;
		}

		$origin = Modal_Config::origin( $this->config->url() );
		if ( '' !== $origin ) {
			$urls[] = array(
				'href'        => $origin,
				'crossorigin' => 'anonymous',
			);
		}

		return $urls;
	}

	/**
	 * The browser config as JSON, safe to print inside a <script> element.
	 *
	 * @return string '' when the modal is off.
	 */
	public function config_json(): string {
		if ( ! $this->enabled() ) {
			return '';
		}

		$uids = array();
		foreach ( Modal_Config::TABS as $type ) {
			$uids[ $type ] = $this->config->index( $type );
		}

		$config = Modal_Config::build(
			$this->config->url(),
			$this->config->search_key(),
			$uids,
			home_url( '/' ),
			$this->strings(),
			$this->settings->timeout_ms()
		);

		/**
		 * Filters the search modal's browser config.
		 *
		 * A debugging hook: a bad url or uid here exercises the SearchWP
		 * fallback. Never add the indexing key.
		 *
		 * @param array $config See Modal_Config::build().
		 */
		$config = apply_filters( 'lwtv_meili_modal_config', $config );

		$json = wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return is_string( $json ) ? $json : '';
	}

	/**
	 * How long server-side searches wait before falling back, in seconds.
	 *
	 * @return float
	 */
	public function timeout_seconds(): float {
		return $this->settings->timeout_seconds();
	}

	/**
	 * Translated strings for the browser.
	 *
	 * @return array<string, mixed>
	 */
	private function strings(): array {
		$plurals = array(
			/* translators: %d: number of shows. */
			'shows'      => _n_noop( '%d show', '%d shows', 'lwtv-underscores' ),
			/* translators: %d: number of characters. */
			'characters' => _n_noop( '%d character', '%d characters', 'lwtv-underscores' ),
			/* translators: %d: number of actors. */
			'actors'     => _n_noop( '%d actor', '%d actors', 'lwtv-underscores' ),
			/* translators: %d: number of posts and pages. */
			'content'    => _n_noop( '%d post', '%d posts', 'lwtv-underscores' ),
		);
		/* translators: %d: number of results in a search tab. */
		$results = _n_noop( '%d result', '%d results', 'lwtv-underscores' );

		$strings = array(
			'labels'      => array(
				'shows'      => __( 'Shows', 'lwtv-underscores' ),
				'characters' => __( 'Characters', 'lwtv-underscores' ),
				'actors'     => __( 'Actors', 'lwtv-underscores' ),
				'content'    => __( 'Posts', 'lwtv-underscores' ),
			),
			'one'         => array(),
			'many'        => array(),
			'resultOne'   => translate_nooped_plural( $results, 1, 'lwtv-underscores' ),
			'resultMany'  => translate_nooped_plural( $results, 2, 'lwtv-underscores' ),
			'dialogLabel' => __( 'Search the site', 'lwtv-underscores' ),
			'inputLabel'  => __( 'Search for', 'lwtv-underscores' ),
			'close'       => __( 'Close search', 'lwtv-underscores' ),
			'seeAll'      => __( 'See all results', 'lwtv-underscores' ),
			/* translators: %s: the search term. */
			'noResults'   => __( 'No results for %s', 'lwtv-underscores' ),
			/* translators: %s: the name of the selected tab, e.g. "Characters". */
			'showing'     => __( 'Showing %s.', 'lwtv-underscores' ),
			'unavailable' => __( 'Search is unavailable right now.', 'lwtv-underscores' ),
			'searchPage'  => __( 'Try the search page', 'lwtv-underscores' ),
			'fallback'    => __( 'Switched to the standard search.', 'lwtv-underscores' ),
			/* translators: %s: comma-separated list of alternate show names. */
			'aka'         => __( 'Also known as: %s', 'lwtv-underscores' ),
			'news'        => __( 'News', 'lwtv-underscores' ),
			'page'        => __( 'Page', 'lwtv-underscores' ),
		);

		foreach ( $plurals as $type => $noop ) {
			$strings['one'][ $type ]  = translate_nooped_plural( $noop, 1, 'lwtv-underscores' );
			$strings['many'][ $type ] = translate_nooped_plural( $noop, 2, 'lwtv-underscores' );
		}

		return $strings;
	}
}
