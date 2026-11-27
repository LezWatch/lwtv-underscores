<?php
/**
 * Name: Client
 * Description: The only place that talks HTTP to Meilisearch.
 *
 * Every method returns the decoded response body (an array) or a WP_Error,
 * and never throws. Error messages come from describe_error() or the
 * transport, so they never contain request headers or keys.
 *
 * Request shapes checked against the Meilisearch docs for v1.57:
 * https://www.meilisearch.com/docs/reference/api/
 *
 * @package LWTV
 */

namespace LWTV\Meilisearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Client {

	/**
	 * Timeout for single-document and status calls, in seconds.
	 */
	const SHORT_TIMEOUT = 3;

	/**
	 * Task statuses that mean the task is finished.
	 */
	const FINISHED = array( 'succeeded', 'failed', 'canceled' );

	/**
	 * Failed status polls in a row that wait_for_task() tolerates.
	 */
	const POLL_RETRIES = 3;

	private Config $config;

	/**
	 * @param Config $config Connection settings.
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * GET /health — {"status":"available"}.
	 *
	 * @return array|\WP_Error
	 */
	public function health() {
		// /health needs no key, so none is sent: a mistyped URL never receives the indexing key here.
		return $this->request( 'GET', '/health', null, self::SHORT_TIMEOUT, '' );
	}

	/**
	 * POST /indexes — body uid, primaryKey. Returns a task.
	 *
	 * @param string $uid         Index uid.
	 * @param string $primary_key Primary key attribute.
	 * @return array|\WP_Error
	 */
	public function create_index( string $uid, string $primary_key ) {
		return $this->request(
			'POST',
			'/indexes',
			array(
				'uid'        => $uid,
				'primaryKey' => $primary_key,
			)
		);
	}

	/**
	 * DELETE /indexes/{uid}. Returns a task; 404 index_not_found when missing.
	 *
	 * @param string $uid Index uid.
	 * @return array|\WP_Error
	 */
	public function delete_index( string $uid ) {
		return $this->request( 'DELETE', '/indexes/' . rawurlencode( $uid ) );
	}

	/**
	 * GET /indexes/{uid}/stats — numberOfDocuments, isIndexing, ...
	 *
	 * @param string $uid Index uid.
	 * @return array|\WP_Error
	 */
	public function index_stats( string $uid ) {
		return $this->request( 'GET', '/indexes/' . rawurlencode( $uid ) . '/stats' );
	}

	/**
	 * GET /indexes/{uid}/settings.
	 *
	 * @param string $uid Index uid.
	 * @return array|\WP_Error
	 */
	public function get_settings( string $uid ) {
		return $this->request( 'GET', '/indexes/' . rawurlencode( $uid ) . '/settings' );
	}

	/**
	 * PATCH /indexes/{uid}/settings. Returns a task.
	 *
	 * Meilisearch merges a partial payload, so sending only `synonyms` leaves
	 * the other settings alone. Careful: on a missing index this creates it.
	 *
	 * @param string $uid      Index uid.
	 * @param array  $settings Settings payload.
	 * @param int    $timeout  Seconds; web requests pass SHORT_TIMEOUT.
	 * @return array|\WP_Error
	 */
	public function update_settings( string $uid, array $settings, int $timeout = 30 ) {
		return $this->request( 'PATCH', '/indexes/' . rawurlencode( $uid ) . '/settings', $settings, $timeout );
	}

	/**
	 * POST /indexes/{uid}/documents — add or replace. Returns a task.
	 *
	 * @param string $uid       Index uid.
	 * @param array  $documents Documents.
	 * @param int    $timeout   Seconds.
	 * @return array|\WP_Error
	 */
	public function add_documents( string $uid, array $documents, int $timeout = 30 ) {
		return $this->request( 'POST', '/indexes/' . rawurlencode( $uid ) . '/documents', array_values( $documents ), $timeout );
	}

	/**
	 * DELETE /indexes/{uid}/documents/{id}. Returns a task.
	 *
	 * @param string $uid Index uid.
	 * @param int    $id  Document ID.
	 * @return array|\WP_Error
	 */
	public function delete_document( string $uid, int $id ) {
		return $this->request( 'DELETE', '/indexes/' . rawurlencode( $uid ) . '/documents/' . $id );
	}

	/**
	 * POST /indexes/{uid}/documents/delete — delete every document matching
	 * a filter. Returns a task whose details.deletedDocuments is the count.
	 *
	 * @param string $uid    Index uid.
	 * @param string $filter Filter expression on filterable attributes.
	 * @return array|\WP_Error
	 */
	public function delete_documents_by_filter( string $uid, string $filter ) {
		return $this->request( 'POST', '/indexes/' . rawurlencode( $uid ) . '/documents/delete', array( 'filter' => $filter ), 60 );
	}

