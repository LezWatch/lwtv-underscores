<?php
/**
 * Template part for displaying the character or actor related posts
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

$this_id = $args['to_show'] ?? null;

$related = lwtv_plugin()->has_cpt_related_posts( (int) $this_id );

// Related Posts.
if ( isset( $related ) && $related ) {
	?>
	<section name="related-posts" id="related-posts" class="relatedposts-section">
		<h2>Related Articles</h2>
		<div class="container"><div class="card-body">
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput
		echo lwtv_plugin()->get_cpt_related_posts( (int) $this_id );
		?>
		</div></div>
	</section>
	<?php
}
