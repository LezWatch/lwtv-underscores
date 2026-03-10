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
	 * Call the AI server
	 *
	 * @param string $prompt
	 * @return string
	 */
	public function call_ai_server( $prompt ) {
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
						'model'  => 'llama3.1',
						'prompt' => $prompt,
						'stream' => false, // Set to true if you build a JS stream handler
					),
				),
				'timeout' => 45, // CPU inference needs time to "think"
			),
		);

		return wp_json_encode( wp_remote_retrieve_body( $response ), true );
	}

	/**
	 * Get shows by trope and minimum "Realness" score
	 *
	 * @param string $trope
	 * @param int $realness_score
	 * @return array
	 */
	public function get_shows_by_trope_and_realness( $trope, $realness_score ) {
		$shows = get_posts(
			array(
				'post_type'    => 'post_type_shows',
				'meta_key'     => 'lezshows_tropes',
				'meta_value'   => $trope,
				'meta_compare' => '=',
				'meta_query'   => array(
					array(
						'key'     => 'lezshows_realness_rating',
						'value'   => $realness_score,
						'compare' => '>=',
					),
				),
			)
		);
		return $shows;
	}

	/**
	 * Get shows by trope and Score (lezshows_the_score)
	 *
	 * @param string $trope
	 * @param int $score
	 * @return array
	 */
	public function get_shows_by_trope_and_score( $trope, $score ) {
		$shows = get_posts(
			array(
				'post_type'    => 'post_type_shows',
				'meta_key'     => 'lezshows_tropes',
				'meta_value'   => $trope,
				'meta_compare' => '=',
				'meta_query'   => array(
					array(
						'key'     => 'lezshows_the_score',
						'value'   => $score,
						'compare' => '>=',
					),
				),
			)
		);
		return $shows;
	}
}
