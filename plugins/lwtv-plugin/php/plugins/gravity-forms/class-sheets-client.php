<?php
/**
 * Name: Sheets Client
 * Description: Reads and writes the "LWTV: Content to Add" Google Sheet.
 *
 * Authenticates as a service account (hand-rolled JWT, no Composer dependency)
 * and talks to the Sheets v4 REST API through wp_remote_*. Network errors, 429
 * and 5xx throw Transient_Error (worth retrying); other failures throw a plain
 * RuntimeException (configuration problems, not retried).
 *
 * @package LWTV
 */

namespace LWTV\Plugins\Gravity_Forms;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception messages are logged and caught, never printed.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Plugins\Gravity_Forms\Build\Service_Account_Jwt;
use LWTV\Plugins\Gravity_Forms\Build\Sheet_Table;

class Sheets_Client {

	const SCOPE         = 'https://www.googleapis.com/auth/spreadsheets';
	const TOKEN_PREFIX  = 'lwtv_gsheets_token_';
	const API_BASE      = 'https://sheets.googleapis.com/v4/spreadsheets/';
	const GRANT_TYPE    = 'urn:ietf:params:oauth:grant-type:jwt-bearer';
	const TOKEN_TIMEOUT = 15;
	const API_TIMEOUT   = 20;

	/**
	 * Spreadsheet ID.
	 *
	 * @var string
	 */
	private $spreadsheet_id;

	/**
	 * Absolute path to the service-account JSON key file.
	 *
	 * @var string
	 */
	private $key_path;

	/**
	 * Decoded key file, read once.
	 *
	 * @var array|null
	 */
	private $key = null;

	/**
	 * Constructor.
	 *
	 * @param string $spreadsheet_id Spreadsheet ID.
	 * @param string $key_path       Path to the service-account key file.
	 */
	public function __construct( string $spreadsheet_id, string $key_path ) {
		$this->spreadsheet_id = $spreadsheet_id;
		$this->key_path       = $key_path;
	}

	/**
	 * Read several ranges in one call.
	 *
	 * @param string[] $ranges A1 ranges, tab names quoted: "'To Add (NEW)'!A:E".
	 * @return array<string, array> Rows per range, keyed exactly as passed in.
	 * @throws Transient_Error On retryable failures.
	 * @throws \RuntimeException On configuration failures.
	 */
	public function get_ranges( array $ranges ): array {
		$ranges = array_values( $ranges );
		if ( empty( $ranges ) ) {
			return array();
		}

		$query = 'valueRenderOption=FORMATTED_VALUE';
		foreach ( $ranges as $range ) {
			$query .= '&ranges=' . rawurlencode( (string) $range );
		}

		$data   = $this->request( 'GET', 'values:batchGet?' . $query );
		$sets   = isset( $data['valueRanges'] ) && is_array( $data['valueRanges'] ) ? array_values( $data['valueRanges'] ) : array();
		$result = array();

		// The API echoes ranges normalised, so map back by order, not by the returned string.
		foreach ( $ranges as $i => $range ) {
			$result[ $range ] = isset( $sets[ $i ]['values'] ) && is_array( $sets[ $i ]['values'] ) ? $sets[ $i ]['values'] : array();
		}

		return $result;
	}

