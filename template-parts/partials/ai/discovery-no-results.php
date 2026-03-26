<?php
/**
 * Template part: AI Discovery No-Results Block
 *
 * Shown when SearchWP returns zero results. Passes failed_query to the AI
 * as context so it can suggest closest matches (e.g. "The L Wood" → "The L Word").
 *
 * @package LWTV Underscores
 *
 * @param string $failed_query The search query that returned no results.
 * @param array  $mood_chips  Optional context-aware mood chips.
 */

$failed_query = $args['failed_query'] ?? get_search_query();
$mood_chips   = $args['mood_chips'] ?? array();

if ( empty( $failed_query ) ) {
	$mood_chips = array_merge(
		$mood_chips,
		array(
			__( 'International web series', 'lwtv-underscores' ),
			__( 'Happy ending drama', 'lwtv-underscores' ),
			__( 'Worth watching British shows', 'lwtv-underscores' ),
		)
	);
}
?>

<div class="lwtv-discovery-no-results-block">
	<p class="lwtv-discovery-no-results-intro">
		<?php
		printf(
			/* translators: %s: the failed search query */
			esc_html__( 'No results for "%s". Try the Discovery Engine for a best match or alternative.', 'lwtv-underscores' ),
			esc_html( $failed_query )
		);
		?>
	</p>

	<?php
	if ( defined( 'LWTV_USE_AGENTS' ) && true === LWTV_USE_AGENTS ) {
		get_template_part(
			'template-parts/partials/ai/discovery-panel',
			null,
			array(
				'context'        => 'no-results',
				'heading'        => __( 'Find your next favorite show', 'lwtv-underscores' ),
				'initial_prompt' => $failed_query,
				'mood_chips'     => $mood_chips,
				'failed_query'   => $failed_query,
			)
		);
	}
	?>
</div>
