<?php
/**
 * Template Name: Full-width Page no sidebar
 *
 * @package YIKES Starter
 */
get_header(); ?>


<div id="main" class="site-main" role="main">
	<div class="container">
		<div class="row">
			<div class="col-sm-12">
				<div id="primary" class="content-area">
					<div id="content" class="site-content clearfix" role="main">

						<?php 
							while ( have_posts() ) : the_post();
								get_template_part( 'template-parts/content', 'page' );								
							endwhile;
						?>

					</div><!-- #content -->
				</div><!-- #primary -->
		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<?php get_footer();