<?php
/**
 * Name: Search Rules REST
 * Description: The REST API behind the Search Rules admin page.
 *
 * Admins only (manage_options), with the standard REST nonce that
 * @wordpress/api-fetch sends. WordPress talks to Meilisearch; the browser
 * never sees a key. Errors carry Meilisearch's code and message only.
 *
 * - GET  lwtv/v1/meili-rules        the saved list, applied status (refreshed), warnings
 * - POST lwtv/v1/meili-rules        validate, save and apply { synonyms }; 400 on errors
 * - POST lwtv/v1/meili-rules/apply  re-apply the saved list
 * - GET  lwtv/v1/meili-rules/test   top 3 hits per set-up index for ?q=
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Meilisearch\Build\Index_State;
use LWTV\Meilisearch\Build\Search_Rules;

class Search_Rules_Rest {

	const APISPACE   = 'lwtv/v1';
	const ROUTE      = '/meili-rules';
	const CAPABILITY = 'manage_options';
	const TEST_LIMIT = 3;

	private Search_Rules_Store $store;
	private Indexes $indexes;
	private Client $client;

	/**
	 * @param Search_Rules_Store $store   Saved rules.
	 * @param Indexes            $indexes Every index.
	 * @param Client             $client  Meilisearch client.
	 */
	public function __construct( Search_Rules_Store $store, Indexes $indexes, Client $client ) {
		$this->store   = $store;
		$this->indexes = $indexes;
		$this->client  = $client;
	}

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function routes(): void {
		$allowed = array( $this, 'allowed' );

		register_rest_route(
			self::APISPACE,
			self::ROUTE,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'read' ),
					'permission_callback' => $allowed,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save' ),
					'permission_callback' => $allowed,
					'args'                => array(
						'synonyms' => array(
							'required' => true,
							'type'     => 'object',
						),
					),
				),
			)
		);

		register_rest_route(
			self::APISPACE,
			self::ROUTE . '/apply',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'apply' ),
				'permission_callback' => $allowed,
			)
		);

		register_rest_route(
			self::APISPACE,
			self::ROUTE . '/test',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'test' ),
				'permission_callback' => $allowed,
				'args'                => array(
					'q' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => fn( $value ) => is_string( $value ) && '' !== trim( sanitize_text_field( $value ) ),
					),
				),
			)
		);
	}

	/**
	 * Admins only.
	 *
	 * @return bool
	 */
	public function allowed(): bool {
		return current_user_can( self::CAPABILITY );
	}

	/**
	 * GET: the saved list and where it stands.
	 *
	 * @return \WP_REST_Response
	 */
	public function read(): \WP_REST_Response {
		$rules    = $this->store->get();
		$is_empty = empty( $rules['synonyms']['two_way'] ) && empty( $rules['synonyms']['one_way'] );

		return rest_ensure_response(
			array(
				'rules'      => array( 'synonyms' => $rules['synonyms'] ),
				'applied'    => self::applied( $this->store->refresh() ),
				'warnings'   => $this->store->warnings(),
				'stop_words' => $this->store->stop_words(),
				'indexes'    => $this->describe_indexes(),
				'starter'    => $is_empty ? $this->starter() : null,
			)
		);
	}

	/**
	 * POST: validate, save and apply.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save( \WP_REST_Request $request ) {
		$result = $this->store->save( $request->get_param( 'synonyms' ) );

		if ( ! $result['ok'] ) {
			return new \WP_Error(
				'lwtv_meili_rules_invalid',
				_n( 'The list has a problem, so nothing was saved.', 'The list has problems, so nothing was saved.', count( $result['errors'] ), 'lwtv-underscores' ),
				array(
					'status'   => 400,
					'errors'   => $result['errors'],
					'warnings' => $result['warnings'],
				)
			);
		}

		return rest_ensure_response(
			array(
				'rules'    => array( 'synonyms' => $this->store->get()['synonyms'] ),
				'applied'  => self::applied( $result['applied'] ),
				'errors'   => array(),
				'warnings' => $result['warnings'],
			)
		);
	}

	/**
	 * POST /apply: re-apply the saved list.
	 *
	 * @return \WP_REST_Response
	 */
	public function apply(): \WP_REST_Response {
		return rest_ensure_response( array( 'applied' => self::applied( $this->store->apply() ) ) );
	}

	/**
	 * GET /test: the top hits per set-up index, searched as the site does.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function test( \WP_REST_Request $request ) {
		$q       = (string) $request->get_param( 'q' );
		$queries = array();
		$targets = array();

		foreach ( $this->indexes->all() as $index ) {
			if ( Index_State::EXISTS !== $index->state() ) {
				continue;
			}
			$targets[] = $index;
			$queries[] = array(
				'indexUid'             => $index->uid(),
				'q'                    => $q,
				'limit'                => self::TEST_LIMIT,
				'matchingStrategy'     => 'frequency',
				'attributesToRetrieve' => array( 'id', 'name', 'title' ),
			);
		}

		if ( empty( $queries ) ) {
			return rest_ensure_response( array( 'results' => array() ) );
		}

		$response = $this->client->multi_search( array( 'queries' => $queries ) );
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'lwtv_meili_rules_test', $response->get_error_message(), array( 'status' => 502 ) );
		}

		$results = array();
		foreach ( $targets as $i => $index ) {
			$hits = array();
			foreach ( (array) ( $response['results'][ $i ]['hits'] ?? array() ) as $hit ) {
				if ( ! is_array( $hit ) ) {
					continue;
				}
				$hits[] = array(
					'id'    => (int) ( $hit['id'] ?? 0 ),
					'title' => (string) ( $hit['name'] ?? $hit['title'] ?? '' ),
				);
			}
			$results[] = array(
				'type'  => $index->type(),
				'label' => $index->label(),
				'hits'  => $hits,
			);
		}

		return rest_ensure_response( array( 'results' => $results ) );
	}

	/**
	 * Each index for the app.
	 *
	 * @return array<int, array{type: string, uid: string, label: string, state: string}>
	 */
	private function describe_indexes(): array {
		$out = array();
		foreach ( $this->store->targets() as $index ) {
			$out[] = array(
				'type'  => $index->type(),
				'uid'   => $index->uid(),
				'label' => $index->label(),
				'state' => $index->state(),
			);
		}

		return $out;
	}

	/**
	 * The bundled starter list, normalized.
	 *
	 * @return array|null
	 */
	private function starter(): ?array {
		$json = file_get_contents( __DIR__ . '/config/synonyms-starter.json' );
		if ( false === $json ) {
			return null;
		}

		return Search_Rules::normalize( json_decode( $json, true ), array() )['synonyms'];
	}

	/**
	 * The applied value with its index map kept a JSON object when empty.
	 *
	 * @param array $applied Applied value.
	 * @return array
	 */
	private static function applied( array $applied ): array {
		$applied['indexes'] = (object) ( $applied['indexes'] ?? array() );

		return $applied;
	}
}
