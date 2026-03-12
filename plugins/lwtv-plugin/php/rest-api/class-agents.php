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
	 * Country slug aliases: natural-language terms to try when resolving lez_country.
	 * Tried in order; first existing term wins. Handles "british" -> "uk", "american" -> "usa", etc.
	 *
	 * @var array<string, array<string>>
	 */
	/**
	 * Trope slug aliases for exclusion: colloquial names to taxonomy slugs.
	 *
	 * @var array<string, string>
	 */
	private const TROPE_ALIASES = array(
		'bury-your-gays' => 'dead-queers',
		'bury your gays' => 'dead-queers',
		'dead queers'    => 'dead-queers',
		'dead-queers'    => 'dead-queers',
	);

	private const COUNTRY_ALIASES = array(
		'british'        => array( 'united-kingdom', 'uk', 'wales', 'scotland', 'ireland', 'england' ),
		'uk'             => array( 'uk', 'united-kingdom' ),
		'united kingdom' => array( 'united-kingdom', 'uk' ),
		'great britain'  => array( 'united-kingdom', 'uk' ),
		'american'       => array( 'usa', 'us' ),
		'canadian'       => array( 'canada' ),
		'australian'     => array( 'australia' ),
		'german'         => array( 'germany', 'west-germany' ),
		'french'         => array( 'france' ),
		'spanish'        => array( 'spain' ),
		'irish'          => array( 'ireland' ),
		'scottish'       => array( 'scotland' ),
		'welsh'          => array( 'wales' ),
		'japanese'       => array( 'japan' ),
		'korean'         => array( 'south-korea' ),
		'italian'        => array( 'italy' ),
		'dutch'          => array( 'netherlands' ),
		'brazilian'      => array( 'brazil' ),
		'mexican'        => array( 'mexico' ),
		'indian'         => array( 'india' ),
		'argentine'      => array( 'argentina' ),
		'argentinian'    => array( 'argentina' ),
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
	 * Extract trope to exclude from "without X trope" / "exclude X trope" patterns.
	 *
	 * @param string $prompt The raw prompt text.
	 * @return string|null Term slug or null.
	 */
	private function extract_trope_exclude( string $prompt ): ?string {
		$prompt_lower = strtolower( $prompt );

		// Only consider exclusion when user says "without" or "exclude"
		if ( false === strpos( $prompt_lower, 'without' ) && false === strpos( $prompt_lower, 'exclude' ) ) {
			return null;
		}

		// Check aliases first (Bury Your Gays -> dead-queers)
		foreach ( self::TROPE_ALIASES as $phrase => $slug ) {
			if ( false !== strpos( $prompt_lower, strtolower( $phrase ) ) ) {
				$term = get_term_by( 'slug', $slug, 'lez_tropes' );
				if ( $term && ! is_wp_error( $term ) ) {
					return $slug;
				}
			}
		}

		// Match "without X trope" or "exclude X trope" patterns.
		if ( preg_match( '/without\s+(?:the\s+)?[\'"]?([^\'"]+)[\'"]?\s+trope/i', $prompt, $m ) ) {
			$captured       = trim( $m[1] );
			$captured_lower = strtolower( $captured );
			$slug           = isset( self::TROPE_ALIASES[ $captured_lower ] ) ? self::TROPE_ALIASES[ $captured_lower ] : sanitize_title( $captured );
			$term           = get_term_by( 'slug', $slug, 'lez_tropes' );
			if ( $term && ! is_wp_error( $term ) ) {
				return $term->slug;
			}
		}
		if ( preg_match( '/exclude\s+(?:the\s+)?([a-z0-9\s-]+)\s+trope/i', $prompt, $m ) ) {
			$captured       = trim( $m[1] );
			$captured_lower = strtolower( $captured );
			$slug           = isset( self::TROPE_ALIASES[ $captured_lower ] ) ? self::TROPE_ALIASES[ $captured_lower ] : sanitize_title( $captured );
			$term           = get_term_by( 'slug', $slug, 'lez_tropes' );
			if ( $term && ! is_wp_error( $term ) ) {
				return $term->slug;
			}
		}

		return null;
	}

	/**
	 * Extract first matching term from prompt for a taxonomy
	 *
	 * For lez_country, first checks natural-language adjectives (german, british, etc.)
	 * since the taxonomy may use noun slugs (germany, united-kingdom).
	 *
	 * @param string      $prompt        The raw prompt text.
	 * @param string      $taxonomy      Taxonomy name (e.g. lez_tropes).
	 * @param string|null $exclude_slugs Comma-separated or single slug to skip (e.g. when used in "without X trope").
	 * @return string|null Term slug or null.
	 */
	private function extract_taxonomy_term( string $prompt, string $taxonomy, ?string $exclude_slugs = null ): ?string {
		$prompt_lower = strtolower( $prompt );
		$exclude      = array();
		if ( ! empty( $exclude_slugs ) ) {
			$exclude = array_map( 'trim', explode( ',', $exclude_slugs ) );
			$exclude = array_filter( $exclude );
		}

		// For country: check natural-language adjectives first (german, british, etc.)
		if ( 'lez_country' === $taxonomy ) {
			foreach ( self::COUNTRY_ALIASES as $adjective => $slugs ) {
				if ( false !== strpos( $prompt_lower, $adjective ) ) {
					foreach ( $slugs as $slug ) {
						$term = get_term_by( 'slug', $slug, $taxonomy );
						if ( $term && ! is_wp_error( $term ) ) {
							return $slug;
						}
					}
				}
			}
		}

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

		foreach ( $terms as $term ) {
			if ( ! empty( $exclude ) && in_array( $term->slug, $exclude, true ) ) {
				continue;
			}
			$pattern_name = '/\b' . preg_quote( $term->name, '/' ) . '\b/i';
			$pattern_slug = '/\b' . preg_quote( $term->slug, '/' ) . '\b/i';
			if ( preg_match( $pattern_name, $prompt ) || preg_match( $pattern_slug, $prompt ) ) {
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
			'trope_exclude' => null,
			'genre'         => null,
			'format'        => null,
			'country'       => null,
			'station'       => null,
			'stars'         => null,
			'triggers'      => null,
			'intersections' => null,
			'score'         => '50',
			'score_op'      => '>=',
			'worthit'       => null,
			'on_air'        => null,
			'status'        => null,
			'year_min'      => null,
			'year_max'      => null,
		);

		// Structured format: trope:slow-burn,genre:drama,trope_exclude:dead-queers,score:80 (allows optional space after colon)
		if ( preg_match( '/trope:\s*([a-z0-9-]+)/i', $prompt, $m ) ) {
			$params['trope'] = sanitize_title( trim( $m[1] ) );
		}
		if ( preg_match( '/trope_exclude:\s*([a-z0-9-]+)/i', $prompt, $m ) ) {
			$params['trope_exclude'] = sanitize_title( trim( $m[1] ) );
		}
		if ( preg_match( '/genre:\s*([a-z0-9-]+)/i', $prompt, $m ) ) {
			$params['genre'] = sanitize_title( trim( $m[1] ) );
		}
		if ( preg_match( '/format:\s*([a-z0-9-]+)/i', $prompt, $m ) ) {
			$params['format'] = sanitize_title( trim( $m[1] ) );
		}
		if ( preg_match( '/country:\s*([a-z0-9-]+)/i', $prompt, $m ) ) {
			$params['country'] = sanitize_title( trim( $m[1] ) );
		}
		if ( preg_match( '/station:\s*([a-z0-9-]+)/i', $prompt, $m ) ) {
			$params['station'] = sanitize_title( trim( $m[1] ) );
		}
		if ( preg_match( '/score:\s*(\d+)/i', $prompt, $m ) ) {
			$params['score'] = (int) trim( $m[1] );
		}
		if ( preg_match( '/on_air:\s*(yes|no)/i', $prompt, $m ) ) {
			$params['on_air'] = strtolower( trim( $m[1] ) );
		}
		if ( preg_match( '/status:\s*(ongoing|ended)/i', $prompt, $m ) ) {
			$params['status'] = strtolower( trim( $m[1] ) );
		}
		if ( preg_match( '/worthit:\s*(yes|no|meh|tbd)/i', $prompt, $m ) ) {
			$params['worthit'] = strtolower( trim( $m[1] ) );
		}

		// Translator: status:ongoing|ended from AI -> on_air for lezshows_on_air meta
		if ( null !== $params['status'] && null === $params['on_air'] ) {
			if ( 'ongoing' === $params['status'] ) {
				$params['on_air'] = 'yes';
			} elseif ( 'ended' === $params['status'] ) {
				$params['on_air'] = 'no';
			}
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
		// Only run when not already set by structured format (worthit:yes etc.)
		if ( null === $params['worthit'] ) {
			if ( preg_match( '/(?:not\s+worth\s+it|skip\s+it)/i', $prompt ) ) {
				$params['worthit'] = 'no';
			} elseif ( preg_match( '/(?:worth\s+it|worth\s+watching|recommended)/i', $prompt ) ) {
				$params['worthit'] = 'yes';
			} elseif ( preg_match( '/(?:^|\s)meh(\s|$)/i', $prompt ) || preg_match( '/\bmixed\b/i', $prompt ) ) {
				$params['worthit'] = 'meh';
			} elseif ( preg_match( '/(?:^|\s)tbd(\s|$)/i', $prompt ) || preg_match( '/to\s+be\s+determined/i', $prompt ) ) {
				$params['worthit'] = 'tbd';
			}
		}

		// On air extraction (natural language)
		if ( null === $params['on_air'] ) {
			$negative_on_air = preg_match( '/(?:not|no\s+longer)\s+(?:on\s+air|airing)\b|off\s+the\s+air|\b(?:ended|cancelled|canceled)\b/i', $prompt );
			$positive_on_air = preg_match( '/(?:currently\s+)?(?:on\s+air|airing|airs?\s+now|currently\s+on)\b|\bongoing\b/i', $prompt );
			if ( $negative_on_air ) {
				$params['on_air'] = 'no';
			} elseif ( $positive_on_air ) {
				$params['on_air'] = 'yes';
			}
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

		// Natural language: "without X trope" / "exclude X trope" -> trope_exclude
		if ( null === $params['trope_exclude'] ) {
			$excluded = $this->extract_trope_exclude( $prompt );
			if ( null !== $excluded ) {
				$params['trope_exclude'] = $excluded;
			}
		}

		// "from the UK" / "from Britain" / "from Italy" -> country (avoids "it" in "worth it")
		if ( null === $params['country'] && preg_match( '/\bfrom\s+(?:the\s+)?([a-z0-9\s-]+?)(?:\s+that|\s+worth|\s+with|$)/i', $prompt, $m ) ) {
			$phrase = trim( $m[1] );
			if ( ! empty( $phrase ) && ! preg_match( '/^\d{4}$/', $phrase ) ) {
				$slug = $this->extract_taxonomy_term( $phrase, 'lez_country' );
				if ( null !== $slug ) {
					$params['country'] = $slug;
				}
			}
		}

		// Natural language: taxonomy extraction (priority order: trope > genre > format > country > station > stars > triggers > intersections)
		foreach ( self::TAXONOMY_KEYS as $key => $taxonomy ) {
			if ( null === $params[ $key ] ) {
				$slug = $this->extract_taxonomy_term( $prompt, $taxonomy, $params['trope_exclude'] );
				if ( null !== $slug ) {
					$params[ $key ] = $slug;
				}
			}
		}

		// Resolve slugs to term IDs for tax_query
		foreach ( self::TAXONOMY_KEYS as $key => $taxonomy ) {
			if ( null === $params[ $key ] ) {
				continue;
			}
			$slug     = $params[ $key ];
			$resolved = $this->resolve_term_id( $slug, $taxonomy );
			if ( null === $resolved && 'country' === $key && isset( self::COUNTRY_ALIASES[ $slug ] ) ) {
				foreach ( self::COUNTRY_ALIASES[ $slug ] as $alias ) {
					if ( $alias === $slug ) {
						continue;
					}
					$resolved = $this->resolve_term_id( $alias, $taxonomy );
					if ( null !== $resolved ) {
						break;
					}
				}
			}
			$params[ $key ] = $resolved ?? null;
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
		$tax_keys = array( 'trope', 'trope_exclude', 'genre', 'format', 'country', 'station', 'stars', 'triggers', 'intersections' );
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
		if ( null !== $params['on_air'] ) {
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
					'message' => 'Could not extract any filters from prompt. Try: trope (e.g. slow-burn), genre (e.g. drama), format (e.g. web-series), country (e.g. british), score (e.g. high/80), worth it, on air, or year.',
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

		$context = trim( (string) $request->get_param( 'context' ) );
		$results = array(
			'shows'   => array(),
			'context' => $context,
		);

		foreach ( $shows as $post ) {
			$excerpt = get_the_excerpt( $post->ID );
			if ( empty( trim( $excerpt ) ) ) {
				$excerpt = $post->post_content ?? '';
			}
			$excerpt = wp_trim_words( wp_strip_all_tags( $excerpt ), 25 );

			$show_score = get_post_meta( $post->ID, 'lezshows_the_score', true );
			$show_score = is_numeric( $show_score ) ? min( (int) $show_score, 100 ) : 0;

			$total_chars = (int) get_post_meta( $post->ID, 'lezshows_char_count', true );
			$dead_chars  = (int) get_post_meta( $post->ID, 'lezshows_dead_count', true );
			$tropes      = get_the_terms( $post->ID, 'lez_tropes' );
			$trope_slugs = ( $tropes && ! is_wp_error( $tropes ) )
				? wp_list_pluck( $tropes, 'slug' )
				: array();

			$results['shows'][] = array(
				'title'      => get_the_title( $post->ID ),
				'permalink'  => get_permalink( $post->ID ),
				'score'      => $show_score,
				'excerpt'    => $excerpt,
				'characters' => $total_chars,
				'dead'       => $dead_chars,
				'tropes'     => $trope_slugs,
			);
		}

		// Strict Mode: When 0 matches, include canonical message. Filter allows override.
		if ( empty( $results['shows'] ) ) {
			$results['message'] = apply_filters(
				'lwtv_agent_no_results_message',
				"I'm sorry, I can't find any shows that match your request. I'm still learning, though, so try again with a different request.",
				$prompt,
				$context
			);
		}

		lwtv_plugin()->set_transient( $cache_key, $results, HOUR_IN_SECONDS );

		return new \WP_REST_Response( $results, 200 );
	}
}