	/**
	 * POST /multi-search with the search key — results[], one per query.
	 *
	 * The indexing key has no `search` action, so searching uses the
	 * search-only key. A non-2xx response, an undecodable body or a
	 * transport error (including the timeout) is a WP_Error.
	 *
	 * @param array $body    Body: array( 'queries' => array( ... ) ).
	 * @param float $timeout Seconds; fractions allowed (the Search Settings timeout).
	 * @return array|\WP_Error
	 */
	public function multi_search( array $body, float $timeout = 2 ) {
		$response = $this->request( 'POST', '/multi-search', $body, $timeout, $this->config->search_key() );

		if ( ! is_wp_error( $response ) && ! isset( $response['results'] ) ) {
			return new \WP_Error( 'lwtv_meili_bad_response', 'Unexpected multi-search response.' );
		}

		return $response;
	}

	/**
	 * GET /tasks/{taskUid}.
	 *
	 * @param int $task_uid Task uid.
	 * @return array|\WP_Error
	 */
	public function get_task( int $task_uid ) {
		return $this->request( 'GET', '/tasks/' . $task_uid );
	}

	/**
	 * GET /tasks with filters (uids, statuses, indexUids, limit…).
	 *
	 * @param array<string, string|int> $query Query parameters; lists comma-separated.
	 * @return array|\WP_Error
	 */
	public function list_tasks( array $query ) {
		return $this->request( 'GET', '/tasks?' . http_build_query( $query ) );
	}

	/**
	 * Poll a task until it finishes. CLI only: this blocks.
	 *
	 * A failed or canceled task comes back as a WP_Error. A status poll that
	 * fails (a network blip) is retried up to POLL_RETRIES times in a row.
	 *
	 * @param int $task_uid    Task uid.
	 * @param int $max_seconds Give up after this long.
	 * @return array|\WP_Error The finished task.
	 */
	public function wait_for_task( int $task_uid, int $max_seconds = 120 ) {
		$deadline = microtime( true ) + $max_seconds;
		$failures = 0;

		do {
			$task = $this->get_task( $task_uid );
			if ( is_wp_error( $task ) ) {
				if ( ++$failures >= self::POLL_RETRIES ) {
					return $task;
				}
				sleep( 1 );
				continue;
			}
			$failures = 0;

			$status = (string) ( $task['status'] ?? '' );
			if ( 'succeeded' === $status ) {
				return $task;
			}
			if ( in_array( $status, self::FINISHED, true ) ) {
				$error = is_array( $task['error'] ?? null ) ? $task['error'] : array();
				return new \WP_Error(
					'lwtv_meili_task_' . $status,
					sprintf( 'Task %d %s: %s — %s', $task_uid, $status, (string) ( $error['code'] ?? '' ), (string) ( $error['message'] ?? '' ) )
				);
			}

			usleep( 250000 );
		} while ( microtime( true ) < $deadline );

		return new \WP_Error( 'lwtv_meili_task_timeout', sprintf( 'Task %d did not finish within %d seconds.', $task_uid, $max_seconds ) );
	}

	/**
	 * A log-safe description of an error response.
	 *
	 * Uses only the status and Meilisearch's error code and message.
	 *
	 * @param int    $status HTTP status.
	 * @param string $body   Response body.
	 * @return string
	 */
	public static function describe_error( int $status, string $body ): string {
		$decoded = json_decode( $body, true );

		if ( is_array( $decoded ) && isset( $decoded['code'], $decoded['message'] ) ) {
			return sprintf( 'HTTP %d: %s — %s', $status, (string) $decoded['code'], (string) $decoded['message'] );
		}

		return sprintf( 'HTTP %d', $status );
	}

	/**
	 * Send one request, with the indexing key unless told otherwise ('' sends no key).
	 *
	 * @param string      $method  HTTP method.
	 * @param string      $path    Path from the server root.
	 * @param array|null  $body    JSON body.
	 * @param float       $timeout Seconds.
	 * @param string|null $key     API key; null for the indexing key, '' for none.
	 * @return array|\WP_Error
	 */
	private function request( string $method, string $path, ?array $body = null, float $timeout = self::SHORT_TIMEOUT, ?string $key = null ) {
		$headers = array( 'Content-Type' => 'application/json' );
		if ( '' !== $key ) {
			$headers['Authorization'] = 'Bearer ' . ( $key ?? $this->config->index_key() );
		}

		$args = array(
			'method'  => $method,
			'timeout' => $timeout,
			'headers' => $headers,
		);

		if ( null !== $body ) {
			$args['body'] = (string) wp_json_encode( $body );
		}

		$response = wp_remote_request( $this->config->url() . $path, $args );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'lwtv_meili_transport', $response->get_error_message() );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = (string) wp_remote_retrieve_body( $response );

		if ( $status < 200 || $status >= 300 ) {
			return new \WP_Error( 'lwtv_meili_http_' . $status, self::describe_error( $status, $raw ), array( 'status' => $status ) );
		}

		$decoded = json_decode( $raw, true );

		return is_array( $decoded ) ? $decoded : array();
	}
}
