<?php
/**
 * Description: REST-API: Agents
 *
 * Agent endpoint for AI integration. Parses prompts to extract trope and score,
 * returns matching shows with title, permalink, score, and excerpt.
 */

namespace LWTV\Rest_API;

class Agents {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
	}

	/**
	 * Rest API init
	 *
	 * Creates the /lwtv/v1/agent route.
	 */
	public function rest_api_init() {
		register_rest_route(
			'lwtv/v1',
			'/agent',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'agent_rest_api_callback' ),
				'permission_callback' => array( $this, 'check_ai_key_permission' ),
				'args'                => array(
					'prompt'  => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $param ) {
							return ! empty( trim( $param ) );
						},
					),
					'context' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Check permission via X-LezWatch-AI-Key header
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return bool True if key is valid, false otherwise.
	 */
	public function check_ai_key_permission( \WP_REST_Request $request ): bool {
		if ( ! defined( 'LWTV_AI_KEY' ) ) {
			return false;
		}

		$header_key = $request->get_header( 'X-LezWatch-AI-Key' );

		return ! empty( $header_key ) && hash_equals( (string) LWTV_AI_KEY, (string) $header_key );
	}

	/**
	 * Parse prompt to extract trope and score
	 *
	 * @param string $prompt The raw prompt text.
	 * @return array{0: string|null, 1: int|null} [trope, score] or [null, null] if unparseable.
	 */
	private function parse_prompt( string $prompt ): array {
		$trope = null;
		$score = null;

		// Structured format: trope:slow-burn,score:80
		if ( preg_match( '/trope:([a-z0-9-]+)/i', $prompt, $trope_match ) ) {
			$trope = sanitize_title( $trope_match[1] );
		}
		if ( preg_match( '/score:(\d+)/i', $prompt, $score_match ) ) {
			$score = (int) $score_match[1];
		}

		// Natural language: greedy score regex
		if ( null === $score && preg_match( '/(?:score|rated|rating)\s*(?:of|over|above|is)?\s*(\d+)/i', $prompt, $score_match ) ) {
			$score = (int) $score_match[1];
		}

		// Natural language: match trope against lez_tropes terms
		if ( null === $trope ) {
			$terms = get_terms(
				array(
					'taxonomy'   => 'lez_tropes',
					'hide_empty' => false,
					'fields'     => 'all',
				)
			);

			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$prompt_lower = strtolower( $prompt );
				foreach ( $terms as $term ) {
					if ( false !== strpos( $prompt_lower, strtolower( $term->name ) ) ||
						false !== strpos( $prompt_lower, strtolower( $term->slug ) ) ) {
						$trope = $term->slug;
						break;
					}
				}
			}
		}

		// CMB2 stores term IDs; resolve slug to ID for get_shows_by_trope_and_score
		if ( null !== $trope ) {
			$term = is_numeric( $trope )
				? get_term_by( 'id', (int) $trope, 'lez_tropes' )
				: get_term_by( 'slug', $trope, 'lez_tropes' );
			if ( $term && ! is_wp_error( $term ) ) {
				$trope = (string) $term->term_id;
			}
		}

		return array( $trope, $score );
	}

	/**
	 * Agent REST API callback
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function agent_rest_api_callback( \WP_REST_Request $request ) {
		$prompt = trim( $request->get_param( 'prompt' ) );

		if ( empty( $prompt ) ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'missing_prompt',
					'message' => 'The prompt parameter is required.',
				),
				400
			);
		}

		list( $trope, $score ) = $this->parse_prompt( $prompt );

		if ( null === $trope || null === $score ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'unparseable_prompt',
					'message' => 'Could not extract trope and score from prompt. Try: "trope:slow-burn,score:80" or natural language like "Find me a slow burn show with a score over 80".',
				),
				400
			);
		}

		$cache_key = 'lwtv_ai_' . md5( $trope . $score );
		$cached    = function_exists( 'lwtv_plugin' ) ? lwtv_plugin()->get_transient( $cache_key ) : false;

		if ( false !== $cached ) {
			return new \WP_REST_Response( $cached, 200 );
		}

		$shows = function_exists( 'lwtv_plugin' )
			? lwtv_plugin()->get_shows_by_trope_and_score( $trope, $score )
			: array();

		$results = array(
			'shows' => array(),
		);

		foreach ( $shows as $post ) {
			$excerpt = get_the_excerpt( $post->ID );
			if ( empty( trim( $excerpt ) ) ) {
				$excerpt = $post->post_content ?? '';
			}
			$excerpt = wp_trim_words( wp_strip_all_tags( $excerpt ), 25 );

			$show_score = get_post_meta( $post->ID, 'lezshows_the_score', true );
			$show_score = is_numeric( $show_score ) ? min( (int) $show_score, 100 ) : 0;

			$results['shows'][] = array(
				'title'     => get_the_title( $post->ID ),
				'permalink' => get_permalink( $post->ID ),
				'score'     => $show_score,
				'excerpt'   => $excerpt,
			);
		}

		if ( function_exists( 'lwtv_plugin' ) ) {
			lwtv_plugin()->set_transient( $cache_key, $results, HOUR_IN_SECONDS );
		}

		return new \WP_REST_Response( $results, 200 );
	}
}
