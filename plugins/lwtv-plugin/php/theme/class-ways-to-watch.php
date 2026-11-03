<?php
/**
 * Name: Ways to Watch
 * Description: Edit 'ways to watch' on the fly, based on networks and links
 *
 * A lez_watch_urls term, matched by host, supplies the display name verbatim;
 * hosts with no term fall through to guess_name().
 * See docs/architecture/watch-providers.md#display-names.
 *
 * When TMDB has a displayable provider for the show, a strip of provider logos
 * replaces the curated links unless the show's override is on.
 * See docs/plans/tmdb-watch-providers.md#rendering-phase-3.
 */

namespace LWTV\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\_Helpers\Tmdb_Providers;
use LWTV\CPTs\Shows\Watching\Host_Name;
use LWTV\CPTs\Shows\Watching\Watch_Host_Names;
use LWTV\CPTs\Shows\Watching\Watch_Hosts;
use LWTV\CPTs\Shows\Watching\Ways_Mode;
use LWTV\Schedulers\Watch_Providers_Task;


class Ways_To_Watch {

	/**
	 * Taxonomy holding the watch providers.
	 */
	const TAXONOMY = 'lez_watch_urls';

	/**
	 * Script handle for the country switcher.
	 */
	const SCRIPT_HANDLE = 'lwtv-ways-to-watch-country';

	/**
	 * Id of the section's label; the template's <section> points at it.
	 */
	const TITLE_ID = 'ways-to-watch-title';

	/**
	 * Most logos in the strip. The TMDB page has the full list.
	 */
	const LOGO_CAP = 8;

	/**
	 * Attribution links. TMDB requires JustWatch to be named as the source.
	 */
	const TMDB_HOME      = 'https://www.themoviedb.org/';
	const JUSTWATCH_HOME = 'https://www.justwatch.com/';

	/**
	 * Call Custom Links
	 *
	 * This is used by shows to figure out where people can watch things
	 * There's some juggling for certain sites
	 */
	public function ways_to_watch( $id ) {
		$providers = Watch_Providers_Task::stored( (int) $id );
		$country   = Tmdb_Providers::default_country( $providers, Tmdb_Providers::DISPLAY_TYPES );

		$mode = Ways_Mode::decide(
			'1' === (string) get_post_meta( $id, 'lezshows_waystowatch_curated', true ),
			// The ACF repeater's row count: '' (never set) and '0' (all rows
			// deleted) are both correctly falsy.
			(bool) get_post_meta( $id, 'lezshows_waystowatch', true ),
			'' !== $country
		);

		if ( Ways_Mode::MODE_NONE === $mode ) {
			return '';
		}

		if ( Ways_Mode::MODE_PROVIDERS === $mode ) {
			return $this->render_providers( $providers, $country );
		}

		$rows       = get_field( 'lezshows_waystowatch', $id );
		$watch_urls = is_array( $rows ) ? array_filter( array_column( $rows, 'url' ) ) : array();

		$links       = self::generate_links( $watch_urls );
		$link_output = implode( '', $links );

		return $this->icon() . '<span class="how-to-watch" id="' . esc_attr( self::TITLE_ID ) . '">Ways to Watch:</span> ' . $link_output;
	}

	/**
	 * The section icon, shared by both outputs so the section looks the same.
	 *
	 * @return string
	 */
	private function icon(): string {
		return lwtv_plugin()->get_symbolicon( svg: 'tv-hd.svg', icon: 'svg-tv' );
	}

