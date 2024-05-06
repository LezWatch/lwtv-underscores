<?php
/**
 * Template part for displaying the character or actor image
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
<button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#articlesModal">
	Related Articles
</button>

<div class="modal fade" id="articlesModal" tabindex="-1" aria-labelledby="articlesModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h3 class="modal-title fs-5" id="articlesModalLabel">Related Articles</h3>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
			<section name="related-posts" id="related-posts" class="relatedposts-section">
				<div class="container"><div class="card-body">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput
				echo lwtv_plugin()->get_cpt_related_posts( (int) $this_id, 'overlay' );
				?>
				</div></div>
			</section>
			</div>
		</div>
	</div>
</div>

