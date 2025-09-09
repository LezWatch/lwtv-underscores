<?php

namespace LWTV\Statistics\Build;

use LWTV\Statistics\Build\Taxonomy_Optimized as Build_Taxonomy;

class Dead_Basic {

	/**
	 * Statistics Basic death - Optimized wrapper for death statistics
	 *
	 * Simple wrapper that delegates to optimized Build_Taxonomy class
	 * and formats output for different use cases (characters/shows)
	 *
	 * @param string $subject - whatever we're working with (characters/shows)
	 * @param string $output  - Array or Count
	 *
	 * @return array|int
	 */
	public function make( $subject, $output ) {
		try {
			// Validate input parameters
			if ( ! in_array( $subject, array( 'characters', 'shows' ), true ) ) {
				lwtv_plugin()->error_log( 'dead-basic-error', "Invalid subject: {$subject}" );
				return 'count' === $output ? 0 : array();
			}

			if ( ! in_array( $output, array( 'array', 'count' ), true ) ) {
				lwtv_plugin()->error_log( 'dead-basic-error', "Invalid output: {$output}" );
				return 'count' === $output ? 0 : array();
			}

			// Configure taxonomy and terms based on subject
			switch ( $subject ) {
				case 'characters':
					$taxonomy = 'lez_cliches';
					$terms    = 'dead';
					break;
				case 'shows':
					$taxonomy = 'lez_tropes';
					$terms    = 'dead-queers';
					break;
			}

			// Get data from optimized Build_Taxonomy class
			$array = ( new Build_Taxonomy() )->make( 'post_type_' . $subject, $taxonomy, $terms );

			// Format output based on subject
			switch ( $subject ) {
				case 'characters':
					$array['dead'] = array(
						'count' => $array['dead']['count'] ?? 0,
						'name'  => 'Dead Characters',
						'url'   => home_url( '/cliche/dead/' ),
					);
					$count         = $array['dead']['count'] ?? 0;
					break;
				case 'shows':
					$array['dead-queers'] = array(
						'count' => $array['dead-queers']['count'] ?? 0,
						'name'  => 'Shows with Dead',
						'url'   => home_url( '/trope/dead-queers/' ),
					);
					$count                = $array['dead-queers']['count'] ?? 0;
					break;
			}

			// Return formatted output
			return 'count' === $output ? $count : $array;

		} catch ( \Exception $e ) {
			lwtv_plugin()->error_log( 'dead-basic-error', 'Error building dead basic statistics: ' . $e->getMessage() );
			return 'count' === $output ? 0 : array();
		}
	}
}
