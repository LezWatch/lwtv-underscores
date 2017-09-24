<?php
/**
 * The template for displaying 404 pages (Not Found).
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
						<article id="post-0" class="post not-found">
							<header class="entry-header">
								<h1 class="entry-title"><?php esc_attr_e( 'Oops! That page can&rsquo;t be found.', 'yikes_starter' ); ?></h1>
							</header><!-- .entry-header -->

							<div class="entry-content clearfix">
								<p><?php esc_attr_e( 'Sorry, nothing was found at this address. Please try again or use the search below.', 'yikes_starter' ); ?></p>

								<div class="col-sm-6">
									<?php get_search_form(); ?>
								</div>
							</div><!-- .entry-content -->
						</article><!-- #post-0 .post .not-found -->
					</div><!-- #content -->
				</div><!-- #primary -->
			</div><!-- .col-sm-8 -->

			<div class="col-sm-4 site-sidebar site-loop">

				<?php get_sidebar(); ?>

			</div><!-- .col-sm-4 -->
		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<?php get_footer();