	/**
	 * Append one row at an explicit empty row past the end of a tab.
	 *
	 * values:append detects the "table" from the range it is given. Starting at
	 * A1 lets a fully blank row mid-sheet end that table, so the new row lands
	 * in the gap and pushes every row below it down. Starting at the first row
	 * past the data avoids that; INSERT_ROWS still grows the grid.
	 *
	 * @param string $tab       Tab name (unquoted).
	 * @param array  $row       Cell values, in column order.
	 * @param int    $start_row 1-based row to write: the last row read + 1 + rows already appended this run.
	 * @return int The row the API actually wrote.
	 * @throws Transient_Error On retryable failures.
	 * @throws \RuntimeException On configuration failures.
	 */
	public function append_row( string $tab, array $row, int $start_row ): int {
		$start_row = max( 2, $start_row );
		$range     = $this->quote_tab( $tab ) . '!A' . $start_row;

		$data    = $this->request(
			'POST',
			'values/' . rawurlencode( $range ) . ':append?valueInputOption=RAW&insertDataOption=INSERT_ROWS',
			array( 'values' => array( array_values( $row ) ) )
		);
		$updated = isset( $data['updates']['updatedRange'] ) && is_string( $data['updates']['updatedRange'] ) ? $data['updates']['updatedRange'] : '';
		$written = Sheet_Table::range_row( $updated );

		if ( $written <= 0 ) {
			$this->log( 'Sheets append to ' . $tab . ' row ' . $start_row . ' did not report the row it wrote; assuming row ' . $start_row . '.' );
			return $start_row;
		}
		if ( $written !== $start_row ) {
			$this->log( 'Sheets append to ' . $tab . ' landed on row ' . $written . ' instead of row ' . $start_row . '.' );
		}

		return $written;
	}

	/**
	 * Write individual cells on one row.
	 *
	 * @param string               $tab             Tab name (unquoted).
	 * @param int                  $row             1-based row number.
	 * @param array<string,string> $cells_by_letter Column letter => value.
	 * @return void
	 * @throws Transient_Error On retryable failures.
	 * @throws \RuntimeException On configuration failures.
	 */
	public function update_cells( string $tab, int $row, array $cells_by_letter ): void {
		if ( empty( $cells_by_letter ) ) {
			return;
		}

		$data = array();
		foreach ( $cells_by_letter as $letter => $value ) {
			$data[] = array(
				'range'  => $this->quote_tab( $tab ) . '!' . $letter . $row,
				'values' => array( array( $value ) ),
			);
		}

		$this->request(
			'POST',
			'values:batchUpdate',
			array(
				'valueInputOption' => 'RAW',
				'data'             => $data,
			)
		);
	}

	/**
	 * Quote a tab name for A1 notation.
	 *
	 * @param string $tab Tab name.
	 * @return string
	 */
	private function quote_tab( string $tab ): string {
		return "'" . str_replace( "'", "''", $tab ) . "'";
	}

	/**
	 * Call the Sheets API, retrying once on 401 with a fresh token.
	 *
	 * @param string     $method HTTP method.
	 * @param string     $path   Path after the spreadsheet ID.
	 * @param array|null $body   JSON body.
	 * @return array Decoded response.
	 * @throws Transient_Error On retryable failures.
	 * @throws \RuntimeException On configuration failures.
	 */
	private function request( string $method, string $path, ?array $body = null ): array {
		$url = self::API_BASE . rawurlencode( $this->spreadsheet_id ) . '/' . $path;

		$response = $this->send( $method, $url, $body );

		if ( ! is_wp_error( $response ) && 401 === (int) wp_remote_retrieve_response_code( $response ) ) {
			// Stale or revoked cached token: mint a fresh one and retry once.
			delete_transient( $this->token_key() );
			$response = $this->send( $method, $url, $body );
		}

		return $this->parse( $response, 'Sheets API' );
	}

	/**
	 * Send one authenticated request.
	 *
	 * @param string     $method HTTP method.
	 * @param string     $url    Full URL.
	 * @param array|null $body   JSON body.
	 * @return array|\WP_Error
	 * @throws Transient_Error On retryable token failures.
	 * @throws \RuntimeException On configuration failures.
	 */
	private function send( string $method, string $url, ?array $body ) {
		$args = array(
			'method'  => $method,
			'timeout' => self::API_TIMEOUT,
			'headers' => array( 'Authorization' => 'Bearer ' . $this->get_token() ),
		);
		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}

