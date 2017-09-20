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
			<div class="col-sm-9">

				<div id="primary" class="content-area">
					<div id="content" class="site-content clearfix" role="main">

						<?php while ( have_posts() ) : the_post(); ?>

							<?php get_template_part( 'content', 'page' ); ?>

						<?php endwhile; // end of the loop. ?>

					</div><!-- #content -->
				</div><!-- #primary -->
				
			</div><!-- .col-sm-9 -->

			<div class="col-sm-3">

				<?php get_sidebar(); ?>

			</div><!-- .col-sm-3 -->
		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<?php get_footer(); ?>
