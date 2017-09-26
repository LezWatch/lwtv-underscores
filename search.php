<?php
/**
 * The template for displaying Search Results pages.
 *
 * @package YIKES Starter
 */
get_header(); ?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<header class="archive-header">
				<h1 class="page-title">
					<?php printf( esc_attr__( 'Search Results for: %s', 'yikes_starter' ), '<span>' . get_search_query() . '</span>' ); ?>
				</h1>
			</header><!-- .archive-header -->
		</div><!-- .container -->
	</div><!-- /.jumbotron -->
</div>

<div id="main" class="site-main" role="main">
	<div class="container">
		<div class="row">
			<div class="col">
				<div id="primary" class="content-area">
					<div id="content" class="site-content clearfix" role="main">

						<?php if ( have_posts() ) : ?>

		        			<div class="row site-loop main-posts-loop equal-height">

								<?php while ( have_posts() ) : the_post(); ?>

									<div class="col-sm-4">
										<?php get_template_part( 'template-parts/content', 'search' ); ?>
									</div>

								<?php endwhile; ?>
							</div>

							<!-- Alt Bootstrap pagination is page_navi() -->
							<?php yikes_starter_paging_nav(); ?>

						<?php else : ?>

							<?php get_template_part( 'template-parts/content', 'none' ); ?>

						<?php endif; ?>
					</div><!-- #content -->
				</div><!-- #primary -->
			</div><!-- .col -->
		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<?php get_footer();