<?php
/**
 * The template for displaying Archive pages.
 *
 * @package YIKES Starter
 */

get_header(); ?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<header class="archive-header">
				<?php
					the_archive_title( '<h1 class="page-title">', '</h1>' );
					the_archive_description( '<div class="taxonomy-description">', '</div>' );
				?>
			</header><!-- .archive-header -->
		</div><!-- .container -->
	</div><!-- /.jumbotron -->
</div>

<div id="main" class="site-main" role="main">
	<div class="container">
		<div class="row">
			<div class="col-sm-9">
				<div id="primary" class="content-area">
					<div id="content" class="site-content clearfix" role="main">

						<?php if ( have_posts() ) : ?>

							<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>	
								<div class="entry-content">
				        			<div class="row site-loop main-posts-loop equal-height">

										<?php while ( have_posts() ) : the_post(); ?>

											<div class="col-sm-4">
												<?php get_template_part( 'template-parts/content', 'posts' ); ?>
											</div>

										<?php endwhile; ?>

									</div>

									<?php wp_bootstrap_pagination(); ?>

									<?php else : ?>

										<?php get_template_part( 'template-parts/content', 'none' ); ?>

									<?php endif; ?>

								</div>
							</article><!-- #post-## -->
					</div><!-- #content -->
				</div><!-- #primary -->
			</div><!-- .col-sm-9 -->

			<div class="col-sm-3 site-sidebar site-loop">

				<?php get_sidebar(); ?>

			</div><!-- .col-sm-3 -->
		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->


<?php get_footer();