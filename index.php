<?php
/**
 * The main template file.
 *
 * @package YIKES Starter
 */
get_header(); ?>

<div id="main" class="site-main" role="main">
	<div class="container">
		<div class="row">
			<div class="col-sm-8">
				<div id="primary" class="content-area">
					<div id="content" class="site-content clearfix" role="main">

						<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
							<header class="entry-header">
								<h1 class="entry-title"><?php esc_attr_e( yikes_starter_blog_page_title() ); ?></h1>
							</header><!-- .entry-header -->

							<div class="entry-content">

								<?php if ( have_posts() ) : ?>

				        			<div class="row site-loop main-posts-loop equal-height">

										<?php while ( have_posts() ) : the_post(); ?>

											<div class="col-sm-6">
												<?php get_template_part( 'template-parts/content', 'posts' ); ?>
											</div>

										<?php endwhile; ?>

									</div>

									<?php wp_bootstrap_pagination(); ?>

								<?php else : ?>

									<?php get_template_part( 'content', 'none' ); ?>

								<?php endif; ?>

							</div>
						</article><!-- #post-## -->
					</div><!-- #content -->
				</div><!-- #primary -->
			</div><!-- .col-sm-8 -->

			<div class="col-sm-4 site-sidebar site-loop">

				<?php get_sidebar(); ?>

			</div><!-- .col-sm-4 -->
		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<?php get_footer(); ?>
