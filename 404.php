<?php
/**
 * The template for displaying 404 pages (Not Found).
 *
 * @package YIKES Starter
 */

get_header();
?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<section class="archive-header">
				<div class="row">
					<div class="col-10"><h1 class="entry-title"><?php esc_attr_e( 'Oops! This isn\'t the page you thought it was.', 'lwtv-underscores' ); ?></h1></div>
					<div class="col-2 icon plain"><span role="img" aria-label="404" title="404 - Page Not Found" class="taxonomy-svg 404"><?php echo lwtv_symbolicons( 'easter-egg-alt.svg', 'fa-gift' ); ?></span></div>
				</div>
			</section><!-- .archive-header -->
		</div><!-- .container -->
	</div><!-- /.jumbotron -->
</div>

<div id="main" tabindex="-1" class="site-main" role="main">
	<div class="container">
		<div class="row">
			<div class="col">
				<div id="primary" class="content-area">
					<div id="content" class="site-content clearfix">
						<article id="post-0" class="post not-found">
							<div class="entry-content clearfix">
								<p><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/rose.gif" alt="Rose revealing herself from Jane the Virgin" class="alignleft"/></p>
								<p><?php esc_attr_e( 'Sorry, there is no page with this address. Please try again or use the search below.', 'lwtv-underscores' ); ?></p>

								<div class="row g-0">
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

<?php

get_footer();
