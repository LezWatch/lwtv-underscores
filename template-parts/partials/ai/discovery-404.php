<?php
/**
 * Template part: AI Discovery 404 Block
 *
 * Inline Discovery panel for 404 pages. Primary CTA with search form as secondary.
 *
 * @package LWTV Underscores
 *
 * @param array $mood_chips Optional context-aware mood chips.
 */

$mood_chips = $args['mood_chips'] ?? array(
	__( "I'm lost—just show me the highest-rated shows right now", 'lwtv-underscores' ),
	__( 'International web series', 'lwtv-underscores' ),
	__( 'Happy ending drama', 'lwtv-underscores' ),
	__( 'Worth watching British shows', 'lwtv-underscores' ),
);
?>

<div class="lwtv-discovery-404-block">
	<p class="lwtv-discovery-404-intro">
		<?php esc_html_e( "We couldn't find that page, but tell us what you're in the mood for and we'll search the database for you.", 'lwtv-underscores' ); ?>
	</p>

	<?php
	get_template_part(
		'template-parts/partials/ai/discovery-panel',
		null,
		array(
			'context'        => '404',
			'heading'        => __( 'Find your next favorite show', 'lwtv-underscores' ),
			'initial_prompt' => '',
			'mood_chips'     => $mood_chips,
			'failed_query'   => '',
		)
	);
	?>

	<p class="lwtv-discovery-404-search-label"><?php esc_html_e( 'Or try searching:', 'lwtv-underscores' ); ?></p>
	<?php get_search_form(); ?>
</div>
