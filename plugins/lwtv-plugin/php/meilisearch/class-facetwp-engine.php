<?php
/**
 * Name: FacetWP Engine
 * Description: Meilisearch engines for FacetWP's keyword (search) facets.
 *
 * Adds "Meilisearch – Shows / Characters / Actors" to the search facet's
 * engine dropdown and answers facets set to one of them. Nothing changes
 * until a facet is switched in FacetWP's settings.
 *
 * The LWTV_MEILI_MODAL switch applies: a request it doesn't cover gets the
 * same-type SearchWP engine, exactly as before. So does any Meilisearch
 * failure, while SearchWP is active; without SearchWP, FacetWP's own
 * WordPress search runs. Failures are logged, never shown.
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Meilisearch\Build\Facet_Search;

class FacetWP_Engine {

	/**
	 * Engine key prefix in FacetWP's settings.
	 */
	const PREFIX = 'meili_';

	/**
	 * Index types offered as engines. Posts and pages have no archive facet.
	 */
	const TYPES = array( 'shows', 'characters', 'actors' );

	private Config $config;
	private Search_Modal $modal;

	/**
	 * @param Config       $config Connection settings; must be configured.
	 * @param Search_Modal $modal  The switch decision, shared with the modal.
	 */
	public function __construct( Config $config, Search_Modal $modal ) {
		$this->config = $config;
		$this->modal  = $modal;
	}

	/**
	 * Hook in. After FacetWP's own SearchWP integration (10), which leaves
	 * non-swp_ engines alone.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'facetwp_facet_search_engines', array( $this, 'engines' ) );
		add_filter( 'facetwp_facet_filter_posts', array( $this, 'filter_posts' ), 20, 2 );
	}

	/**
	 * Add the Meilisearch engines to the dropdown.
	 *
	 * @param array $engines Key => label.
	 * @return array
	 */
	public function engines( $engines ) {
		$engines = is_array( $engines ) ? $engines : array();
		$labels  = array(
			'shows'      => __( 'Meilisearch – Shows', 'lwtv-underscores' ),
			'characters' => __( 'Meilisearch – Characters', 'lwtv-underscores' ),
			'actors'     => __( 'Meilisearch – Actors', 'lwtv-underscores' ),
		);

		foreach ( self::TYPES as $type ) {
			$engines[ self::PREFIX . $type ] = $labels[ $type ];
		}

		return $engines;
	}

	/**
	 * Answer a search facet set to a Meilisearch engine.
	 *
	 * @param mixed $matches FacetWP's value so far: false means "not handled".
	 * @param array $params  Facet, selected values, etc.
	 * @return mixed Post IDs, 'continue' for an empty keyword, or $matches untouched.
	 */
	public function filter_posts( $matches, $params ) {
		$facet = is_array( $params['facet'] ?? null ) ? $params['facet'] : array();
		$type  = $this->type( $facet );
		if ( 'search' !== ( $facet['type'] ?? '' ) || null === $type ) {
			return $matches;
		}

		$selected = $params['selected_values'] ?? '';
		$q        = trim( (string) ( is_array( $selected ) ? ( $selected[0] ?? '' ) : $selected ) );
		if ( '' === $q ) {
			return 'continue';
		}

		if ( 'no' === ( $facet['enable_relevance'] ?? 'yes' ) ) {
			add_filter( 'facetwp_use_search_relevancy', '__return_false' );
		}

		$allowed = function_exists( 'FWP' ) && is_array( FWP()->unfiltered_post_ids ?? null ) ? FWP()->unfiltered_post_ids : array();

		if ( ! $this->modal->enabled() ) {
			return $this->searchwp( $q, $type, $allowed, $matches );
		}

		$response = ( new Client( $this->config ) )->multi_search( Facet_Search::body( $q, $this->config->index( $type ) ), $this->modal->timeout_seconds() );
		if ( is_wp_error( $response ) ) {
			return $this->fail( $response->get_error_message(), $q, $type, $allowed, $matches );
		}

		try {
			$ids = Facet_Search::ids( $response );
		} catch ( \UnexpectedValueException $e ) {
			return $this->fail( $e->getMessage(), $q, $type, $allowed, $matches );
		}

		return Facet_Search::allowed( $ids, $allowed );
	}

	/**
	 * The index type a facet's engine names, or null if it isn't ours.
	 *
	 * @param array $facet Facet settings.
	 * @return string|null
	 */
	private function type( array $facet ): ?string {
		$engine = (string) ( $facet['search_engine'] ?? '' );
		if ( 0 !== strpos( $engine, self::PREFIX ) ) {
			return null;
		}

		$type = substr( $engine, strlen( self::PREFIX ) );

		return in_array( $type, self::TYPES, true ) ? $type : null;
	}

	/**
	 * The same-type SearchWP engine, as FacetWP's SearchWP integration runs
	 * it. Without SearchWP, FacetWP's default WordPress search.
	 *
	 * @param string $q       Keyword.
	 * @param string $type    Index type; SearchWP engines share the name.
	 * @param array  $allowed The archive's post IDs.
	 * @param mixed  $matches FacetWP's value so far.
	 * @return mixed
	 */
	private function searchwp( string $q, string $type, array $allowed, $matches ) {
		if ( ! class_exists( '\SWP_Query' ) ) {
			return $matches;
		}

		if ( empty( $allowed ) ) {
			return array();
		}

		$query = new \SWP_Query(
			array(
				's'              => $q,
				'engine'         => $type,
				'post__in'       => $allowed,
				'posts_per_page' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- IDs only; FacetWP's own SearchWP integration uses 200.
				'fields'         => 'ids',
				'facetwp'        => true,
			)
		);

		return is_array( $query->posts ) ? $query->posts : array();
	}

	/**
	 * Log a failure and hand the keyword to SearchWP (or WordPress).
	 *
	 * @param string $message Log-safe message (never contains a key).
	 * @param string $q       Keyword.
	 * @param string $type    Index type.
	 * @param array  $allowed The archive's post IDs.
	 * @param mixed  $matches FacetWP's value so far.
	 * @return mixed
	 */
	private function fail( string $message, string $q, string $type, array $allowed, $matches ) {
		lwtv_plugin()->debug_log( 'meilisearch', sprintf( 'FacetWP %s search fell back: %s', $type, $message ) );

		return $this->searchwp( $q, $type, $allowed, $matches );
	}
}
