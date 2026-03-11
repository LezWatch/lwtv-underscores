<?php
/**
 * Description: REST-API: Agents
 *
 * Agent endpoint for AI integration. Parses prompts to extract trope, genre, format,
 * country, station, score, worth-it, year, and other dimensions. Returns matching
 * shows with title, permalink, score, and excerpt.
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
	 * Semantic score mappings: high=80, low=20, default=50.
	 *
	 * @var array<string, array{value: int, operator: string}>
	 */
	private const SCORE_SEMANTICS = array(
		'high'    => array(
			'value'    => 80,
			'operator' => '>=',
		),
		'low'     => array(
			'value'    => 20,
			'operator' => '<=',
		),
		'default' => array(
			'value'    => 50,
			'operator' => '>=',
		),
	);

	/**
	 * Taxonomy config for extraction. Order: trope > genre > format > country > station > stars > triggers > intersections.
	 *
	 * @var array<string, string>
	 */
	private const TAXONOMY_KEYS = array(
		'trope'         => 'lez_tropes',
		'genre'         => 'lez_genres',
		'format'        => 'lez_formats',
		'country'       => 'lez_country',
		'station'       => 'lez_stations',
		'stars'         => 'lez_stars',
		'triggers'      => 'lez_triggers',
		'intersections' => 'lez_intersections',
	);

	/**
	 * Extract first matching term from prompt for a taxonomy
	 *
	 * @param string $prompt   The raw prompt text.
	 * @param string $taxonomy Taxonomy name (e.g. lez_tropes).
	 * @return string|null Term slug or null.
	 */
	private function extract_taxonomy_term( string $prompt, string $taxonomy ): ?string {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'all',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return null;
		}

		$prompt_lower = strtolower( $prompt );
		foreach ( $terms as $term ) {
			if ( false !== strpos( $prompt_lower, strtolower( $term->name ) ) ||
				false !== strpos( $prompt_lower, strtolower( $term->slug ) ) ) {
				return $term->slug;
			}
		}

		return null;
	}

	/**
	 * Resolve slug to term_id for a taxonomy
	 *
	 * @param string|null $term_slug Slug or term ID.
	 * @param string      $taxonomy  Taxonomy name.
	 * @return string|null Term ID as string, or null.
	 */
	private function resolve_term_id( ?string $term_slug, string $taxonomy ): ?string {
		if ( null === $term_slug || '' === $term_slug ) {
			return null;
		}
		$term = is_numeric( $term_slug )
			? get_term_by( 'id', (int) $term_slug, $taxonomy )
			: get_term_by( 'slug', $term_slug, $taxonomy );
		if ( $term && ! is_wp_error( $term ) ) {
			return (string) $term->term_id;
		}
		return null;
	}

	/**
	 * Parse prompt to extract params array (trope, genre, format, score, etc.)
	 *
	 * @param string $prompt The raw prompt text.
	 * @return array Params array with taxonomy term IDs, score, worthit, year_min, year_max.
	 */
	private function parse_prompt( string $prompt ): array {
		$params = array(
			'trope'         => null,
			'genre'         => null,
			'format'        => null,
			'country'       => null,
			'station'       => null,
			'stars'         => null,
			'triggers'      => null,
			'intersections' => null,
			'score'         => null,
			'score_op'      => '>=',
			'worthit'       => null,
			'year_min'      => null,
			'year_max'      => null,
		);

		// Structured format: trope:slow-burn,genre:drama,score:80
		if ( preg_match( '/trope:([a-z0-9-]+)/i', $prompt, $m ) ) {
			$params['trope'] = sanitize_title( $m[1] );
		}
		if ( preg_match( '/genre:([a-z0-9-]+)/i', $prompt, $m ) ) {
			$params['genre'] = sanitize_title( $m[1] );
		}
		if ( preg_match( '/format:([a-z0-9-]+)/i', $prompt, $m ) ) {
			$params['format'] = sanitize_title( $m[1] );
		}
		if ( preg_match( '/country:([a-z0-9-]+)/i', $prompt, $m ) ) {
			$params['country'] = sanitize_title( $m[1] );
		}
		if ( preg_match( '/station:([a-z0-9-]+)/i', $prompt, $m ) ) {
			$params['station'] = sanitize_title( $m[1] );
		}
		if ( preg_match( '/score:(\d+)/i', $prompt, $m ) ) {
			$params['score'] = (int) $m[1];
		}

		// Natural language: semantic score terms (high/low/default)
		if ( null === $params['score'] && preg_match( '/(?:with\s+)?(?:a\s+)?(high|low|default)\s+score/i', $prompt, $m ) ) {
			$term               = strtolower( $m[1] );
			$params['score']    = self::SCORE_SEMANTICS[ $term ]['value'];
			$params['score_op'] = self::SCORE_SEMANTICS[ $term ]['operator'];
		}
		if ( null === $params['score'] && preg_match( '/score\s+(?:of\s+)?(high|low|default)/i', $prompt, $m ) ) {
			$term               = strtolower( $m[1] );
			$params['score']    = self::SCORE_SEMANTICS[ $term ]['value'];
			$params['score_op'] = self::SCORE_SEMANTICS[ $term ]['operator'];
		}

		// Natural language: numeric score under/below
		if ( null === $params['score'] && preg_match( '/(?:score|rated|rating)\s*(?:under|below)\s*(\d+)/i', $prompt, $m ) ) {
			$params['score']    = (int) $m[1];
			$params['score_op'] = '<=';
		}

		// Natural language: numeric score over/above/of
		if ( null === $params['score'] && preg_match( '/(?:score|rated|rating)\s*(?:of|over|above|is)?\s*(\d+)/i', $prompt, $m ) ) {
			$params['score'] = (int) $m[1];
		}

		// Worth it extraction (check "not worth it" before "worth it" to avoid false positive)
		if ( preg_match( '/(?:not\s+worth\s+it|skip\s+it)/i', $prompt ) ) {
			$params['worthit'] = 'no';
		} elseif ( preg_match( '/(?:worth\s+it|worth\s+watching|recommended)/i', $prompt ) ) {
			$params['worthit'] = 'yes';
		} elseif ( preg_match( '/(?:^|\s)meh(\s|$)/i', $prompt ) || preg_match( '/\bmixed\b/i', $prompt ) ) {
			$params['worthit'] = 'meh';
		} elseif ( preg_match( '/(?:^|\s)tbd(\s|$)/i', $prompt ) || preg_match( '/to\s+be\s+determined/i', $prompt ) ) {
			$params['worthit'] = 'tbd';
		}

		// Year extraction
		if ( preg_match( '/(?:from|in|after)\s+(\d{4})/i', $prompt, $m ) ) {
			$params['year_min'] = (int) $m[1];
		}
		if ( preg_match( '/(?:before|pre-?)\s*(\d{4})/i', $prompt, $m ) ) {
			$params['year_max'] = (int) $m[1];
		}
		if ( preg_match( '/(\d{4})\s*(?:to|-)\s*(\d{4})/i', $prompt, $m ) ) {
			$params['year_min'] = (int) $m[1];
			$params['year_max'] = (int) $m[2];
		}
		if ( preg_match( '/(?:^|\s)(\d{4})(?:\s|$)/i', $prompt, $m ) && null === $params['year_min'] && null === $params['year_max'] ) {
			$params['year_min'] = (int) $m[1];
			$params['year_max'] = (int) $m[1];
		}

		// Natural language: taxonomy extraction (priority order: trope > genre > format > country > station > stars > triggers > intersections)
		foreach ( self::TAXONOMY_KEYS as $key => $taxonomy ) {
			if ( null === $params[ $key ] ) {
				$slug = $this->extract_taxonomy_term( $prompt, $taxonomy );
				if ( null !== $slug ) {
					$params[ $key ] = $slug;
				}
			}
		}

		// Resolve slugs to term IDs for tax_query
		foreach ( self::TAXONOMY_KEYS as $key => $taxonomy ) {
			if ( null !== $params[ $key ] ) {
				$resolved = $this->resolve_term_id( $params[ $key ], $taxonomy );
				if ( null !== $resolved ) {
					$params[ $key ] = $resolved;
				} else {
					$params[ $key ] = null;
				}
			}
		}

		return $params;
	}

	/**
	 * Check if params has at least one filter dimension
	 *
	 * @param array $params Params from parse_prompt.
	 * @return bool True if at least one filter is set.
	 */
	private function params_has_filter( array $params ): bool {
		$tax_keys = array( 'trope', 'genre', 'format', 'country', 'station', 'stars', 'triggers', 'intersections' );
		foreach ( $tax_keys as $key ) {
			if ( ! empty( $params[ $key ] ) ) {
				return true;
			}
		}
		if ( null !== $params['score'] ) {
			return true;
		}
		if ( null !== $params['worthit'] ) {
			return true;
		}
		if ( null !== $params['year_min'] || null !== $params['year_max'] ) {
			return true;
		}
		return false;
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

		$params = $this->parse_prompt( $prompt );

		if ( ! $this->params_has_filter( $params ) ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'unparseable_prompt',
					'message' => 'Could not extract any filters from prompt. Try: trope (e.g. slow-burn), genre (e.g. drama), format (e.g. web-series), country (e.g. british), score (e.g. high/80), worth it, or year.',
				),
				400
			);
		}

		$cache_key = 'lwtv_ai_' . md5( wp_json_encode( $params ) );
		$cached    = function_exists( 'lwtv_plugin' ) ? lwtv_plugin()->get_transient( $cache_key ) : false;

		if ( false !== $cached ) {
			return new \WP_REST_Response( $cached, 200 );
		}

		$shows = function_exists( 'lwtv_plugin' )
			? lwtv_plugin()->get_shows_by_params( $params )
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
