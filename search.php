<?php
/**
 * The template for displaying Search Results pages.
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

						<?php if ( have_posts() ) : ?>

							<header class="page-header">
								<h1 class="page-title"><?php printf( esc_attr__( 'Search Results for: %s', 'yikes_starter' ), '<span>' . get_search_query() . '</span>' ); ?></h1>
							</header>

							<?php while ( have_posts() ) : the_post(); ?>

								<?php get_template_part( 'template-parts/content', 'search' ); ?>

							<?php endwhile; ?>

							<!-- Alt Bootstrap pagination is <?php page_navi(); ?> -->
							<?php yikes_starter_paging_nav(); ?>

						<?php else : ?>

							<?php get_template_part( 'content', 'none' ); ?>

						<?php endif; ?>
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
