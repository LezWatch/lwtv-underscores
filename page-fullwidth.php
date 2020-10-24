<?php
/**
 * Template Name: Full-width Page no sidebar
 *
 * @package YIKES Starter
 */

get_header(); ?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<header class="archive-header">
				<h1 class="entry-title">
					<?php the_title(); ?></h1>
				</h1>
			</header><!-- .archive-header -->
		</div><!-- .container -->
	</div><!-- /.jumbotron -->
</div>

<div id="main" tabindex="-1" class="site-main" role="main">
	<div class="container">
		<div class="row">
			<div class="col-sm-12">
				<div id="primary" class="content-area">
					<div id="content" class="site-content clearfix" role="main">
						<?php
						while ( have_posts() ) :
							the_post();
							get_template_part( 'template-parts/content', 'page' );
						endwhile;
						?>
					</div><!-- #content -->
				</div><!-- #primary -->
		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<?php

get_footer();
