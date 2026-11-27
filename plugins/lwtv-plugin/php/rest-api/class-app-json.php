<?php
/**
 * Description: REST-API: App catalogue and availability
 *
 * The WordPress side of the lwtv/v2/app contract:
 *   - /lwtv/v2/app/catalogue    Every published show, with term dictionaries.
 *   - /lwtv/v2/app/availability Where each show streams, by region.
 *
 * Gathers the raw values, hands them to the pure builders in build/, caches
 * each response for a day and drops the cache when the data under it changes.
 *
 * @package LWTV
 */

namespace LWTV\Rest_API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\_Helpers\Tmdb_Providers;
use LWTV\CPTs\Shows as CPT_Shows;
use LWTV\CPTs\Shows\Airdates;
use LWTV\Rest_API\Build\App_Availability;
use LWTV\Rest_API\Build\App_Catalogue;
use LWTV\Rest_API\Build\App_Envelope;
use LWTV\Rest_API\Build\App_Show_Record;
use LWTV\Rest_API\Build\Service_Map;
use LWTV\Schedulers\Watch_Providers_Task;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_Term;

class App_JSON {

	/**
	 * REST namespace.
	 */
	const ROUTE_NAMESPACE = 'lwtv/v2';

	/**
	 * Transient holding the wrapped catalogue response.
	 */
	const CACHE_CATALOGUE = 'lwtv_app_catalogue_v1';

	/**
	 * Route, under ROUTE_NAMESPACE => the method that answers it.
	 */
	const ROUTES = array(
		'/app/catalogue'    => 'catalogue',
		'/app/availability' => 'availability',
	);

	/**
	 * Transient holding the wrapped availability response.
	 */
	const CACHE_AVAILABILITY = 'lwtv_app_availability_v1';

	/**
	 * Show meta keys the catalogue reads, including the airdate keys Airdates::get() reads.
	 */
	const CATALOGUE_META = array(
		'lezshows_the_score',
		'lezshows_on_air',
		'lezshows_triggerwarning',
		'lezshows_similar_shows',
		'lezshows_tmdb_id',
		Airdates::META_START,
		Airdates::META_FINISH,
		Airdates::META_LEGACY,
	);

	/**
	 * Taxonomies outside App_Show_Record::TAXONOMIES that the catalogue reads.
	 */
	const EXTRA_TAXONOMIES = array( 'lez_triggers', 'lez_stars' );

	/**
	 * Taxonomy whose terms feed availability.
	 */
	const AVAILABILITY_TAXONOMY = 'lez_watch_urls';

	/**
	 * Browser and proxy cache lifetime, in seconds.
	 */
	const MAX_AGE = 3600;

	/**
	 * Provider meta rows read per query on a cold availability build.
	 */
	const META_PAGE = 250;

	/**
	 * Shows primed and read per chunk on a cold catalogue build.
	 */
	const CATALOGUE_CHUNK = 200;

	/**
	 * Transients already dropped this request, as transient => true.
	 *
	 * @var array<string, bool>
	 */
	private static $flushed = array();

	/**
	 * Encoded JSON waiting for rest_pre_serve_request to echo it, or null.
	 *
	 * Set only when the request being served over HTTP is one of ours.
	 *
	 * @var string|null
	 */
	private $pending = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
		add_filter( 'rest_pre_serve_request', array( $this, 'serve_cached' ), 10, 3 );

		add_action( 'save_post_' . CPT_Shows::SLUG, array( $this, 'flush_all' ) );
		add_action( 'trashed_post', array( $this, 'flush_if_show' ) );
		add_action( 'untrashed_post', array( $this, 'flush_if_show' ) );
		add_action( 'deleted_post', array( $this, 'flush_if_deleted_show' ), 10, 2 );

		add_action( 'edited_term', array( $this, 'flush_for_term' ), 10, 3 );
		add_action( 'created_term', array( $this, 'flush_for_term' ), 10, 3 );
		add_action( 'delete_term', array( $this, 'flush_for_term' ), 10, 3 );

