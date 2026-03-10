<?php
/*
 * Agents
 */
namespace LWTV\_Components;

class Agents implements Component, Templater {

	public function init(): void {
		// TODO: Implement init() method.
	}

	/**
	 * Gets tags to expose as methods accessible through `lwtv_plugin()`.
	 *
	 * @return array Associative array of $method_name => $callback_info pairs. Each $callback_info must either be
	 *               a callable or an array with key 'callable'. This approach is used to reserve the possibility of
	 *               adding support for further arguments in the future.
	 */
	public function get_template_tags(): array {
		return array(
			'get_agents'                   => array( $this, 'get_agents' ),
			'get_shows_by_trope_and_score' => array( $this, 'get_shows_by_trope_and_score' ),
		);
	}

	/**
	 * Call the AI server (currently using Ollama)
	 *
	 * @param string $prompt
	 * @return string
	 */
	public function call_ai_server( $prompt ) {

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
				'timeout' => 45, // CPU inference needs time to "think"
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

	/**
	 * Get shows by trope and minimum "Realness" score
	 *
	 * @param string|int $trope Term slug or term ID from lez_tropes taxonomy.
	 * @param int        $realness_score Minimum realness score.
	 * @return array
	 */
	public function get_shows_by_trope_and_realness( $trope, $realness_score ) {
		$term = is_numeric( $trope )
			? get_term_by( 'id', (int) $trope, 'lez_tropes' )
			: get_term_by( 'slug', $trope, 'lez_tropes' );

		if ( ! $term || is_wp_error( $term ) ) {
			return array();
		}

		$shows = get_posts(
			array(
				'post_type'      => 'post_type_shows',
				'tax_query'      => array(
					array(
						'taxonomy' => 'lez_tropes',
						'field'    => 'term_id',
						'terms'    => array( $term->term_id ),
					),
				),
				'meta_query'     => array(
					array(
						'key'     => 'lezshows_realness_rating',
						'value'   => $realness_score,
						'compare' => '>=',
						'type'    => 'NUMERIC',
					),
				),
				'posts_per_page' => -1,
				'no_found_rows'  => true,
			)
		);
		return $shows;
	}

	/**
	 * Get shows by trope and Score (lezshows_the_score)
	 *
	 * @param string|int $trope Term slug or term ID from lez_tropes taxonomy.
	 * @param int        $score Minimum score.
	 * @return array
	 */
	public function get_shows_by_trope_and_score( $trope, $score ) {
		$term = is_numeric( $trope )
			? get_term_by( 'id', (int) $trope, 'lez_tropes' )
			: get_term_by( 'slug', $trope, 'lez_tropes' );

		if ( ! $term || is_wp_error( $term ) ) {
			return array();
		}

		$shows = get_posts(
			array(
				'post_type'      => 'post_type_shows',
				'tax_query'      => array(
					array(
						'taxonomy' => 'lez_tropes',
						'field'    => 'term_id',
						'terms'    => array( $term->term_id ),
					),
				),
				'meta_query'     => array(
					array(
						'key'     => 'lezshows_the_score',
						'value'   => $score,
						'compare' => '>=',
						'type'    => 'NUMERIC',
					),
				),
				'posts_per_page' => -1,
				'no_found_rows'  => true,
			)
		);
		return $shows;
	}
}
