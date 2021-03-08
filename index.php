<?php
/**
 * The main template file.
 *
 * @package YIKES Starter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header(); ?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<section class="archive-header">
				<h1 class="entry-title">
					<?php echo wp_kses_post( yikes_starter_blog_page_title() ); ?>
				</h1>
			</section><!-- .archive-header -->
		</div><!-- .container -->
	</div><!-- /.jumbotron -->
</div>

<div id="main" tabindex="-1" class="site-main" role="main">
	<div class="container">
		<div class="row">
			<div class="col-sm-9">
				<div id="primary" class="content-area">
					<div id="content" class="site-content clearfix">
						<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
							<div class="entry-content">
								<?php
								if ( have_posts() ) {
									echo '<div class="row site-loop main-posts-loop">';
									while ( have_posts() ) {
										the_post();
										get_template_part( 'template-parts/content', 'posts' );
									}
									echo '</div>';
									wp_bootstrap_pagination();
								} else {
									get_template_part( 'template-parts/content', 'none' );
								}
								?>
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

<?php

get_footer();
