<?php
/*
 * Agents
 */
namespace LWTV\_Components;

use ftp;

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
			'get_shows_by_trope_and_score'    => array( $this, 'get_shows_by_trope_and_score' ),
			'get_shows_by_trope_and_realness' => array( $this, 'get_shows_by_trope_and_realness' ),
			'get_shows_by_params'             => array( $this, 'get_shows_by_params' ),
			'present_results_to_ai'           => array( $this, 'present_results_to_ai' ),
		);
	}

	/**
	 * Send database results to Ollama for curated presentation (Curator's Note + formatted show list).
	 *
	 * @param string $user_prompt The user's original request.
	 * @param array  $results     The results array (e.g. from agent REST API: shows, message, context).
	 * @return string|array The AI-formatted response string, or error array on failure.
	 */
	public function present_results_to_ai( string $user_prompt, array $results ) {
		$json   = wp_json_encode( $results );
		$prompt = sprintf(
			"SEARCH COMPLETED. Your task is now to act as the Curator (STEP 2).

			USER REQUEST: %s
			DATABASE RESULTS (JSON): %s

			INSTRUCTIONS:
			1. Ignore STEP 1 (Extraction) as the search is already done.
			2. Use the 'excerpt', 'tropes', 'characters', and 'dead' fields from the JSON above to write your response.
			3. Format your output exactly as defined in your STEP 2 rules (Curator's Note + Show List).",
			$user_prompt,
			$json
		);

		return ( new \LWTV\Agents\Call_AI() )->call_server( $prompt );
	}

	/**
	 * Get shows by params array (trope, genre, format, score, etc.)
	 *
	 * @param array $params Params from parse_prompt or structured input.
	 * @return array Array of WP_Post objects.
	 */
	public function get_shows_by_params( array $params ) {
		return ( new \LWTV\Agents\Get_Shows() )->get_shows_by_params( $params );
	}

	/**
	 * Get shows by trope and realness (lezshows_realness_score)
	 *
	 * @param string|int $trope    Term slug or term ID from lez_tropes taxonomy.
	 * @param int        $realness_score Realness threshold.
	 * @return array
	 */
	public function get_shows_by_trope_and_realness( $trope, $realness_score ) {
		return ( new \LWTV\Agents\Get_Shows() )->get_by_trope_and_realness( $trope, $realness_score );
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
		return ( new \LWTV\Agents\Get_Shows() )->get_by_trope_and_score( $trope, $score, $operator );
	}
}
