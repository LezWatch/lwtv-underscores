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
	<?php if ( 1 === $check_paged ) { ?>

	<!-- Home page top section -->
	<section class="home-featured-posts">
		<div class="container">
			<div class="row">
				<!-- Newest posts -->
				<div class="col-sm-8">
					<div class="site-loop home-featured-post-loop">
						<h2 class="posts-title">
							New Posts <?php echo lwtv_plugin()->get_symbolicon( 'newspaper.svg', 'fa-newspaper' ); ?>
						</h2>

						<?php
						$lastpostloop = new WP_Query(
							array(
								'posts_per_page' => '1',
								'orderby'        => 'date',
								'order'          => 'DESC',
								'no_found_rows'  => true,
							)
						);
						?>

						<!-- // The Loop -->
						<?php
						while ( $lastpostloop->have_posts() ) :
							$lastpostloop->the_post();
							$already_displayed_posts[] = get_the_ID();
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
										<?php echo lwtv_plugin()->get_symbolicon( 'user-circle.svg', 'fa-user-circle' ); ?>
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
						endwhile;

						wp_reset_postdata();
						?>
					</div>

					<div class="site-loop home-featured-secondary-loop">
						<?php
						$newpostsloop = new WP_Query(
							array(
								'posts_per_page' => '5',
								'offset'         => '1',
								'orderby'        => 'date',
								'order'          => 'DESC',
								'no_found_rows'  => true,
							)
						);
						?>

						<!-- // The Loop -->
						<?php
						while ( $newpostsloop->have_posts() ) :
							$newpostsloop->the_post();
							$already_displayed_posts[] = get_the_ID();
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
											<?php echo lwtv_plugin()->get_symbolicon( 'user-circle.svg', 'fa-user-circle' ); ?>
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
						endwhile;

						wp_reset_postdata();
						?>
					</div><!-- .home-featured-secondary-loop -->
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
					<h2>Shows We Love <?php echo lwtv_plugin()->get_symbolicon( 'hearts.svg', 'fa-heart' ); ?></h2>
				</div>
			</div>
			<?php
			$class = ( 1 === $check_paged ) ? '' : 'four-across-loop';
			?>
			<div class="row site-loop shows-we-love-loop <?php echo esc_attr( $class ); ?>">
				<?php
				// Collect 30 loved posts (max) and then pick 3.
				$lovedpostloop = new WP_Query(
					array(
						'post_type'      => 'post_type_shows',
						'posts_per_page' => '30',
						'post_status'    => array( 'publish' ),
						'no_found_rows'  => true,
						'_loved_shuffle' => 3,
						'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery -- Risk of slow query accepted.
							array(
								'key'     => 'lezshows_worthit_show_we_love',
								'value'   => 'on',
								'compare' => '=',
							),
						),
					)
				);

				while ( $lovedpostloop->have_posts() ) :
					$lovedpostloop->the_post();
					?>
					<?php get_template_part( 'template-parts/content/loved' ); ?>
					<?php
				endwhile;
				wp_reset_postdata();
				?>
				<!-- End Loop -->
			</div><!-- .row -->
		</div><!-- .container -->
	</section>

	<?php } ?>

	<!-- Older Posts -->
	<section class="home-older-posts">
		<div class="container">
			<div class="row">
				<div class="col">
					<h2 class="posts-title">
						More Posts <?php echo lwtv_plugin()->get_symbolicon( 'newspaper.svg', 'fa-newspaper' ); ?>
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
					)
				);
				?>

				<!-- // The Loop -->
				<?php
				while ( $oldpostsloop->have_posts() ) :
					$oldpostsloop->the_post();
					?>

					<?php get_template_part( 'template-parts/content/posts' ); ?>
					<?php
				endwhile;

				wp_reset_postdata();
				?>
			</div><!-- .row .home-featured-post-loop -->

			<?php yikes_generate_pagination_buttons( $check_paged, $oldpostsloop->max_num_pages ); ?>
		</div><!-- .container -->
	</section>
</div><!-- #main -->

<?php get_footer(); ?>
