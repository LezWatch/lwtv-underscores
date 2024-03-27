<?php
/**
 * Template part for displaying the show or actor related posts
 *
 * This is the code that looks for a tag with the same slug as the actor/show, and displays related posts.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch.TV
 */

$this_id = $args['to_show'] ?? null;

if ( ! $this_id ) {
	return;
}

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
