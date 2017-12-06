<?php
/**
 * The Template for displaying all single actor pages.
 *
 * @package YIKES Starter
 */

// Build the icon
// We don't use this on actors yet. We might later
// use it to show death? Or actors we love? Let's
// keep options open.
$icon  = '<div class="show-header-svg">';
$icon .= '</div>';
 
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
							<div class="entry-content actor-page">
								<div class="card">
									<?php
										while ( have_posts() ) : the_post(); 
											get_template_part( 'template-parts/content', get_post_type() );
											// Force Jetpack to display sharing links where we want them.
											lwtv_yikes_jetpack_post_meta();
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