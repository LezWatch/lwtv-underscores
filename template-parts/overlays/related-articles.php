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
		<span data-bs-toggle="modal" data-bs-target="#articles" id="articles-modal">
			<h5><?php echo lwtv_plugin()->get_symbolicon( svg: 'newspaper.svg', icon: 'svg-newspaper' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> Related Articles</h5>
		</span>
	</div>
</div>

<div class="modal fade" data-modal-type="overlay" id="articles" tabindex="-1" aria-labelledby="articlesLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h3 class="modal-title fs-5" id="articlesLabel">Related Articles</h3>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body lwtv-actor-articles-modal">
				<?php
				$lwtv_max_posts     = 6;
				$lwtv_related_posts = lwtv_plugin()->get_cpt_related_posts( (int) $this_id, $lwtv_max_posts, 'overlay' );
				$lwtv_total_posts   = (int) ( $lwtv_related_posts['total'] ?? 0 );
				$lwtv_cat_class_map = array(
					'news'        => 'pink',
					'site'        => 'blue',
					'queer-beats' => 'dkpink',
				);
				?>
				<p class="lwtv-articles-intro">
					<?php
					/* translators: %s: number of related articles. */
					printf( esc_html( _n( '%s article tagged with this actor on the LezWatch.TV blog.', '%s articles tagged with this actor on the LezWatch.TV blog.', $lwtv_total_posts, 'lwtv' ) ), esc_html( number_format_i18n( $lwtv_total_posts ) ) );
					?>
				</p>

				<div class="lwtv-article-list">
					<?php
					foreach ( $lwtv_related_posts['posts'] as $lwtv_related_post ) {
						$lwtv_post_obj = get_post( $lwtv_related_post );
						if ( ! $lwtv_post_obj ) {
							continue;
						}
						$lwtv_pid   = $lwtv_post_obj->ID;
						$lwtv_link  = get_the_permalink( $lwtv_pid );
						$lwtv_cats  = get_the_category( $lwtv_pid );
						$lwtv_cat   = ! empty( $lwtv_cats ) ? $lwtv_cats[0] : null;
						$lwtv_ccls  = ( $lwtv_cat && isset( $lwtv_cat_class_map[ $lwtv_cat->slug ] ) ) ? $lwtv_cat_class_map[ $lwtv_cat->slug ] : 'grey';
						$lwtv_thumb = get_the_post_thumbnail(
							$lwtv_pid,
							'medium',
							array(
								'class'   => 'lwtv-article-thumb-img',
								'loading' => 'lazy',
							)
						);
						?>
						<article class="lwtv-article-card">
							<a class="lwtv-article-thumb" href="<?php echo esc_url( $lwtv_link ); ?>" tabindex="-1" aria-hidden="true">
								<?php
								if ( $lwtv_thumb ) {
									echo $lwtv_thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core markup.
								} else {
									?>
									<span class="lwtv-article-thumb-empty"><?php echo lwtv_plugin()->get_symbolicon( svg: 'camera.svg', icon: 'svg-camera', max_size: '22' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<?php
								}
								if ( $lwtv_cat ) {
									?>
									<span class="lwtv-article-tag lwtv-article-tag--<?php echo esc_attr( $lwtv_ccls ); ?>"><?php echo esc_html( $lwtv_cat->name ); ?></span>
									<?php
								}
								?>
							</a>
							<div class="lwtv-article-info">
								<h4 class="lwtv-article-title"><a href="<?php echo esc_url( $lwtv_link ); ?>"><?php echo esc_html( get_the_title( $lwtv_pid ) ); ?></a></h4>
								<p class="lwtv-article-excerpt"><?php echo esc_html( get_the_excerpt( $lwtv_pid ) ); ?></p>
								<p class="lwtv-article-date">
									<?php echo lwtv_plugin()->get_symbolicon( svg: 'calendar-alt.svg', icon: 'svg-calendar', max_size: '13' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									<span><?php echo esc_html( get_the_date( get_option( 'date_format' ), $lwtv_pid ) ); ?></span>
								</p>
							</div>
						</article>
						<?php
					}

					if ( $lwtv_total_posts > $lwtv_max_posts ) {
						$lwtv_slug = get_post_field( 'post_name', get_post( $this_id ) );
						$lwtv_tag  = term_exists( $lwtv_slug, 'post_tag' );
						if ( ! is_null( $lwtv_tag ) && is_array( $lwtv_tag ) ) {
							?>
							<a class="lwtv-articles-foot" href="<?php echo esc_url( get_tag_link( $lwtv_tag['term_id'] ) ); ?>"><?php esc_html_e( 'See all related coverage', 'lwtv' ); ?> <span aria-hidden="true">&rarr;</span></a>
							<?php
						}
					}
					?>
				</div>
			</div>
		</div>
	</div>
</div>

