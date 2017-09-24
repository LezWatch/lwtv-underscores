<?php
/**
 * Template Name: Sidebar on right, content on left
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

						<?php while ( have_posts() ) : the_post(); ?>

							<?php get_template_part( 'template-parts/content', 'page' ); ?>

						<?php endwhile; // end of the loop. ?>

					</div><!-- #content -->
				</div><!-- #primary -->
				
			</div><!-- .col-sm-8 -->

			<div class="col-sm-4">

				<?php get_sidebar(); ?>

			</div><!-- .col-sm-4 -->
		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<?php get_footer(); ?>
