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
			'get_shows_by_params'          => array( $this, 'get_shows_by_params' ),
			'build_agent_query_args'       => array( $this, 'build_agent_query_args' ),
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
	 * Backward-compatible wrapper for get_shows_by_params.
	 *
	 * @param string|int $trope    Term slug or term ID from lez_tropes taxonomy.
	 * @param int        $score    Score threshold.
	 * @param string     $operator Comparison operator: '>=' (min score) or '<=' (max score). Default '>='.
	 * @return array
	 */
	public function get_shows_by_trope_and_score( $trope, $score, $operator = '>=' ) {
		$params = array(
			'trope'    => $trope,
			'score'    => $score,
			'score_op' => in_array( $operator, array( '>=', '<=' ), true ) ? $operator : '>=',
		);

		return $this->get_shows_by_params( $params );
	}

	/**
	 * Build WP_Query args from agent params array
	 *
	 * @param array $params Params with keys: trope, genre, format, country, station, stars, triggers, intersections,
	 *                      score, score_op, worthit, year_min, year_max.
	 * @return array WP_Query-compatible args.
	 */
	public function build_agent_query_args( array $params ): array {
		$tax_clauses  = array();
		$meta_clauses = array();

		$taxonomies = array(
			'trope'         => 'lez_tropes',
			'genre'         => 'lez_genres',
			'format'        => 'lez_formats',
			'country'       => 'lez_country',
			'station'       => 'lez_stations',
			'stars'         => 'lez_stars',
			'triggers'      => 'lez_triggers',
			'intersections' => 'lez_intersections',
		);

		foreach ( $taxonomies as $param_key => $taxonomy ) {
			$value = $params[ $param_key ] ?? null;
			if ( null === $value || '' === $value ) {
				continue;
			}

			$term = is_numeric( $value )
				? get_term_by( 'id', (int) $value, $taxonomy )
				: get_term_by( 'slug', $value, $taxonomy );

			if ( $term && ! is_wp_error( $term ) ) {
				$tax_clauses[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => array( $term->term_id ),
				);
			}
		}

		// Trope exclusion (e.g. "without Bury Your Gays")
		$trope_exclude = $params['trope_exclude'] ?? null;
		if ( ! empty( $trope_exclude ) ) {
			$exclude_ids = array();
			$slugs       = is_array( $trope_exclude ) ? $trope_exclude : array( $trope_exclude );
			foreach ( $slugs as $slug ) {
				$term = is_numeric( $slug )
					? get_term_by( 'id', (int) $slug, 'lez_tropes' )
					: get_term_by( 'slug', $slug, 'lez_tropes' );
				if ( $term && ! is_wp_error( $term ) ) {
					$exclude_ids[] = $term->term_id;
				}
			}
			if ( ! empty( $exclude_ids ) ) {
				$tax_clauses[] = array(
					'taxonomy' => 'lez_tropes',
					'field'    => 'term_id',
					'terms'    => $exclude_ids,
					'operator' => 'NOT IN',
				);
			}
		}

		// Score meta
		$score = $params['score'] ?? null;
		if ( null !== $score && is_numeric( $score ) ) {
			$score_op       = $params['score_op'] ?? '>=';
			$compare        = in_array( $score_op, array( '>=', '<=' ), true ) ? $score_op : '>=';
			$meta_clauses[] = array(
				'key'     => 'lezshows_the_score',
				'value'   => (int) $score,
				'compare' => $compare,
				'type'    => 'NUMERIC',
			);
		}

		// Worth it meta (stored as Yes/Meh/No/TBD per CMB2 THUMBS)
		$worthit = $params['worthit'] ?? null;
		if ( null !== $worthit && in_array( $worthit, array( 'yes', 'no', 'meh', 'tbd' ), true ) ) {
			$worthit_stored = array(
				'yes' => 'Yes',
				'no'  => 'No',
				'meh' => 'Meh',
				'tbd' => 'TBD',
			)[ $worthit ];
			$meta_clauses[] = array(
				'key'     => 'lezshows_worthit_rating',
				'value'   => $worthit_stored,
				'compare' => '=',
			);
		}

		$query_args = array(
			'post_type'      => 'post_type_shows',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		);

		if ( ! empty( $tax_clauses ) ) {
			$query_args['tax_query'] = array_merge( array( 'relation' => 'AND' ), $tax_clauses );
		}

		if ( ! empty( $meta_clauses ) ) {
			$query_args['meta_query'] = array_merge( array( 'relation' => 'AND' ), $meta_clauses );
		}

		return $query_args;
	}

	/**
	 * Get shows by params array (trope, genre, format, score, etc.)
	 *
	 * Optionally applies year filter via PHP post-filter when year_min/year_max are set.
	 *
	 * @param array $params Params from parse_prompt or structured input.
	 * @return array Array of WP_Post objects.
	 */
	public function get_shows_by_params( array $params ): array {
		$query_args = $this->build_agent_query_args( $params );
		$posts      = get_posts( $query_args );

		$year_min = isset( $params['year_min'] ) && is_numeric( $params['year_min'] ) ? (int) $params['year_min'] : null;
		$year_max = isset( $params['year_max'] ) && is_numeric( $params['year_max'] ) ? (int) $params['year_max'] : null;

		if ( ( null !== $year_min || null !== $year_max ) && ! empty( $posts ) ) {
			$posts = array_filter(
				$posts,
				function ( $post ) use ( $year_min, $year_max ) {
					$airdates = get_post_meta( $post->ID, 'lezshows_airdates', true );
					if ( ! is_array( $airdates ) || ! isset( $airdates['start'] ) || ! isset( $airdates['finish'] ) ) {
						return false;
					}
					$start  = (int) $airdates['start'];
					$finish = 'current' === $airdates['finish'] ? (int) gmdate( 'Y' ) : (int) $airdates['finish'];

					if ( null !== $year_min && $finish < $year_min ) {
						return false;
					}
					if ( null !== $year_max && $start > $year_max ) {
						return false;
					}
					return true;
				}
			);
		}

		return array_values( $posts );
	}
}
