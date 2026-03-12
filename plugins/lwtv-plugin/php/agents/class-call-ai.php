<?php
/*
 * Call AI
 *
 * @since 6.6.0
 */

namespace LWTV\Agents;

class Call_AI {

	/**
	 * Call the AI server (currently using Ollama)
	 *
	 * @param string $prompt
	 * @return string
	 */
	public function call_server( $prompt ) {

		if ( ! defined( 'LWTV_AGENTS_USER' ) || ! defined( 'LWTV_AGENTS_PASS' ) ) {
			return new \WP_Error( 'missing_auth', 'AI Server credentials not defined.' );
		}

		$user = LWTV_AGENTS_USER;
		$pass = LWTV_AGENTS_PASS;
		$auth = base64_encode( "$user:$pass" );

		$response = wp_remote_post(
			'https://ai.ipstenu.com/api/generate',
			array(
				'headers' => array(
					'Authorization' => "Basic $auth",
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'  => 'lezwatch-bot',
						'prompt' => $prompt,
						'stream' => false, // Set to true if you build a JS stream handler
					),
				),
				'timeout' => 300, // CPU inference needs time to "think"
			),
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Decode the JSON string from Ollama into a PHP array
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		// If decoding failed or response is missing, return an error array
		if ( ! isset( $data['response'] ) ) {
			return array(
				'error'   => 'no_response',
				'message' => 'The AI server returned an empty or invalid response.',
			);
		}

		return $data['response'];
	}
}
