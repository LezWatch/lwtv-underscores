<?php
/**
 * Name: Gemini Client
 * Description: Asks Gemini to pull character details out of free text.
 *
 * Network errors, 429, 5xx and unusable responses throw Transient_Error (worth
 * retrying); other failures throw a plain RuntimeException. The API key is sent
 * only in the x-goog-api-key header and never appears in a message or log.
 *
 * @package LWTV
 */

namespace LWTV\Plugins\Gravity_Forms;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception messages are logged and caught, never printed.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\Plugins\Gravity_Forms\Build\Extraction;

class Gemini_Client {

	// Defaults. Override with LWTV_GEMINI_MODEL and LWTV_GEMINI_API_BASE in wp-config.php.
	const MODEL    = 'gemini-3.8-flash';
	const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';
	const TIMEOUT  = 30;

	/**
	 * API key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Model name.
	 *
	 * @var string
	 */
	private $model;

	/**
	 * API base URL, ending in a slash.
	 *
	 * @var string
	 */
	private $api_base;

	/**
	 * Constructor.
	 *
	 * @param string $api_key  API key.
	 * @param string $model    Model name. Empty uses LWTV_GEMINI_MODEL, then the default.
	 * @param string $api_base API base URL. Empty uses LWTV_GEMINI_API_BASE, then the default.
	 */
	public function __construct( string $api_key, string $model = '', string $api_base = '' ) {
		$this->api_key  = $api_key;
		$this->model    = '' !== $model ? $model : self::config( 'LWTV_GEMINI_MODEL', self::MODEL );
		$this->api_base = trailingslashit( '' !== $api_base ? $api_base : self::config( 'LWTV_GEMINI_API_BASE', self::API_BASE ) );
	}

	/**
	 * A wp-config string constant, or the default when it is missing or empty.
	 *
	 * @param string $name     Constant name.
	 * @param string $fallback Default value.
	 * @return string
	 */
	private static function config( string $name, string $fallback ): string {
		$value = defined( $name ) ? constant( $name ) : '';

		return is_string( $value ) && '' !== trim( $value ) ? trim( $value ) : $fallback;
	}

	/**
	 * Extract characters from a description.
	 *
	 * @param string   $show    Show title.
	 * @param string   $text    Description text.
	 * @param string[] $allowed Allowed controlled values.
	 * @return string The model's JSON text, for Extraction::parse().
	 * @throws Transient_Error On retryable failures.
	 * @throws \RuntimeException On other failures.
	 */
	public function extract( string $show, string $text, array $allowed ): string {
		$response = wp_remote_post(
			$this->api_base . rawurlencode( $this->model ) . ':generateContent',
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array(
					'Content-Type'   => 'application/json',
					'x-goog-api-key' => $this->api_key,
				),
				'body'    => wp_json_encode(
					array(
						'contents'         => array(
							array(
								'parts' => array(
									array( 'text' => Extraction::prompt( $show, $text, $allowed ) ),
								),
							),
						),
						'generationConfig' => array(
							'responseMimeType' => 'application/json',
							'responseSchema'   => Extraction::schema(),
							'temperature'      => 0,
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->fail_transient( 'Gemini request failed: ' . $response->get_error_message() );
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$decoded = is_array( $decoded ) ? $decoded : array();

		if ( $code < 200 || $code >= 300 ) {
			$message = 'Gemini returned HTTP ' . $code;
			if ( isset( $decoded['error']['message'] ) && is_string( $decoded['error']['message'] ) ) {
				$message .= ': ' . $decoded['error']['message'];
			}
			if ( 429 === $code || $code >= 500 ) {
				$this->fail_transient( $message );
			}
			$this->log( $message );
			throw new \RuntimeException( $message );
		}

		$candidate = $decoded['candidates'][0] ?? null;
		$text_part = is_array( $candidate ) ? ( $candidate['content']['parts'][0]['text'] ?? null ) : null;
		$finish    = is_array( $candidate ) && isset( $candidate['finishReason'] ) ? (string) $candidate['finishReason'] : '';

		if ( 'SAFETY' === $finish || ! is_string( $text_part ) || '' === $text_part ) {
			$this->fail_transient( 'Gemini returned no usable candidate' . ( '' !== $finish ? ' (finishReason ' . $finish . ')' : '' ) . '.' );
		}

		return $text_part;
	}

	/**
	 * Log and throw a Transient_Error.
	 *
	 * @param string $message Message.
	 * @return never
	 * @throws Transient_Error Always.
	 */
	private function fail_transient( string $message ) {
		$this->log( $message );
		throw new Transient_Error( $message );
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
