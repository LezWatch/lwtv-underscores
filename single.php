<?php
/**
 * The Template for displaying all single posts.
 *
 * @package YIKES Starter
 */

get_header(); ?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<header class="archive-header">
				<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
			</header><!-- .archive-header -->
		</div><!-- .container -->
	</div><!-- /.jumbotron -->
</div>

<div id="main" tabindex="-1" class="site-main" role="main">
	<div class="container">
		<div class="row">
			<div class="col-sm-9">
				<div id="primary" class="content-area">
					<div id="content" class="site-content clearfix" role="main">
						<?php
						while ( have_posts() ) :
							the_post();
							// Check for custom post types.
							if ( 'post' === get_post_type() ) {
								get_template_part( 'template-parts/content/single' );
							} else {
								// NOTE! We use single-post_type_{shows|character}.php for the
								// individual CPTs.
								get_template_part( 'template-parts/content/' . get_post_type() );
							}

							// Force Jetpack to display sharing links where we want them.
							lwtv_yikes_jetpack_post_meta();

							// If comments are open or we have at least one comment, load up the comment template.
							if ( comments_open() || '0' !== get_comments_number() ) {
								comments_template();
							}

							// Only show post nav on posts (to not break facet).
							if ( 'post' === get_post_type() ) {
								yikes_starter_post_nav();
							}

						endwhile;
						// end of the loop.
						?>
					</div><!-- #content -->
				</div><!-- #primary -->
			</div><!-- .col-sm-9 -->

			<div class="col-sm-3 site-sidebar site-loop">

				<?php get_sidebar(); ?>

			</div><!-- .col-sm-3 -->
		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<?php

get_footer();
