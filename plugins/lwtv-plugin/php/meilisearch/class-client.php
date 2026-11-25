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
		return $this->request( 'GET', '/health' );
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
	 * @param string $uid      Index uid.
	 * @param array  $settings Settings payload.
	 * @return array|\WP_Error
	 */
	public function update_settings( string $uid, array $settings ) {
		return $this->request( 'PATCH', '/indexes/' . rawurlencode( $uid ) . '/settings', $settings, 30 );
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
	 * GET /tasks/{taskUid}.
	 *
	 * @param int $task_uid Task uid.
	 * @return array|\WP_Error
	 */
	public function get_task( int $task_uid ) {
		return $this->request( 'GET', '/tasks/' . $task_uid );
	}

	/**
	 * Poll a task until it finishes. CLI only: this blocks.
	 *
	 * A failed or canceled task comes back as a WP_Error.
	 *
	 * @param int $task_uid    Task uid.
	 * @param int $max_seconds Give up after this long.
	 * @return array|\WP_Error The finished task.
	 */
	public function wait_for_task( int $task_uid, int $max_seconds = 120 ) {
		$deadline = microtime( true ) + $max_seconds;

		do {
			$task = $this->get_task( $task_uid );
			if ( is_wp_error( $task ) ) {
				return $task;
			}

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
	 * Send one request with the indexing key.
	 *
	 * @param string     $method  HTTP method.
	 * @param string     $path    Path from the server root.
	 * @param array|null $body    JSON body.
	 * @param int        $timeout Seconds.
	 * @return array|\WP_Error
	 */
	private function request( string $method, string $path, ?array $body = null, int $timeout = self::SHORT_TIMEOUT ) {
		$args = array(
			'method'  => $method,
			'timeout' => $timeout,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->config->index_key(),
				'Content-Type'  => 'application/json',
			),
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
