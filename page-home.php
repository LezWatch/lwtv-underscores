<?php
/**
 * Template Name: Home page
 *
 * @package YIKES Starter
 */

get_header(); ?>

<?php
$check_paged             = get_query_var( 'page' ) ? get_query_var( 'page' ) : 1;
$already_displayed_posts = array();
?>

<div id="main" tabindex="-1" class="site-main" role="main">
	<?php

	if ( 1 === $check_paged ) {
		// Get the 6 newest posts.
		$front_posts = new WP_Query(
			array(
				'posts_per_page' => 6,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);
		?>

		<!-- Home page top section -->
		<section class="home-featured-posts">
			<div class="container">
				<div class="row">
					<!-- Newest posts -->
					<div class="col-sm-8">
						<div class="site-loop home-featured-posts-loop">
							<h2 class="posts-title">
								New Posts <?php echo lwtv_plugin()->get_symbolicon( svg: 'newspaper.svg', icon: 'svg-newspaper' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</h2>

							<?php
							$post_counter = 0;
							if ( $front_posts->have_posts() ) :
								while ( $front_posts->have_posts() ) :
									$front_posts->the_post();
									++$post_counter;
									$already_displayed_posts[] = get_the_ID();

									// First post gets featured layout
									if ( 1 === $post_counter ) :
										?>
										<div class="card">
											<?php
											if ( has_post_thumbnail() ) :
												?>
												<a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>" >
													<?php the_post_thumbnail( 'large', array( 'class' => 'card-img-top' ) ); ?>
												</a>
												<?php
											endif;
											?>
											<div class="card-body">
												<h3 class="card-title"><?php the_title(); ?></h3>
												<div class="card-meta text-muted">
													<?php the_date(); ?>
													<?php echo lwtv_plugin()->get_symbolicon( svg: 'user-circle.svg', icon: 'svg-user-circle', max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
													<?php the_author(); ?>
												</div>
												<div class="card-text">
													<?php the_excerpt(); ?>
												</div>
											</div><!-- .card-body -->
											<div class="card-footer">
												<a href="<?php the_permalink(); ?>" class="btn btn-outline-primary">
													Read More <span class="screen-reader-text">about <?php the_title(); ?></span>
												</a>
											</div><!-- .card-footer -->
										</div><!-- .card -->
										<?php
									else :
										// Posts 2-6 get secondary layout
										?>
										<div class="card-group">
											<div class="card col-sm-5"
												<?php
												if ( has_post_thumbnail() ) {
													$alt_src = get_post_meta( get_the_ID(), '_wp_attachment_image_alt', true );
													$alt_txt = ( isset( $alt_src ) && '' !== $alt_src ) ? $alt_src : get_the_title();
													?>
													style="background-image: url(<?php the_post_thumbnail_url( 'large' ); ?>);"
													<?php
												}
												?>
												>
											</div>
											<div class="card col-sm-7">
												<div class="card-body">
													<h3 class="card-title"><?php the_title(); ?></h3>
													<div class="card-meta text-muted">
														<?php the_date(); ?>
														<?php echo lwtv_plugin()->get_symbolicon( svg: 'user-circle.svg', icon: 'svg-user-circle', max_size: '20' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
														<?php the_author(); ?>
													</div>
													<div class="card-text">
														<?php the_excerpt(); ?>
													</div>
												</div>
												<div class="card-footer">
													<a href="<?php the_permalink(); ?>" class="btn btn-sm btn-outline-primary">
														Read More <span class="screen-reader-text">about <?php the_title(); ?></span>
													</a>
												</div>
											</div><!-- .card -->
										</div><!-- .card-group -->
										<?php
									endif;
								endwhile;
							else :
								// Fallback if no posts found
								?>
								<div class="alert alert-info">
									<p>No recent posts found. Please check back later!</p>
								</div>
								<?php
							endif;

							wp_reset_postdata();
							?>
						</div><!-- .home-featured-posts-loop -->
					</div><!-- .col-sm-8 -->

					<!-- Home Page Sidebar -->
					<div class="col-sm-4 site-sidebar site-loop">
						<?php dynamic_sidebar( 'sidebar-1' ); ?>
					</div>
				</div><!-- .row -->
			</div><!-- .container -->
		</section>

		<!-- Shows We Love -->
		<section class="home-featured-shows">
			<div class="container">
				<div class="row">
					<div class="col">
						<h2>Shows We Love <?php echo lwtv_plugin()->get_symbolicon( svg: 'hearts.svg', icon: 'svg-heart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
					</div>
				</div>
				<?php
				$class = ( 1 === $check_paged ) ? '' : 'four-across-loop';
				?>
				<div class="row site-loop shows-we-love-loop <?php echo esc_attr( $class ); ?>">
					<?php get_template_part( 'template-parts/content/loved' ); ?>
				</div><!-- .row -->
			</div><!-- .container -->
		</section>

		<?php
	} // End of first page only code.
	?>

	<!-- Older Posts -->
	<section class="home-older-posts">
		<div class="container">
			<div class="row">
				<div class="col">
					<h2 class="posts-title">
						More Posts <?php echo lwtv_plugin()->get_symbolicon( svg: 'newspaper.svg', icon: 'svg-newspaper' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</h2>
				</div>
			</div>
			<?php
			$class = ( 1 === $check_paged ) ? '' : 'four-across-loop';
			?>
			<div class="row site-loop main-posts-loop <?php echo esc_attr( $class ); ?>">
				<?php

				$old_posts_per_page = ( 1 === $check_paged ) ? '6' : '12';

				$oldpostsloop = new WP_Query(
					array(
						'posts_per_page' => $old_posts_per_page,
						'paged'          => $check_paged,
						'post__not_in'   => $already_displayed_posts,
						'orderby'        => 'date',
						'order'          => 'DESC',
						'no_found_rows'  => true,
					)
				);
				?>

				<!-- // The Loop -->
				<?php
				if ( $oldpostsloop->have_posts() ) :
					while ( $oldpostsloop->have_posts() ) :
						$oldpostsloop->the_post();
						get_template_part( 'template-parts/content/posts' );
					endwhile;
				else :
					// Fallback if no older posts found
					?>
					<div class="col-12">
						<div class="alert alert-info">
							<p>No additional posts found. Please check back later!</p>
						</div>
					</div>
					<?php
				endif;

				wp_reset_postdata();
				?>
			</div><!-- .row .home-featured-post-loop -->

			<?php lwtv_generate_pagination_buttons( $check_paged, $oldpostsloop->max_num_pages ); ?>
		</div><!-- .container -->
	</section>
</div><!-- #main -->

<?php get_footer(); ?>