		return wp_remote_request( $url, $args );
	}

	/**
	 * Turn an HTTP response into decoded data or the right exception.
	 *
	 * @param array|\WP_Error $response HTTP response.
	 * @param string          $what     Label for messages.
	 * @return array
	 * @throws Transient_Error On WP_Error, 429, 5xx.
	 * @throws \RuntimeException On other failures.
	 */
	private function parse( $response, string $what ): array {
		if ( is_wp_error( $response ) ) {
			$this->log( $what . ' request failed: ' . $response->get_error_message() );
			throw new Transient_Error( $what . ' request failed: ' . $response->get_error_message() );
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$decoded = is_array( $decoded ) ? $decoded : array();

		if ( $code >= 200 && $code < 300 ) {
			return $decoded;
		}

		$message = $what . ' returned HTTP ' . $code;
		if ( isset( $decoded['error']['message'] ) && is_string( $decoded['error']['message'] ) ) {
			$message .= ': ' . $decoded['error']['message'];
		} elseif ( isset( $decoded['error_description'] ) && is_string( $decoded['error_description'] ) ) {
			$message .= ': ' . $decoded['error_description'];
		}

		$this->log( $message );

		if ( 429 === $code || $code >= 500 ) {
			throw new Transient_Error( $message );
		}

		throw new \RuntimeException( $message );
	}

	/**
	 * Get a cached access token, minting one if needed.
	 *
	 * @return string
	 * @throws Transient_Error On retryable failures.
	 * @throws \RuntimeException On configuration failures.
	 */
	private function get_token(): string {
		$token_key = $this->token_key();
		$cached    = get_transient( $token_key );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$key = $this->read_key();
		try {
			$jwt = Service_Account_Jwt::build( $key, time(), self::SCOPE );
		} catch ( \UnexpectedValueException $e ) {
			throw new \RuntimeException( $e->getMessage(), 0, $e );
		}

		$token_uri = ! empty( $key['token_uri'] ) && is_string( $key['token_uri'] ) ? $key['token_uri'] : Service_Account_Jwt::DEFAULT_TOKEN_URI;

		$response = wp_remote_post(
			$token_uri,
			array(
				'timeout' => self::TOKEN_TIMEOUT,
				'body'    => array(
					'grant_type' => self::GRANT_TYPE,
					'assertion'  => $jwt,
				),
			)
		);

		$data = $this->parse( $response, 'Google token endpoint' );
		if ( empty( $data['access_token'] ) || ! is_string( $data['access_token'] ) ) {
			throw new \RuntimeException( 'Google token endpoint did not return an access token.' );
		}

		$ttl = max( 60, (int) ( $data['expires_in'] ?? 3600 ) - 60 );
		set_transient( $token_key, $data['access_token'], $ttl );

		return $data['access_token'];
	}

	/**
	 * Token transient key for this service account, so a rotated key file never reuses another account's token.
	 *
	 * @return string
	 * @throws \RuntimeException If the key file is unreadable or not valid JSON.
	 */
	private function token_key(): string {
		$key = $this->read_key();
		return self::TOKEN_PREFIX . md5( is_string( $key['client_email'] ?? null ) ? $key['client_email'] : '' );
	}

	/**
	 * Read and decode the service-account key file (once per client).
	 *
	 * @return array
	 * @throws \RuntimeException If unreadable or not valid JSON.
	 */
	private function read_key(): array {
		if ( null !== $this->key ) {
			return $this->key;
		}

		$raw = is_readable( $this->key_path ) ? file_get_contents( $this->key_path ) : false;
		if ( false === $raw ) {
			throw new \RuntimeException( 'Google service account key file is not readable.' );
		}

		$key = json_decode( $raw, true );
		if ( ! is_array( $key ) ) {
			throw new \RuntimeException( 'Google service account key file is not valid JSON.' );
		}

		$this->key = $key;
		return $key;
	}

	/**
	 * Log to the submissions topic.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	private function log( string $message ): void {
		if ( function_exists( 'lwtv_plugin' ) ) {
			lwtv_plugin()->error_log( 'submissions', $message );
		}
	}
}
