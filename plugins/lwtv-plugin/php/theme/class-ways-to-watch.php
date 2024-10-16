<?php
/**
 * Name: Ways to Watch
 * Description: Edit 'ways to watch' on the fly, based on networks and links
 *
 */

namespace LWTV\Theme;

use LWTV\CPTs\Shows\Ways_To_Watch as Ways_To_Watch_Taxonomy;

class Ways_To_Watch {

	const SUBDOMAINS = array( 'gshow.', 'play.', 'premium.', 'watch.', 'www.' );
	const TLDS       = array( '.com', '.co.nz', '.co.uk', '.ca', '.cbc', '.co', '.fandom', '.globo', '.go', '.org', '.tv' );

	// URLs that belong to someone else.
	const URL_OWNER = array(
		'paus.tv'         => 'paus',
		'watch.paus'      => 'paus',
		'sho'             => 'showtime',
		'showtimeanytime' => 'showtime',
	);

	// URL and name params based on host.
	const PRETTY_NAME = array(
		'acorn.tv'            => 'Acorn',
		'adultswim'           => 'Adult Swim',
		'bet.plus'            => 'BET+',
		'bifltheseries'       => 'BIFL',
		'cwtv'                => 'The CW',
		'dcuniverse'          => 'DC Universe',
		'hbomax'              => 'HBO Max',
		'lesflicksvod'        => 'LesFlicks',
		'paus'                => 'paus',
		'peepoodo.bobbypills' => 'BobbyPills',
		'reelwomensnetwork'   => 'Reel Women\'s Network',
		'roosterteeth'        => 'Roster Teeth',
		'svtvnetwork'         => 'SVtv',
		'tellofilms'          => 'Tello Films',
		'tntdrama'            => 'TNT Drama',
		'tvnz'                => 'TVNZ',
		'tv.line.me'          => 'LineTV',
	);

	/**
	 * Call Custom Links
	 *
	 * This is used by shows to figure out where people can watch things
	 * There's some juggling for certain sites
	 */
	public function ways_to_watch( $id ) {
		// Check the Ways to Watch. This will silently migrate everything.
		( new Ways_To_Watch_Taxonomy() )->migrate_ways_to_watch( $id );

		$watch_urls = get_post_meta( $id, 'lezshows_waystowatch', true );

		$links       = self::generate_links( $watch_urls );
		$link_output = implode( '', $links );

		$icon   = lwtv_plugin()->get_symbolicon( 'tv-hd.svg', 'fa-tv' );
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
			$uc_string  = false;
			$parsed_url = wp_parse_url( $url );
			$clean_url  = $parsed_url['scheme'] . '://' . $parsed_url['host'];
			$terms      = $this->get_term_by_url( $clean_url );

			// If this is empty, check the alt URL.
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				$alt_host = $this->check_alt_url( $parsed_url );
				$terms    = $this->get_term_by_url( $alt_host );
			}

