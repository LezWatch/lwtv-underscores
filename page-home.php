<?php
/**
 * Template Name: Home page
 *
 * @package LWTV Underscores
 */

get_header(); ?>

<?php
$check_paged = get_query_var( 'page' ) ? get_query_var( 'page' ) : 1;

// Get the 6 newest posts.
$front_posts = new WP_Query(
	array(
		'posts_per_page' => 7,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	)
);

// Get the IDs of the posts in $front_posts
$already_displayed_posts = wp_list_pluck( $front_posts->posts, 'ID' );
?>

<div id="main" tabindex="-1" class="site-main" role="main">
	<?php

	if ( 1 === $check_paged ) {
		?>
		<!-- Home page top section -->
		<section class="home-featured-posts">
			<div class="container">
				<div class="row">
					<!-- Newest posts -->
					<div class="col-sm-8">
						<h2 class="posts-title">
							New Posts <?php echo lwtv_plugin()->get_symbolicon( svg: 'newspaper.svg', icon: 'svg-newspaper' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</h2>

						<?php
						get_template_part(
							'template-parts/content/front-page-posts',
							null,
							array(
								'front_posts'     => $front_posts,
								'front_posts_ids' => $already_displayed_posts,
							)
						);
						?>
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
