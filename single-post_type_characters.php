<?php
/**
 * The Template for displaying all single character pages.
 *
 * @package YIKES Starter
 */

// Default to a blank icon
$icon = '';
 
get_header(); ?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<header class="showschar-header">
				<?php the_title( '<h1 class="entry-title">', $icon . '</h1>' ); ?>
			</header><!-- .archive-header -->
		</div><!-- .container -->
	</div><!-- /.jumbotron -->
</div>

<div id="main" class="site-main" role="main">
	<div class="container">
		<div class="row">
			<div class="col-sm-8">
				<div id="primary" class="content-area">
					<div id="content" class="site-content clearfix" role="main">						
						<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
							<div class="entry-content">
								<div class="card-group">
									<?php
										while ( have_posts() ) : the_post(); 
											get_template_part( 'template-parts/content', get_post_type() );
										endwhile; // end of the loop. 
									?>
								</div>
							</div><!-- .entry-content -->
						</article><!-- #post-## -->
					</div><!-- #content -->
				</div><!-- #primary -->
			</div><!-- .col-sm-8 -->

			<div class="col-sm-4 site-sidebar site-loop showschar-section">

				<?php get_sidebar(); ?>

			</div><!-- .col-sm-4 -->
		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<?php get_footer();