		add_action( 'added_post_meta', array( $this, 'flush_for_meta' ), 10, 3 );
		add_action( 'updated_post_meta', array( $this, 'flush_for_meta' ), 10, 3 );
		add_action( 'deleted_post_meta', array( $this, 'flush_for_meta' ), 10, 3 );
	}

	/**
	 * Register the routes.
	 */
	public function rest_api_init(): void {
		$route = array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'args'                => array(
				'schema' => array(
					'type'              => 'integer',
					'required'          => false,
					'validate_callback' => array( $this, 'validate_schema' ),
				),
			),
		);

		foreach ( self::ROUTES as $path => $method ) {
			register_rest_route(
				self::ROUTE_NAMESPACE,
				$path,
				array_merge( $route, array( 'callback' => array( $this, $method ) ) )
			);
		}
	}

	/**
	 * Only schema 1 exists. Anything else fails, and core answers 400 rest_invalid_param.
	 *
	 * @param mixed $value Raw parameter value.
	 *
	 * @return bool
	 */
	public function validate_schema( $value ): bool {
		return is_scalar( $value ) && '1' === (string) $value;
	}

	/**
	 * GET /lwtv/v2/app/catalogue
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response
	 */
	public function catalogue( WP_REST_Request $request ): WP_REST_Response {
		$json = lwtv_plugin()->get_transient( self::CACHE_CATALOGUE );

		if ( ! is_string( $json ) || '' === $json ) {
			$body = App_Envelope::wrap( App_Catalogue::build( $this->catalogue_raws() ), time() );
			// No flags: the same bytes WP_REST_Server::serve_request() would send.
			$json = (string) wp_json_encode( $body );
			unset( $body );
			lwtv_plugin()->set_transient( self::CACHE_CATALOGUE, $json, DAY_IN_SECONDS );
		}

		return $this->respond( $json, $request );
	}

	/**
	 * GET /lwtv/v2/app/availability
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response
	 */
	public function availability( WP_REST_Request $request ): WP_REST_Response {
		$json = lwtv_plugin()->get_transient( self::CACHE_AVAILABILITY );

		if ( ! is_string( $json ) || '' === $json ) {
			$stored = $this->stored_providers();
			$body   = App_Availability::build( $stored, $this->service_overrides(), $this->region_names( $stored ) );
			unset( $stored );
			$body = App_Envelope::wrap( $body, time() );
			// No flags: the same bytes WP_REST_Server::serve_request() would send.
			$json = (string) wp_json_encode( $body );
			unset( $body );
			lwtv_plugin()->set_transient( self::CACHE_AVAILABILITY, $json, DAY_IN_SECONDS );
		}

		return $this->respond( $json, $request );
	}

	/**
	 * Provider ids an editor has assigned to a service, from the watch URL terms.
	 *
	 * One query, then plain term meta reads. When two terms claim the same
	 * provider id, the lower term id wins.
	 *
	 * @return array<int, array{slug: string, name: string}> Provider id => service.
	 */
	public function service_overrides(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => self::AVAILABILITY_TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'term_id',
				'order'      => 'ASC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- cold build only, a handful of terms.
				'meta_query' => array(
					array(
						'key'     => 'lezwatchurls_tmdb_providers',
						'value'   => '',
						'compare' => '!=',
					),
				),
			)
		);

		$overrides = array();

		if ( ! is_array( $terms ) ) {
			return $overrides;
		}

		foreach ( $terms as $term ) {
			if ( ! ( $term instanceof WP_Term ) ) {
				continue;
			}

			$name = App_Envelope::plain_text( $term->name );
			$slug = Service_Map::slug( $name );

			if ( '' === $slug ) {
				$slug = 'service-term-' . $term->term_id;
			}

			$raw = get_term_meta( $term->term_id, 'lezwatchurls_tmdb_providers', true );

			foreach ( Service_Map::parse_ids( is_string( $raw ) ? $raw : '' ) as $id ) {
				if ( ! isset( $overrides[ $id ] ) ) {
					$overrides[ $id ] = array(
						'slug' => $slug,
						'name' => $name,
					);
				}
			}
		}

		return $overrides;
	}

	/**
	 * Echo our cached JSON as-is, instead of letting the server re-encode a decoded copy.
	 *
	 * The decoded availability body costs about 90 MB of PHP arrays, so on the
	 * HTTP path the string is never decoded at all.
	 *
	 * @param bool              $served  Whether the request has already been served.
	 * @param \WP_HTTP_Response $result  The response.
	 * @param WP_REST_Request   $request The request.
	 *
	 * @return bool
	 */
	public function serve_cached( $served, $result, $request ): bool {
		if ( $served || null === $this->pending || ! $result instanceof WP_REST_Response ) {
			return (bool) $served;
		}

		$ours = array_map(
			static fn( string $path ): string => '/' . self::ROUTE_NAMESPACE . $path,
			array_keys( self::ROUTES )
		);
		if ( ! in_array( $result->get_matched_route(), $ours, true ) ) {
			return false;
		}

		$json          = $this->pending;
		$this->pending = null;

		if ( ! headers_sent() ) {
			header( 'Content-Type: application/json; charset=UTF-8' );
			header( 'Cache-Control: public, max-age=' . self::MAX_AGE );
		}

		if ( 'HEAD' !== $request->get_method() ) {
			echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON built by wp_json_encode().
		}

		return true;
	}

	/**
	 * Drop the cached catalogue.
	 */
	public function flush_catalogue(): void {
		$this->flush( self::CACHE_CATALOGUE );
	}

	/**
	 * Drop the cached availability.
	 */
	public function flush_availability(): void {
		$this->flush( self::CACHE_AVAILABILITY );
	}

	/**
	 * Drop both caches.
	 */
	public function flush_all(): void {
		$this->flush_catalogue();
		$this->flush_availability();
	}

	/**
	 * Trashed or untrashed: drop both caches when the post is a show.
	 *
	 * @param int $post_id Post ID.
	 */
	public function flush_if_show( $post_id ): void {
		if ( CPT_Shows::SLUG === get_post_type( (int) $post_id ) ) {
			$this->flush_all();
		}
	}

	/**
	 * Deleted: the post is gone from the cache by now, so read the type from the passed post.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    The deleted post.
	 */
	public function flush_if_deleted_show( $post_id, $post = null ): void {
		if ( $post instanceof WP_Post && CPT_Shows::SLUG === $post->post_type ) {
			$this->flush_all();
		}
	}

	/**
	 * A term was created, edited or deleted.
	 *
	 * @param mixed  $term_id  Term ID (or term, for delete_term).
	 * @param mixed  $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 */
	public function flush_for_term( $term_id, $tt_id, $taxonomy ): void {
		if ( in_array( $taxonomy, $this->catalogue_taxonomies(), true ) ) {
			$this->flush_catalogue();
		} elseif ( self::AVAILABILITY_TAXONOMY === $taxonomy ) {
			$this->flush_availability();
		}
	}

	/**
	 * Show meta was added, updated or deleted.
	 *
	 * Score recalculation and the watch-provider task write meta without
	 * touching the post, so these are the hooks that keep data_version honest.
	 *
	 * @param mixed  $meta_id   Meta ID (or IDs, for deleted_post_meta).
	 * @param int    $object_id Post ID.
	 * @param string $meta_key  Meta key.
	 */
	public function flush_for_meta( $meta_id, $object_id, $meta_key ): void {
		$catalogue    = in_array( $meta_key, self::CATALOGUE_META, true );
		$availability = Watch_Providers_Task::META_DATA === $meta_key;

		if ( ( ! $catalogue && ! $availability ) || CPT_Shows::SLUG !== get_post_type( (int) $object_id ) ) {
			return;
		}

		if ( $catalogue ) {
			$this->flush_catalogue();
		} else {
			$this->flush_availability();
		}
	}

	/**
	 * Delete a transient now, and again when the request ends.
	 *
	 * Batches (score recalculation, the watch-provider task) write meta for
	 * minutes. Another process can rebuild between the first write and the
	 * last, caching a half-updated payload, so the cache is marked dirty and
	 * deleted once more on shutdown. Each happens once per request, however
	 * many writes there are.
	 *
	 * @param string $transient Transient name.
	 */
	private function flush( string $transient ): void {
		if ( isset( self::$flushed[ $transient ] ) ) {
			return;
		}

		self::$flushed[ $transient ] = true;
		lwtv_plugin()->delete_transient( $transient );

		add_action(
			'shutdown',
			static function () use ( $transient ): void {
				lwtv_plugin()->delete_transient( $transient );
			}
		);
	}

	/**
	 * The response for one of our routes.
	 *
	 * Served over HTTP, the string is handed to serve_cached() and the
	 * response carries no data. Dispatched internally (rest_do_request(),
	 * which never reaches rest_pre_serve_request), the string is decoded so
	 * the caller gets ordinary response data.
	 *
	 * @param string          $json    Encoded JSON.
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response
	 */
	private function respond( string $json, WP_REST_Request $request ): WP_REST_Response {
		if ( $this->is_served_request( $request ) ) {
			$this->pending = $json;
			$response      = new WP_REST_Response( null );
		} else {
			// Objects, not assoc arrays, so empty dictionaries stay {}.
			$response = new WP_REST_Response( json_decode( $json ) );
		}

		$response->header( 'Cache-Control', 'public, max-age=' . self::MAX_AGE );

		return $response;
	}

	/**
	 * Is this the request WordPress is serving over HTTP right now?
	 *
	 * rest_api_loaded() serves the route in the rest_route query var; any
	 * other dispatch of ours in the same request is internal.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return bool
	 */
	private function is_served_request( WP_REST_Request $request ): bool {
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST || null !== $this->pending ) {
			return false;
		}

		$route = $GLOBALS['wp']->query_vars['rest_route'] ?? '';

		return is_string( $route ) && untrailingslashit( $route ) === $request->get_route();
	}

	/**
	 * The seven taxonomies the catalogue reads.
	 *
	 * @return string[]
	 */
	private function catalogue_taxonomies(): array {
		return array_merge( array_values( App_Show_Record::TAXONOMIES ), self::EXTRA_TAXONOMIES );
	}

	/**
	 * Published show ids, ascending. No caches are primed here.
	 *
	 * @return int[]
	 */
	private function show_ids(): array {
		$ids = get_posts(
			array(
				'post_type'              => CPT_Shows::SLUG,
				'post_status'            => 'publish',
				'has_password'           => false,
				'posts_per_page'         => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return array_map( 'intval', $ids );
	}

	/**
	 * Raw show values, in the shape App_Show_Record::build() takes.
	 *
	 * Read a chunk at a time: each chunk's posts, terms and meta are primed
	 * together, and its meta is dropped from the cache afterwards, because
	 * every show's meta includes the large watch-provider JSON.
	 *
	 * @return array<int, array>
	 */
	private function catalogue_raws(): array {
		$raws = array();

		foreach ( array_chunk( $this->show_ids(), self::CATALOGUE_CHUNK ) as $chunk ) {
			_prime_post_caches( $chunk, true, false );
			update_meta_cache( 'post', $chunk );

			foreach ( $chunk as $id ) {
				$raws[] = $this->catalogue_raw( $id );
				wp_cache_delete( $id, 'post_meta' );
			}
		}

		return $raws;
	}

	/**
	 * One show's raw values.
	 *
	 * @param int $id Show ID.
	 *
	 * @return array
	 */
	private function catalogue_raw( int $id ): array {
		$terms = array();
		foreach ( App_Show_Record::TAXONOMIES as $field => $taxonomy ) {
			$terms[ $field ] = $this->term_map( $id, $taxonomy );
		}

		$airdates = Airdates::get( $id );

		return array(
			'id'           => $id,
			'slug'         => (string) get_post_field( 'post_name', $id ),
			'title'        => get_the_title( $id ),
			'url'          => (string) get_permalink( $id ),
			'start'        => $airdates['start'],
			'finish'       => $airdates['finish'],
			'on_air'       => get_post_meta( $id, 'lezshows_on_air', true ),
			'terms'        => $terms,
			'trigger_term' => $this->first_slug( $id, 'lez_triggers' ),
			'trigger_meta' => get_post_meta( $id, 'lezshows_triggerwarning', true ),
			'star'         => $this->first_slug( $id, 'lez_stars' ),
			'score'        => get_post_meta( $id, 'lezshows_the_score', true ),
			'tmdb_id'      => get_post_meta( $id, 'lezshows_tmdb_id', true ),
			'similar'      => get_post_meta( $id, 'lezshows_similar_shows', true ),
		);
	}

	/**
	 * A show's terms in one taxonomy, keyed by term id.
	 *
	 * @param int    $id       Show ID.
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return array<int, array{slug: string, name: string}>
	 */
	private function term_map( int $id, string $taxonomy ): array {
		$terms = get_the_terms( $id, $taxonomy );
		$map   = array();

		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$map[ (int) $term->term_id ] = array(
					'slug' => (string) $term->slug,
					'name' => (string) $term->name,
				);
			}
		}

		return $map;
	}

	/**
	 * The slug of a show's first term in a taxonomy, or ''.
	 *
	 * @param int    $id       Show ID.
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return string
	 */
	private function first_slug( int $id, string $taxonomy ): string {
		$terms = get_the_terms( $id, $taxonomy );

		$first = is_array( $terms ) ? reset( $terms ) : false;

		return $first instanceof \WP_Term ? (string) $first->slug : '';
	}

	/**
	 * Stored watch providers for every published show that has any.
	 *
	 * The stored JSON runs to tens of megabytes, so it is read straight from
	 * postmeta a page at a time rather than through get_posts(), which would
	 * load every show's post, terms and full meta into the object cache. Each
	 * show is cut down as it is decoded (see compact_providers()).
	 *
	 * @return array<int, array>
	 */
	private function stored_providers(): array {
		global $wpdb;

		$stored  = array();
		$seen    = array();
		$last_id = 0;

		do {
			// Read once per cold build, then cached as the whole response.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
					WHERE pm.meta_key = %s AND p.post_type = %s AND p.post_status = 'publish' AND p.post_password = '' AND pm.post_id > %d
					ORDER BY pm.post_id ASC, pm.meta_id ASC
					LIMIT %d",
					Watch_Providers_Task::META_DATA,
					CPT_Shows::SLUG,
					$last_id,
					self::META_PAGE
				)
			);

			$rows = is_array( $rows ) ? $rows : array();
			$full = count( $rows ) === self::META_PAGE;

			foreach ( $rows as $row ) {
				$id      = (int) $row->post_id;
				$last_id = $id;

				// get_post_meta( ..., true ) reads the first row, so do the same.
				if ( isset( $stored[ $id ] ) ) {
					continue;
				}

				$data = json_decode( (string) $row->meta_value, true );
				if ( is_array( $data ) ) {
					$stored[ $id ] = $this->compact_providers( $data, $seen );
				}
			}
		} while ( $full );

		return $stored;
	}

	/**
	 * One show's decoded providers, cut down to what the builder reads.
	 *
	 * Only non-empty offer lists are kept (the builder leaves empty ones and
	 * empty regions out anyway), and every entry for a provider id becomes
	 * the same shared array: the first one seen, in the order the builder
	 * walks the data. The builder reads only the id from later entries, so
	 * its output is unchanged.
	 *
	 * @param array                    $countries Region code => stored block.
	 * @param array<int, array<mixed>> $seen      Provider id => first entry seen, shared across shows.
	 *
	 * @return array<int|string, array<string, array<int, array<mixed>>>>
	 */
	private function compact_providers( array $countries, array &$seen ): array {
		$compact = array();

		foreach ( $countries as $code => $block ) {
			if ( 1 !== preg_match( '/^[A-Z]{2}$/', (string) $code ) || ! is_array( $block ) ) {
				continue;
			}

			$lists = array();
			foreach ( Tmdb_Providers::TYPES as $type ) {
				if ( ! is_array( $block[ $type ] ?? null ) ) {
					continue;
				}

				$list = array();
				foreach ( $block[ $type ] as $provider ) {
					$provider_id = is_array( $provider ) ? (int) ( $provider['id'] ?? 0 ) : 0;
					if ( $provider_id > 0 ) {
						$seen[ $provider_id ] = $seen[ $provider_id ] ?? $provider;
						$list[]               = $seen[ $provider_id ];
					}
				}

				if ( ! empty( $list ) ) {
					$lists[ $type ] = $list;
				}
			}

			if ( ! empty( $lists ) ) {
				$compact[ $code ] = $lists;
			}
		}

		return $compact;
	}

	/**
	 * English names for the region codes in the stored data.
	 *
	 * @param array<int, array> $stored Show id => stored providers.
	 *
	 * @return array<string, string> Region code => name; codes without one are left out.
	 */
	private function region_names( array $stored ): array {
		$names = array();

		if ( ! class_exists( '\Locale' ) ) {
			return $names;
		}

		foreach ( $stored as $countries ) {
			foreach ( array_keys( $countries ) as $code ) {
				$code = (string) $code;
				if ( ! isset( $names[ $code ] ) ) {
					$names[ $code ] = (string) \Locale::getDisplayRegion( '-' . $code, 'en' );
				}
			}
		}

		// The builder falls back to the code only for a missing name, not an empty one.
		return array_filter( $names, static fn( string $name ): bool => '' !== $name );
	}
}
