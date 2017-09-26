<?php
/**
 * The template for displaying 404 pages (Not Found).
 *
 * @package YIKES Starter
 */

get_header(); ?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<header class="archive-header">
				<h1 class="page-title">
					<?php esc_attr_e( 'Oops! This isn\'t the page you thought it was.', 'yikes_starter' ); ?>
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
						<article id="post-0" class="post not-found">
							<div class="entry-content clearfix">
								<p>
									<img src="<?php echo get_template_directory_uri(); ?>/images/rose.gif" alt="Rose from Jane the Virgin" />
								</p>
								<p>
									<?php esc_attr_e( 'Sorry, there is no page with this address. Please try again or use the search below.', 'yikes_starter' ); ?>										
								</p>

								<div class="row no-gutters">
									<div class="col-sm-8">
										<?php get_search_form(); ?>
									</div>
								</div>
							</div><!-- .entry-content -->
						</article><!-- #post-0 .post .not-found -->
					</div><!-- #content -->
				</div><!-- #primary -->
			</div><!-- .col -->
		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<?php get_footer();