	/**
	 * The provider strip: heading with the country switcher, logos grouped by
	 * Tmdb_Providers::GROUPS, one link, attribution.
	 *
	 * One link, not one per logo, because TMDB gives no deep links: every logo
	 * would go to the same page. The logos are a labelled list, so each is
	 * named by its alt. See docs/plans/tmdb-watch-providers.md#markup-and-accessibility.
	 *
	 * Rendered for the default country (US, else the first), never from the
	 * request, so the page stays cacheable. The script picks the reader's.
	 *
	 * @param  array  $providers Watch_Providers_Task::stored().
	 * @param  string $country   The country to render.
	 * @return string
	 */
	private function render_providers( array $providers, string $country ): string {
		$strip     = Tmdb_Providers::strip( $providers, Tmdb_Providers::DISPLAY_TYPES, $country, self::LOGO_CAP );
		$countries = $this->country_names( Tmdb_Providers::countries( $providers, Tmdb_Providers::DISPLAY_TYPES ) );
		$payload   = Tmdb_Providers::payload( $providers, Tmdb_Providers::DISPLAY_TYPES );

		$labels = $this->group_labels();

		$payload['cap']      = self::LOGO_CAP;
		$payload['logoBase'] = Tmdb_Providers::LOGO_BASE;
		$payload['default']  = $country;
		$payload['labels']   = $labels;

		$options = '';
		foreach ( $countries as $code => $name ) {
			$options .= '<option value="' . esc_attr( $code ) . '"' . selected( $code, $country, false ) . '>' . esc_html( $name ) . '</option>';
		}

		$by_id  = array_column( $strip['shown'], null, 'id' );
		$groups = '';
		foreach ( $strip['groups'] as $group => $ids ) {
			$logos = '';
			foreach ( $ids as $id ) {
				$logos .= '<li>' . $this->logo( $by_id[ $id ] ) . '</li>';
			}

			$label_id = 'watch-providers-' . $group;
			$groups  .= '<div class="watch-providers__group">'
				. '<p class="watch-providers__label" id="' . esc_attr( $label_id ) . '">' . esc_html( $labels[ $group ] ) . '</p>'
				. '<ul class="watch-providers__logos" aria-labelledby="' . esc_attr( $label_id ) . '">' . $logos . '</ul>'
				. '</div>';
		}

		$more = $strip['more'] > 0
			/* translators: %s: number of further streaming services. */
			? sprintf( _n( 'and %s more', 'and %s more', $strip['more'], 'lwtv-underscores' ), number_format_i18n( $strip['more'] ) )
			: '';

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			LWTV_PLUGIN_URL . '/assets/js/ways-to-watch-country.js',
			array( 'wp-i18n' ),
			// The theme version moves with every release; the plugin's does not.
			defined( 'LWTV_THEME_VERSION' ) ? LWTV_THEME_VERSION['lwtv-underscores'] : LWTV_PLUGIN_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_set_script_translations( self::SCRIPT_HANDLE, 'lwtv-underscores' );

		// The country select sits in the heading, so the heading's name reads
		// "Where to watch in Canada". Without JavaScript the static country shows.
		return '<h2 class="how-to-watch" id="' . esc_attr( self::TITLE_ID ) . '">' . $this->icon()
				. esc_html__( 'Where to watch', 'lwtv-underscores' )
				/* translators: %s: country name. */
				. '<span class="watch-providers__in" data-watch-providers-in> ' . esc_html( sprintf( __( 'in %s', 'lwtv-underscores' ), $countries[ $country ] ?? $country ) ) . '</span>'
				. '<span class="watch-providers__country" data-watch-providers-picker hidden> '
					. esc_html_x( 'in', 'Where to watch in [country select]', 'lwtv-underscores' ) . ' '
					. '<select id="watch-providers-country" class="watch-providers__select" aria-label="' . esc_attr__( 'Country', 'lwtv-underscores' ) . '">' . $options . '</select>'
				. '</span>'
			. '</h2>'
			. '<div class="watch-providers" data-watch-providers>'
				. '<div class="watch-providers__groups">'
					. $groups
					. '<p class="watch-providers__more"' . ( '' === $more ? ' hidden' : '' ) . '>' . esc_html( $more ) . '</p>'
				. '</div>'
				. '<p class="watch-providers__status" role="status"></p>'
				. '<div class="watch-providers__footer">'
					. '<a class="watch-providers__cta" href="' . esc_url( $strip['link'] ) . '" target="_blank" rel="noopener">'
						. esc_html__( 'See all options on TMDB', 'lwtv-underscores' )
						. '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'lwtv-underscores' ) . '</span>'
						. self::external_icon()
					. '</a>'
					. '<p class="watch-providers__credit">'
						. sprintf(
							/* translators: 1: TMDB, linked. 2: JustWatch, linked. */
							esc_html__( 'Powered by %1$s and %2$s', 'lwtv-underscores' ),
							'<a href="' . esc_url( self::TMDB_HOME ) . '" target="_blank" rel="noopener">TMDB</a>',
							'<a href="' . esc_url( self::JUSTWATCH_HOME ) . '" target="_blank" rel="noopener">JustWatch</a>'
						)
					. '</p>'
				. '</div>'
				. '<script type="application/json" data-watch-providers-data>'
				. wp_json_encode( $payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES )
				. '</script>'
			. '</div>';
	}

	/**
	 * One logo, or the service's name when TMDB has no usable logo for it.
	 *
	 * @param  array{id: int, name: string, logo: string} $provider One provider.
	 * @return string
	 */
	private function logo( array $provider ): string {
		$url = Tmdb_Providers::logo_url( $provider['logo'] );

		if ( '' === $url ) {
			return '<span class="watch-providers__logo watch-providers__logo--text">' . esc_html( $provider['name'] ) . '</span>';
		}

		// The logos are not links (TMDB gives no deep links), so each names its service.
		return '<img class="watch-providers__logo" src="' . esc_url( $url ) . '" alt="' . esc_attr( $provider['name'] ) . '" width="46" height="46" loading="lazy" decoding="async">';
	}

	/**
	 * Visible label per Tmdb_Providers::GROUPS key. The script reads them from
	 * the payload, so the strings are translated once.
	 *
	 * @return array<string, string>
	 */
	private function group_labels(): array {
		return array(
			'stream' => __( 'Stream', 'lwtv-underscores' ),
			'free'   => __( 'Free', 'lwtv-underscores' ),
		);
	}

	/**
	 * Country codes to names in the site's language, sorted by name.
	 *
	 * Uses intl when the server has it, else the codes. The script renames them
	 * in the reader's language either way.
	 *
	 * @param  array<int, string> $codes Country codes.
	 * @return array<string, string> Code => name.
	 */
	private function country_names( array $codes ): array {
		$names = array();

		foreach ( $codes as $code ) {
			$name           = class_exists( '\Locale' ) ? \Locale::getDisplayRegion( '-' . $code, get_locale() ) : '';
			$names[ $code ] = ( '' !== $name && $name !== $code ) ? $name : $code;
		}

		asort( $names, SORT_NATURAL | SORT_FLAG_CASE );

		return $names;
	}

	/**
	 * A small "opens elsewhere" arrow, drawn in the text colour.
	 *
	 * @return string
	 */
	private static function external_icon(): string {
		return '<svg class="watch-providers__external" viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false"><path d="M9 2h5v5M14 2 7 9M12 9.5V14H2V4h4.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
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
		return '<a href="' . esc_url( $url ) . '" target="_blank" class="btn btn-solid theme-primary" rel="nofollow">' . esc_html( $name ) . '</a>';
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