			// If this is STILL empty, we have an old-school URL.
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				$old_style_urls[] = $url;
				$uc_string        = true;
			}

			// No terms? Skip.
			if ( empty( $terms ) ) {
				continue;
			}

			// If Hide Display is flagged, hide the display.
			if ( get_term_meta( $terms[0]->ID, 'lezwatchurls_setting_hide_display' ) ) {
				continue;
			}

			$slug = $terms[0]->name;

			// Clean the name
			$name = $this->clean_name( $slug, $uc_string );

			// Add to the links array.
			$links[] = $this->build_link( $url, $name );
		}

		// If we have old style URLs, we need to generate those links.
		if ( ! empty( $old_style_urls ) ) {
			$old_links = $this->generate_links_old( $old_style_urls );
			$links     = array_merge( $links, $old_links );
		}

		return $links;
	}

	/**
	 * Check alternate URL
	 *
	 * We have to check both WWW and non-WWW versions of the URL because
	 * I don't know what URL people will put in!
	 *
	 * @param  string $url
	 * @return string
	 */
	public function check_alt_url( $parsed_url ) {
		$clean_host = $this->clean_subdomain( $parsed_url['host'] );

		if ( 'www.' === substr( $parsed_url['host'], 0, 4 ) ) {
			$alt_url = $clean_host;
		} else {
			$alt_url = 'www.' . $clean_host;
		}

		$clean_url = $parsed_url['scheme'] . '://' . $alt_url;

		return $clean_url;
	}

	/**
	 * Get Term by URL
	 *
	 * @param  string $url
	 * @return array
	 */
	public function get_term_by_url( $url ): array {
		$args = array(
			'hide_empty' => false, // also retrieve terms which are not used yet
			'meta_query' => array(
				array(
					'key'     => 'lezwatchurls_all',
					'value'   => $url,
					'compare' => 'LIKE',
				),
			),
			'taxonomy'   => 'lez_watch_urls',
		);

		$terms = get_terms( $args );

		return $terms;
	}

	/**
	 * Generate URLs
	 *
	 * @param  array $watch_urls
	 * @return array
	 */
	public function generate_links_old( $watch_urls ) {
		$links = array();

		// Parse each URL to figure out who it is...
		foreach ( $watch_urls as $url ) {
			$parsed_url = wp_parse_url( $url );
			$hostname   = $parsed_url['host'];

			// Clean the subdomain.
			$hostname = $this->clean_subdomain( $hostname );

			// Remove TLDs from the end:
			$hostname = $this->clean_tlds( $hostname );

			// Get the slug based on the hostname to array translation.
			$slug = ( array_key_exists( $hostname, self::URL_OWNER ) ) ? self::URL_OWNER[ $hostname ] : $hostname;

			// Clean the name
			$name = $this->clean_name( $slug, true );

			// Add to the links array.
			$links[] = $this->build_link( $url, $name );
		}

		return $links;
	}

	/**
	 * Build formatted link
	 *
	 * @param  string $url
	 * @param  string $name
	 * @param  string $extra
	 * @return string
	 */
	public function build_link( $url, $name ): string {
		return '<a href="' . $url . '" target="_blank" class="btn btn-primary" rel="nofollow">' . $name . '</a>';
	}

	/**
	 * Clean Subdomains
	 *
	 * @param  string $hostname
	 * @return string
	 */
	public function clean_subdomain( $hostname ): string {
		foreach ( self::SUBDOMAINS as $remove ) {
			$count = strlen( $remove );
			if ( substr( $hostname, 0, $count ) === $remove ) {
				$hostname = ltrim( $hostname, $remove );
				break;
			}
		}

		return $hostname;
	}

	/**
	 * Clean TLDs off hosts
	 *
	 * @param  string $hostname
	 * @return string
	 */
	public function clean_tlds( $hostname ): string {
		foreach ( self::TLDS as $remove ) {
			$count = strlen( $remove );
			if ( substr( $hostname, -$count ) === $remove ) {
				$hostname = substr( $hostname, 0, -$count );
				break;
			}
		}

		return $hostname;
	}

	/**
	 * Clean the pretty name based on the slug
	 *
	 * @param  string $slug
		* @param  bool   $uc_string
	 * @return string
	 */
	private function clean_name( string $slug, bool $uc_string = false ): string {
		// Set name based on slug in url_array. If not set, capitalize string.
		$name = ( isset( self::PRETTY_NAME[ $slug ] ) ) ? self::PRETTY_NAME[ $slug ] : ucfirst( $slug );

		// If it's three letters, it's always capitalized.
		$name = ( $uc_string && ! isset( self::PRETTY_NAME[ $slug ] ) && 3 === strlen( $name ) ) ? strtoupper( $name ) : $name;

		// Crazy failsafe:
		if ( empty( $name ) ) {
			$name = 'Watch Online';
		}

		return $name;
	}
}
