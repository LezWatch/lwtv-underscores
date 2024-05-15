<?php
/**
 * Overlay for related articles.
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
<div class="col">
	<div class="card text-center">
		<span data-bs-toggle="modal" data-bs-target="#articlesModal">
			<?php echo lwtv_symbolicons( 'newspaper.svg', 'fa-newspaper' ); ?>
			<p>Related Articles</p>
		</span>
	</div>
</div>

<div class="modal fade" id="articlesModal" tabindex="-1" aria-labelledby="articlesModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h3 class="modal-title fs-5" id="articlesModalLabel">Related Articles</h3>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
			<section name="related-posts" id="related-posts" class="relatedposts-section">
				<div class="container"><div class="card-body">
				<?php
				$max_posts     = 6;
				$related_posts = lwtv_plugin()->get_cpt_related_posts( (int) $this_id, $max_posts, 'overlay' );

				foreach ( $related_posts['posts'] as $related_post ) {
					?>
					<div class="card">
						<div class="card-body">
							<h5 class="card-title">
								<a href="<?php echo esc_url( get_permalink( $related_post ) ); ?>">
									<?php echo esc_html( get_the_title( $related_post ) ); ?>
								</a>
							</h5>
							<p class="card-text">
								<?php echo esc_html( get_the_excerpt( $related_post ) ); ?>
							</p>
						</div>
					</div>
					<?php
				}
				// Read More Link if needed:
				if ( $related_posts['total'] > $max_posts ) {
					$slug     = get_post_field( 'post_name', get_post( $this_id ) );
					$get_tags = term_exists( $slug, 'post_tag' );
					if ( ! is_null( $get_tags ) && $get_tags >= 1 ) {
						echo '<p class="read-more"><a href="' . esc_url( get_tag_link( $get_tags['term_id'] ) ) . '" class="btn btn-outline-primary">Read More ...</a></p>';
					}
				}
				?>

				</div></div>
			</section>
			</div>
		</div>
	</div>
</div>

